<?php
/**
 * Verifica totais financeiros reais no banco
 */
require_once __DIR__ . '/../app/autoload.php';
use App\Config\Database;
use App\Config\Env;

Env::load();
$db = Database::getInstance()->getConnection();
$cfcId = 1;

echo "=== VERIFICAÇÃO TOTAIS FINANCEIROS ===\n\n";

// 1. Total a receber SEM excluir pagos (query antiga)
$stmt = $db->prepare("
    SELECT SUM(CASE WHEN e.status != 'cancelada' AND e.final_price > COALESCE(e.entry_amount, 0) 
        THEN (e.final_price - COALESCE(e.entry_amount, 0)) ELSE 0 END) as total
    FROM enrollments e INNER JOIN students s ON e.student_id = s.id
    WHERE s.cfc_id = ?
");
$stmt->execute([$cfcId]);
$antigo = (float)$stmt->fetch()['total'];
echo "1. Total a receber (SEM excluir pagos): R$ " . number_format($antigo, 2, ',', '.') . "\n";

// 2. Total a receber EXCLUINDO gateway=paid e outstanding=0
$stmt = $db->prepare("
    SELECT SUM(e.final_price - COALESCE(e.entry_amount, 0)) as total
    FROM enrollments e INNER JOIN students s ON e.student_id = s.id
    WHERE s.cfc_id = ? AND e.status != 'cancelada'
      AND e.final_price > COALESCE(e.entry_amount, 0)
      AND (e.gateway_last_status IS NULL OR e.gateway_last_status != 'paid')
      AND (e.outstanding_amount IS NULL OR e.outstanding_amount > 0)
");
$stmt->execute([$cfcId]);
$novo = (float)$stmt->fetch()['total'];
echo "2. Total a receber (EXCLUINDO pagos): R$ " . number_format($novo, 2, ',', '.') . "\n\n";

// 3. Alunos com pendências em ATRASO (due date < hoje)
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT e.student_id) as qtd
    FROM enrollments e INNER JOIN students s ON e.student_id = s.id
    WHERE s.cfc_id = ? AND e.status != 'cancelada'
      AND e.final_price > COALESCE(e.entry_amount, 0)
      AND (e.gateway_last_status IS NULL OR e.gateway_last_status != 'paid')
      AND (e.outstanding_amount IS NULL OR e.outstanding_amount > 0)
      AND COALESCE(NULLIF(e.first_due_date,'0000-00-00'), NULLIF(e.down_payment_due_date,'0000-00-00'), DATE(e.created_at)) < CURDATE()
");
$stmt->execute([$cfcId]);
$qtdAtraso = (int)$stmt->fetch()['qtd'];
echo "3. Alunos com pendências EM ATRASO: {$qtdAtraso}\n";

// 4. Alunos com pendências totais (atraso + a vencer)
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT e.student_id) as qtd
    FROM enrollments e INNER JOIN students s ON e.student_id = s.id
    WHERE s.cfc_id = ? AND e.status != 'cancelada'
      AND e.final_price > COALESCE(e.entry_amount, 0)
      AND (e.gateway_last_status IS NULL OR e.gateway_last_status != 'paid')
      AND (e.outstanding_amount IS NULL OR e.outstanding_amount > 0)
");
$stmt->execute([$cfcId]);
$qtdTotal = (int)$stmt->fetch()['qtd'];
echo "4. Alunos com pendências (total): {$qtdTotal}\n";
