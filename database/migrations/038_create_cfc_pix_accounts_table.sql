-- Migration 038: Criar tabela cfc_pix_accounts para suporte a múltiplas contas PIX por CFC
-- IDEMPOTENTE: Verifica se tabela já existe antes de criar

-- Verificar se tabela já existe
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cfc_pix_accounts');

SET @sql = IF(@table_exists = 0, 
    'CREATE TABLE IF NOT EXISTS `cfc_pix_accounts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `cfc_id` int(11) NOT NULL,
        `label` varchar(255) NOT NULL COMMENT ''Apelido/nome da conta (ex: PagBank, Efí)'',
        `bank_code` varchar(10) DEFAULT NULL COMMENT ''Código do banco (ex: 290, 364)'',
        `bank_name` varchar(255) DEFAULT NULL COMMENT ''Nome do banco/instituição'',
        `agency` varchar(20) DEFAULT NULL COMMENT ''Agência (opcional)'',
        `account_number` varchar(20) DEFAULT NULL COMMENT ''Número da conta (opcional)'',
        `account_type` varchar(50) DEFAULT NULL COMMENT ''Tipo de conta (corrente, poupança, etc)'',
        `holder_name` varchar(255) NOT NULL COMMENT ''Nome do titular'',
        `holder_document` varchar(20) DEFAULT NULL COMMENT ''CPF/CNPJ do titular'',
        `pix_key` varchar(255) NOT NULL COMMENT ''Chave PIX'',
        `pix_key_type` enum(''cpf'',''cnpj'',''email'',''telefone'',''aleatoria'') DEFAULT NULL COMMENT ''Tipo da chave PIX'',
        `note` text DEFAULT NULL COMMENT ''Observações adicionais'',
        `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''Conta padrão do CFC'',
        `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT ''Conta ativa'',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `cfc_id` (`cfc_id`),
        KEY `is_default` (`is_default`),
        KEY `is_active` (`is_active`),
        CONSTRAINT `cfc_pix_accounts_ibfk_1` FOREIGN KEY (`cfc_id`) REFERENCES `cfcs` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Contas PIX do CFC'';',
    'SELECT ''Tabela cfc_pix_accounts já existe, pulando...'' AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
