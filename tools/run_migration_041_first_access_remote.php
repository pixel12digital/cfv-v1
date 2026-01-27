<?php
/**
 * Migration 041 - Tabela first_access_tokens (magic link primeiro acesso pós-matrícula)
 *
 * ✅ SEGURO PARA PRODUÇÃO — idempotente (CREATE TABLE IF NOT EXISTS)
 * Execute via SSH: php tools/run_migration_041_first_access_remote.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
} else {
    require_once APP_PATH . '/autoload.php';
}

use App\Config\Env;
use App\Config\Database;

Env::load();

echo "=== EXECUTANDO MIGRATION 041 - FIRST_ACCESS_TOKENS ===\n\n";

try {
    $db = Database::getInstance()->getConnection();

    echo "1. Verificando conexão...\n";
    $stmt = $db->query("SELECT DATABASE() as current_db");
    $cur = $stmt->fetch();
    echo "   Banco: " . ($cur['current_db'] ?? 'N/A') . "\n";
    echo "   Host: " . ($_ENV['DB_HOST'] ?? 'localhost') . "\n\n";

    echo "2. Verificando se a tabela first_access_tokens já existe...\n";
    $stmt = $db->query("SHOW TABLES LIKE 'first_access_tokens'");
    if ($stmt->rowCount() > 0) {
        echo "   ⏭️  Tabela 'first_access_tokens' já existe. Nada a fazer.\n\n";
        echo "✅ MIGRATION 041 JÁ APLICADA.\n";
        exit(0);
    }

    echo "3. Criando tabela first_access_tokens...\n";
    $sqlFile = ROOT_PATH . '/database/migrations/041_create_first_access_tokens.sql';
    if (!is_readable($sqlFile)) {
        die("   ❌ ERRO: Arquivo não encontrado: {$sqlFile}\n");
    }
    $sql = file_get_contents($sqlFile);
    $sql = preg_replace('/--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)), function ($s) {
        return strlen($s) > 5;
    });
    foreach ($statements as $statement) {
        $db->exec($statement);
    }
    echo "   ✅ Tabela criada.\n\n";

    echo "4. Verificação final...\n";
    $stmt = $db->query("SHOW TABLES LIKE 'first_access_tokens'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ first_access_tokens existe\n\n";
        echo "✅ MIGRATION 041 EXECUTADA COM SUCESSO!\n";
    } else {
        echo "   ❌ Falha na verificação\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
