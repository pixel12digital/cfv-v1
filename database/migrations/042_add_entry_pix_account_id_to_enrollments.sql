-- Migration 042: Adicionar campo entry_pix_account_id na tabela enrollments
-- Permite registrar qual conta PIX recebeu o pagamento da entrada

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Adicionar campo para conta PIX da entrada
ALTER TABLE `enrollments`
ADD COLUMN `entry_pix_account_id` int(11) DEFAULT NULL COMMENT 'Conta PIX que recebeu a entrada' AFTER `entry_payment_method`;

-- Adicionar índice para melhor performance
ALTER TABLE `enrollments`
ADD KEY `idx_entry_pix_account` (`entry_pix_account_id`);

-- Adicionar foreign key (opcional - comentado para flexibilidade)
-- ALTER TABLE `enrollments`
-- ADD CONSTRAINT `fk_entry_pix_account` FOREIGN KEY (`entry_pix_account_id`) REFERENCES `cfc_pix_accounts` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
