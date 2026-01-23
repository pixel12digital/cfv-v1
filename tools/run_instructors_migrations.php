<?php
/**
 * Script para executar migrations 012 e 014 (Instrutores, Veículos e Aulas)
 * Execute: php tools/run_instructors_migrations.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

// Carregar autoload
require_once APP_PATH . '/autoload.php';

// Carregar variáveis de ambiente
use App\Config\Env;
use App\Config\Database;
Env::load();

try {
    $db = Database::getInstance()->getConnection();
    
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  EXECUTANDO MIGRATIONS: INSTRUCTORS, VEHICLES, LESSONS\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    // Verificar banco atual
    $stmt = $db->query("SELECT DATABASE() as current_db");
    $currentDb = $stmt->fetch();
    $dbName = $_ENV['DB_NAME'] ?? 'cfc_db';
    
    echo "📍 Banco de dados:\n";
    echo "   Configurado: {$dbName}\n";
    echo "   Em uso: " . ($currentDb['current_db'] ?? 'N/A') . "\n";
    echo "   Host: " . ($_ENV['DB_HOST'] ?? 'N/A') . "\n\n";
    
    // Verificar se tabelas já existem
    echo "🔍 Verificando tabelas existentes...\n";
    $tablesToCheck = ['instructors', 'vehicles', 'lessons', 'instructor_availability'];
    $existingTables = [];
    
    foreach ($tablesToCheck as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            $existingTables[] = $table;
            echo "   ⚠️  Tabela '{$table}' já existe\n";
        } else {
            echo "   ✓ Tabela '{$table}' não existe (será criada)\n";
        }
    }
    echo "\n";
    
    // Migration 012
    echo "📦 Executando Migration 012: Instrutores, Veículos e Aulas...\n";
    $migration012File = ROOT_PATH . '/database/migrations/012_create_instructors_vehicles_lessons.sql';
    
    if (!file_exists($migration012File)) {
        throw new Exception("Arquivo não encontrado: {$migration012File}");
    }
    
    $sql012 = file_get_contents($migration012File);
    
    // Executar SQL (CREATE TABLE IF NOT EXISTS já trata tabelas existentes)
    $db->exec($sql012);
    
    echo "   ✓ Migration 012 executada com sucesso!\n";
    echo "   Tabelas criadas/verificadas:\n";
    echo "     - instructors\n";
    echo "     - vehicles\n";
    echo "     - lessons\n\n";
    
    // Migration 014
    echo "📦 Executando Migration 014: Completar tabela de instrutores...\n";
    $migration014File = ROOT_PATH . '/database/migrations/014_complete_instructors_table.sql';
    
    if (!file_exists($migration014File)) {
        throw new Exception("Arquivo não encontrado: {$migration014File}");
    }
    
    $sql014 = file_get_contents($migration014File);
    
    // Verificar se precisa executar ALTER TABLE (verificar se colunas já existem)
    $stmt = $db->query("SHOW COLUMNS FROM instructors LIKE 'credential_expiry_date'");
    $needsAlter = ($stmt->rowCount() === 0);
    
    if ($needsAlter) {
        // Executar SQL (ALTER TABLE já trata colunas existentes com IF NOT EXISTS implícito)
        $db->exec($sql014);
        echo "   ✓ Migration 014 executada com sucesso!\n";
        echo "   Campos adicionados à tabela instructors\n";
        echo "   Tabela instructor_availability criada/verificada\n\n";
    } else {
        echo "   ⚠️  Migration 014 já foi executada anteriormente (campos já existem)\n\n";
    }
    
    // Verificar resultado final
    echo "✅ Verificação final das tabelas...\n";
    foreach ($tablesToCheck as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            // Contar registros
            try {
                $countStmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
                $count = $countStmt->fetch()['count'];
                echo "   ✓ Tabela '{$table}' existe ({$count} registro(s))\n";
            } catch (\Exception $e) {
                echo "   ✓ Tabela '{$table}' existe\n";
            }
        } else {
            echo "   ❌ Tabela '{$table}' NÃO existe\n";
        }
    }
    
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  ✅ MIGRATIONS EXECUTADAS COM SUCESSO!\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    
} catch (\PDOException $e) {
    echo "\n❌ ERRO DE BANCO DE DADOS:\n";
    echo "   SQLSTATE: " . $e->getCode() . "\n";
    echo "   Mensagem: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . "\n";
    echo "   Linha: " . $e->getLine() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERRO:\n";
    echo "   Mensagem: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . "\n";
    echo "   Linha: " . $e->getLine() . "\n";
    exit(1);
}
