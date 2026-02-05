-- Migration 045: Adicionar campos para exclusão definitiva de matrículas (soft delete)
-- Permite excluir matrículas canceladas definitivamente, mantendo registro para auditoria

ALTER TABLE `enrollments`
ADD COLUMN `deleted_at` DATETIME DEFAULT NULL COMMENT 'Data/hora da exclusão definitiva (Admin)',
ADD COLUMN `deleted_by_user_id` INT(11) DEFAULT NULL COMMENT 'Usuário que excluiu definitivamente';
