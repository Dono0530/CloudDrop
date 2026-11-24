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

    $displayName = str_replace(["\r", "\n", "\0"], '', (string)$file['original_name']);
    $asciiFileName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $displayName) ?: 'download';
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$asciiFileName\"; filename*=UTF-8''" . rawurlencode($displayName));
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
$redirect = '/php/dashboard.php';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$refererParts = $referer !== '' ? parse_url($referer) : false;
if (is_array($refererParts) && isset($refererParts['path'])) {
    $refererPath = $refererParts['path'];
    $refererQuery = $refererParts['query'] ?? '';
    if (str_starts_with($refererPath, '/') && !str_starts_with($refererPath, '//')
        && strpbrk($refererPath . $refererQuery, "\r\n") === false) {
        $redirect = $refererPath;
        if ($refererQuery !== '') $redirect .= '?' . $refererQuery;
    }
}

switch ($action) {
    case 'delete_file':
        try {
            $fm->deleteFile((int)($_POST['file_id'] ?? 0), $_SESSION['user_id'], Auth::isAdmin());
            $_SESSION['flash_success'] = 'Fichier supprimé.';
        } catch (Exception $e) { $_SESSION['flash_error'] = $e->getMessage(); }
        header('Location: ' . $redirect); exit;

    case 'set_file_visibility':
        try {
            $visibility = $_POST['is_public'] ?? null;
            if (!in_array($visibility, ['0', '1'], true)) {
                throw new Exception('Visibilité invalide');
            }
            $isPublic = $visibility === '1';
            $fm->setFileVisibility(
                (int)($_POST['file_id'] ?? 0),
                $isPublic,
                (int)$_SESSION['user_id'],
                Auth::isAdmin()
            );
            $_SESSION['flash_success'] = $isPublic ? 'Fichier rendu public.' : 'Fichier rendu privé.';
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
            $share = $fm->createShare((int)($_POST['file_id'] ?? 0), $_SESSION['user_id'], (int)($_POST['expiry_hours'] ?? 24), Auth::isAdmin());
            $url = '/php/share.php?token=' . rawurlencode($share['token']);
            echo json_encode(['success' => true, 'url' => $url, 'token' => $share['token'], 'expires_at' => $share['expires_at']]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;

    case 'update_share_expiry':
        try {
            $fm->updateShareExpiry(
                (int)($_POST['share_id'] ?? 0),
                (int)$_SESSION['user_id'],
                (int)($_POST['expiry_hours'] ?? 0)
            );
            $_SESSION['flash_success'] = 'Durée du lien mise à jour.';
        } catch (Exception $e) { $_SESSION['flash_error'] = $e->getMessage(); }
        header('Location: /php/shares.php'); exit;

    case 'delete_share':
        try {
            $fm->deleteShare((int)($_POST['share_id'] ?? 0), (int)$_SESSION['user_id']);
            $_SESSION['flash_success'] = 'Lien de partage supprimé.';
        } catch (Exception $e) { $_SESSION['flash_error'] = $e->getMessage(); }
        header('Location: /php/shares.php'); exit;

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
