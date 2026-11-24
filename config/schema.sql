-- ═══════════════════════════════════════════════════════════════
-- SCHÉMA BASE DE DONNÉES - Drive Partagé
-- ═══════════════════════════════════════════════════════════════

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pseudo VARCHAR(50) NOT NULL UNIQUE,
    mdp VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    is_banned TINYINT(1) NOT NULL DEFAULT 0,
    banned_until DATETIME DEFAULT NULL,
    ban_reason VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pseudo (pseudo),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des dossiers
CREATE TABLE IF NOT EXISTS folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    parent_id INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_parent_id (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des fichiers uploadés
CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    file_extension VARCHAR(20) DEFAULT '',
    ip_address VARCHAR(45) DEFAULT '',
    folder_id INT DEFAULT 0,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_at DATETIME NOT NULL,
    deleted_at DATETIME DEFAULT NULL,
    deleted_by INT DEFAULT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_uploaded_at (uploaded_at),
    INDEX idx_deleted_at (deleted_at),
    INDEX idx_folder_id (folder_id),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des liens de partage
CREATE TABLE IF NOT EXISTS shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    file_id INT NOT NULL,
    user_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    download_limit INT UNSIGNED DEFAULT NULL,
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_file_id (file_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Historique des téléchargements via liens de partage
CREATE TABLE IF NOT EXISTS share_access_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    share_id INT NOT NULL,
    access_type ENUM('download', 'preview') NOT NULL DEFAULT 'download',
    accessed_at DATETIME NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    INDEX idx_share_access_time (share_id, accessed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Limitation des erreurs de mot de passe sur les liens publics
CREATE TABLE IF NOT EXISTS share_password_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    share_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    attempted_at DATETIME NOT NULL,
    INDEX idx_share_password_attempt (share_id, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des tentatives de connexion
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(100) DEFAULT '',
    attempted_at DATETIME NOT NULL,
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table du journal d'activité
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT '',
    created_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Réglages globaux administrables depuis le panneau d'administration
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
