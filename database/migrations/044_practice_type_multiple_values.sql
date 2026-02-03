-- Migration: Permitir múltiplos tipos de aula prática (rua, garagem, baliza)
-- Data: 2026-02-03
-- Permite que uma aula tenha mais de um tipo (ex: rua,garagem,baliza)

-- Alterar coluna de ENUM para VARCHAR para armazenar valores separados por vírgula
ALTER TABLE `lessons` 
MODIFY COLUMN `practice_type` VARCHAR(50) NULL DEFAULT NULL 
COMMENT 'Tipos de aula prática (separados por vírgula): rua, garagem, baliza';
