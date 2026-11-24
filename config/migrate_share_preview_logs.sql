-- À exécuter une seule fois si migrate_share_security.sql a déjà été appliquée.
ALTER TABLE share_access_log
    ADD COLUMN access_type ENUM('download', 'preview') NOT NULL DEFAULT 'download' AFTER share_id;
