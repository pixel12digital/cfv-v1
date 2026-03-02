-- Migration 049: Criar tabela de categorias/tipos de aula prática
-- Data: 2026-03-02
-- Objetivo: Permitir controle de aulas práticas por categoria (A, B, C, D, E)

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Tabela de Categorias de Aula Prática
CREATE TABLE IF NOT EXISTS `lesson_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL COMMENT 'Código da categoria (A, B, C, D, E, AB, etc.)',
  `name` varchar(100) NOT NULL COMMENT 'Nome descritivo (ex: Moto, Carro, Caminhão)',
  `description` text DEFAULT NULL COMMENT 'Descrição detalhada da categoria',
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Categoria ativa',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `is_active` (`is_active`),
  KEY `order` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
