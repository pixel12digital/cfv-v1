<?php
/**
 * Executa a migration 036 (Filiação: nome_mae, nome_pai) de forma idempotente.
 * Uso: php tools/run_migration_036_filiacao.php
 *   ou via web: /tools/run_migration_036_filiacao.php (se a pasta tools for acessível)
 *
 * Banco: usa .env (DB_HOST, DB_NAME, DB_USER, DB_PASS). Para banco remoto, configure no .env.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = dirname(__DIR__);
require_once $root . '/app/autoload.php';
require_once $root . '/app/Config/Env.php';
require_once $root . '/app/Config/Database.php';

use App\Config\Env;
use App\Config\Database;

Env::load();

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

try {
    $pdo = Database::getInstance()->getConnection();
    $dbName = $_ENV['DB_NAME'] ?? '?';
    $dbHost = $_ENV['DB_HOST'] ?? '?';
    echo "Banco: {$dbName} | Host: {$dbHost}\n\n";

    $cols = $pdo->query("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students'
        AND COLUMN_NAME IN ('nome_mae', 'nome_pai')
    ")->fetchAll(PDO::FETCH_COLUMN);

    $has = array_flip($cols);

    if (!isset($has['nome_mae'])) {
        $pdo->exec("ALTER TABLE students ADD COLUMN nome_mae VARCHAR(255) DEFAULT NULL AFTER rg_issue_date");
        echo "OK nome_mae adicionada.\n";
    } else {
        echo "OK nome_mae já existe.\n";
    }

    if (!isset($has['nome_pai'])) {
        $pdo->exec("ALTER TABLE students ADD COLUMN nome_pai VARCHAR(255) DEFAULT NULL AFTER nome_mae");
        echo "OK nome_pai adicionada.\n";
    } else {
        echo "OK nome_pai já existe.\n";
    }

    echo "\nMigration 036 (Filiação) concluída.\n";
} catch (Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
