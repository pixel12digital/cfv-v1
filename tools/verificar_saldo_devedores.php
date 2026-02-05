<?php
/**
 * Script para verificar a contagem de "Alunos com Saldo Devedor" do dashboard
 * Mesma lógica usada em DashboardController::dashboardAdmin()
 *
 * Uso: php tools/verificar_saldo_devedores.php [cfc_id]
 */

require_once __DIR__ . '/../app/autoload.php';

use App\Config\Database;
use App\Config\Env;
use App\Config\Constants;

Env::load();

$cfcId = (int)($argv[1] ?? Constants::CFC_ID_DEFAULT);
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$isRemote = !in_array(strtolower((string)$dbHost), ['localhost', '127.0.0.1', '::1']);

echo "=== VERIFICAÇÃO: ALUNOS COM SALDO DEVEDOR ===\n\n";
echo "Banco: {$dbHost} " . ($isRemote ? "[REMOTO]" : "[LOCAL]") . "\n";
echo "CFC ID: {$cfcId}\n\n";

try {
    $db = Database::getInstance()->getConnection();

    // Query exata do DashboardController (linhas 756-764)
    $stmt = $db->prepare(
        "SELECT COUNT(DISTINCT e.student_id) as qtd_devedores
         FROM enrollments e
         INNER JOIN students s ON e.student_id = s.id
         WHERE s.cfc_id = ?
           AND e.status != 'cancelada'
           AND e.final_price > COALESCE(e.entry_amount, 0)"
    );
    $stmt->execute([$cfcId]);
    $result = $stmt->fetch();
    $qtdDevedores = (int)($result['qtd_devedores'] ?? 0);

    echo "Resultado da query (mesma do dashboard):\n";
    echo "  Alunos com Saldo Devedor: {$qtdDevedores}\n\n";

    // Detalhar quais alunos são
    $stmt = $db->prepare(
        "SELECT DISTINCT s.id, s.name, s.full_name, s.cpf,
                e.id as enrollment_id, e.final_price, e.entry_amount,
                (e.final_price - COALESCE(e.entry_amount, 0)) as saldo_devedor
         FROM enrollments e
         INNER JOIN students s ON e.student_id = s.id
         WHERE s.cfc_id = ?
           AND e.status != 'cancelada'
           AND e.final_price > COALESCE(e.entry_amount, 0)
         ORDER BY s.name"
    );
    $stmt->execute([$cfcId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "Nenhum aluno com saldo devedor encontrado.\n";
    } else {
        echo "Detalhamento dos alunos:\n";
        $seen = [];
        foreach ($rows as $r) {
            $sid = $r['id'];
            if (!isset($seen[$sid])) {
                $seen[$sid] = true;
                $saldo = (float)$r['saldo_devedor'];
                echo "  - ID {$r['id']}: {$r['name']} (CPF: {$r['cpf']})\n";
                echo "    Matrícula #{$r['enrollment_id']}: Final R$ " . number_format($r['final_price'], 2, ',', '.');
                echo " | Entrada R$ " . number_format($r['entry_amount'] ?? 0, 2, ',', '.');
                echo " | Saldo R$ " . number_format($saldo, 2, ',', '.') . "\n";
            }
        }
    }

    echo "\n✅ Dado REAL do banco (não é placeholder).\n";
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
