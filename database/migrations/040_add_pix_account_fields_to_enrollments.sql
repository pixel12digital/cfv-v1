-- Migration 040: Adicionar campos pix_account_id e pix_account_snapshot em enrollments
-- IDEMPOTENTE: Verifica se colunas já existem antes de adicionar

-- Verificar e adicionar pix_account_id
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'enrollments' 
    AND COLUMN_NAME = 'pix_account_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `enrollments` ADD COLUMN `pix_account_id` int(11) DEFAULT NULL COMMENT ''ID da conta PIX usada no pagamento'' AFTER `payment_method`,
    ADD KEY `pix_account_id` (`pix_account_id`),
    ADD CONSTRAINT `enrollments_ibfk_pix_account` FOREIGN KEY (`pix_account_id`) REFERENCES `cfc_pix_accounts` (`id`) ON DELETE SET NULL;',
    'SELECT ''Coluna pix_account_id já existe, pulando...'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar pix_account_snapshot
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'enrollments' 
    AND COLUMN_NAME = 'pix_account_snapshot');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `enrollments` ADD COLUMN `pix_account_snapshot` JSON DEFAULT NULL COMMENT ''Snapshot dos dados da conta PIX no momento do pagamento (para histórico imutável)'' AFTER `pix_account_id`;',
    'SELECT ''Coluna pix_account_snapshot já existe, pulando...'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
