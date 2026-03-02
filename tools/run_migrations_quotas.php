<?php
/**
 * Script para executar migrations de quotas de aulas práticas por categoria
 * Executa migrations 049, 050, 051 e seed 007
 */

require_once __DIR__ . '/../app/autoload.php';

// Carregar variáveis de ambiente
use App\Config\Env;
use App\Config\Database;

Env::load();

try {
    $db = Database::getInstance()->getConnection();
    
    echo "=== EXECUTANDO MIGRATIONS DE QUOTAS POR CATEGORIA ===\n\n";
    
    // Migration 049: Criar tabela lesson_categories
    echo "[1/4] Executando migration 049 - lesson_categories...\n";
    $sql049 = file_get_contents(__DIR__ . '/../database/migrations/049_create_lesson_categories_table.sql');
    $db->exec($sql049);
    echo "✓ Tabela lesson_categories criada com sucesso!\n\n";
    
    // Migration 050: Criar tabela enrollment_lesson_quotas
    echo "[2/4] Executando migration 050 - enrollment_lesson_quotas...\n";
    $sql050 = file_get_contents(__DIR__ . '/../database/migrations/050_create_enrollment_lesson_quotas_table.sql');
    $db->exec($sql050);
    echo "✓ Tabela enrollment_lesson_quotas criada com sucesso!\n\n";
    
    // Migration 051: Adicionar lesson_category_id em lessons
    echo "[3/4] Executando migration 051 - adicionar lesson_category_id...\n";
    $sql051 = file_get_contents(__DIR__ . '/../database/migrations/051_add_lesson_category_to_lessons.sql');
    $db->exec($sql051);
    echo "✓ Campo lesson_category_id adicionado à tabela lessons!\n\n";
    
    // Seed 007: Popular categorias padrão
    echo "[4/4] Executando seed 007 - categorias padrão...\n";
    $seed007 = file_get_contents(__DIR__ . '/../database/seeds/007_seed_lesson_categories.sql');
    $db->exec($seed007);
    echo "✓ Categorias padrão inseridas com sucesso!\n\n";
    
    // Verificar estrutura criada
    echo "=== VERIFICANDO ESTRUTURA CRIADA ===\n\n";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM lesson_categories");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Categorias cadastradas: {$result['count']}\n";
    
    $stmt = $db->query("SHOW TABLES LIKE 'enrollment_lesson_quotas'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Tabela enrollment_lesson_quotas existe\n";
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM lessons LIKE 'lesson_category_id'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Campo lesson_category_id existe na tabela lessons\n";
    }
    
    echo "\n=== MIGRATIONS EXECUTADAS COM SUCESSO! ===\n";
    echo "\nSistema de quotas por categoria está ATIVO e pronto para uso.\n";
    
} catch (PDOException $e) {
    echo "\n❌ ERRO AO EXECUTAR MIGRATIONS:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERRO:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
