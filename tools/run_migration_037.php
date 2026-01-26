<?php
/**
 * Script de Execução da Migration 037 - Adicionar campos PIX na tabela cfcs
 * 
 * ⚠️ APENAS PARA USO LOCAL/DEVELOPMENT
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

// Verificar se está em ambiente local (segurança)
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || 
           strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
           (php_sapi_name() === 'cli');

if (!$isLocal && php_sapi_name() !== 'cli') {
    die('⚠️ Este script só pode ser executado em ambiente local!');
}

echo "=== EXECUTANDO MIGRATION 037 - ADICIONAR CAMPOS PIX NA TABELA CFCS ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar banco atual
    echo "1. Verificando banco de dados...\n";
    $stmt = $db->query("SELECT DATABASE() as current_db");
    $currentDb = $stmt->fetch();
    $dbName = $_ENV['DB_NAME'] ?? 'cfc_db';
    
    echo "   Banco configurado: {$dbName}\n";
    echo "   Banco em uso: " . ($currentDb['current_db'] ?? 'N/A') . "\n\n";
    
    // Verificar se a tabela cfcs existe
    echo "2. Verificando tabela cfcs...\n";
    $stmt = $db->query("SHOW TABLES LIKE 'cfcs'");
    if ($stmt->rowCount() === 0) {
        die("   ❌ ERRO: Tabela 'cfcs' não existe!\n");
    }
    echo "   ✅ Tabela 'cfcs' existe\n\n";
    
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
    
    // Ler arquivo SQL da migration
    $migrationFile = ROOT_PATH . '/database/migrations/037_add_pix_fields_to_cfcs.sql';
    if (!file_exists($migrationFile)) {
        die("   ❌ ERRO: Arquivo de migration não encontrado: {$migrationFile}\n");
    }
    
    echo "3. Executando migration 037 (idempotente)...\n";
    $migrationSQL = file_get_contents($migrationFile);
    
    // Executar SQL diretamente (já é idempotente)
    try {
        // Dividir em comandos (separados por ponto e vírgula)
        $statements = array_filter(
            array_map('trim', explode(';', $migrationSQL)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^--/', $stmt);
            }
        );
        
        $columnsAdded = [];
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $db->exec($statement);
                    // Extrair nome da coluna do statement
                    if (preg_match('/ADD COLUMN `(\w+)`/i', $statement, $matches)) {
                        $columnsAdded[] = $matches[1];
                    }
                } catch (\PDOException $e) {
                    // Ignorar erro se coluna já existe (idempotente)
                    if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                        throw $e;
                    }
                }
            }
        }
        
        if (!empty($columnsAdded)) {
            echo "   ✅ Colunas adicionadas: " . implode(', ', $columnsAdded) . "\n\n";
        } else {
            echo "   ⏭️  Todas as colunas já existem, migration já foi executada anteriormente\n\n";
        }
        
    } catch (\PDOException $e) {
        echo "   ❌ Erro ao executar migration: " . $e->getMessage() . "\n\n";
        exit(1);
    }
    
    // Verificação final
    echo "4. Verificação final...\n";
    $columnsToCheck = ['pix_banco', 'pix_titular', 'pix_chave', 'pix_observacao'];
    $allExist = true;
    
    foreach ($columnsToCheck as $column) {
        if ($columnExists('cfcs', $column)) {
            echo "   ✅ Coluna '{$column}' existe\n";
        } else {
            echo "   ❌ Coluna '{$column}' NÃO existe!\n";
            $allExist = false;
        }
    }
    
    echo "\n";
    
    if ($allExist) {
        echo "✅ MIGRATION 037 EXECUTADA COM SUCESSO!\n";
        echo "\nOs campos PIX foram adicionados à tabela cfcs:\n";
        echo "  - pix_banco\n";
        echo "  - pix_titular\n";
        echo "  - pix_chave\n";
        echo "  - pix_observacao\n\n";
        echo "Agora você pode configurar os dados do PIX nas Configurações do CFC.\n";
    } else {
        echo "⚠️  MIGRATION FALHOU - Algumas colunas não foram criadas\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
