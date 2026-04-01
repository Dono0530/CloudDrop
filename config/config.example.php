<?php
/**
 * CONFIGURATION - CloudDrop
 * Copiez ce fichier en config.php et remplissez vos paramètres
 */

// Empêcher l'accès direct
defined('ABSPATH') || define('ABSPATH', dirname(__DIR__) . '/');

// ── BASE DE DONNÉES ──────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'clouddrop');
define('DB_CHARSET', 'utf8mb4');

// ── SESSION ──────────────────────────────────────────────────────
define('SESSION_NAME', 'CLOUDDROP_SESSION');
define('SESSION_LIFETIME', 7200);
define('SESSION_REGEN_INTERVAL', 300);

// ── UPLOADS ──────────────────────────────────────────────────────
define('UPLOAD_DIR', ABSPATH . 'uploads/');
define('TEMP_DIR', ABSPATH . 'uploads/temp/');
define('MAX_FILE_SIZE', 100 * 1024 * 1024 * 1024); // 100 Go
define('CHUNK_SIZE', 10 * 1024 * 1024); // 10 Mo

// ── SÉCURITÉ ─────────────────────────────────────────────────────
define('CSRF_TOKEN_NAME', '_csrf_token');
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);
define('BCRYPT_COST', 12);

// ── TYPES DE FICHIERS AUTORISÉS ──────────────────────────────────
define('ALLOWED_EXTENSIONS', [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'txt', 'csv', 'rtf', 'odt', 'ods',
    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp',
    'mp3', 'mp4', 'avi', 'mkv', 'mov', 'wav', 'flac',
    'zip', 'rar', '7z', 'tar', 'gz',
    'json', 'xml', 'html', 'css', 'js',
    'py', 'java', 'c', 'cpp', 'h'
]);

define('DANGEROUS_EXTENSIONS', [
    'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar',
    'exe', 'bat', 'cmd', 'sh', 'bash', 'com', 'msi', 'scr'
]);

define('IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp']);
