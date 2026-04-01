<?php
/**
 * INITIALISATION - À inclure en premier dans chaque page
 * Configure sessions, sécurité, et autoload
 */

// Charger la config
require_once __DIR__ . '/config.php';

// ── SESSION SÉCURISÉE ────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.name', SESSION_NAME);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', 0); // cookie de session (expire à la fermeture)

    // Activer le cookie sécurisé en HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }

    session_start();
}

// ── RÉGÉNÉRATION PÉRIODIQUE DE L'ID DE SESSION ───────────────────
if (isset($_SESSION['last_regeneration'])) {
    if (time() - $_SESSION['last_regeneration'] > SESSION_REGEN_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
} else {
    $_SESSION['last_regeneration'] = time();
}

// ── HEADERS DE SÉCURITÉ ──────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ── CSRF TOKEN ───────────────────────────────────────────────────
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

// ── AUTOLOAD DES CLASSES ─────────────────────────────────────────
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/',
        __DIR__ . '/../includes/',
    ];
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
