<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';

Auth::logout();
header('Location: /auth/login.php');
exit;
