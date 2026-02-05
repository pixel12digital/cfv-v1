<?php
/**
 * Verifica se RAFAEL e DEVID têm o mesmo bug da Maria (pagaram mas aparecem como pendentes)
 */

require_once __DIR__ . '/../app/autoload.php';

use App\Config\Database;
use App\Config\Env;

Env::load();
$db = Database::getInstance()->getConnection();

// Simular nova query de pendentes (exclui gateway=paid ou outstanding=0)
$stmt = $db->prepare("
    SELECT COUNT(*) as total FROM enrollments e
    INNER JOIN students s ON s.id = e.student_id
    WHERE s.cfc_id = 1 AND e.status != 'cancelada'
      AND e.final_price > COALESCE(e.entry_amount, 0)
      AND (e.gateway_last_status IS NULL OR e.gateway_last_status != 'paid')
      AND (e.outstanding_amount IS NULL OR e.outstanding_amount > 0)
");
$stmt->execute();
$novoCount = (int)$stmt->fetch()['total'];
echo "Nova query (exclui pagos): {$novoCount} pendentes\n\n";

// IDs conhecidos: RAFAEL=2, DEVID=7 (da investigação anterior)
$stmt = $db->prepare("
    SELECT e.id, e.student_id, s.name, s.full_name, e.final_price, e.entry_amount, e.outstanding_amount,
           e.payment_method, e.gateway_provider, e.gateway_last_status, e.gateway_charge_id,
           e.financial_status, e.status
    FROM enrollments e
    INNER JOIN students s ON s.id = e.student_id
    WHERE s.cfc_id = 1 AND e.status != 'cancelada'
      AND e.student_id IN (2, 7)
    ORDER BY s.name
");

$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== VERIFICAÇÃO: RAFAEL e DEVID - Mesmo bug da Maria? ===\n\n";

foreach ($rows as $r) {
    echo "--- " . ($r['full_name'] ?: $r['name']) . " (Matrícula #{$r['id']}) ---\n";
        echo "  final_price: R$ " . number_format($r['final_price'], 2, ',', '.') . "\n";
        echo "  entry_amount: R$ " . number_format($r['entry_amount'] ?? 0, 2, ',', '.') . "\n";
        echo "  outstanding_amount: R$ " . number_format($r['outstanding_amount'] ?? 0, 2, ',', '.') . "\n";
        echo "  payment_method: " . ($r['payment_method'] ?? 'NULL') . "\n";
        echo "  gateway_provider: " . ($r['gateway_provider'] ?? 'NULL') . "\n";
        echo "  gateway_last_status: " . ($r['gateway_last_status'] ?? 'NULL') . "\n";
        echo "  gateway_charge_id: " . ($r['gateway_charge_id'] ?? 'NULL') . "\n";
        echo "  financial_status: " . ($r['financial_status'] ?? 'NULL') . "\n";
        
        $calcDebt = max(0, $r['final_price'] - ($r['entry_amount'] ?? 0));
        $paid = ($r['gateway_last_status'] ?? '') === 'paid' || ($r['gateway_provider'] ?? '') === 'local';
        echo "\n  Calculado (final - entry): R$ " . number_format($calcDebt, 2, ',', '.') . "\n";
        echo "  Gateway indica pago? " . ($paid ? 'SIM' : 'NÃO') . "\n";
    echo "  MESMO BUG (pagou mas lista mostra pendente)? " . ($paid && ($r['outstanding_amount'] ?? 0) == 0 ? 'SIM' : 'NÃO') . "\n\n";
}
