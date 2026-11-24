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
                'SELECT is_banned, banned_until, ban_reason FROM users WHERE id = ? AND is_banned = 1 AND (banned_until IS NULL OR banned_until > NOW())',
                [$userId]
            );
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function banTimeRemaining(?string $bannedUntil): string {
        if ($bannedUntil === null || $bannedUntil === '') return 'sans date de fin';
        $seconds = max(0, strtotime($bannedUntil) - time());
        if ($seconds <= 0) return 'bannissement expiré';
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($days > 0) $parts[] = $days . ' j';
        if ($hours > 0) $parts[] = $hours . ' h';
        if ($minutes > 0 || !$parts) $parts[] = max(1, $minutes) . ' min';
        return 'fin le ' . date('d/m/Y à H:i', strtotime($bannedUntil)) . ' · reste ' . implode(' ', $parts);
    }

    public static function formatBanMessage(array $ban, bool $includeReason = false): string {
        $until = $ban['banned_until'] ?? null;
        $message = $until === null
            ? 'Ce compte est suspendu sans date de fin.'
            : 'Ce compte est suspendu : ' . self::banTimeRemaining((string)$until) . '.';
        if ($includeReason && !empty($ban['ban_reason'])) $message .= ' Motif : ' . (string)$ban['ban_reason'];
        return $message;
    }

    public static function enforceRequestPolicy(): void {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        if (!$isAdmin && !empty($_SESSION['user_id'])) {
            $ban = self::isUserBanned((int)$_SESSION['user_id']);
            if ($ban) {
                $_SESSION['ban_notice'] = self::formatBanMessage($ban);
                foreach (['user_id', 'username', 'user_role', 'logged_in', 'login_time', 'last_regeneration'] as $key) unset($_SESSION[$key]);
                if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
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
            '/php/download_file.php', '/php/preview_serve.php', '/php/share_preview.php',
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
            $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Service indisponible — CloudDrop</title><body style="font-family:system-ui,sans-serif;max-width:680px;margin:12vh auto;padding:24px;color:#1e293b"><h1>Service temporairement indisponible</h1><p>' . $safeMessage . '</p><p><a href="/index.php">Réessayer plus tard</a></p></body></html>';
        }
        exit;
    }
}
