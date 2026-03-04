<?php

namespace App\Controllers;

use App\Config\Database;
use App\Config\Constants;
use App\Models\Enrollment;

/**
 * Relatório de Transações Financeiras por período (ADMIN/SECRETARIA).
 * Lista matrículas como transações com detalhamento de valores e formas de pagamento.
 */
class RelatorioTransacoesController extends Controller
{
    private $cfcId;
    private $db;

    public function __construct()
    {
        $this->cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        $this->db = Database::getInstance()->getConnection();
    }

    public function index()
    {
        $role = $_SESSION['active_role'] ?? $_SESSION['current_role'] ?? null;
        if (!in_array($role, ['ADMIN', 'SECRETARIA'], true)) {
            http_response_code(403);
            echo 'Acesso negado. Apenas administradores e secretárias podem acessar o Relatório de Transações.';
            return;
        }

        // Filtros
        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
        $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
        $formaPagamento = isset($_GET['forma_pagamento']) && $_GET['forma_pagamento'] !== '' ? $_GET['forma_pagamento'] : null;
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

        // Buscar transações (matrículas) no período
        $transacoes = $this->getTransacoesByPeriod($dataInicio, $dataFim, $formaPagamento, $status);

        // Calcular totais
        $totais = $this->calculateTotals($transacoes);

        $pageTitle = 'Relatório de Transações Financeiras';
        $this->view('relatorio/transacoes', [
            'pageTitle' => $pageTitle,
            'transacoes' => $transacoes,
            'totais' => $totais,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim,
            'filtroFormaPagamento' => $formaPagamento,
            'filtroStatus' => $status
        ]);
    }

    /**
     * Busca transações (matrículas) no período com filtros
     */
    private function getTransacoesByPeriod($dataInicio, $dataFim, $formaPagamento = null, $status = null)
    {
        $sql = "SELECT 
                    e.id,
                    e.created_at,
                    e.entry_payment_date,
                    COALESCE(s.full_name, s.name) as aluno_nome,
                    s.cpf as aluno_cpf,
                    sv.name as servico_nome,
                    e.payment_method,
                    e.entry_payment_method,
                    e.base_price,
                    e.discount_value,
                    e.extra_value,
                    e.final_price,
                    e.entry_amount,
                    e.outstanding_amount,
                    e.financial_status,
                    e.status as enrollment_status,
                    e.installments,
                    e.gateway_last_status,
                    e.gateway_provider
                FROM enrollments e
                INNER JOIN students s ON s.id = e.student_id
                INNER JOIN services sv ON sv.id = e.service_id
                WHERE e.cfc_id = ?
                AND e.status != 'cancelada'
                AND (e.deleted_at IS NULL)
                AND DATE(e.created_at) BETWEEN ? AND ?";

        $params = [$this->cfcId, $dataInicio, $dataFim];

        // Filtro por forma de pagamento
        if ($formaPagamento !== null) {
            $sql .= " AND e.payment_method = ?";
            $params[] = $formaPagamento;
        }

        // Filtro por status financeiro
        if ($status !== null) {
            $sql .= " AND e.financial_status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY e.created_at DESC, e.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Calcula totais gerais e por forma de pagamento
     */
    private function calculateTotals($transacoes)
    {
        $totais = [
            'quantidade' => 0,
            'valor_total' => 0,
            'valor_pago' => 0,
            'saldo_devedor' => 0,
            'por_forma' => [
                'pix' => ['quantidade' => 0, 'valor' => 0, 'pago' => 0, 'saldo' => 0],
                'boleto' => ['quantidade' => 0, 'valor' => 0, 'pago' => 0, 'saldo' => 0],
                'cartao' => ['quantidade' => 0, 'valor' => 0, 'pago' => 0, 'saldo' => 0],
                'entrada_parcelas' => ['quantidade' => 0, 'valor' => 0, 'pago' => 0, 'saldo' => 0]
            ],
            'por_status' => [
                'em_dia' => 0,
                'pendente' => 0,
                'bloqueado' => 0
            ]
        ];

        foreach ($transacoes as $t) {
            $finalPrice = (float)$t['final_price'];
            $entryAmount = (float)($t['entry_amount'] ?? 0);
            $outstandingAmount = (float)($t['outstanding_amount'] ?? 0);
            $paymentMethod = $t['payment_method'];
            $financialStatus = $t['financial_status'];

            // Totais gerais
            $totais['quantidade']++;
            $totais['valor_total'] += $finalPrice;
            $totais['valor_pago'] += $entryAmount;
            $totais['saldo_devedor'] += $outstandingAmount;

            // Totais por forma de pagamento
            if (isset($totais['por_forma'][$paymentMethod])) {
                $totais['por_forma'][$paymentMethod]['quantidade']++;
                $totais['por_forma'][$paymentMethod]['valor'] += $finalPrice;
                $totais['por_forma'][$paymentMethod]['pago'] += $entryAmount;
                $totais['por_forma'][$paymentMethod]['saldo'] += $outstandingAmount;
            }

            // Totais por status
            if (isset($totais['por_status'][$financialStatus])) {
                $totais['por_status'][$financialStatus]++;
            }
        }

        return $totais;
    }

    /**
     * Exporta relatório para impressão/PDF
     */
    public function exportar()
    {
        $role = $_SESSION['active_role'] ?? $_SESSION['current_role'] ?? null;
        if (!in_array($role, ['ADMIN', 'SECRETARIA'], true)) {
            http_response_code(403);
            echo 'Acesso negado.';
            return;
        }

        // Mesmos filtros do index
        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
        $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
        $formaPagamento = isset($_GET['forma_pagamento']) && $_GET['forma_pagamento'] !== '' ? $_GET['forma_pagamento'] : null;
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

        $transacoes = $this->getTransacoesByPeriod($dataInicio, $dataFim, $formaPagamento, $status);
        $totais = $this->calculateTotals($transacoes);

        // Redirecionar para mesma view (ela detecta impressão via CSS)
        $pageTitle = 'Relatório de Transações Financeiras';
        $this->view('relatorio/transacoes', [
            'pageTitle' => $pageTitle,
            'transacoes' => $transacoes,
            'totais' => $totais,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim,
            'filtroFormaPagamento' => $formaPagamento,
            'filtroStatus' => $status,
            'printMode' => true
        ]);
    }
}
