-- Migration 050: Criar tabela de quotas de aulas práticas por categoria na matrícula
-- Data: 2026-03-02
-- Objetivo: Distribuir quantidade de aulas contratadas por categoria

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Tabela de Quotas de Aulas por Categoria
CREATE TABLE IF NOT EXISTS `enrollment_lesson_quotas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enrollment_id` int(11) NOT NULL COMMENT 'ID da matrícula',
  `lesson_category_id` int(11) NOT NULL COMMENT 'ID da categoria de aula',
  `quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'Quantidade de aulas contratadas desta categoria',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enrollment_category` (`enrollment_id`, `lesson_category_id`),
  KEY `enrollment_id` (`enrollment_id`),
  KEY `lesson_category_id` (`lesson_category_id`),
  CONSTRAINT `enrollment_lesson_quotas_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollment_lesson_quotas_ibfk_2` FOREIGN KEY (`lesson_category_id`) REFERENCES `lesson_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
