<?php
/**
 * PREVIEW SERVE - Sert un fichier pour l'aperçu via PHP
 * Remplace l'accès direct /uploads/ qui est bloqué par .htaccess
 */
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../includes/FileManager.php';

$fileId = (int)($_GET['id'] ?? 0);
$fm = new FileManager();
$file = $fm->getFileById($fileId);

if (!$file) { http_response_code(404); die('Fichier introuvable'); }

$filePath = UPLOAD_DIR . $file['filename'];
if (!file_exists($filePath)) { http_response_code(404); die('Fichier introuvable'); }

$ext = strtolower($file['file_extension']);

// Bloquer les extensions dangereuses
if (in_array($ext, DANGEROUS_EXTENSIONS)) {
    http_response_code(403);
    die('Type non autorisé');
}

// Déterminer le Content-Type
$mimeTypes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'gif' => 'image/gif', 'bmp' => 'image/bmp', 'svg' => 'image/svg+xml',
    'webp' => 'image/webp', 'ico' => 'image/x-icon',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg',
    'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'flac' => 'audio/flac',
    'pdf' => 'application/pdf',
    'txt' => 'text/plain; charset=utf-8',
    'csv' => 'text/csv; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'xml' => 'application/xml; charset=utf-8',
    'html' => 'text/html; charset=utf-8',
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'md' => 'text/plain; charset=utf-8',
];

$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
$fileSize = filesize($filePath);

// Headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, max-age=3600');
header('Accept-Ranges: bytes');

// Support range requests (pour vidéo/audio)
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $start = 0;
    $end = $fileSize - 1;

    if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
        $start = (int)$matches[1];
        if (!empty($matches[2])) $end = (int)$matches[2];
    }

    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    header('Content-Length: ' . ($end - $start + 1));

    $handle = fopen($filePath, 'rb');
    fseek($handle, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($handle)) {
        $chunkSize = min(8192, $remaining);
        echo fread($handle, $chunkSize);
        $remaining -= $chunkSize;
    }
    fclose($handle);
} else {
    readfile($filePath);
}
exit;
