-- Migration 039: Migrar dados PIX antigos da tabela cfcs para cfc_pix_accounts
-- IDEMPOTENTE: Só migra se não houver contas na nova tabela e se existirem dados antigos

-- Verificar se já existem contas na nova tabela
SET @has_accounts = (SELECT COUNT(*) FROM `cfc_pix_accounts`);

-- Verificar se existem CFCs com dados PIX antigos
SET @has_old_data = (SELECT COUNT(*) FROM `cfcs` 
    WHERE (`pix_chave` IS NOT NULL AND `pix_chave` != '') 
    OR (`pix_titular` IS NOT NULL AND `pix_titular` != ''));

-- Só migrar se não houver contas na nova tabela E houver dados antigos
SET @should_migrate = IF(@has_accounts = 0 AND @has_old_data > 0, 1, 0);

-- Migrar dados
SET @sql = IF(@should_migrate = 1,
    CONCAT('
        INSERT INTO `cfc_pix_accounts` (
            `cfc_id`, 
            `label`, 
            `bank_code`, 
            `bank_name`, 
            `holder_name`, 
            `holder_document`, 
            `pix_key`, 
            `pix_key_type`, 
            `note`, 
            `is_default`, 
            `is_active`,
            `created_at`
        )
        SELECT 
            `id` as `cfc_id`,
            COALESCE(`pix_banco`, ''PIX Principal'') as `label`,
            NULL as `bank_code`,
            `pix_banco` as `bank_name`,
            COALESCE(`pix_titular`, ''Titular não informado'') as `holder_name`,
            NULL as `holder_document`,
            `pix_chave` as `pix_key`,
            CASE 
                WHEN `pix_chave` REGEXP ''^[0-9]{11}$'' THEN ''cpf''
                WHEN `pix_chave` REGEXP ''^[0-9]{14}$'' THEN ''cnpj''
                WHEN `pix_chave` REGEXP ''^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$'' THEN ''email''
                WHEN `pix_chave` REGEXP ''^\\+?[0-9]{10,11}$'' THEN ''telefone''
                ELSE ''aleatoria''
            END as `pix_key_type`,
            `pix_observacao` as `note`,
            1 as `is_default`,
            1 as `is_active`,
            NOW() as `created_at`
        FROM `cfcs`
        WHERE (`pix_chave` IS NOT NULL AND `pix_chave` != '') 
        OR (`pix_titular` IS NOT NULL AND `pix_titular` != '');
    '),
    'SELECT ''Migração não necessária (já existem contas ou não há dados antigos)'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
