<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/FileManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}
$token = (string)($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) render_error_page(404);

$fm = new FileManager();
$share = $fm->getShareByToken($token);
if (!$share) render_error_page(404);
if (!empty($share['password_hash'])
    && (int)($_SESSION['share_link_access'][(int)$share['id']] ?? 0) <= time()) {
    render_error_page(403);
}

$extension = strtolower((string)$share['file_extension']);
$mimeTypes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'gif' => 'image/gif', 'bmp' => 'image/bmp', 'webp' => 'image/webp',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg',
    'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'pdf' => 'application/pdf',
    'txt' => 'text/plain; charset=utf-8', 'csv' => 'text/plain; charset=utf-8',
    'json' => 'text/plain; charset=utf-8', 'xml' => 'text/plain; charset=utf-8',
    'md' => 'text/plain; charset=utf-8', 'html' => 'text/plain; charset=utf-8',
    'css' => 'text/plain; charset=utf-8', 'js' => 'text/plain; charset=utf-8',
];
if (!isset($mimeTypes[$extension])) render_error_page(403);

$uploadDirectory = realpath(UPLOAD_DIR);
$filename = basename((string)$share['filename']);
$filePath = $uploadDirectory !== false
    ? realpath($uploadDirectory . DIRECTORY_SEPARATOR . $filename)
    : false;
if ($filePath === false || !is_file($filePath)
    || strpos($filePath, $uploadDirectory . DIRECTORY_SEPARATOR) !== 0) {
    render_error_page(404);
}

$size = filesize($filePath);
if ($size === false) render_error_page(500);
$fm->recordSharePreview((int)$share['id'], clouddrop_client_ip(), (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Disposition: inline');
header('Content-Length: ' . $size);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; frame-ancestors 'self'");
header('Accept-Ranges: bytes');

if (isset($_SERVER['HTTP_RANGE'])) {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $matches)
        || ($matches[1] === '' && $matches[2] === '')) {
        http_response_code(416);
        exit;
    }
    if ($matches[1] === '') {
        $suffixLength = (int)$matches[2];
        if ($suffixLength <= 0) { http_response_code(416); exit; }
        $start = max(0, $size - $suffixLength);
        $end = $size - 1;
    } else {
        $start = (int)$matches[1];
        $end = $matches[2] === '' ? $size - 1 : min((int)$matches[2], $size - 1);
    }
    if ($start >= $size || $end < $start) { http_response_code(416); exit; }

    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Content-Length: ' . ($end - $start + 1));
    $handle = fopen($filePath, 'rb');
    if (!$handle) render_error_page(500);
    fseek($handle, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($handle)) {
        $chunkLength = min(8192, $remaining);
        $data = fread($handle, $chunkLength);
        if ($data === false || $data === '') break;
        echo $data;
        $remaining -= strlen($data);
    }
    fclose($handle);
} else {
    readfile($filePath);
}
exit;
