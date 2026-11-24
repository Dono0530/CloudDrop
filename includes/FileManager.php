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

    public function recordUpload(string $filename, int $userId, int $fileSize, string $originalName, int $folderId = 0, bool $isPublic = false): array {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $ip = clouddrop_client_ip();
        $id = $this->db->insert(
            "INSERT INTO files (filename, original_name, user_id, file_size, file_extension, ip_address, folder_id, is_public, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$filename, $originalName, $userId, $fileSize, $ext, $ip, $folderId, $isPublic ? 1 : 0]
        );
        $this->db->insert("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (?, 'upload', ?, ?, NOW())",
            [$userId, "Upload: $originalName (" . self::formatBytes($fileSize) . ")", $ip]);
        return $this->db->fetchOne("SELECT * FROM files WHERE id = ?", [$id]);
    }

    public function getFiles(array $filters = []): array {
        $sql = "SELECT f.*, u.pseudo as uploader_name FROM files f LEFT JOIN users u ON f.user_id = u.id WHERE f.deleted_at IS NULL";
        $params = [];
        if (!empty($filters['user_id'])) {
            $sql .= " AND f.user_id = ?";
            $params[] = (int)$filters['user_id'];
        }
        if (array_key_exists('folder_id', $filters)) {
            $sql .= " AND f.folder_id = ?";
            $params[] = (int)$filters['folder_id'];
        }
        if (array_key_exists('is_public', $filters)) {
            $sql .= " AND f.is_public = ?";
            $params[] = !empty($filters['is_public']) ? 1 : 0;
        }
        if (array_key_exists('visible_to_user_id', $filters)) {
            $sql .= " AND (f.is_public = 1 OR f.user_id = ?)";
            $params[] = (int)$filters['visible_to_user_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (f.original_name LIKE ? OR f.filename LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
        }
        if (!empty($filters['extension'])) {
            $sql .= " AND f.file_extension = ?";
            $params[] = $filters['extension'];
        }

        $sort = $filters['sort'] ?? 'date_desc';
        $sql .= match ($sort) {
            'date_asc' => " ORDER BY f.uploaded_at ASC", 'name_asc' => " ORDER BY f.original_name ASC",
            'name_desc' => " ORDER BY f.original_name DESC", 'size_asc' => " ORDER BY f.file_size ASC",
            'size_desc' => " ORDER BY f.file_size DESC", default => " ORDER BY f.uploaded_at DESC",
        };

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$filters['limit'];
            $params[] = (int)($filters['offset'] ?? 0);
        }
        return $this->db->fetchAll($sql, $params);
    }

    public function countFiles(array $filters = []): int {
        $sql = "SELECT COUNT(*) as total FROM files WHERE deleted_at IS NULL";
        $params = [];
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = (int)$filters['user_id'];
        }
        if (array_key_exists('folder_id', $filters)) {
            $sql .= " AND folder_id = ?";
            $params[] = (int)$filters['folder_id'];
        }
        if (array_key_exists('is_public', $filters)) {
            $sql .= " AND is_public = ?";
            $params[] = !empty($filters['is_public']) ? 1 : 0;
        }
        if (array_key_exists('visible_to_user_id', $filters)) {
            $sql .= " AND (is_public = 1 OR user_id = ?)";
            $params[] = (int)$filters['visible_to_user_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (original_name LIKE ? OR filename LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
        }
        if (!empty($filters['extension'])) {
            $sql .= " AND file_extension = ?";
            $params[] = $filters['extension'];
        }
        return (int)($this->db->fetchOne($sql, $params)['total'] ?? 0);
    }

    public function getFileById(int $id): ?array {
        return $this->db->fetchOne("SELECT f.*, u.pseudo as uploader_name FROM files f LEFT JOIN users u ON f.user_id = u.id WHERE f.id = ? AND f.deleted_at IS NULL", [$id]);
    }

    public function getFileByFilename(string $filename): ?array {
        return $this->db->fetchOne(
            "SELECT f.*, u.pseudo as uploader_name FROM files f LEFT JOIN users u ON f.user_id = u.id WHERE f.filename = ? AND f.deleted_at IS NULL",
            [basename($filename)]
        );
    }

    public function canAccessFile(array $file, ?int $userId = null, bool $isAdmin = false): bool {
        return $isAdmin
            || !empty($file['is_public'])
            || ($userId !== null && (int)$file['user_id'] === $userId);
    }

    public function removeUserFilesFromDisk(int $userId): int {
        if ($userId <= 0) throw new Exception('Utilisateur invalide');

        $files = $this->db->fetchAll("SELECT filename FROM files WHERE user_id = ?", [$userId]);
        if (empty($files)) return 0;

        if (!is_dir(UPLOAD_DIR)) return 0;
        $uploadDirectory = realpath(UPLOAD_DIR);
        if ($uploadDirectory === false) throw new Exception('Dossier de stockage inaccessible');

        $removed = 0;
        foreach ($files as $file) {
            $filename = basename((string)($file['filename'] ?? ''));
            if ($filename === '' || $filename === '.' || $filename === '..') {
                throw new Exception('Nom de fichier invalide dans le stockage');
            }

            $path = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
            if (is_link($path) || is_file($path)) {
                if (!unlink($path)) {
                    throw new Exception('Impossible de supprimer un fichier du stockage');
                }
                $removed++;
            } elseif (file_exists($path)) {
                throw new Exception('Un élément inattendu bloque le nettoyage du stockage');
            }
        }

        return $removed;
    }

    public function setFileVisibility(int $fileId, bool $isPublic, int $userId, bool $isAdmin = false): bool {
        $file = $this->getFileById($fileId);
        if (!$file) throw new Exception('Fichier introuvable');
        if ((int)$file['user_id'] !== $userId && !$isAdmin) throw new Exception('Permission refusée');

        $this->db->execute("UPDATE files SET is_public = ? WHERE id = ?", [$isPublic ? 1 : 0, $fileId]);
        return true;
    }

    public function deleteFile(int $fileId, int $userId, bool $isAdmin = false): bool {
        $file = $this->getFileById($fileId);
        if (!$file) throw new Exception('Fichier introuvable');
        if ((int)$file['user_id'] !== $userId && !$isAdmin) throw new Exception('Permission refusée');
        $uploadDirectory = realpath(UPLOAD_DIR);
        if ($uploadDirectory !== false) {
            $filename = basename((string)$file['filename']);
            if ($filename === '' || $filename === '.' || $filename === '..') {
                throw new Exception('Nom de fichier invalide dans le stockage');
            }
            $candidatePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
            if (is_link($candidatePath)) {
                throw new Exception('Lien de stockage inattendu');
            }
            $realPath = realpath($candidatePath);
            if ($realPath !== false
                && (strpos($realPath, $uploadDirectory . DIRECTORY_SEPARATOR) !== 0 || !is_file($realPath))) {
                throw new Exception('Chemin de stockage invalide');
            }
        }

        $this->db->execute("UPDATE files SET deleted_at = NOW(), deleted_by = ? WHERE id = ?", [$userId, $fileId]);
        if ($realPath !== false && !unlink($realPath)) {
            throw new Exception('Impossible de supprimer le fichier du stockage');
        }
        $this->db->insert("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (?, 'delete', ?, ?, NOW())",
            [$userId, "Suppression: {$file['original_name']}", clouddrop_client_ip()]);
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
            [$userId, "Déplacé: {$file['original_name']} → $destName", clouddrop_client_ip()]);
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

    public function getFoldersVisibleToUser(int $userId, bool $isAdmin = false): array {
        $visibility = $isAdmin
            ? ''
            : 'WHERE f.user_id = ? OR EXISTS (
                    SELECT 1 FROM files public_file
                    WHERE public_file.folder_id = f.id
                      AND public_file.deleted_at IS NULL
                      AND public_file.is_public = 1
                )';
        $params = $isAdmin ? [] : [$userId];
        $countVisibility = $isAdmin
            ? 'folder_file.deleted_at IS NULL'
            : '(folder_file.deleted_at IS NULL AND (f.user_id = ? OR folder_file.is_public = 1))';
        if (!$isAdmin) $params[] = $userId;

        return $this->db->fetchAll(
            "SELECT f.*, u.pseudo AS owner_name,
                    (SELECT COUNT(*) FROM files folder_file
                     WHERE folder_file.folder_id = f.id AND $countVisibility) AS file_count
             FROM folders f
             LEFT JOIN users u ON u.id = f.user_id
             $visibility
             ORDER BY f.name ASC",
            $params
        );
    }

    public function canAccessFolder(array $folder, int $userId, bool $isAdmin = false): bool {
        if ($isAdmin || (int)$folder['user_id'] === $userId) return true;

        return $this->db->fetchOne(
            "SELECT id FROM files
             WHERE folder_id = ? AND deleted_at IS NULL AND is_public = 1
             LIMIT 1",
            [(int)$folder['id']]
        ) !== null;
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

    private const SHARE_DURATIONS = [1, 24, 168, 720];
    private const SHARE_DOWNLOAD_LIMITS = [1, 5, 10, 25, 50, 100];

    public function createShare(int $fileId, int $userId, int $hours = 24, bool $isAdmin = false, string $password = '', ?int $downloadLimit = null): array {
        $file = $this->getFileById($fileId);
        if (!$file) throw new Exception('Fichier introuvable');
        if ((int)$file['user_id'] !== $userId && !$isAdmin) throw new Exception('Permission refusée');
        if (!in_array($hours, self::SHARE_DURATIONS, true)) throw new Exception('Durée de partage invalide');
        $this->validateShareSettings($password, $downloadLimit);

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $passwordHash = $password === '' ? null : password_hash($password, PASSWORD_DEFAULT);
        $id = $this->db->insert(
            "INSERT INTO shares (token, file_id, user_id, expires_at, password_hash, download_limit) VALUES (?, ?, ?, ?, ?, ?)",
            [$token, $fileId, $userId, $expiresAt, $passwordHash, $downloadLimit]
        );
        return $this->db->fetchOne("SELECT * FROM shares WHERE id = ?", [$id]);
    }

    private function validateShareSettings(string $password, ?int $downloadLimit): void {
        if ($password !== '' && (strlen($password) < 8 || strlen($password) > 128)) {
            throw new Exception('Le mot de passe du lien doit faire entre 8 et 128 caractères');
        }
        if ($downloadLimit !== null && !in_array($downloadLimit, self::SHARE_DOWNLOAD_LIMITS, true)) {
            throw new Exception('Limite de téléchargements invalide');
        }
    }

    public function updateShareSettings(int $shareId, int $userId, string $password, bool $removePassword, ?int $downloadLimit): bool {
        $this->validateShareSettings($password, $downloadLimit);
        $share = $this->db->fetchOne("SELECT id, password_hash FROM shares WHERE id = ? AND user_id = ?", [$shareId, $userId]);
        if (!$share) throw new Exception('Lien de partage introuvable');

        $passwordHash = $share['password_hash'];
        if ($removePassword) $passwordHash = null;
        elseif ($password !== '') $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $this->db->execute(
            "UPDATE shares SET password_hash = ?, download_limit = ? WHERE id = ? AND user_id = ?",
            [$passwordHash, $downloadLimit, $shareId, $userId]
        );
        return true;
    }

    public function isSharePasswordRateLimited(int $shareId, string $ipAddress): bool {
        $attempts = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM share_password_attempts
             WHERE share_id = ? AND ip_address = ?
               AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            [$shareId, substr($ipAddress, 0, 45)]
        );
        return (int)($attempts['total'] ?? 0) >= 5;
    }

    public function recordFailedSharePasswordAttempt(int $shareId, string $ipAddress): void {
        $ipAddress = substr($ipAddress, 0, 45);
        $this->db->execute(
            "DELETE FROM share_password_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)"
        );
        $this->db->execute(
            "INSERT INTO share_password_attempts (share_id, ip_address, attempted_at) VALUES (?, ?, NOW())",
            [$shareId, $ipAddress]
        );
    }

    public function clearSharePasswordAttempts(int $shareId, string $ipAddress): void {
        $this->db->execute(
            "DELETE FROM share_password_attempts WHERE share_id = ? AND ip_address = ?",
            [$shareId, substr($ipAddress, 0, 45)]
        );
    }

    public function recordShareDownload(int $shareId, string $ipAddress, string $userAgent): bool {
        $updated = $this->db->execute(
            "UPDATE shares SET download_count = download_count + 1
             WHERE id = ? AND expires_at > NOW()
               AND (download_limit IS NULL OR download_count < download_limit)",
            [$shareId]
        );
        if ($updated !== 1) return false;

        $this->db->execute(
            "INSERT INTO share_access_log (share_id, access_type, accessed_at, ip_address, user_agent)
             VALUES (?, 'download', NOW(), ?, ?)",
            [$shareId, substr($ipAddress, 0, 45), substr($userAgent, 0, 255)]
        );
        return true;
    }

    public function recordSharePreview(int $shareId, string $ipAddress, string $userAgent): void {
        $this->db->execute(
            "INSERT INTO share_access_log (share_id, access_type, accessed_at, ip_address, user_agent)
             VALUES (?, 'preview', NOW(), ?, ?)",
            [$shareId, substr($ipAddress, 0, 45), substr($userAgent, 0, 255)]
        );
    }

    public function getShareAccessLogs(int $shareId, int $userId): array {
        return $this->db->fetchAll(
            "SELECT l.access_type, l.accessed_at, l.ip_address, l.user_agent
             FROM share_access_log l
             JOIN shares s ON s.id = l.share_id
             WHERE s.id = ? AND s.user_id = ?
             ORDER BY l.accessed_at DESC
             LIMIT 20",
            [$shareId, $userId]
        );
    }

    public function getUserShares(int $userId): array {
        return $this->db->fetchAll(
            "SELECT s.id, s.token, s.file_id, s.expires_at, s.created_at,
                    f.original_name, f.file_extension, f.file_size, f.deleted_at AS file_deleted_at,
                    s.download_limit, s.download_count, (s.password_hash IS NOT NULL) AS password_protected
             FROM shares s
             LEFT JOIN files f ON f.id = s.file_id
             WHERE s.user_id = ?
             ORDER BY s.created_at DESC",
            [$userId]
        );
    }

    public function updateShareExpiry(int $shareId, int $userId, int $hours): bool {
        if (!in_array($hours, self::SHARE_DURATIONS, true)) throw new Exception('Durée de partage invalide');
        $share = $this->db->fetchOne(
            "SELECT s.id FROM shares s
             JOIN files f ON f.id = s.file_id AND f.deleted_at IS NULL
             WHERE s.id = ? AND s.user_id = ?",
            [$shareId, $userId]
        );
        if (!$share) throw new Exception('Lien de partage introuvable');

        $expiresAt = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $this->db->execute("UPDATE shares SET expires_at = ? WHERE id = ? AND user_id = ?", [$expiresAt, $shareId, $userId]);
        return true;
    }

    public function deleteShare(int $shareId, int $userId): bool {
        $share = $this->db->fetchOne("SELECT id FROM shares WHERE id = ? AND user_id = ?", [$shareId, $userId]);
        if (!$share) throw new Exception('Lien de partage introuvable');
        $this->db->execute("DELETE FROM share_access_log WHERE share_id = ?", [$shareId]);
        $this->db->execute("DELETE FROM share_password_attempts WHERE share_id = ?", [$shareId]);
        $this->db->execute("DELETE FROM shares WHERE id = ? AND user_id = ?", [$shareId, $userId]);
        return true;
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

    public function getShareByTokenForDisplay(string $token): ?array {
        $share = $this->db->fetchOne(
            "SELECT s.*, f.original_name, f.filename, f.file_extension, f.file_size, u.pseudo as owner_name, s.expires_at
             FROM shares s
             JOIN files f ON s.file_id = f.id AND f.deleted_at IS NULL
             JOIN users u ON s.user_id = u.id
             WHERE s.token = ?",
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

    public function getAvailableExtensions(?int $userId = null, bool $isAdmin = false): array {
        $sql = "SELECT DISTINCT file_extension, COUNT(*) as count FROM files WHERE deleted_at IS NULL";
        $params = [];
        if ($userId !== null && !$isAdmin) {
            $sql .= " AND (is_public = 1 OR user_id = ?)";
            $params[] = $userId;
        }
        $sql .= " GROUP BY file_extension ORDER BY count DESC";
        return $this->db->fetchAll($sql, $params);
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
