-- À exécuter une seule fois si migrate_share_security.sql avait déjà été appliquée
-- avant l'ajout de la limitation des essais de mot de passe.
CREATE TABLE IF NOT EXISTS share_password_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    share_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    attempted_at DATETIME NOT NULL,
    INDEX idx_share_password_attempt (share_id, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
