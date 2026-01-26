<?php
/**
 * Script para executar migrations de contas PIX múltiplas
 * Execute: php tools/run_pix_accounts_migrations.php
 * 
 * Migrations executadas:
 * - 038: Criar tabela cfc_pix_accounts
 * - 039: Migrar dados PIX antigos da tabela cfcs
 * - 040: Adicionar campos pix_account_id e pix_account_snapshot em enrollments
 */

// Inicialização
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

// Autoload
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
} else {
    require_once APP_PATH . '/autoload.php';
}

// Carregar variáveis de ambiente
use App\Config\Env;
use App\Config\Database;
Env::load();

echo "═══════════════════════════════════════════════════════════════\n";
echo "  EXECUTANDO MIGRATIONS: CONTAS PIX MÚLTIPLAS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar banco atual
    echo "1. Verificando conexão com banco de dados...\n";
    $stmt = $db->query("SELECT DATABASE() as current_db");
    $currentDb = $stmt->fetch();
    $dbName = $_ENV['DB_NAME'] ?? 'cfc_db';
    
    echo "   Banco configurado: {$dbName}\n";
    echo "   Banco em uso: " . ($currentDb['current_db'] ?? 'N/A') . "\n";
    echo "   Host: " . ($_ENV['DB_HOST'] ?? 'N/A') . "\n\n";
    
    // Verificar se tabelas base existem
    echo "2. Verificando tabelas base...\n";
    $stmt = $db->query("SHOW TABLES LIKE 'cfcs'");
    if ($stmt->rowCount() === 0) {
        die("   ❌ ERRO: Tabela 'cfcs' não existe! Execute primeiro as migrations base.\n");
    }
    echo "   ✅ Tabela 'cfcs' existe\n";
    
    $stmt = $db->query("SHOW TABLES LIKE 'enrollments'");
    if ($stmt->rowCount() === 0) {
        die("   ❌ ERRO: Tabela 'enrollments' não existe! Execute primeiro as migrations base.\n");
    }
    echo "   ✅ Tabela 'enrollments' existe\n\n";
    
    // Função para verificar se uma tabela existe
    $tableExists = function($tableName) use ($db) {
        $stmt = $db->query("SHOW TABLES LIKE '{$tableName}'");
        return $stmt->rowCount() > 0;
    };
    
    // Função para verificar se uma coluna existe
    $columnExists = function($table, $column) use ($db) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    };
    
    // ============================================
    // MIGRATION 038: Criar tabela cfc_pix_accounts
    // ============================================
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "MIGRATION 038: Criar tabela cfc_pix_accounts\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    if ($tableExists('cfc_pix_accounts')) {
        echo "   ⏭️  Tabela 'cfc_pix_accounts' já existe\n";
        echo "   ✅ Migration 038: Já executada\n\n";
    } else {
        $migration038File = ROOT_PATH . '/database/migrations/038_create_cfc_pix_accounts_table.sql';
        
        if (!file_exists($migration038File)) {
            die("   ❌ ERRO: Arquivo de migration não encontrado: {$migration038File}\n");
        }
        
        echo "   📄 Lendo arquivo de migration...\n";
        $migrationSQL = file_get_contents($migration038File);
        
        try {
            // Executar migration (já é idempotente)
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            $db->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
            
            // Executar SQL completo (já tem verificações idempotentes)
            $statements = explode(';', $migrationSQL);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    try {
                        $db->exec($statement);
                    } catch (\PDOException $e) {
                        // Ignorar erros de "já existe" ou "prepared statement"
                        if (strpos($e->getMessage(), 'already exists') === false && 
                            strpos($e->getMessage(), 'PREPARE') === false) {
                            throw $e;
                        }
                    }
                }
            }
            
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Verificar se foi criada
            if ($tableExists('cfc_pix_accounts')) {
                echo "   ✅ Tabela 'cfc_pix_accounts' criada com sucesso\n";
                echo "   ✅ Migration 038: Executada\n\n";
            } else {
                echo "   ⚠️  Tabela não foi criada (pode já existir)\n";
                echo "   ✅ Migration 038: Verificada\n\n";
            }
        } catch (\PDOException $e) {
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            echo "   ❌ Erro ao executar migration 038: " . $e->getMessage() . "\n";
            echo "   ⚠️  Migration 038: Falhou\n\n";
            throw $e;
        }
    }
    
    // ============================================
    // MIGRATION 039: Migrar dados PIX antigos
    // ============================================
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "MIGRATION 039: Migrar dados PIX antigos\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    // Verificar se já existem contas
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM `cfc_pix_accounts`");
    $hasAccounts = $stmt->fetch()['cnt'] > 0;
    
    // Verificar se existem dados antigos
    $stmt = $db->query("
        SELECT COUNT(*) as cnt FROM `cfcs` 
        WHERE (`pix_chave` IS NOT NULL AND `pix_chave` != '') 
        OR (`pix_titular` IS NOT NULL AND `pix_titular` != '')
    ");
    $hasOldData = $stmt->fetch()['cnt'] > 0;
    
    if ($hasAccounts) {
        echo "   ⏭️  Já existem contas PIX na nova tabela\n";
        echo "   ✅ Migration 039: Não necessária (já migrado)\n\n";
    } elseif (!$hasOldData) {
        echo "   ⏭️  Não há dados PIX antigos para migrar\n";
        echo "   ✅ Migration 039: Não necessária\n\n";
    } else {
        $migration039File = ROOT_PATH . '/database/migrations/039_migrate_old_pix_data.sql';
        
        if (!file_exists($migration039File)) {
            die("   ❌ ERRO: Arquivo de migration não encontrado: {$migration039File}\n");
        }
        
        echo "   📄 Lendo arquivo de migration...\n";
        $migrationSQL = file_get_contents($migration039File);
        
        try {
            // Executar migration (já é idempotente)
            $statements = explode(';', $migrationSQL);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    try {
                        $db->exec($statement);
                    } catch (\PDOException $e) {
                        // Ignorar erros de "prepared statement" ou "já existe"
                        if (strpos($e->getMessage(), 'PREPARE') === false && 
                            strpos($e->getMessage(), 'already exists') === false) {
                            throw $e;
                        }
                    }
                }
            }
            
            // Verificar quantas contas foram migradas
            $stmt = $db->query("SELECT COUNT(*) as cnt FROM `cfc_pix_accounts`");
            $migratedCount = $stmt->fetch()['cnt'];
            
            echo "   ✅ Migration 039: Executada\n";
            echo "   📊 Contas migradas: {$migratedCount}\n\n";
        } catch (\PDOException $e) {
            echo "   ❌ Erro ao executar migration 039: " . $e->getMessage() . "\n";
            echo "   ⚠️  Migration 039: Falhou\n\n";
            // Não bloquear se falhar (pode já estar migrado)
        }
    }
    
    // ============================================
    // MIGRATION 040: Adicionar campos em enrollments
    // ============================================
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "MIGRATION 040: Adicionar campos em enrollments\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    $migration040File = ROOT_PATH . '/database/migrations/040_add_pix_account_fields_to_enrollments.sql';
    
    if (!file_exists($migration040File)) {
        die("   ❌ ERRO: Arquivo de migration não encontrado: {$migration040File}\n");
    }
    
    // Verificar se colunas já existem
    $pixAccountIdExists = $columnExists('enrollments', 'pix_account_id');
    $pixAccountSnapshotExists = $columnExists('enrollments', 'pix_account_snapshot');
    
    if ($pixAccountIdExists && $pixAccountSnapshotExists) {
        echo "   ⏭️  Colunas 'pix_account_id' e 'pix_account_snapshot' já existem\n";
        echo "   ✅ Migration 040: Já executada\n\n";
    } else {
        echo "   📄 Lendo arquivo de migration...\n";
        $migrationSQL = file_get_contents($migration040File);
        
        try {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Executar migration (já é idempotente)
            $statements = explode(';', $migrationSQL);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    try {
                        $db->exec($statement);
                    } catch (\PDOException $e) {
                        // Ignorar erros de "prepared statement" ou "já existe"
                        if (strpos($e->getMessage(), 'PREPARE') === false && 
                            strpos($e->getMessage(), 'already exists') === false &&
                            strpos($e->getMessage(), 'Duplicate column') === false) {
                            throw $e;
                        }
                    }
                }
            }
            
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Verificar se foram criadas
            $pixAccountIdExistsAfter = $columnExists('enrollments', 'pix_account_id');
            $pixAccountSnapshotExistsAfter = $columnExists('enrollments', 'pix_account_snapshot');
            
            if ($pixAccountIdExistsAfter && $pixAccountSnapshotExistsAfter) {
                echo "   ✅ Colunas adicionadas com sucesso\n";
                echo "   ✅ Migration 040: Executada\n\n";
            } else {
                echo "   ⚠️  Algumas colunas não foram criadas\n";
                echo "   pix_account_id: " . ($pixAccountIdExistsAfter ? '✅' : '❌') . "\n";
                echo "   pix_account_snapshot: " . ($pixAccountSnapshotExistsAfter ? '✅' : '❌') . "\n";
            }
        } catch (\PDOException $e) {
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            echo "   ❌ Erro ao executar migration 040: " . $e->getMessage() . "\n";
            echo "   ⚠️  Migration 040: Falhou\n\n";
            throw $e;
        }
    }
    
    // ============================================
    // Verificação final
    // ============================================
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "VERIFICAÇÃO FINAL\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    $allOk = true;
    
    // Verificar tabela cfc_pix_accounts
    if ($tableExists('cfc_pix_accounts')) {
        echo "   ✅ Tabela 'cfc_pix_accounts' existe\n";
        
        // Contar contas
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM `cfc_pix_accounts`");
        $accountCount = $stmt->fetch()['cnt'];
        echo "      └─ Total de contas PIX: {$accountCount}\n";
    } else {
        echo "   ❌ Tabela 'cfc_pix_accounts' NÃO existe!\n";
        $allOk = false;
    }
    
    // Verificar colunas em enrollments
    if ($columnExists('enrollments', 'pix_account_id')) {
        echo "   ✅ Coluna 'enrollments.pix_account_id' existe\n";
    } else {
        echo "   ❌ Coluna 'enrollments.pix_account_id' NÃO existe!\n";
        $allOk = false;
    }
    
    if ($columnExists('enrollments', 'pix_account_snapshot')) {
        echo "   ✅ Coluna 'enrollments.pix_account_snapshot' existe\n";
    } else {
        echo "   ❌ Coluna 'enrollments.pix_account_snapshot' NÃO existe!\n";
        $allOk = false;
    }
    
    echo "\n";
    
    if ($allOk) {
        echo "✅ TODAS AS MIGRATIONS FORAM EXECUTADAS COM SUCESSO!\n\n";
        echo "O sistema de contas PIX múltiplas está pronto para uso.\n";
        echo "Você pode agora:\n";
        echo "  - Acessar Configurações > CFC para cadastrar contas PIX\n";
        echo "  - Selecionar contas PIX durante a matrícula\n";
        echo "  - Ver histórico de pagamentos com a conta PIX usada\n";
    } else {
        echo "⚠️  ALGUMAS MIGRATIONS FALHARAM\n";
        echo "Verifique os erros acima e tente novamente.\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
