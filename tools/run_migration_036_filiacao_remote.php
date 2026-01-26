<?php
/**
 * Migration 036 - Filiação (nome_mae, nome_pai) na tabela students
 *
 * ✅ SEGURO PARA PRODUÇÃO — idempotente
 * Execute via SSH: php tools/run_migration_036_filiacao_remote.php
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

echo "=== EXECUTANDO MIGRATION 036 - FILIAÇÃO (nome_mae, nome_pai) NA TABELA STUDENTS ===\n\n";

try {
    $db = Database::getInstance()->getConnection();

    echo "1. Verificando conexão com banco de dados...\n";
    $stmt = $db->query("SELECT DATABASE() as current_db");
    $cur = $stmt->fetch();
    echo "   Banco: " . ($cur['current_db'] ?? 'N/A') . "\n";
    echo "   Host: " . ($_ENV['DB_HOST'] ?? 'localhost') . "\n\n";

    echo "2. Verificando tabela students...\n";
    $stmt = $db->query("SHOW TABLES LIKE 'students'");
    if ($stmt->rowCount() === 0) {
        die("   ❌ ERRO: Tabela 'students' não existe!\n");
    }
    echo "   ✅ Tabela 'students' existe\n\n";

    $columnExists = function ($table, $column) use ($db) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetch()['c'] > 0;
    };

    echo "3. Verificando e adicionando colunas de filiação...\n";

    if (!$columnExists('students', 'nome_mae')) {
        $db->exec("ALTER TABLE students ADD COLUMN nome_mae VARCHAR(255) DEFAULT NULL AFTER rg_issue_date");
        echo "   ✅ Coluna 'nome_mae' adicionada\n";
    } else {
        echo "   ⏭️  Coluna 'nome_mae' já existe\n";
    }

    if (!$columnExists('students', 'nome_pai')) {
        $db->exec("ALTER TABLE students ADD COLUMN nome_pai VARCHAR(255) DEFAULT NULL AFTER nome_mae");
        echo "   ✅ Coluna 'nome_pai' adicionada\n";
    } else {
        echo "   ⏭️  Coluna 'nome_pai' já existe\n";
    }

    echo "\n4. Verificação final...\n";
    $ok = $columnExists('students', 'nome_mae') && $columnExists('students', 'nome_pai');
    if ($ok) {
        echo "   ✅ nome_mae e nome_pai existem em students\n\n";
        echo "✅ MIGRATION 036 EXECUTADA COM SUCESSO!\n";
        echo "\nOs campos Filiação — Mãe e Filiação — Pai estão disponíveis no cadastro de alunos.\n";
    } else {
        echo "   ❌ Falha na verificação\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
