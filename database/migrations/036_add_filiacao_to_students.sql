-- Migration 036: Adicionar Filiação (nome_mae, nome_pai) à tabela students
-- Idempotente: verifica se as colunas já existem antes de adicionar.
-- Seguro para rodar em local e produção; não gera duplicidade.

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- nome_mae: só adiciona se não existir
SET @exist_mae = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'nome_mae');
SET @sql_mae = IF(@exist_mae = 0,
  'ALTER TABLE `students` ADD COLUMN `nome_mae` VARCHAR(255) DEFAULT NULL AFTER `rg_issue_date`',
  'SELECT 1');
PREPARE stmt_mae FROM @sql_mae;
EXECUTE stmt_mae;
DEALLOCATE PREPARE stmt_mae;

-- nome_pai: só adiciona se não existir
SET @exist_pai = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'nome_pai');
SET @sql_pai = IF(@exist_pai = 0,
  'ALTER TABLE `students` ADD COLUMN `nome_pai` VARCHAR(255) DEFAULT NULL AFTER `nome_mae`',
  'SELECT 1');
PREPARE stmt_pai FROM @sql_pai;
EXECUTE stmt_pai;
DEALLOCATE PREPARE stmt_pai;

SET FOREIGN_KEY_CHECKS = 1;
