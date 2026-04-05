<?php
/**
 * UPLOAD CHUNK - Binaire pur (pas FormData)
 * Les métadonnées sont dans les headers HTTP, le body = le chunk brut
 * Compatible fichiers de TOUTES tailles (100 Go+)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Connexion requise']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// ── Métadonnées depuis les headers HTTP ──────────────────────
$chunkIndex = (int)($_SERVER['HTTP_X_CHUNK_INDEX'] ?? 0);
$totalChunks = (int)($_SERVER['HTTP_X_TOTAL_CHUNKS'] ?? 0);
$fileName = basename(urldecode($_SERVER['HTTP_X_FILE_NAME'] ?? ''));
$fileId = $_SERVER['HTTP_X_FILE_ID'] ?? '';
$folderId = (int)($_SERVER['HTTP_X_FOLDER_ID'] ?? 0);
$fileSize = (int)($_SERVER['HTTP_X_FILE_SIZE'] ?? 0);

// ── Validation ───────────────────────────────────────────────
if (empty($fileName) || empty($fileId) || $totalChunks <= 0) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes']);
    exit;
}

if (!preg_match('/^[0-9]+_[a-zA-Z0-9]+$/', $fileId)) {
    echo json_encode(['success' => false, 'error' => 'ID de fichier invalide']);
    exit;
}

// ── Lire le body binaire brut ────────────────────────────────
$rawBody = file_get_contents('php://input');

if ($rawBody === false || $rawBody === '') {
    echo json_encode(['success' => false, 'error' => 'Aucune donnée reçue']);
    exit;
}

// ── Chemins ──────────────────────────────────────────────────
$uploadDir = UPLOAD_DIR;
$tempDir = TEMP_DIR;

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

// ── Extension ────────────────────────────────────────────────
$safeName = preg_replace("/[^a-zA-Z0-9._-]/", "_", $fileName);
$ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));

if (in_array($ext, DANGEROUS_EXTENSIONS)) {
    echo json_encode(['success' => false, 'error' => 'Type de fichier non autorisé']);
    exit;
}

if (!empty($ext) && !in_array($ext, ALLOWED_EXTENSIONS)) {
    echo json_encode(['success' => false, 'error' => 'Extension .' . $ext . ' non autorisée']);
    exit;
}

// ── Sauvegarder le chunk ─────────────────────────────────────
$tempFile = $tempDir . $fileId . '_' . $chunkIndex;

if (file_put_contents($tempFile, $rawBody, LOCK_EX) === false) {
    echo json_encode(['success' => false, 'error' => 'Erreur de sauvegarde du chunk']);
    exit;
}

// ── Dernier chunk → assembler ────────────────────────────────
if ($chunkIndex === $totalChunks - 1) {
    // Vérifier que tous les chunks sont présents
    for ($i = 0; $i < $totalChunks; $i++) {
        if (!file_exists($tempDir . $fileId . '_' . $i)) {
            echo json_encode(['success' => false, 'error' => 'Chunks manquants (chunk ' . $i . ')']);
            exit;
        }
    }

    // Assembler le fichier final
    $finalPath = $uploadDir . $safeName;

    // Gérer les doublons
    $counter = 1;
    $pathInfo = pathinfo($finalPath);
    while (file_exists($finalPath)) {
        $finalPath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '_' . $counter;
        if (!empty($pathInfo['extension'])) {
            $finalPath .= '.' . $pathInfo['extension'];
        }
        $counter++;
    }

    // Vérification de sécurité du chemin
    $realUploadDir = realpath($uploadDir);
    $realFinalDir = realpath(dirname($finalPath));
    if ($realFinalDir === false || strpos($realFinalDir . DIRECTORY_SEPARATOR, $realUploadDir . DIRECTORY_SEPARATOR) !== 0) {
        echo json_encode(['success' => false, 'error' => 'Chemin invalide']);
        exit;
    }

    // Assembler les chunks en binaire pur
    $finalFile = fopen($finalPath, 'wb');
    if (!$finalFile) {
        echo json_encode(['success' => false, 'error' => 'Impossible de créer le fichier final']);
        exit;
    }

    for ($i = 0; $i < $totalChunks; $i++) {
        $chunkPath = $tempDir . $fileId . '_' . $i;
        $chunkData = file_get_contents($chunkPath);
        if ($chunkData !== false) {
            fwrite($finalFile, $chunkData);
        }
        unlink($chunkPath);
    }
    fclose($finalFile);

    $finalSize = filesize($finalPath);
    $originalName = urldecode($_SERVER['HTTP_X_FILE_NAME'] ?? $safeName);

    // Enregistrer dans la BDD
    require_once __DIR__ . '/../includes/FileManager.php';
    $fm = new FileManager();
    $record = $fm->recordUpload(basename($finalPath), $_SESSION['user_id'], $finalSize, $originalName, $folderId);

    echo json_encode([
        'success' => true,
        'complete' => true,
        'fileName' => $originalName,
        'fileSize' => $finalSize,
        'fileId' => $record['id'] ?? null,
    ]);
} else {
    echo json_encode([
        'success' => true,
        'complete' => false,
        'chunkIndex' => $chunkIndex,
    ]);
}
