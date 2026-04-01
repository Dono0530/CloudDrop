<?php
/**
 * HANDLER D'ACTIONS POST
 */
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';

require_once __DIR__ . '/../includes/FileManager.php';
$fm = new FileManager();

// Partage public (pas besoin d'être connecté pour accéder)
if (isset($_GET['share'])) {
    $token = $_GET['share'];
    $share = $fm->getShareByToken($token);
    if (!$share) { http_response_code(404); die('Lien invalide ou expiré'); }

    $file = $fm->getFileById($share['file_id']);
    if (!$file) { http_response_code(404); die('Fichier supprimé'); }

    // Servir le fichier
    $filePath = UPLOAD_DIR . $file['filename'];
    if (!file_exists($filePath)) { http_response_code(404); die('Fichier introuvable'); }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (in_array($ext, DANGEROUS_EXTENSIONS)) { http_response_code(403); die('Type non autorisé'); }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $file['original_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// Actions POST nécessitent une connexion
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /php/dashboard.php'); exit; }
if (!Auth::validateCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'CSRF invalide']); exit; }
    http_response_code(403); die('CSRF invalide');
}

$action = $_POST['action'] ?? '';
$redirect = $_SERVER['HTTP_REFERER'] ?? '/php/dashboard.php';

switch ($action) {
    case 'delete_file':
        try {
            $fm->deleteFile((int)($_POST['file_id'] ?? 0), $_SESSION['user_id'], Auth::isAdmin());
            $_SESSION['flash_success'] = 'Fichier supprimé.';
        } catch (Exception $e) { $_SESSION['flash_error'] = $e->getMessage(); }
        header('Location: ' . $redirect); exit;

    case 'rename_file':
        try {
            $fm->renameFile((int)($_POST['file_id'] ?? 0), $_POST['new_name'] ?? '', $_SESSION['user_id']);
            $_SESSION['flash_success'] = 'Fichier renommé.';
        } catch (Exception $e) { $_SESSION['flash_error'] = $e->getMessage(); }
        header('Location: ' . $redirect); exit;

    case 'create_share':
        header('Content-Type: application/json');
        try {
            $share = $fm->createShare((int)($_POST['file_id'] ?? 0), $_SESSION['user_id'], (int)($_POST['expiry_hours'] ?? 24));
            $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/php/actions.php?share=' . $share['token'];
            echo json_encode(['success' => true, 'url' => $url, 'token' => $share['token'], 'expires_at' => $share['expires_at']]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;

    case 'create_folder':
        try {
            $fm->createFolder(trim($_POST['folder_name'] ?? ''), $_SESSION['user_id']);
            $_SESSION['flash_success'] = 'Dossier créé.';
        } catch (Exception $e) { $_SESSION['flash_error'] = $e->getMessage(); }
        header('Location: /php/download.php'); exit;

    case 'delete_folder':
        try {
            $fm->deleteFolder((int)($_POST['folder_id'] ?? 0), $_SESSION['user_id']);
            $_SESSION['flash_success'] = 'Dossier supprimé.';
        } catch (Exception $e) { $_SESSION['flash_error'] = $e->getMessage(); }
        header('Location: /php/download.php'); exit;

    case 'move_file':
        try {
            $fm->moveFile(
                (int)($_POST['file_id'] ?? 0),
                (int)($_POST['target_folder'] ?? 0),
                $_SESSION['user_id'],
                Auth::isAdmin()
            );
            $_SESSION['flash_success'] = 'Fichier déplacé.';
        } catch (Exception $e) { $_SESSION['flash_error'] = $e->getMessage(); }
        header('Location: ' . $redirect); exit;

    default:
        $_SESSION['flash_error'] = 'Action inconnue.';
        header('Location: ' . $redirect); exit;
}
