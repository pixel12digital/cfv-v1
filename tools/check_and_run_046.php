<?php
/**
 * Verifica e executa migration 046 se necessário
 */
$base = dirname(__DIR__);
require_once $base . '/app/autoload.php';
\App\Config\Env::load();
$db = \App\Config\Database::getInstance()->getConnection();
$stmt = $db->query("SHOW COLUMNS FROM enrollments LIKE 'aulas_contratadas'");
if ($stmt->fetch()) {
    echo "OK: aulas_contratadas ja existe.\n";
    exit(0);
}
$db->exec("ALTER TABLE enrollments ADD COLUMN aulas_contratadas INT(11) DEFAULT NULL COMMENT 'Quantidade de aulas praticas contratadas (NULL = sem limite)' AFTER status");
echo "OK: Migration 046 executada.\n";
