<?php
/**
 * UPLOAD CHUNK - Version améliorée avec BDD
 * Traite les chunks d'upload et assemble les fichiers
 * Authentification requise (utilisateurs connectés)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';

// Vérifier l'authentification
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Connexion requise']);
    exit;
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Chemins
$uploadDir = UPLOAD_DIR;
$tempDir = TEMP_DIR;

// Créer les répertoires si nécessaire
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

// Récupérer les données du chunk
$chunkIndex = (int)($_POST['chunkIndex'] ?? 0);
$totalChunks = (int)($_POST['totalChunks'] ?? 0);
$fileName = basename($_POST['fileName'] ?? '');
$fileId = $_POST['fileId'] ?? '';

// Validation
if (empty($fileName) || empty($fileId) || !isset($_FILES['chunk'])) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes']);
    exit;
}

// Sécuriser le fileId
if (!preg_match('/^[0-9]+_[a-z0-9]+$/', $fileId)) {
    echo json_encode(['success' => false, 'error' => 'ID de fichier invalide']);
    exit;
}

// Nettoyer le nom de fichier
$safeName = preg_replace("/[^a-zA-Z0-9._-]/", "_", $fileName);
$ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));

// Vérifier l'extension
if (in_array($ext, DANGEROUS_EXTENSIONS)) {
    echo json_encode(['success' => false, 'error' => 'Type de fichier non autorisé']);
    exit;
}

// Sauvegarder le chunk
$tempFile = $tempDir . $fileId . '_' . $chunkIndex;

if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $tempFile)) {
    echo json_encode(['success' => false, 'error' => 'Erreur de sauvegarde du chunk']);
    exit;
}

// Vérifier si c'est le dernier chunk
if ($chunkIndex === $totalChunks - 1) {
    // Vérifier que tous les chunks sont présents
    for ($i = 0; $i < $totalChunks; $i++) {
        if (!file_exists($tempDir . $fileId . '_' . $i)) {
            echo json_encode(['success' => false, 'error' => 'Chunks manquants']);
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

    // Assembler
    $finalFile = fopen($finalPath, 'wb');
    if (!$finalFile) {
        echo json_encode(['success' => false, 'error' => 'Impossible de créer le fichier final']);
        exit;
    }

    for ($i = 0; $i < $totalChunks; $i++) {
        $chunkPath = $tempDir . $fileId . '_' . $i;
        $chunk = fopen($chunkPath, 'rb');
        if ($chunk) {
            while (!feof($chunk)) {
                fwrite($finalFile, fread($chunk, 8192));
            }
            fclose($chunk);
            unlink($chunkPath);
        }
    }
    fclose($finalFile);

    $fileSize = filesize($finalPath);
    $originalName = $_POST['fileName'] ?? $safeName;

    // Enregistrer dans la BDD
    require_once __DIR__ . '/../includes/FileManager.php';
    $fm = new FileManager();
    $folderId = (int)($_POST['folder_id'] ?? 0);
    $record = $fm->recordUpload(basename($finalPath), $_SESSION['user_id'], $fileSize, $originalName, $folderId);

    echo json_encode([
        'success' => true,
        'complete' => true,
        'fileName' => $originalName,
        'fileSize' => $fileSize,
        'fileId' => $record['id'] ?? null,
    ]);
} else {
    echo json_encode([
        'success' => true,
        'complete' => false,
        'chunkIndex' => $chunkIndex,
    ]);
}
