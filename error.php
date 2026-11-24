<?php
require_once __DIR__ . '/config/init.php';

$code = (int)($_GET['code'] ?? ($_SERVER['REDIRECT_STATUS'] ?? 404));
render_error_page($code);
