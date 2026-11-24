<?php
/**
 * Contrôles globaux du service : bannissements, maintenance et disponibilité.
 */
require_once __DIR__ . '/../config/Database.php';

class SiteControl {
    private static ?array $settingsCache = null;
    private const DEFAULTS = [
        'maintenance_mode' => '0',
        'uploads_disabled' => '0',
        'downloads_disabled' => '0',
        'maintenance_message' => 'Le service est temporairement indisponible pour maintenance.',
    ];

    public static function settings(): array {
        if (self::$settingsCache !== null) return self::$settingsCache;
        $values = self::DEFAULTS;
        try {
            $rows = Database::getInstance()->fetchAll('SELECT setting_key, setting_value FROM site_settings');
            foreach ($rows as $row) {
                if (array_key_exists($row['setting_key'], $values)) $values[$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) {
            // Avant la migration, les contrôles restent inactifs et les pages continuent de fonctionner.
        }
        return self::$settingsCache = $values;
    }

    public function saveSettings(array $input): void {
        $allowed = ['maintenance_mode', 'uploads_disabled', 'downloads_disabled'];
        $db = Database::getInstance();
        foreach ($allowed as $key) {
            $value = !empty($input[$key]) ? '1' : '0';
            $db->execute(
                'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [$key, $value]
            );
        }
        $message = trim((string)($input['maintenance_message'] ?? ''));
        if ($message === '') $message = self::DEFAULTS['maintenance_message'];
        $message = substr($message, 0, 300);
        $db->execute(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['maintenance_message', $message]
        );
        self::$settingsCache = null;
    }

    public static function isUserBanned(int $userId): ?array {
        try {
            return Database::getInstance()->fetchOne(
                "SELECT is_banned, banned_until, ban_reason,
                        CASE WHEN banned_until IS NULL THEN NULL
                             ELSE GREATEST(TIMESTAMPDIFF(SECOND, NOW(), banned_until), 0) END AS ban_seconds_remaining,
                        CASE WHEN banned_until IS NULL THEN NULL
                             ELSE DATE_FORMAT(banned_until, '%d/%m/%Y à %H:%i') END AS ban_until_display
                 FROM users
                 WHERE id = ? AND is_banned = 1 AND (banned_until IS NULL OR banned_until > NOW())",
                [$userId]
            );
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function banTimeRemaining(
        ?string $bannedUntil,
        ?int $remainingSeconds = null,
        ?string $displayUntil = null
    ): string {
        if ($bannedUntil === null || $bannedUntil === '') return 'sans date de fin';
        // Utilise la durée renvoyée par MySQL, la même horloge que celle du contrôle de bannissement.
        $seconds = $remainingSeconds ?? max(0, strtotime($bannedUntil) - time());
        if ($seconds <= 0) return 'bannissement expiré';
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($days > 0) $parts[] = $days . ' j';
        if ($hours > 0) $parts[] = $hours . ' h';
        if ($minutes > 0 || !$parts) $parts[] = max(1, $minutes) . ' min';
        $endDate = $displayUntil ?? $bannedUntil;
        return 'fin le ' . $endDate . ' · reste ' . implode(' ', $parts);
    }

    public static function formatBanMessage(array $ban, bool $includeReason = false): string {
        $until = $ban['banned_until'] ?? null;
        $message = $until === null
            ? 'Ce compte est suspendu sans date de fin.'
            : 'Ce compte est suspendu : ' . self::banTimeRemaining(
                (string)$until,
                isset($ban['ban_seconds_remaining']) ? (int)$ban['ban_seconds_remaining'] : null,
                $ban['ban_until_display'] ?? null
            ) . '.';
        if ($includeReason && !empty($ban['ban_reason'])) $message .= ' Motif : ' . (string)$ban['ban_reason'];
        return $message;
    }

    private static function refreshSessionRole(): void {
        if (empty($_SESSION['user_id'])) return;
        try {
            $user = Database::getInstance()->fetchOne(
                'SELECT role FROM users WHERE id = ?',
                [(int)$_SESSION['user_id']]
            );
        } catch (Throwable $e) {
            // En cas d'indisponibilité de la base, ne jamais conserver un rôle administrateur en cache.
            $_SESSION['user_role'] = 'user';
            return;
        }

        if (!$user) {
            foreach (['user_id', 'username', 'user_role', 'logged_in', 'login_time', 'last_regeneration'] as $key) {
                unset($_SESSION[$key]);
            }
            if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
            return;
        }
        $_SESSION['user_role'] = ($user['role'] ?? '') === 'admin' ? 'admin' : 'user';
    }

    public static function enforceRequestPolicy(): void {
        self::refreshSessionRole();
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        if (!$isAdmin && !empty($_SESSION['user_id'])) {
            $ban = self::isUserBanned((int)$_SESSION['user_id']);
            if ($ban) {
                $banMessage = self::formatBanMessage($ban);
                $_SESSION['ban_notice'] = $banMessage;
                foreach (['user_id', 'username', 'user_role', 'logged_in', 'login_time', 'last_regeneration'] as $key) unset($_SESSION[$key]);
                if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
                if (!in_array($path, ['/auth/login.php', '/auth/logout.php'], true)) {
                    self::deny(403, $banMessage);
                }
            }
        }

        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        $settings = self::settings();
        if (!$isAdmin && $settings['maintenance_mode'] === '1'
            && !in_array($path, ['/auth/login.php', '/auth/logout.php', '/legal.php', '/error.php'], true)) {
            self::deny(503, $settings['maintenance_message']);
        }

        if (!$isAdmin && $settings['uploads_disabled'] === '1' && $path === '/admin/upload_chunk.php') {
            self::deny(503, 'Les envois de fichiers sont temporairement désactivés.', true);
        }

        $downloadPath = in_array($path, [
            '/php/download_file.php', '/php/preview.php', '/php/preview_serve.php', '/php/share.php', '/php/share_preview.php',
        ], true) || ($path === '/php/actions.php' && isset($_GET['share']));
        if (!$isAdmin && $settings['downloads_disabled'] === '1' && $downloadPath) {
            self::deny(503, 'Les téléchargements et aperçus de fichiers sont temporairement désactivés.', str_contains($path, 'actions.php'));
        }
    }

    private static function deny(int $status, string $message, bool $json = false): void {
        http_response_code($status);
        header('Retry-After: 300');
        if ($json || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $isBan = $status === 403;
            $isDownloadsDisabled = $status === 503 && stripos($message, 'télécharg') !== false;
            $isUploadsDisabled = $status === 503 && stripos($message, 'envoi') !== false;
            if ($isBan) {
                $title = 'Compte suspendu';
                $icon = 'bi-person-lock';
            } elseif ($isDownloadsDisabled) {
                $title = 'Téléchargements et aperçus désactivés';
                $icon = 'bi-cloud-slash';
            } elseif ($isUploadsDisabled) {
                $title = 'Envois temporairement désactivés';
                $icon = 'bi-cloud-arrow-up';
            } else {
                $title = 'Maintenance en cours';
                $icon = 'bi-tools';
            }
            $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $target = $isBan ? '/auth/login.php' : ($isDownloadsDisabled ? '/php/download.php' : '/index.php');
            $linkLabel = $isBan ? 'Retour à la connexion' : ($isDownloadsDisabled ? 'Retour aux fichiers' : 'Retour à l’accueil');
            $safeTarget = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
            $safeLinkLabel = htmlspecialchars($linkLabel, ENT_QUOTES, 'UTF-8');
            $safeIcon = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
            ob_start();
            require __DIR__ . '/SiteHeader.php';
            $sharedHeader = ob_get_clean();
            ob_start();
            require __DIR__ . '/SiteFooter.php';
            $sharedFooter = ob_get_clean();
            echo '<!doctype html>
<html lang="fr" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>' . $status . ' · ' . $safeTitle . ' — CloudDrop</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="/css/modern.css?v=20261125-2">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
.service-error-page{min-height:100vh;display:flex;flex-direction:column;padding:0;background:radial-gradient(ellipse at 12% 0%,rgba(99,102,241,.16),transparent 42%),radial-gradient(ellipse at 100% 100%,rgba(6,182,212,.13),transparent 40%),var(--light,#f8fafc);color:var(--text,#1e293b);font-family:Inter,system-ui,sans-serif}
.service-error-card{width:min(calc(100% - 32px),650px);margin:auto;padding:clamp(28px,6vw,52px);border:1px solid var(--border,#e2e8f0);border-radius:24px;background:rgba(255,255,255,.92);box-shadow:0 24px 70px rgba(15,23,42,.12);text-align:center}
.service-error-logo{display:inline-flex;align-items:center;gap:9px;margin-bottom:32px;color:#6366f1;font-size:1rem;font-weight:750}
.service-error-icon{width:76px;height:76px;margin:0 auto 20px;display:grid;place-items:center;border-radius:24px;background:linear-gradient(135deg,#6366f1,#06b6d4);color:white;font-size:30px;box-shadow:0 12px 28px rgba(99,102,241,.25)}
.service-error-code{margin:0;color:#6366f1;font-size:clamp(3.5rem,12vw,6rem);font-weight:850;line-height:1;letter-spacing:-.07em}
.service-error-card h1{margin:18px 0 10px;font-size:clamp(1.35rem,4vw,1.8rem);font-weight:750}
.service-error-card p{max-width:490px;margin:0 auto 26px;color:var(--text-muted,#64748b);line-height:1.75}
.service-error-actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
.service-error-actions a{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 17px;border-radius:11px;text-decoration:none;font-weight:650}
.service-error-primary{color:#fff;background:linear-gradient(135deg,#4f46e5,#6366f1)}
.service-error-secondary{color:var(--text,#1e293b);border:1px solid var(--border,#e2e8f0);background:transparent}
.service-error-footer{margin-top:28px;color:var(--text-muted,#94a3b8);font-size:.82rem}
.service-error-footer a{color:#6366f1;text-decoration:none}
[data-theme="dark"] .service-error-page{background:radial-gradient(ellipse at 12% 0%,rgba(99,102,241,.20),transparent 42%),radial-gradient(ellipse at 100% 100%,rgba(6,182,212,.14),transparent 40%),#0f172a;color:#f1f5f9}
[data-theme="dark"] .service-error-card{background:rgba(30,41,59,.94);border-color:#334155}
[data-theme="dark"] .service-error-secondary{color:#f1f5f9;border-color:#475569}
</style>
</head>
<body class="service-error-page">' . $sharedHeader . "\n" . '
<main class="service-error-card">
<div class="service-error-icon" aria-hidden="true"><i class="bi ' . $safeIcon . '"></i></div>
<p class="service-error-code">' . $status . '</p>
<h1>' . $safeTitle . '</h1>
<p>' . $safeMessage . '</p>
<div class="service-error-actions">
<a class="service-error-primary" href="' . $safeTarget . '"><i class="bi bi-arrow-left"></i> ' . $safeLinkLabel . '</a>
<a class="service-error-secondary" href="/legal.php"><i class="bi bi-info-circle"></i> Informations du service</a>
</div>
</main>
<script src="/js/theme.js?v=20261125-1"></script>' . $sharedFooter . "\n" . '
</body>
</html>';
        }
        exit;
    }
}
