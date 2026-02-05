<?php
/**
 * Script para executar migration 045: campos deleted_at e deleted_by_user_id em enrollments
 */

require_once __DIR__ . '/../app/autoload.php';
use App\Config\Database;
use App\Config\Env;

Env::load();
$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->query("SHOW COLUMNS FROM enrollments LIKE 'deleted_at'");
    if ($stmt->fetch()) {
        echo "Migration 045 já aplicada (deleted_at existe).\n";
        exit(0);
    }
    
    $db->exec("ALTER TABLE enrollments ADD COLUMN deleted_at DATETIME DEFAULT NULL COMMENT 'Data/hora da exclusão definitiva (Admin)'");
    $db->exec("ALTER TABLE enrollments ADD COLUMN deleted_by_user_id INT(11) DEFAULT NULL COMMENT 'Usuário que excluiu definitivamente'");
    
    echo "Migration 045 executada com sucesso!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
