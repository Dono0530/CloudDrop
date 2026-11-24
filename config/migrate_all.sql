-- Migration unique et relançable pour mettre une base existante au schéma CloudDrop actuel.
-- Sauvegardez la base avant exécution. Sélectionnez d'abord la base cible.
SET @db_name = DATABASE();
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'files' AND COLUMN_NAME = 'is_public') = 0,
    'ALTER TABLE files ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER folder_id',
    'SELECT 1'
);
PREPARE clouddrop_stmt FROM @sql; EXECUTE clouddrop_stmt; DEALLOCATE PREPARE clouddrop_stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_is_public') = 0,
    'ALTER TABLE files ADD INDEX idx_is_public (is_public)',
    'SELECT 1'
);
PREPARE clouddrop_stmt FROM @sql; EXECUTE clouddrop_stmt; DEALLOCATE PREPARE clouddrop_stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'shares' AND COLUMN_NAME = 'password_hash') = 0,
    'ALTER TABLE shares ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER expires_at',
    'SELECT 1'
);
PREPARE clouddrop_stmt FROM @sql; EXECUTE clouddrop_stmt; DEALLOCATE PREPARE clouddrop_stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'shares' AND COLUMN_NAME = 'download_limit') = 0,
    'ALTER TABLE shares ADD COLUMN download_limit INT UNSIGNED DEFAULT NULL AFTER password_hash',
    'SELECT 1'
);
PREPARE clouddrop_stmt FROM @sql; EXECUTE clouddrop_stmt; DEALLOCATE PREPARE clouddrop_stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'shares' AND COLUMN_NAME = 'download_count') = 0,
    'ALTER TABLE shares ADD COLUMN download_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER download_limit',
    'SELECT 1'
);
PREPARE clouddrop_stmt FROM @sql; EXECUTE clouddrop_stmt; DEALLOCATE PREPARE clouddrop_stmt;

CREATE TABLE IF NOT EXISTS share_access_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    share_id INT NOT NULL,
    access_type ENUM('download', 'preview') NOT NULL DEFAULT 'download',
    accessed_at DATETIME NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    INDEX idx_share_access_time (share_id, accessed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'share_access_log' AND COLUMN_NAME = 'access_type') = 0,
    'ALTER TABLE share_access_log ADD COLUMN access_type ENUM(''download'', ''preview'') NOT NULL DEFAULT ''download'' AFTER share_id',
    'SELECT 1'
);
PREPARE clouddrop_stmt FROM @sql; EXECUTE clouddrop_stmt; DEALLOCATE PREPARE clouddrop_stmt;

CREATE TABLE IF NOT EXISTS share_password_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    share_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    attempted_at DATETIME NOT NULL,
    INDEX idx_share_password_attempt (share_id, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
