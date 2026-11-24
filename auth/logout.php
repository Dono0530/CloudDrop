<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
if (!Auth::validateCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    render_error_page(403);
}

Auth::logout();
header('Location: /auth/login.php');
exit;
