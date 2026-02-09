-- Migration 046: Adicionar aulas_contratadas na tabela enrollments
-- Quantidade de aulas práticas contratadas na matrícula (NULL = sem limite, retrocompatível)

SELECT @col_exists := COUNT(*) FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'enrollments'
  AND COLUMN_NAME = 'aulas_contratadas';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `enrollments` ADD COLUMN `aulas_contratadas` int(11) DEFAULT NULL COMMENT ''Quantidade de aulas práticas contratadas (NULL = sem limite)'' AFTER `status`',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
