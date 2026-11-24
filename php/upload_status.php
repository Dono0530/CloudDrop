<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';

Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$uploadsDisabled = !Auth::isAdmin()
    && (SiteControl::settings()['uploads_disabled'] ?? '0') === '1';

echo json_encode([
    'success' => true,
    'uploads_disabled' => $uploadsDisabled,
], JSON_UNESCAPED_UNICODE);
