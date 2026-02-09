<?php
/**
 * Script para executar migration 046: campo aulas_contratadas em enrollments
 * Execute: php tools/run_migration_046.php
 */

require_once __DIR__ . '/../app/autoload.php';
use App\Config\Database;
use App\Config\Env;

Env::load();
$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->query("SHOW COLUMNS FROM enrollments LIKE 'aulas_contratadas'");
    if ($stmt->fetch()) {
        echo "Migration 046 já aplicada (aulas_contratadas existe).\n";
        exit(0);
    }

    $db->exec("ALTER TABLE enrollments ADD COLUMN aulas_contratadas INT(11) DEFAULT NULL COMMENT 'Quantidade de aulas práticas contratadas (NULL = sem limite)' AFTER status");

    echo "Migration 046 executada com sucesso!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
