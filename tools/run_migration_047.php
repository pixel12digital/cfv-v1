<?php
/**
 * Verifica e executa migration 047: campo numero_pe em students (DETRAN-PE)
 */

$base = dirname(__DIR__);
require_once $base . '/app/autoload.php';
\App\Config\Env::load();
$db = \App\Config\Database::getInstance()->getConnection();

try {
    $stmt = $db->query("SHOW COLUMNS FROM students LIKE 'numero_pe'");
    if ($stmt->fetch()) {
        echo "OK: numero_pe ja existe.\n";
        exit(0);
    }
    $db->exec("ALTER TABLE students ADD COLUMN numero_pe VARCHAR(9) DEFAULT NULL COMMENT 'Número PE - exigência DETRAN-PE para CFC (9 dígitos)' AFTER rg_issue_date");
    echo "OK: Migration 047 executada.\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
