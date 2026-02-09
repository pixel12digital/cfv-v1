<?php
/**
 * API Financeiro - Relatórios
 * Sistema CFC - Bom Conselho MVP
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

// Verificar autenticação e permissão
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$currentUser = getCurrentUser();
// Relatórios financeiros/gerenciais: apenas ADMIN (SECRETARIA não tem acesso)
if (($currentUser['tipo'] ?? '') !== 'admin') {
    error_log('[BLOQUEIO] Financeiro-relatorios API negado: tipo=' . ($currentUser['tipo'] ?? '') . ', user_id=' . ($currentUser['id'] ?? ''));
    http_response_code(403);
    echo json_encode(['error' => 'Você não tem permissão.']);
    exit;
}

$db = Database::getInstance();
$tipo = $_GET['tipo'] ?? 'receitas_despesas';

try {
    switch ($tipo) {
        case 'receitas_despesas':
            getReceitasDespesas($db);
            break;
        case 'inadimplencia':
            getInadimplencia($db);
            break;
        case 'fluxo_caixa':
            getFluxoCaixa($db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Tipo de relatório inválido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getReceitasDespesas($db) {
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
    
    // Receitas
    $receitas = $db->fetchAll("
        SELECT 
            DATE(f.vencimento) as data,
            f.status,
            SUM(f.valor_total) as total
        FROM financeiro_faturas f
        WHERE f.vencimento BETWEEN ? AND ?
        GROUP BY DATE(f.vencimento), f.status
        ORDER BY f.vencimento DESC
    ", [$data_inicio, $data_fim]);
    
    // Despesas
    $despesas = $db->fetchAll("
        SELECT 
            DATE(p.vencimento) as data,
            p.status,
            SUM(p.valor) as total
        FROM financeiro_pagamentos p
        WHERE p.vencimento BETWEEN ? AND ?
        GROUP BY DATE(p.vencimento), p.status
        ORDER BY p.vencimento DESC
    ", [$data_inicio, $data_fim]);
    
    // Totais por período
    $totalReceitas = $db->fetchColumn("
        SELECT COALESCE(SUM(valor_total), 0)
        FROM financeiro_faturas
        WHERE vencimento BETWEEN ? AND ?
    ", [$data_inicio, $data_fim]);
    
    $totalDespesas = $db->fetchColumn("
        SELECT COALESCE(SUM(valor), 0)
        FROM financeiro_pagamentos
        WHERE vencimento BETWEEN ? AND ?
    ", [$data_inicio, $data_fim]);
    
    $receitasPagas = $db->fetchColumn("
        SELECT COALESCE(SUM(valor_total), 0)
        FROM financeiro_faturas
        WHERE status = 'paga' AND vencimento BETWEEN ? AND ?
    ", [$data_inicio, $data_fim]);
    
    $despesasPagas = $db->fetchColumn("
        SELECT COALESCE(SUM(valor), 0)
        FROM financeiro_pagamentos
        WHERE status = 'paga' AND vencimento BETWEEN ? AND ?
    ", [$data_inicio, $data_fim]);
    
    echo json_encode([
        'success' => true,
        'periodo' => [
            'inicio' => $data_inicio,
            'fim' => $data_fim
        ],
        'totais' => [
            'receitas_total' => (float)$totalReceitas,
            'receitas_pagas' => (float)$receitasPagas,
            'receitas_abertas' => (float)$totalReceitas - (float)$receitasPagas,
            'despesas_total' => (float)$totalDespesas,
            'despesas_pagas' => (float)$despesasPagas,
            'despesas_pendentes' => (float)$totalDespesas - (float)$despesasPagas,
            'saldo' => (float)$receitasPagas - (float)$despesasPagas
        ],
        'detalhamento' => [
            'receitas' => $receitas,
            'despesas' => $despesas
        ]
    ]);
}

function getInadimplencia($db) {
    // Usar fallback seguro: se tabela não existir, usar 30 dias padrão
    try {
        $config = $db->fetch("SELECT valor FROM financeiro_configuracoes WHERE chave = 'dias_inadimplencia'");
        $diasInadimplencia = $config ? (int)$config['valor'] : 30;
    } catch (Exception $e) {
        // Se tabela não existir, usar valor padrão
        $diasInadimplencia = 30;
    }
    
    // Alunos inadimplentes
    $inadimplentes = $db->fetchAll("
        SELECT 
            a.id,
            a.nome,
            a.cpf,
            a.inadimplente_desde,
            COUNT(f.id) as total_faturas_vencidas,
            SUM(f.valor_total) as valor_total_devido,
            MAX(f.vencimento) as ultima_fatura_vencida
        FROM alunos a
        JOIN financeiro_faturas f ON a.id = f.aluno_id
        WHERE a.inadimplente = 1
        AND f.status IN ('aberta', 'vencida')
        AND f.vencimento < DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY a.id, a.nome, a.cpf, a.inadimplente_desde
        ORDER BY valor_total_devido DESC
    ", [$diasInadimplencia]);
    
    // Estatísticas gerais
    $totalInadimplentes = count($inadimplentes);
    $valorTotalInadimplencia = array_sum(array_column($inadimplentes, 'valor_total_devido'));
    
    // Distribuição por tempo de inadimplência
    $distribuicao = $db->fetchAll("
        SELECT 
            CASE 
                WHEN DATEDIFF(NOW(), f.vencimento) <= 30 THEN '1-30 dias'
                WHEN DATEDIFF(NOW(), f.vencimento) <= 60 THEN '31-60 dias'
                WHEN DATEDIFF(NOW(), f.vencimento) <= 90 THEN '61-90 dias'
                ELSE 'Mais de 90 dias'
            END as faixa_tempo,
            COUNT(DISTINCT a.id) as total_alunos,
            SUM(f.valor_total) as valor_total
        FROM alunos a
        JOIN financeiro_faturas f ON a.id = f.aluno_id
        WHERE a.inadimplente = 1
        AND f.status IN ('aberta', 'vencida')
        AND f.vencimento < DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY faixa_tempo
        ORDER BY valor_total DESC
    ", [$diasInadimplencia]);
    
    echo json_encode([
        'success' => true,
        'configuracao' => [
            'dias_inadimplencia' => $diasInadimplencia
        ],
        'estatisticas' => [
            'total_inadimplentes' => $totalInadimplentes,
            'valor_total_inadimplencia' => (float)$valorTotalInadimplencia,
            'valor_medio_por_aluno' => $totalInadimplentes > 0 ? (float)$valorTotalInadimplencia / $totalInadimplentes : 0
        ],
        'distribuicao_tempo' => $distribuicao,
        'alunos_inadimplentes' => $inadimplentes
    ]);
}

function getFluxoCaixa($db) {
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
    
    // Fluxo diário
    $fluxoDiario = $db->fetchAll("
        SELECT 
            DATE(f.vencimento) as data,
            'receita' as tipo,
            f.status,
            SUM(f.valor_total) as valor
        FROM financeiro_faturas f
        WHERE f.vencimento BETWEEN ? AND ?
        GROUP BY DATE(f.vencimento), f.status
        
        UNION ALL
        
        SELECT 
            DATE(p.vencimento) as data,
            'despesa' as tipo,
            p.status,
            SUM(p.valor) as valor
        FROM financeiro_pagamentos p
        WHERE p.vencimento BETWEEN ? AND ?
        GROUP BY DATE(p.vencimento), p.status
        
        ORDER BY data DESC, tipo
    ", [$data_inicio, $data_fim, $data_inicio, $data_fim]);
    
    // Saldo acumulado
    $saldoAcumulado = 0;
    $fluxoComSaldo = [];
    
    foreach ($fluxoDiario as $item) {
        if ($item['tipo'] === 'receita' && $item['status'] === 'paga') {
            $saldoAcumulado += $item['valor'];
        } elseif ($item['tipo'] === 'despesa' && $item['status'] === 'paga') {
            $saldoAcumulado -= $item['valor'];
        }
        
        $fluxoComSaldo[] = array_merge($item, ['saldo_acumulado' => $saldoAcumulado]);
    }
    
    echo json_encode([
        'success' => true,
        'periodo' => [
            'inicio' => $data_inicio,
            'fim' => $data_fim
        ],
        'fluxo_diario' => $fluxoComSaldo,
        'saldo_final' => $saldoAcumulado
    ]);
}
