-- À exécuter une seule fois sur une base CloudDrop déjà installée.
ALTER TABLE shares
    ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER expires_at,
    ADD COLUMN download_limit INT UNSIGNED DEFAULT NULL AFTER password_hash,
    ADD COLUMN download_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER download_limit;

CREATE TABLE IF NOT EXISTS share_access_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    share_id INT NOT NULL,
    accessed_at DATETIME NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    INDEX idx_share_access_time (share_id, accessed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
