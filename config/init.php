<?php
/**
 * INITIALISATION - À inclure en premier dans chaque page
 * Configure sessions, sécurité, et autoload
 */

// ── FUSEAU HORAIRE FRANCE ──────────────────────────────────────
date_default_timezone_set('Europe/Paris');

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

// Résout l'adresse du visiteur seulement si le pair réseau est un proxy Cloudflare.
function clouddrop_client_ip(): string {
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!filter_var($remoteAddress, FILTER_VALIDATE_IP)) return 'unknown';

    $cloudflareRanges = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    $isCloudflareProxy = false;
    $remoteBinary = inet_pton($remoteAddress);
    foreach ($cloudflareRanges as $range) {
        [$network, $prefix] = explode('/', $range, 2);
        $networkBinary = inet_pton($network);
        if ($networkBinary === false || strlen($networkBinary) !== strlen($remoteBinary)) continue;
        $prefix = (int)$prefix;
        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;
        if (substr($remoteBinary, 0, $wholeBytes) !== substr($networkBinary, 0, $wholeBytes)) continue;
        if ($remainingBits > 0) {
            $mask = (0xff << (8 - $remainingBits)) & 0xff;
            if ((ord($remoteBinary[$wholeBytes]) & $mask) !== (ord($networkBinary[$wholeBytes]) & $mask)) continue;
        }
        $isCloudflareProxy = true;
        break;
    }

    $clientAddress = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if ($isCloudflareProxy && filter_var($clientAddress, FILTER_VALIDATE_IP)) return $clientAddress;
    return $remoteAddress;
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

require_once __DIR__ . '/../includes/ErrorPage.php';

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
