<?php
/**
 * Sert le flux d’aperçu après vérification des droits d’accès.
 */
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/FileManager.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
Auth::requireLogin();

$fileId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($fileId === false || $fileId <= 0) render_error_page(404);

$fm = new FileManager();
$file = $fm->getFileById($fileId);
if (!$file || !$fm->canAccessFile($file, (int)$_SESSION['user_id'], Auth::isAdmin())) {
    render_error_page(404);
}

$uploadDirectory = realpath(UPLOAD_DIR);
$filename = basename((string)$file['filename']);
$filePath = $uploadDirectory !== false
    ? realpath($uploadDirectory . DIRECTORY_SEPARATOR . $filename)
    : false;
if ($filePath === false || !is_file($filePath)
    || strpos($filePath, $uploadDirectory . DIRECTORY_SEPARATOR) !== 0) {
    render_error_page(404);
}

$extension = strtolower((string)$file['file_extension']);
if (in_array($extension, DANGEROUS_EXTENSIONS, true)) render_error_page(403);

$mimeTypes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'gif' => 'image/gif', 'bmp' => 'image/bmp', 'webp' => 'image/webp',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg',
    'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'flac' => 'audio/flac',
    'pdf' => 'application/pdf',
    'txt' => 'text/plain; charset=utf-8', 'csv' => 'text/plain; charset=utf-8',
    'json' => 'text/plain; charset=utf-8', 'xml' => 'text/plain; charset=utf-8',
    'html' => 'text/plain; charset=utf-8', 'css' => 'text/plain; charset=utf-8',
    'js' => 'text/plain; charset=utf-8', 'md' => 'text/plain; charset=utf-8',
];
if (!isset($mimeTypes[$extension])) render_error_page(403);

$size = filesize($filePath);
if ($size === false) render_error_page(500);

header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Disposition: inline');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; frame-ancestors 'self'");
header('Cross-Origin-Resource-Policy: same-origin');
header('Accept-Ranges: bytes');

$start = 0;
$end = max(0, $size - 1);
$status = 200;
$rangeHeader = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));

if ($rangeHeader !== '') {
    if (!preg_match('/^bytes=(\\d*)-(\\d*)$/D', $rangeHeader, $matches)
        || ($matches[1] === '' && $matches[2] === '')) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }

    if ($matches[1] === '') {
        $suffixLength = (int)$matches[2];
        if ($suffixLength <= 0 || $size === 0) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $start = max(0, $size - $suffixLength);
        $end = $size - 1;
    } else {
        $start = (int)$matches[1];
        $end = $matches[2] === '' ? $size - 1 : min((int)$matches[2], $size - 1);
    }

    if ($size === 0 || $start >= $size || $end < $start) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    $status = 206;
    http_response_code($status);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}

$length = $size === 0 ? 0 : ($end - $start + 1);
header('Content-Length: ' . $length);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;

$handle = fopen($filePath, 'rb');
if ($handle === false) render_error_page(500);
if ($start > 0 && fseek($handle, $start) !== 0) {
    fclose($handle);
    render_error_page(500);
}

$remaining = $length;
while ($remaining > 0 && !feof($handle)) {
    $chunkLength = min(8192, $remaining);
    $data = fread($handle, $chunkLength);
    if ($data === false || $data === '') break;
    echo $data;
    $remaining -= strlen($data);
}
fclose($handle);
exit;
