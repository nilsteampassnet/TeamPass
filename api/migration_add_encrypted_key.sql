-- ============================================================================
-- Migration SQL pour TeamPass API - Chiffrement des clés privées
-- ============================================================================
--
-- Cette migration ajoute les champs nécessaires pour stocker les clés privées
-- de manière sécurisée en utilisant le chiffrement AES-256-GCM avec une clé
-- de session unique.
--
-- SÉCURITÉ:
-- - La clé privée est stockée CHIFFRÉE en base de données
-- - La clé de déchiffrement (session_key) est stockée dans le JWT
-- - Les deux sont nécessaires ensemble (défense en profondeur)
--
-- IMPORTANT:
-- - Remplacez 'teampass_' par votre préfixe de tables si différent
-- - Sauvegardez votre base de données avant d'exécuter cette migration
--
-- ============================================================================

-- Vérifier que la table api existe
-- SELECT 'Checking if teampass_api table exists...' AS status;

-- Ajouter les nouvelles colonnes à la table api
ALTER TABLE `teampass_api`
ADD COLUMN IF NOT EXISTS `encrypted_private_key` TEXT NULL
  COMMENT 'Clé privée décryptée puis chiffrée avec AES-256-GCM et la clé de session',
ADD COLUMN IF NOT EXISTS `session_key_salt` VARCHAR(64) NULL
  COMMENT 'Salt pour la clé de session (sécurité additionnelle)',
ADD COLUMN IF NOT EXISTS `timestamp` INT(11) NULL
  COMMENT 'Timestamp de création/mise à jour de la session API';

-- Si votre version de MySQL/MariaDB ne supporte pas IF NOT EXISTS, utilisez:
--
-- ALTER TABLE `teampass_api` ADD COLUMN `encrypted_private_key` TEXT NULL;
-- ALTER TABLE `teampass_api` ADD COLUMN `session_key_salt` VARCHAR(64) NULL;
-- ALTER TABLE `teampass_api` ADD COLUMN `timestamp` INT(11) NULL;
--
-- (Ignorez les erreurs "Duplicate column name" si les colonnes existent déjà)

-- Optionnel: Créer un index sur timestamp pour les requêtes de nettoyage
CREATE INDEX IF NOT EXISTS `idx_api_timestamp` ON `teampass_api` (`timestamp`);

-- ============================================================================
-- SCRIPT DE NETTOYAGE OPTIONNEL
-- ============================================================================
--
-- Les sessions API expirées peuvent être nettoyées périodiquement.
-- Ajoutez ce script à un cron job quotidien:
--
-- DELETE FROM `teampass_api`
-- WHERE `timestamp` IS NOT NULL
-- AND `timestamp` < (UNIX_TIMESTAMP() - 86400);
--
-- (Nettoie les sessions de plus de 24 heures)
--
-- ============================================================================

SELECT 'Migration completed successfully!' AS status;
SELECT 'Please verify the new columns exist:' AS next_step;
SELECT 'DESCRIBE teampass_api;' AS command;
