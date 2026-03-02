<?php
/**
 * Script de debug para verificar quotas de matrícula
 */

require_once __DIR__ . '/../app/autoload.php';

use App\Config\Env;
use App\Config\Database;
use App\Models\EnrollmentLessonQuota;

Env::load();

$enrollmentId = $argv[1] ?? 25; // ID da matrícula criada

echo "=== DEBUG: QUOTAS DA MATRÍCULA #{$enrollmentId} ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Verificar se existem quotas na tabela
    echo "1. Verificando registros na tabela enrollment_lesson_quotas:\n";
    $stmt = $db->prepare("
        SELECT elq.*, lc.code, lc.name 
        FROM enrollment_lesson_quotas elq
        LEFT JOIN lesson_categories lc ON elq.lesson_category_id = lc.id
        WHERE elq.enrollment_id = ?
    ");
    $stmt->execute([$enrollmentId]);
    $quotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($quotas)) {
        echo "   ❌ NENHUMA QUOTA ENCONTRADA!\n";
        echo "   Isso significa que as quotas não foram salvas ao criar a matrícula.\n\n";
    } else {
        echo "   ✅ Encontradas " . count($quotas) . " quotas:\n";
        foreach ($quotas as $q) {
            echo "      - Categoria: {$q['name']} ({$q['code']}) - Quantidade: {$q['quantity']}\n";
        }
        echo "\n";
    }
    
    // 2. Testar método getAllQuotasWithScheduledCount
    echo "2. Testando método getAllQuotasWithScheduledCount():\n";
    $quotaModel = new EnrollmentLessonQuota();
    $quotasWithCount = $quotaModel->getAllQuotasWithScheduledCount($enrollmentId);
    
    if (empty($quotasWithCount)) {
        echo "   ❌ Método retornou vazio!\n\n";
    } else {
        echo "   ✅ Método retornou " . count($quotasWithCount) . " quotas:\n";
        foreach ($quotasWithCount as $q) {
            echo "      - {$q['category_name']} ({$q['code']}): {$q['contracted']} contratadas, {$q['scheduled']} agendadas, {$q['remaining']} restantes\n";
        }
        echo "\n";
    }
    
    // 3. Verificar dados da matrícula
    echo "3. Verificando dados da matrícula:\n";
    $stmt = $db->prepare("SELECT id, aulas_contratadas FROM enrollments WHERE id = ?");
    $stmt->execute([$enrollmentId]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($enrollment) {
        echo "   - ID: {$enrollment['id']}\n";
        echo "   - aulas_contratadas (total): " . ($enrollment['aulas_contratadas'] ?? 'NULL') . "\n\n";
    }
    
    // 4. Simular JSON que seria enviado para o JavaScript
    echo "4. JSON que seria enviado para o JavaScript:\n";
    $jsonData = [
        'has_quotas' => !empty($quotasWithCount),
        'quotas' => $quotasWithCount
    ];
    echo "   " . json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "=== FIM DO DEBUG ===\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
