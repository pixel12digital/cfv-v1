-- Migration 037: Adicionar campos de configuração PIX na tabela cfcs
-- Permite armazenar dados do PIX do CFC para pagamentos locais/manuais
-- IDEMPOTENTE: Verifica se colunas já existem antes de adicionar

-- Verificar e adicionar pix_banco
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cfcs' 
    AND COLUMN_NAME = 'pix_banco');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `cfcs` ADD COLUMN `pix_banco` VARCHAR(255) DEFAULT NULL COMMENT ''Banco/Instituição do PIX do CFC'' AFTER `logo_path`',
    'SELECT ''Coluna pix_banco já existe, pulando...'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar pix_titular
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cfcs' 
    AND COLUMN_NAME = 'pix_titular');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `cfcs` ADD COLUMN `pix_titular` VARCHAR(255) DEFAULT NULL COMMENT ''Nome do titular da conta PIX'' AFTER `pix_banco`',
    'SELECT ''Coluna pix_titular já existe, pulando...'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar pix_chave
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cfcs' 
    AND COLUMN_NAME = 'pix_chave');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `cfcs` ADD COLUMN `pix_chave` VARCHAR(255) DEFAULT NULL COMMENT ''Chave PIX (CPF, CNPJ, email, telefone ou chave aleatória)'' AFTER `pix_titular`',
    'SELECT ''Coluna pix_chave já existe, pulando...'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar pix_observacao
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cfcs' 
    AND COLUMN_NAME = 'pix_observacao');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `cfcs` ADD COLUMN `pix_observacao` TEXT DEFAULT NULL COMMENT ''Observação opcional sobre o PIX'' AFTER `pix_chave`',
    'SELECT ''Coluna pix_observacao já existe, pulando...'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
