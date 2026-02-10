<?php
/**
 * Página de Relatórios Financeiros - Template
 * Sistema CFC - Bom Conselho
 */

// Verificar se sistema financeiro está habilitado
if (!defined('FINANCEIRO_ENABLED') || !FINANCEIRO_ENABLED) {
    echo '<div class="alert alert-danger">Módulo financeiro não está habilitado.</div>';
    return;
}

// Relatórios financeiros/gerenciais: apenas ADMIN (SECRETARIA não tem acesso)
if (!$isAdmin) {
    error_log('[BLOQUEIO] Acesso negado a financeiro-relatorios: tipo=' . ($user['tipo'] ?? '') . ', user_id=' . ($user['id'] ?? ''));
    $_SESSION['flash_message'] = 'Você não tem permissão.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: index.php');
    exit;
}

// Obter período padrão (últimos 30 dias)
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');

// Obter estatísticas do período (contas a pagar: financeiro_pagamentos)
try {
    $stats = [
        'total_receitas' => $db->fetchColumn("SELECT SUM(valor) FROM financeiro_faturas WHERE status = 'paga' AND data_pagamento BETWEEN ? AND ?", [$dataInicio, $dataFim]) ?? 0,
        'total_despesas' => $db->fetchColumn("SELECT SUM(valor) FROM financeiro_pagamentos WHERE status = 'pago' AND data_pagamento BETWEEN ? AND ?", [$dataInicio, $dataFim]) ?? 0,
        'faturas_emitidas' => $db->count('financeiro_faturas', 'data_emissao BETWEEN ? AND ?', [$dataInicio, $dataFim]),
        'despesas_registradas' => $db->count('financeiro_pagamentos', 'vencimento BETWEEN ? AND ?', [$dataInicio, $dataFim])
    ];
    
    $stats['saldo'] = $stats['total_receitas'] - $stats['total_despesas'];
} catch (Exception $e) {
    $stats = [
        'total_receitas' => 0,
        'total_despesas' => 0,
        'faturas_emitidas' => 0,
        'despesas_registradas' => 0,
        'saldo' => 0
    ];
}

// Obter dados para gráficos
try {
    $receitas_mensais = $db->fetchAll("
        SELECT 
            DATE_FORMAT(data_pagamento, '%Y-%m') as mes,
            SUM(valor) as total
        FROM financeiro_faturas 
        WHERE status = 'paga' 
        AND data_pagamento BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(data_pagamento, '%Y-%m')
        ORDER BY mes
    ", [$dataInicio, $dataFim]);
    
    $despesas_categoria = $db->fetchAll("
        SELECT 
            categoria,
            SUM(valor) as total
        FROM financeiro_pagamentos 
        WHERE status = 'pago' 
        AND data_pagamento BETWEEN ? AND ?
        GROUP BY categoria
        ORDER BY total DESC
    ", [$dataInicio, $dataFim]);
} catch (Exception $e) {
    $receitas_mensais = [];
    $despesas_categoria = [];
}
?>

<style>
/* Estilos específicos para relatórios */
.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}
.stats-card.success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}
.stats-card.warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}
.stats-card.info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}
.stats-card.danger {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
}
.chart-container {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}
</style>

<!-- Header da página -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-chart-line me-2"></i>Relatórios Financeiros</h2>
        <p class="text-muted mb-0">Análise financeira e relatórios do sistema</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="exportarRelatorio()">
            <i class="fas fa-download me-1"></i>Exportar
        </button>
        <button class="btn btn-primary" onclick="imprimirRelatorio()">
            <i class="fas fa-print me-1"></i>Imprimir
        </button>
    </div>
</div>

<!-- Filtros de Período -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Período de Análise</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="financeiro-relatorios">
            <div class="col-md-3">
                <label for="data_inicio" class="form-label">Data Início</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $dataInicio; ?>">
            </div>
            <div class="col-md-3">
                <label for="data_fim" class="form-label">Data Fim</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $dataFim; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Atualizar
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="definirPeriodo('hoje')">
                        Hoje
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="definirPeriodo('mes')">
                        Este Mês
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="definirPeriodo('ano')">
                        Este Ano
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card success">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="mb-0">Total Receitas</h6>
                    <h3 class="mb-0">R$ <?php echo number_format($stats['total_receitas'], 2, ',', '.'); ?></h3>
                </div>
                <div class="align-self-center">
                    <i class="fas fa-arrow-up fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card warning">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="mb-0">Total Despesas</h6>
                    <h3 class="mb-0">R$ <?php echo number_format($stats['total_despesas'], 2, ',', '.'); ?></h3>
                </div>
                <div class="align-self-center">
                    <i class="fas fa-arrow-down fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card <?php echo $stats['saldo'] >= 0 ? 'success' : 'danger'; ?>">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="mb-0">Saldo</h6>
                    <h3 class="mb-0">R$ <?php echo number_format($stats['saldo'], 2, ',', '.'); ?></h3>
                </div>
                <div class="align-self-center">
                    <i class="fas fa-balance-scale fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card info">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="mb-0">Transações</h6>
                    <h3 class="mb-0"><?php echo $stats['faturas_emitidas'] + $stats['despesas_registradas']; ?></h3>
                </div>
                <div class="align-self-center">
                    <i class="fas fa-exchange-alt fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos -->
<div class="row">
    <div class="col-md-8">
        <div class="chart-container">
            <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Receitas por Mês</h5>
            <canvas id="chartReceitas" height="100"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-container">
            <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Despesas por Categoria</h5>
            <canvas id="chartDespesas" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Relatório Detalhado -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-table me-2"></i>Resumo Detalhado</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Receitas</h6>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Total de Faturas Pagas:</span>
                        <strong><?php echo $stats['faturas_emitidas']; ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Valor Total:</span>
                        <strong class="text-success">R$ <?php echo number_format($stats['total_receitas'], 2, ',', '.'); ?></strong>
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Despesas</h6>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Total de Despesas Pagas:</span>
                        <strong><?php echo $stats['despesas_registradas']; ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Valor Total:</span>
                        <strong class="text-warning">R$ <?php echo number_format($stats['total_despesas'], 2, ',', '.'); ?></strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gráfico de Receitas
const ctxReceitas = document.getElementById('chartReceitas').getContext('2d');
new Chart(ctxReceitas, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($receitas_mensais, 'mes')); ?>,
        datasets: [{
            label: 'Receitas (R$)',
            data: <?php echo json_encode(array_column($receitas_mensais, 'total')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'R$ ' + value.toLocaleString('pt-BR');
                    }
                }
            }
        }
    }
});

// Gráfico de Despesas por Categoria
const ctxDespesas = document.getElementById('chartDespesas').getContext('2d');
new Chart(ctxDespesas, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($despesas_categoria, 'categoria')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($despesas_categoria, 'total')); ?>,
            backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

function definirPeriodo(tipo) {
    const hoje = new Date();
    const dataFim = document.getElementById('data_fim');
    const dataInicio = document.getElementById('data_inicio');
    
    dataFim.value = hoje.toISOString().split('T')[0];
    
    switch(tipo) {
        case 'hoje':
            dataInicio.value = hoje.toISOString().split('T')[0];
            break;
        case 'mes':
            const inicioMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
            dataInicio.value = inicioMes.toISOString().split('T')[0];
            break;
        case 'ano':
            const inicioAno = new Date(hoje.getFullYear(), 0, 1);
            dataInicio.value = inicioAno.toISOString().split('T')[0];
            break;
    }
}

function exportarRelatorio() {
    alert('Funcionalidade de exportação será implementada em breve.');
}

function imprimirRelatorio() {
    window.print();
}
</script>
