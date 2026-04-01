<?php
/**
 * FILEMANAGER - Gestion des fichiers, dossiers, partages
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/Database.php';

class FileManager {

    private Database $db;

    private const PREVIEWABLE = ['jpg','jpeg','png','gif','bmp','svg','webp','mp4','webm','ogg','mp3','wav','pdf','txt','json','xml','html','css','js','csv','md'];

    public function __construct() { $this->db = Database::getInstance(); }

    // ── FICHIERS ──────────────────────────────────────────────────

    public function recordUpload(string $filename, int $userId, int $fileSize, string $originalName, int $folderId = 0): array {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $id = $this->db->insert(
            "INSERT INTO files (filename, original_name, user_id, file_size, file_extension, ip_address, folder_id, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$filename, $originalName, $userId, $fileSize, $ext, $ip, $folderId]
        );
        $this->db->insert("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (?, 'upload', ?, ?, NOW())",
            [$userId, "Upload: $originalName (" . self::formatBytes($fileSize) . ")", $ip]);
        return $this->db->fetchOne("SELECT * FROM files WHERE id = ?", [$id]);
    }

    public function getFiles(array $filters = []): array {
        $sql = "SELECT f.*, u.pseudo as uploader_name FROM files f LEFT JOIN users u ON f.user_id = u.id WHERE f.deleted_at IS NULL";
        $params = [];
        if (!empty($filters['user_id']))    { $sql .= " AND f.user_id = ?"; $params[] = $filters['user_id']; }
        if (!empty($filters['folder_id']))  { $sql .= " AND f.folder_id = ?"; $params[] = (int)$filters['folder_id']; }
        else { $sql .= " AND f.folder_id = 0"; }
        if (!empty($filters['search']))     { $sql .= " AND (f.original_name LIKE ? OR f.filename LIKE ?)"; $s = '%' . $filters['search'] . '%'; $params[] = $s; $params[] = $s; }
        if (!empty($filters['extension']))  { $sql .= " AND f.file_extension = ?"; $params[] = $filters['extension']; }

        $sort = $filters['sort'] ?? 'date_desc';
        $sql .= match ($sort) {
            'date_asc' => " ORDER BY f.uploaded_at ASC", 'name_asc' => " ORDER BY f.original_name ASC",
            'name_desc' => " ORDER BY f.original_name DESC", 'size_asc' => " ORDER BY f.file_size ASC",
            'size_desc' => " ORDER BY f.file_size DESC", default => " ORDER BY f.uploaded_at DESC",
        };

        if (!empty($filters['limit'])) { $sql .= " LIMIT ? OFFSET ?"; $params[] = (int)$filters['limit']; $params[] = (int)($filters['offset'] ?? 0); }
        return $this->db->fetchAll($sql, $params);
    }

    public function countFiles(array $filters = []): int {
        $sql = "SELECT COUNT(*) as total FROM files WHERE deleted_at IS NULL";
        $params = [];
        if (!empty($filters['user_id']))   { $sql .= " AND user_id = ?"; $params[] = $filters['user_id']; }
        if (!empty($filters['folder_id'])) { $sql .= " AND folder_id = ?"; $params[] = (int)$filters['folder_id']; }
        else { $sql .= " AND folder_id = 0"; }
        if (!empty($filters['search']))    { $sql .= " AND (original_name LIKE ? OR filename LIKE ?)"; $s = '%' . $filters['search'] . '%'; $params[] = $s; $params[] = $s; }
        return (int)($this->db->fetchOne($sql, $params)['total'] ?? 0);
    }

    public function getFileById(int $id): ?array {
        return $this->db->fetchOne("SELECT f.*, u.pseudo as uploader_name FROM files f LEFT JOIN users u ON f.user_id = u.id WHERE f.id = ? AND f.deleted_at IS NULL", [$id]);
    }

    public function deleteFile(int $fileId, int $userId, bool $isAdmin = false): bool {
        $file = $this->getFileById($fileId);
        if (!$file) throw new Exception('Fichier introuvable');
        if ((int)$file['user_id'] !== $userId && !$isAdmin) throw new Exception('Permission refusée');
        $this->db->execute("UPDATE files SET deleted_at = NOW(), deleted_by = ? WHERE id = ?", [$userId, $fileId]);
        $path = UPLOAD_DIR . $file['filename'];
        if (file_exists($path)) unlink($path);
        $this->db->insert("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (?, 'delete', ?, ?, NOW())",
            [$userId, "Suppression: {$file['original_name']}", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        return true;
    }

    public function renameFile(int $fileId, string $newName, int $userId): bool {
        $file = $this->getFileById($fileId);
        if (!$file) throw new Exception('Fichier introuvable');
        if ((int)$file['user_id'] !== $userId) throw new Exception('Permission refusée');
        $newName = basename(trim($newName));
        if (empty($newName)) throw new Exception('Nom invalide');
        $this->db->execute("UPDATE files SET original_name = ? WHERE id = ?", [$newName, $fileId]);
        return true;
    }

    public function moveFile(int $fileId, int $folderId, int $userId, bool $isAdmin = false): bool {
        $file = $this->getFileById($fileId);
        if (!$file) throw new Exception('Fichier introuvable');
        if ((int)$file['user_id'] !== $userId && !$isAdmin) throw new Exception('Permission refusée');

        // Vérifier que le dossier existe (si pas racine)
        if ($folderId > 0) {
            $folder = $this->getFolderById($folderId);
            if (!$folder) throw new Exception('Dossier introuvable');
            if ((int)$folder['user_id'] !== $userId && !$isAdmin) throw new Exception('Pas votre dossier');
        }

        $this->db->execute("UPDATE files SET folder_id = ? WHERE id = ?", [$folderId, $fileId]);

        $destName = $folderId > 0 ? $this->getFolderById($folderId)['name'] : 'Racine';
        $this->db->insert("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (?, 'move', ?, ?, NOW())",
            [$userId, "Déplacé: {$file['original_name']} → $destName", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        return true;
    }

    public function isPreviewable(string $ext): bool { return in_array(strtolower($ext), self::PREVIEWABLE); }

    public static function getPreviewType(string $ext): string {
        $ext = strtolower($ext);
        if (in_array($ext, ['jpg','jpeg','png','gif','bmp','svg','webp'])) return 'image';
        if (in_array($ext, ['mp4','webm','ogg'])) return 'video';
        if (in_array($ext, ['mp3','wav'])) return 'audio';
        if ($ext === 'pdf') return 'pdf';
        if (in_array($ext, ['txt','json','xml','html','css','js','csv','md'])) return 'text';
        return 'none';
    }

    // ── DOSSIERS ──────────────────────────────────────────────────

    public function createFolder(string $name, int $userId, int $parentId = 0): int {
        $name = trim($name);
        if (empty($name)) throw new Exception('Nom de dossier requis');
        return (int)$this->db->insert("INSERT INTO folders (name, user_id, parent_id) VALUES (?, ?, ?)", [$name, $userId, $parentId]);
    }

    public function getFolders(int $userId): array {
        return $this->db->fetchAll(
            "SELECT f.*, (SELECT COUNT(*) FROM files WHERE folder_id = f.id AND deleted_at IS NULL) as file_count
             FROM folders f WHERE f.user_id = ? ORDER BY f.name ASC",
            [$userId]
        );
    }

    public function getAllFolders(): array {
        return $this->db->fetchAll(
            "SELECT f.*, u.pseudo as owner_name, (SELECT COUNT(*) FROM files WHERE folder_id = f.id AND deleted_at IS NULL) as file_count
             FROM folders f LEFT JOIN users u ON f.user_id = u.id ORDER BY u.pseudo ASC, f.name ASC"
        );
    }

    public function getFolderById(int $id): ?array {
        return $this->db->fetchOne("SELECT * FROM folders WHERE id = ?", [$id]);
    }

    public function deleteFolder(int $folderId, int $userId): bool {
        $folder = $this->getFolderById($folderId);
        if (!$folder || (int)$folder['user_id'] !== $userId) throw new Exception('Dossier introuvable');
        $this->db->execute("UPDATE files SET folder_id = 0 WHERE folder_id = ? AND user_id = ?", [$folderId, $userId]);
        $this->db->execute("DELETE FROM folders WHERE id = ?", [$folderId]);
        return true;
    }

    // ── PARTAGES ──────────────────────────────────────────────────

    public function createShare(int $fileId, int $userId, int $hours = 24): array {
        $file = $this->getFileById($fileId);
        if (!$file) throw new Exception('Fichier introuvable');
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
        $id = $this->db->insert("INSERT INTO shares (token, file_id, user_id, expires_at) VALUES (?, ?, ?, ?)",
            [$token, $fileId, $userId, $expiresAt]);
        return $this->db->fetchOne("SELECT * FROM shares WHERE id = ?", [$id]);
    }

    public function getShareByToken(string $token): ?array {
        $share = $this->db->fetchOne(
            "SELECT s.*, f.original_name, f.filename, f.file_extension, f.file_size, u.pseudo as owner_name
             FROM shares s
             JOIN files f ON s.file_id = f.id AND f.deleted_at IS NULL
             JOIN users u ON s.user_id = u.id
             WHERE s.token = ? AND s.expires_at > NOW()",
            [$token]
        );
        return $share;
    }

    public function getFileShares(int $fileId): array {
        return $this->db->fetchAll("SELECT * FROM shares WHERE file_id = ? ORDER BY created_at DESC", [$fileId]);
    }

    // ── STATS ─────────────────────────────────────────────────────

    public function getGlobalStats(): array {
        $stats = $this->db->fetchOne("SELECT COUNT(*) as total_files, COALESCE(SUM(file_size), 0) as total_size, COUNT(DISTINCT user_id) as total_users FROM files WHERE deleted_at IS NULL");
        $topUploaders = $this->db->fetchAll("SELECT u.pseudo, COUNT(f.id) as file_count, COALESCE(SUM(f.file_size), 0) as total_size FROM users u LEFT JOIN files f ON u.id = f.user_id AND f.deleted_at IS NULL GROUP BY u.id ORDER BY file_count DESC LIMIT 5");
        return [
            'total_files' => (int)$stats['total_files'], 'total_size' => (int)$stats['total_size'],
            'total_users' => (int)$stats['total_users'], 'top_uploaders' => $topUploaders,
        ];
    }

    public function getUserStats(int $userId): array {
        $stats = $this->db->fetchOne("SELECT COUNT(*) as total_uploads, COALESCE(SUM(file_size), 0) as total_size, MIN(uploaded_at) as first_upload, MAX(uploaded_at) as last_upload FROM files WHERE user_id = ? AND deleted_at IS NULL", [$userId]);
        $byExtension = $this->db->fetchAll("SELECT file_extension, COUNT(*) as count FROM files WHERE user_id = ? AND deleted_at IS NULL GROUP BY file_extension ORDER BY count DESC", [$userId]);
        return [
            'total_uploads' => (int)($stats['total_uploads'] ?? 0), 'total_size' => (int)($stats['total_size'] ?? 0),
            'first_upload' => $stats['first_upload'] ?? null, 'last_upload' => $stats['last_upload'] ?? null,
            'by_extension' => $byExtension,
        ];
    }

    public function getRecentActivity(int $limit = 20): array {
        return $this->db->fetchAll("SELECT al.*, u.pseudo FROM activity_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT ?", [$limit]);
    }

    public function getAvailableExtensions(): array {
        return $this->db->fetchAll("SELECT DISTINCT file_extension, COUNT(*) as count FROM files WHERE deleted_at IS NULL GROUP BY file_extension ORDER BY count DESC");
    }

    // ── HELPERS ───────────────────────────────────────────────────

    public static function formatBytes(int $bytes): string {
        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
    }

    public static function getFileIcon(string $extension): string {
        $icons = [
            'pdf' => '<i class="bi bi-file-earmark-pdf-fill" style="color:#ef4444"></i>',
            'doc' => '<i class="bi bi-file-earmark-word-fill" style="color:#2563eb"></i>',
            'docx' => '<i class="bi bi-file-earmark-word-fill" style="color:#2563eb"></i>',
            'txt' => '<i class="bi bi-file-earmark-text-fill" style="color:#64748b"></i>',
            'rtf' => '<i class="bi bi-file-earmark-text-fill" style="color:#64748b"></i>',
            'odt' => '<i class="bi bi-file-earmark-word-fill" style="color:#2563eb"></i>',
            'xls' => '<i class="bi bi-file-earmark-excel-fill" style="color:#16a34a"></i>',
            'xlsx' => '<i class="bi bi-file-earmark-excel-fill" style="color:#16a34a"></i>',
            'csv' => '<i class="bi bi-file-earmark-spreadsheet-fill" style="color:#16a34a"></i>',
            'ods' => '<i class="bi bi-file-earmark-excel-fill" style="color:#16a34a"></i>',
            'ppt' => '<i class="bi bi-file-earmark-ppt-fill" style="color:#ea580c"></i>',
            'pptx' => '<i class="bi bi-file-earmark-ppt-fill" style="color:#ea580c"></i>',
            'jpg' => '<i class="bi bi-file-earmark-image-fill" style="color:#8b5cf6"></i>',
            'jpeg' => '<i class="bi bi-file-earmark-image-fill" style="color:#8b5cf6"></i>',
            'png' => '<i class="bi bi-file-earmark-image-fill" style="color:#8b5cf6"></i>',
            'gif' => '<i class="bi bi-file-earmark-image-fill" style="color:#8b5cf6"></i>',
            'bmp' => '<i class="bi bi-file-earmark-image-fill" style="color:#8b5cf6"></i>',
            'svg' => '<i class="bi bi-file-earmark-image-fill" style="color:#8b5cf6"></i>',
            'webp' => '<i class="bi bi-file-earmark-image-fill" style="color:#8b5cf6"></i>',
            'mp3' => '<i class="bi bi-file-earmark-music-fill" style="color:#ec4899"></i>',
            'wav' => '<i class="bi bi-file-earmark-music-fill" style="color:#ec4899"></i>',
            'flac' => '<i class="bi bi-file-earmark-music-fill" style="color:#ec4899"></i>',
            'mp4' => '<i class="bi bi-file-earmark-play-fill" style="color:#0ea5e9"></i>',
            'avi' => '<i class="bi bi-file-earmark-play-fill" style="color:#0ea5e9"></i>',
            'mkv' => '<i class="bi bi-file-earmark-play-fill" style="color:#0ea5e9"></i>',
            'mov' => '<i class="bi bi-file-earmark-play-fill" style="color:#0ea5e9"></i>',
            'webm' => '<i class="bi bi-file-earmark-play-fill" style="color:#0ea5e9"></i>',
            'zip' => '<i class="bi bi-file-earmark-zip-fill" style="color:#d97706"></i>',
            'rar' => '<i class="bi bi-file-earmark-zip-fill" style="color:#d97706"></i>',
            '7z' => '<i class="bi bi-file-earmark-zip-fill" style="color:#d97706"></i>',
            'tar' => '<i class="bi bi-file-earmark-zip-fill" style="color:#d97706"></i>',
            'gz' => '<i class="bi bi-file-earmark-zip-fill" style="color:#d97706"></i>',
            'json' => '<i class="bi bi-file-earmark-code-fill" style="color:#059669"></i>',
            'xml' => '<i class="bi bi-file-earmark-code-fill" style="color:#059669"></i>',
            'html' => '<i class="bi bi-file-earmark-code-fill" style="color:#059669"></i>',
            'css' => '<i class="bi bi-file-earmark-code-fill" style="color:#059669"></i>',
            'js' => '<i class="bi bi-file-earmark-code-fill" style="color:#059669"></i>',
            'py' => '<i class="bi bi-file-earmark-code-fill" style="color:#059669"></i>',
            'java' => '<i class="bi bi-file-earmark-code-fill" style="color:#059669"></i>',
            'c' => '<i class="bi bi-file-earmark-code-fill" style="color:#059669"></i>',
            'cpp' => '<i class="bi bi-file-earmark-code-fill" style="color:#059669"></i>',
            'md' => '<i class="bi bi-file-earmark-text-fill" style="color:#64748b"></i>',
        ];
        return $icons[$extension] ?? '<i class="bi bi-file-earmark-fill" style="color:#64748b"></i>';
    }
}
