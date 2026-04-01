<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../includes/FileManager.php';

$fm = new FileManager();

// Support ancien système (nom de fichier) et nouveau système (ID)
$fileId = (int)($_GET['id'] ?? 0);
$fileName = $_GET['file'] ?? '';

if ($fileId > 0) {
    $file = $fm->getFileById($fileId);
    if (!$file) {
        http_response_code(404);
        die('Fichier introuvable');
    }
    $safeFileName = basename($file['filename']);
} elseif (!empty($fileName)) {
    $safeFileName = basename($fileName);
} else {
    http_response_code(400);
    die('Aucun fichier spécifié');
}

// Chemin sécurisé
$uploadDirectory = realpath(__DIR__ . '/../uploads/');
if ($uploadDirectory === false) {
    http_response_code(500);
    die('Erreur de configuration');
}
$uploadDirectory .= DIRECTORY_SEPARATOR;

$filePath = $uploadDirectory . $safeFileName;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    die('Fichier introuvable');
}

// Vérification path traversal
$realPath = realpath($filePath);
if ($realPath === false || strpos($realPath, $uploadDirectory) !== 0) {
    http_response_code(403);
    die('Accès interdit');
}

// Bloquer les extensions dangereuses
$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
if (in_array($ext, DANGEROUS_EXTENSIONS)) {
    http_response_code(403);
    die('Type de fichier non autorisé');
}

// Téléchargement
$fileSize = filesize($realPath);
$displayName = isset($file['original_name']) ? $file['original_name'] : basename($realPath);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $displayName) . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$handle = fopen($realPath, 'rb');
if ($handle === false) {
    http_response_code(500);
    die('Impossible de lire le fichier');
}

while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
}
fclose($handle);
exit;
