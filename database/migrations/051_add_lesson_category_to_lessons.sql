-- Migration 051: Adicionar categoria de aula prática na tabela lessons
-- Data: 2026-03-02
-- Objetivo: Vincular cada aula agendada a uma categoria específica

SET FOREIGN_KEY_CHECKS = 0;

-- Adicionar coluna lesson_category_id à tabela lessons
ALTER TABLE `lessons` 
ADD COLUMN `lesson_category_id` int(11) DEFAULT NULL 
COMMENT 'Categoria da aula prática (A, B, C, etc.)' 
AFTER `vehicle_id`;

-- Criar índice para consultas por categoria
CREATE INDEX `idx_lessons_category` ON `lessons` (`lesson_category_id`);

-- Adicionar constraint de foreign key
ALTER TABLE `lessons`
ADD CONSTRAINT `lessons_ibfk_7` FOREIGN KEY (`lesson_category_id`) REFERENCES `lesson_categories` (`id`);

SET FOREIGN_KEY_CHECKS = 1;
