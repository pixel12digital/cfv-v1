-- Migration: Criar tabela sessoes (CORREÇÃO DO PROBLEMA DE LOGIN)
-- Esta tabela é necessária para o funcionamento do sistema de autenticação

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Criar tabela de sessões para armazenar tokens de autenticação
CREATE TABLE IF NOT EXISTS `sessoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do usuário dono da sessão',
  `token` varchar(255) NOT NULL COMMENT 'Token único da sessão',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address de origem',
  `user_agent` text DEFAULT NULL COMMENT 'User agent do navegador',
  `expira_em` timestamp NOT NULL COMMENT 'Data/hora de expiração da sessão',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora de criação',
  PRIMARY KEY (`id`),
  KEY `idx_usuario_id` (`usuario_id`),
  KEY `idx_token` (`token`),
  KEY `idx_expira_em` (`expira_em`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de sessões do sistema de autenticação';

SET FOREIGN_KEY_CHECKS = 1;
