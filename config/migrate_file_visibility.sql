-- Ajoute la visibilité publique/privée aux fichiers existants.
-- À exécuter une seule fois sur une base déjà installée.
ALTER TABLE files
    ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER folder_id,
    ADD INDEX idx_is_public (is_public);
