<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/FileManager.php';

$fm = new FileManager();

// Support ancien système (nom de fichier) et nouveau système (ID)
$fileId = (int)($_GET['id'] ?? 0);
$fileName = $_GET['file'] ?? '';

if ($fileId > 0) {
    $file = $fm->getFileById($fileId);
} elseif (!empty($fileName)) {
    $file = $fm->getFileByFilename($fileName);
} else {
    render_error_page(400);
}

if (!$file || !$fm->canAccessFile(
    $file,
    Auth::isLoggedIn() ? (int)$_SESSION['user_id'] : null,
    Auth::isAdmin()
)) {
    render_error_page(404);
}
$safeFileName = basename($file['filename']);

// Chemin sécurisé
$uploadDirectory = realpath(__DIR__ . '/../uploads/');
if ($uploadDirectory === false) {
    render_error_page(500);
}
$uploadDirectory .= DIRECTORY_SEPARATOR;

$filePath = $uploadDirectory . $safeFileName;

if (!file_exists($filePath) || !is_file($filePath)) {
    render_error_page(404);
}

// Vérification path traversal
$realPath = realpath($filePath);
if ($realPath === false || strpos($realPath, $uploadDirectory) !== 0) {
    render_error_page(403);
}

// Bloquer les extensions dangereuses
$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
if (in_array($ext, DANGEROUS_EXTENSIONS)) {
    render_error_page(403);
}

// Téléchargement
$fileSize = filesize($realPath);
$displayName = isset($file['original_name']) ? (string)$file['original_name'] : basename($realPath);
$displayName = str_replace(["\r", "\n", "\0"], '', $displayName);
$asciiFileName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $displayName) ?: 'download';

header('Content-Type: application/octet-stream');
header("Content-Disposition: attachment; filename=\"$asciiFileName\"; filename*=UTF-8''" . rawurlencode($displayName));
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$handle = fopen($realPath, 'rb');
if ($handle === false) {
    render_error_page(500);
}

while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
}
fclose($handle);
exit;
