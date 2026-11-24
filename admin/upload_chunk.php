<?php
/**
 * Réceptionne un chunk binaire et assemble les fichiers après validation.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';

function upload_response(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!Auth::isLoggedIn()) {
    upload_response(401, ['success' => false, 'error' => 'Connexion requise']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    upload_response(405, ['success' => false, 'error' => 'Méthode non autorisée']);
}
if (!Auth::validateCsrf((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    upload_response(403, ['success' => false, 'error' => 'Jeton de sécurité invalide. Rechargez la page.']);
}

$chunkIndex = filter_var($_SERVER['HTTP_X_CHUNK_INDEX'] ?? null, FILTER_VALIDATE_INT);
$totalChunks = filter_var($_SERVER['HTTP_X_TOTAL_CHUNKS'] ?? null, FILTER_VALIDATE_INT);
$fileSize = filter_var($_SERVER['HTTP_X_FILE_SIZE'] ?? null, FILTER_VALIDATE_INT);
$fileId = (string)($_SERVER['HTTP_X_FILE_ID'] ?? '');
$folderId = filter_var($_SERVER['HTTP_X_FOLDER_ID'] ?? '0', FILTER_VALIDATE_INT);
$isPublicHeader = (string)($_SERVER['HTTP_X_FILE_PUBLIC'] ?? '0');
$encodedName = (string)($_SERVER['HTTP_X_FILE_NAME'] ?? '');
$originalName = basename(str_replace('\\', '/', rawurldecode($encodedName)));
$originalName = trim(preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $originalName) ?? '');

if ($chunkIndex === false || $totalChunks === false || $fileSize === false || $folderId === false
    || !preg_match('/^[a-f0-9]{32}$/i', $fileId)
    || $originalName === '' || !preg_match('//u', $originalName)
    || strlen($originalName) > 1020
    || !in_array($isPublicHeader, ['0', '1'], true)) {
    upload_response(400, ['success' => false, 'error' => 'Données de fichier invalides']);
}
if ($fileSize < 1 || $fileSize > MAX_FILE_SIZE || $totalChunks < 1
    || $totalChunks !== (int)ceil($fileSize / CHUNK_SIZE)
    || $totalChunks > (int)ceil(MAX_FILE_SIZE / CHUNK_SIZE)
    || $chunkIndex < 0 || $chunkIndex >= $totalChunks
    || $folderId < 0) {
    upload_response(400, ['success' => false, 'error' => 'Taille ou découpage du fichier invalide']);
}
if (function_exists('mb_substr')) {
    $originalName = mb_substr($originalName, 0, 255, 'UTF-8');
} else {
    $originalName = preg_replace('/^(.{0,255}).*$/us', '$1', $originalName) ?? '';
}
if ($originalName === '') {
    upload_response(400, ['success' => false, 'error' => 'Nom de fichier invalide']);
}

require_once __DIR__ . '/../includes/FileManager.php';
$fm = new FileManager();
if ($folderId > 0) {
    $folder = $fm->getFolderById($folderId);
    if (!$folder || (int)$folder['user_id'] !== (int)$_SESSION['user_id']) {
        upload_response(403, ['success' => false, 'error' => 'Dossier non autorisé']);
    }
}

$sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName) ?? '';
$extension = strtolower(pathinfo($sanitizedName, PATHINFO_EXTENSION));
if (in_array($extension, DANGEROUS_EXTENSIONS, true)
    || ($extension !== '' && !in_array($extension, ALLOWED_EXTENSIONS, true))) {
    upload_response(403, ['success' => false, 'error' => 'Type de fichier non autorisé']);
}

$expectedChunkSize = min(CHUNK_SIZE, $fileSize - ($chunkIndex * CHUNK_SIZE));
$rawBody = file_get_contents('php://input', false, null, 0, CHUNK_SIZE + 1);
if (!is_string($rawBody) || strlen($rawBody) !== $expectedChunkSize) {
    upload_response(400, ['success' => false, 'error' => 'Taille de morceau invalide']);
}

$uploadDir = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$tempDir = rtrim(TEMP_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
foreach ([$uploadDir, $tempDir] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        upload_response(500, ['success' => false, 'error' => 'Stockage indisponible']);
    }
}
@chmod($tempDir, 0700);

$userId = (int)$_SESSION['user_id'];
$tempPrefix = $tempDir . $userId . '_' . strtolower($fileId) . '_';
$tempFile = $tempPrefix . $chunkIndex;
if (file_put_contents($tempFile, $rawBody, LOCK_EX) !== $expectedChunkSize) {
    upload_response(500, ['success' => false, 'error' => 'Erreur de sauvegarde du morceau']);
}

if ($chunkIndex !== $totalChunks - 1) {
    upload_response(200, ['success' => true, 'complete' => false, 'chunkIndex' => $chunkIndex]);
}

for ($i = 0; $i < $totalChunks; $i++) {
    if (!is_file($tempPrefix . $i) || is_link($tempPrefix . $i)) {
        upload_response(400, ['success' => false, 'error' => 'Morceaux manquants (morceau ' . $i . ')']);
    }
}

$realUploadDir = realpath($uploadDir);
if ($realUploadDir === false) {
    upload_response(500, ['success' => false, 'error' => 'Stockage indisponible']);
}
$realUploadDir .= DIRECTORY_SEPARATOR;

$storageName = bin2hex(random_bytes(32)) . ($extension !== '' ? '.' . $extension : '');
$finalPath = $realUploadDir . $storageName;
$finalFile = fopen($finalPath, 'xb');
if ($finalFile === false) {
    upload_response(500, ['success' => false, 'error' => 'Impossible de créer le fichier final']);
}

$written = 0;
$assemblyFailed = false;
for ($i = 0; $i < $totalChunks; $i++) {
    $chunkPath = $tempPrefix . $i;
    $chunkHandle = fopen($chunkPath, 'rb');
    if ($chunkHandle === false) {
        $assemblyFailed = true;
        break;
    }
    $expectedLength = min(CHUNK_SIZE, $fileSize - ($i * CHUNK_SIZE));
    $chunkWritten = stream_copy_to_stream($chunkHandle, $finalFile, $expectedLength);
    fclose($chunkHandle);
    if ($chunkWritten !== $expectedLength) {
        $assemblyFailed = true;
        break;
    }
    $written += $chunkWritten;
}
fclose($finalFile);

if ($assemblyFailed || $written !== $fileSize || filesize($finalPath) !== $fileSize) {
    @unlink($finalPath);
    upload_response(500, ['success' => false, 'error' => 'Assemblage du fichier incomplet']);
}

foreach (glob($tempPrefix . '*') ?: [] as $chunkPath) {
    if (is_file($chunkPath) && !is_link($chunkPath)) @unlink($chunkPath);
}

try {
    $record = $fm->recordUpload($storageName, $userId, $written, $originalName, $folderId, $isPublicHeader === '1');
} catch (Throwable $e) {
    @unlink($finalPath);
    error_log('CloudDrop upload record failed: ' . $e->getMessage());
    upload_response(500, ['success' => false, 'error' => 'Enregistrement du fichier impossible']);
}

upload_response(200, [
    'success' => true,
    'complete' => true,
    'fileName' => $originalName,
    'fileSize' => $written,
    'fileId' => $record['id'] ?? null,
]);
