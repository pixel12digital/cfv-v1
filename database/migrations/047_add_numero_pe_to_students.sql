-- Migration 047: Adicionar numero_pe (campo exclusivo DETRAN-PE para CFCs em Pernambuco)
-- Número de 9 dígitos exigido pelo DETRAN-PE no cadastro do candidato.

SELECT @col_exists := COUNT(*) FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'students'
  AND COLUMN_NAME = 'numero_pe';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `students` ADD COLUMN `numero_pe` VARCHAR(9) DEFAULT NULL COMMENT ''Número PE - exigência DETRAN-PE para CFC (9 dígitos)'' AFTER `rg_issue_date`',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
