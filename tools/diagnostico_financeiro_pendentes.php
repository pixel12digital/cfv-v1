<?php
/**
 * Diagnóstico: Por que o Financeiro mostra 0 pendentes quando o Dashboard mostra 3?
 * Compara as queries do Dashboard vs Financeiro
 *
 * Uso: php tools/diagnostico_financeiro_pendentes.php [cfc_id]
 */

require_once __DIR__ . '/../app/autoload.php';

use App\Config\Database;
use App\Config\Env;
use App\Config\Constants;

Env::load();

$cfcId = (int)($argv[1] ?? Constants::CFC_ID_DEFAULT);
$db = Database::getInstance()->getConnection();

echo "=== DIAGNÓSTICO: DIFERENÇA DASHBOARD vs FINANCEIRO ===\n\n";
echo "CFC ID: {$cfcId}\n\n";

// 0. Valores brutos das matrículas 3, 9, 11
$stmt = $db->query("SELECT id, student_id, cfc_id, service_id, status, final_price, entry_amount, outstanding_amount FROM enrollments WHERE id IN (3, 9, 11)");
$raw = $stmt->fetchAll();
echo "0. Dados brutos das matrículas no banco:\n";
foreach ($raw as $r) {
    echo "   #{$r['id']}: cfc_id=" . var_export($r['cfc_id'], true) . " status={$r['status']} final={$r['final_price']} entry=" . var_export($r['entry_amount'], true) . " outstanding=" . var_export($r['outstanding_amount'], true) . "\n";
}
echo "\n";

// 1. Query do DASHBOARD (usa s.cfc_id - students)
$stmt = $db->prepare(
    "SELECT COUNT(DISTINCT e.student_id) as qtd_devedores
     FROM enrollments e
     INNER JOIN students s ON e.student_id = s.id
     WHERE s.cfc_id = ?
       AND e.status != 'cancelada'
       AND e.final_price > COALESCE(e.entry_amount, 0)"
);
$stmt->execute([$cfcId]);
$dashboardCount = (int)$stmt->fetch()['qtd_devedores'];
echo "1. DASHBOARD (s.cfc_id): {$dashboardCount} alunos com saldo devedor\n";

// 2. Query do FINANCEIRO (após correção: final_price > entry_amount, mesmo critério do Dashboard)
$stmt = $db->prepare(
    "SELECT COUNT(*) as total
     FROM enrollments e
     INNER JOIN students s ON s.id = e.student_id
     INNER JOIN services sv ON sv.id = e.service_id
     WHERE e.cfc_id = ?
       AND e.status != 'cancelada'
       AND (e.final_price > COALESCE(e.entry_amount, 0))"
);
$stmt->execute([$cfcId]);
$financeiroCount = (int)$stmt->fetch()['total'];
echo "2. FINANCEIRO (e.cfc_id): {$financeiroCount} matrículas com saldo devedor\n\n";

// 3. Verificar se há matrículas com s.cfc_id != e.cfc_id
$stmt = $db->prepare(
    "SELECT e.id, e.student_id, e.cfc_id as e_cfc_id, s.cfc_id as s_cfc_id,
            e.final_price, e.entry_amount, e.outstanding_amount, e.status
     FROM enrollments e
     INNER JOIN students s ON e.student_id = s.id
     WHERE e.status != 'cancelada'
       AND e.final_price > COALESCE(e.entry_amount, 0)
       AND (e.cfc_id != s.cfc_id OR e.cfc_id IS NULL)"
);
$stmt->execute();
$mismatch = $stmt->fetchAll();
if (!empty($mismatch)) {
    echo "3. INCONSISTÊNCIA: Matrículas com e.cfc_id != s.cfc_id:\n";
    foreach ($mismatch as $r) {
        echo "   - Matrícula #{$r['id']}: e.cfc_id={$r['e_cfc_id']}, s.cfc_id={$r['s_cfc_id']}\n";
    }
    echo "\n";
} else {
    echo "3. OK: Todas as matrículas têm e.cfc_id = s.cfc_id\n\n";
}

// 4. Listar matrículas que o DASHBOARD encontra (via s.cfc_id)
$stmt = $db->prepare(
    "SELECT e.id, e.student_id, e.cfc_id, s.name, e.final_price, e.entry_amount, e.outstanding_amount
     FROM enrollments e
     INNER JOIN students s ON e.student_id = s.id
     WHERE s.cfc_id = ?
       AND e.status != 'cancelada'
       AND e.final_price > COALESCE(e.entry_amount, 0)
     ORDER BY e.id"
);
$stmt->execute([$cfcId]);
$viaStudentCfc = $stmt->fetchAll();
echo "4. Matrículas encontradas via s.cfc_id (Dashboard): " . count($viaStudentCfc) . "\n";
foreach ($viaStudentCfc as $r) {
    echo "   - #{$r['id']} aluno {$r['student_id']} ({$r['name']}) | e.cfc_id={$r['cfc_id']} | final={$r['final_price']} entry={$r['entry_amount']}\n";
}
echo "\n";

// 4b. Verificar service_id das matrículas do Dashboard
echo "4b. Verificando service_id das matrículas encontradas pelo Dashboard:\n";
foreach ($viaStudentCfc as $r) {
    $stmt = $db->prepare("SELECT e.service_id, sv.id as sv_exists FROM enrollments e LEFT JOIN services sv ON sv.id = e.service_id WHERE e.id = ?");
    $stmt->execute([$r['id']]);
    $check = $stmt->fetch();
    $svExists = $check['sv_exists'] ? 'OK' : 'SERVICE NÃO EXISTE';
    echo "   - Matrícula #{$r['id']}: service_id={$check['service_id']} | {$svExists}\n";
}
echo "\n";

// 5. Financeiro COM critério unificado (final_price > entry_amount)
$stmt = $db->prepare(
    "SELECT e.id, e.student_id, e.cfc_id, s.name, e.final_price, e.entry_amount, e.outstanding_amount
     FROM enrollments e
     INNER JOIN students s ON s.id = e.student_id
     INNER JOIN services sv ON sv.id = e.service_id
     WHERE e.cfc_id = ?
       AND e.status != 'cancelada'
       AND (e.final_price > COALESCE(e.entry_amount, 0))
     ORDER BY e.id"
);
$stmt->execute([$cfcId]);
$viaEnrollmentCfc = $stmt->fetchAll();
echo "5. Financeiro (critério unificado): " . count($viaEnrollmentCfc) . " matrículas\n";
foreach ($viaEnrollmentCfc as $r) {
    echo "   - #{$r['id']} aluno {$r['student_id']} ({$r['name']}) | e.cfc_id={$r['cfc_id']} | final={$r['final_price']} entry={$r['entry_amount']}\n";
}
echo "\n";

// 6. Conclusão
if ($dashboardCount > 0 && $financeiroCount === 0) {
    echo ">>> CAUSA PROVÁVEL: Matrículas têm e.cfc_id diferente de {$cfcId} (ou NULL)\n";
    echo "    O Dashboard usa s.cfc_id (do aluno), o Financeiro usa e.cfc_id (da matrícula).\n";
} elseif ($dashboardCount === $financeiroCount) {
    echo ">>> As duas queries retornam o mesmo. Se a tela mostra 0, pode ser cache ou sessão.\n";
} else {
    echo ">>> Diferença: Dashboard conta ALUNOS únicos, Financeiro conta MATRÍCULAS.\n";
}
