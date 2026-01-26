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
        echo "   📄 Criando tabela cfc_pix_accounts...\n";
        
        try {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            $db->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
            
            // Criar tabela diretamente (idempotente com CREATE TABLE IF NOT EXISTS)
            $createTableSQL = "CREATE TABLE IF NOT EXISTS `cfc_pix_accounts` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `cfc_id` int(11) NOT NULL,
                `label` varchar(255) NOT NULL COMMENT 'Apelido/nome da conta (ex: PagBank, Efí)',
                `bank_code` varchar(10) DEFAULT NULL COMMENT 'Código do banco (ex: 290, 364)',
                `bank_name` varchar(255) DEFAULT NULL COMMENT 'Nome do banco/instituição',
                `agency` varchar(20) DEFAULT NULL COMMENT 'Agência (opcional)',
                `account_number` varchar(20) DEFAULT NULL COMMENT 'Número da conta (opcional)',
                `account_type` varchar(50) DEFAULT NULL COMMENT 'Tipo de conta (corrente, poupança, etc)',
                `holder_name` varchar(255) NOT NULL COMMENT 'Nome do titular',
                `holder_document` varchar(20) DEFAULT NULL COMMENT 'CPF/CNPJ do titular',
                `pix_key` varchar(255) NOT NULL COMMENT 'Chave PIX',
                `pix_key_type` enum('cpf','cnpj','email','telefone','aleatoria') DEFAULT NULL COMMENT 'Tipo da chave PIX',
                `note` text DEFAULT NULL COMMENT 'Observações adicionais',
                `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Conta padrão do CFC',
                `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Conta ativa',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `cfc_id` (`cfc_id`),
                KEY `is_default` (`is_default`),
                KEY `is_active` (`is_active`),
                CONSTRAINT `cfc_pix_accounts_ibfk_1` FOREIGN KEY (`cfc_id`) REFERENCES `cfcs` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contas PIX do CFC'";
            
            $db->exec($createTableSQL);
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Verificar se foi criada
            if ($tableExists('cfc_pix_accounts')) {
                echo "   ✅ Tabela 'cfc_pix_accounts' criada com sucesso\n";
                echo "   ✅ Migration 038: Executada\n\n";
            } else {
                echo "   ⚠️  Tabela não foi criada\n";
                echo "   ❌ Migration 038: Falhou\n\n";
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
        echo "   📄 Migrando dados PIX antigos...\n";
        
        try {
            // Buscar CFCs com dados PIX antigos
            $stmt = $db->query("
                SELECT 
                    `id` as `cfc_id`,
                    COALESCE(`pix_banco`, 'PIX Principal') as `label`,
                    NULL as `bank_code`,
                    `pix_banco` as `bank_name`,
                    COALESCE(`pix_titular`, 'Titular não informado') as `holder_name`,
                    NULL as `holder_document`,
                    `pix_chave` as `pix_key`,
                    `pix_observacao` as `note`
                FROM `cfcs`
                WHERE (`pix_chave` IS NOT NULL AND `pix_chave` != '') 
                OR (`pix_titular` IS NOT NULL AND `pix_titular` != '')
            ");
            $oldPixData = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($oldPixData)) {
                echo "   ⏭️  Nenhum dado PIX antigo encontrado para migrar\n";
                echo "   ✅ Migration 039: Não necessária\n\n";
            } else {
                // Função para detectar tipo de chave PIX
                $detectPixKeyType = function($key) {
                    $key = trim($key);
                    // CPF: 11 dígitos
                    if (preg_match('/^[0-9]{11}$/', $key)) {
                        return 'cpf';
                    }
                    // CNPJ: 14 dígitos
                    if (preg_match('/^[0-9]{14}$/', $key)) {
                        return 'cnpj';
                    }
                    // Email
                    if (filter_var($key, FILTER_VALIDATE_EMAIL)) {
                        return 'email';
                    }
                    // Telefone: +5511999999999 ou 11999999999 (10-11 dígitos)
                    if (preg_match('/^\+?[0-9]{10,11}$/', $key)) {
                        return 'telefone';
                    }
                    // Aleatória (chave alfanumérica)
                    return 'aleatoria';
                };
                
                $migratedCount = 0;
                foreach ($oldPixData as $row) {
                    try {
                        $pixKeyType = $detectPixKeyType($row['pix_key']);
                        
                        $insertStmt = $db->prepare("
                            INSERT INTO `cfc_pix_accounts` (
                                `cfc_id`, `label`, `bank_code`, `bank_name`, `holder_name`, 
                                `holder_document`, `pix_key`, `pix_key_type`, `note`, 
                                `is_default`, `is_active`, `created_at`
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $insertStmt->execute([
                            $row['cfc_id'],
                            $row['label'],
                            $row['bank_code'],
                            $row['bank_name'],
                            $row['holder_name'],
                            $row['holder_document'],
                            $row['pix_key'],
                            $pixKeyType,
                            $row['note'],
                            1, // is_default
                            1  // is_active
                        ]);
                        $migratedCount++;
                    } catch (\PDOException $e) {
                        // Ignorar erros de duplicação (pode já ter sido migrado)
                        if (strpos($e->getMessage(), 'Duplicate') === false) {
                            echo "   ⚠️  Erro ao migrar CFC ID {$row['cfc_id']}: " . $e->getMessage() . "\n";
                        }
                    }
                }
                
                echo "   ✅ Migration 039: Executada\n";
                echo "   📊 Contas migradas: {$migratedCount}\n\n";
            }
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
    
    // Verificar se colunas já existem
    $pixAccountIdExists = $columnExists('enrollments', 'pix_account_id');
    $pixAccountSnapshotExists = $columnExists('enrollments', 'pix_account_snapshot');
    
    if ($pixAccountIdExists && $pixAccountSnapshotExists) {
        echo "   ⏭️  Colunas 'pix_account_id' e 'pix_account_snapshot' já existem\n";
        echo "   ✅ Migration 040: Já executada\n\n";
    } else {
        echo "   📄 Adicionando colunas em enrollments...\n";
        
        try {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Adicionar pix_account_id se não existir
            if (!$pixAccountIdExists) {
                try {
                    $db->exec("
                        ALTER TABLE `enrollments` 
                        ADD COLUMN `pix_account_id` int(11) DEFAULT NULL 
                        COMMENT 'ID da conta PIX usada no pagamento' 
                        AFTER `payment_method`,
                        ADD KEY `pix_account_id` (`pix_account_id`),
                        ADD CONSTRAINT `enrollments_ibfk_pix_account` 
                        FOREIGN KEY (`pix_account_id`) REFERENCES `cfc_pix_accounts` (`id`) ON DELETE SET NULL
                    ");
                    echo "   ✅ Coluna 'pix_account_id' adicionada\n";
                } catch (\PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        throw $e;
                    }
                    echo "   ⏭️  Coluna 'pix_account_id' já existe\n";
                }
            } else {
                echo "   ⏭️  Coluna 'pix_account_id' já existe\n";
            }
            
            // Adicionar pix_account_snapshot se não existir
            if (!$pixAccountSnapshotExists) {
                try {
                    $db->exec("
                        ALTER TABLE `enrollments` 
                        ADD COLUMN `pix_account_snapshot` JSON DEFAULT NULL 
                        COMMENT 'Snapshot dos dados da conta PIX no momento do pagamento (para histórico imutável)' 
                        AFTER `pix_account_id`
                    ");
                    echo "   ✅ Coluna 'pix_account_snapshot' adicionada\n";
                } catch (\PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        throw $e;
                    }
                    echo "   ⏭️  Coluna 'pix_account_snapshot' já existe\n";
                }
            } else {
                echo "   ⏭️  Coluna 'pix_account_snapshot' já existe\n";
            }
            
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Verificar se foram criadas
            $pixAccountIdExistsAfter = $columnExists('enrollments', 'pix_account_id');
            $pixAccountSnapshotExistsAfter = $columnExists('enrollments', 'pix_account_snapshot');
            
            if ($pixAccountIdExistsAfter && $pixAccountSnapshotExistsAfter) {
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
