-- Migration: Adicionar tipo de aula prática (rua, garagem, baliza)
-- Data: 2026-01-30

-- Adicionar campo practice_type à tabela lessons
ALTER TABLE `lessons` 
ADD COLUMN `practice_type` ENUM('rua', 'garagem', 'baliza') NULL DEFAULT NULL 
COMMENT 'Tipo de aula prática: rua, garagem ou baliza' 
AFTER `type`;

-- Criar índice para consultas por tipo de prática
CREATE INDEX `idx_lessons_practice_type` ON `lessons` (`practice_type`);
