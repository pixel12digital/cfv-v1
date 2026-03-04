<?php
$transacoes = $transacoes ?? [];
$totais = $totais ?? ['quantidade' => 0, 'valor_total' => 0, 'valor_pago' => 0, 'saldo_devedor' => 0];
$dataInicio = $dataInicio ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $dataFim ?? date('Y-m-d');
$filtroFormaPagamento = $filtroFormaPagamento ?? '';
$filtroStatus = $filtroStatus ?? '';
$printMode = $printMode ?? false;

$cfcInfo = ['nome' => 'CFC', 'telefone' => '', 'email' => ''];
try {
    $db = \App\Config\Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT nome, telefone, email FROM cfcs WHERE id = 1 LIMIT 1");
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($result) {
        $cfcInfo = $result;
    }
} catch (\Exception $e) {
    // Silenciar erro
}

function formatPaymentMethod($method) {
    $map = [
        'pix' => 'PIX',
        'boleto' => 'Boleto',
        'cartao' => 'Cartão',
        'entrada_parcelas' => 'Entrada + Parcelas'
    ];
    return $map[$method] ?? ucfirst($method);
}

function formatStatus($status) {
    $map = [
        'em_dia' => 'Em Dia',
        'pendente' => 'Pendente',
        'bloqueado' => 'Bloqueado'
    ];
    return $map[$status] ?? ucfirst($status);
}

function statusBadgeClass($status) {
    $map = [
        'em_dia' => 'badge-success',
        'pendente' => 'badge-warning',
        'bloqueado' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-secondary';
}
?>
<style>
@media print {
    .no-print, .sidebar, .topbar, .btn { display: none !important; }
    .relatorio-print { padding: 0; margin: 0; }
    body { 
        margin: 0; 
        padding: 15px; 
        font-family: Arial, sans-serif;
        font-size: 10pt;
    }
    
    @page {
        size: A4 landscape;
        margin: 1cm;
    }
    
    .print-header {
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 10px;
        margin-bottom: 15px;
        border-bottom: 2px solid #333;
    }
    
    .print-header-logo {
        max-height: 50px;
        max-width: 120px;
    }
    
    .print-header-info {
        text-align: right;
        font-size: 9pt;
        line-height: 1.3;
    }
    
    .print-header-info h2 {
        margin: 0 0 3px 0;
        font-size: 14pt;
        font-weight: bold;
        color: #333;
    }
    
    .print-title {
        text-align: center;
        margin: 10px 0 5px 0;
        font-size: 13pt;
        font-weight: bold;
        text-transform: uppercase;
        color: #333;
    }
    
    .print-period {
        text-align: center;
        margin-bottom: 10px;
        font-size: 9pt;
        color: #666;
    }
    
    .print-totals {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        margin-bottom: 15px;
        padding: 8px;
        background: #f0f0f0;
        border: 1px solid #ccc;
        font-size: 8pt;
        page-break-inside: avoid;
    }
    
    .print-totals-item {
        padding: 3px;
        text-align: center;
    }
    
    .print-totals-item strong {
        font-weight: bold;
        display: block;
        margin-bottom: 2px;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 7.5pt;
        page-break-inside: auto;
    }
    
    .table thead {
        display: table-header-group;
    }
    
    .table tfoot {
        display: table-footer-group;
        page-break-inside: avoid;
    }
    
    .table tbody tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    
    .table th {
        background: #333 !important;
        color: white !important;
        padding: 5px 3px;
        text-align: left;
        font-weight: bold;
        border: 1px solid #333;
        font-size: 7.5pt;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .table td {
        padding: 4px 3px;
        border: 1px solid #ccc;
        vertical-align: middle;
    }
    
    .table tbody tr:nth-child(even) {
        background: #f9f9f9 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .table tfoot td {
        background: #e8e8e8 !important;
        font-weight: bold;
        border-top: 2px solid #333;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .badge {
        padding: 1px 4px;
        border-radius: 2px;
        font-size: 7pt;
        font-weight: bold;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .badge-success { 
        background: #28a745 !important; 
        color: white !important; 
    }
    .badge-warning { 
        background: #ffc107 !important; 
        color: #333 !important; 
    }
    .badge-danger { 
        background: #dc3545 !important; 
        color: white !important; 
    }
    .badge-secondary { 
        background: #6c757d !important; 
        color: white !important; 
    }
    
    .print-footer {
        display: block !important;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 8px 15px;
        text-align: center;
        font-size: 8pt;
        border-top: 1px solid #ccc;
        background: white;
    }
    
    /* Otimizações de espaço */
    .card { 
        box-shadow: none !important; 
        border: none !important;
    }
    
    h2, h3, h4 { 
        margin: 5px 0 !important; 
    }
}

@media screen {
    .print-header, .print-footer { display: none; }
}

.badge {
    display: inline-block;
    padding: 0.25em 0.6em;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
}

.badge-success { background-color: #28a745; color: white; }
.badge-warning { background-color: #ffc107; color: #212529; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }

.totals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.total-card {
    background: var(--color-bg-secondary);
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid var(--color-border);
}

.total-card h4 {
    margin: 0 0 0.5rem 0;
    font-size: 0.875rem;
    color: var(--color-text-muted);
    text-transform: uppercase;
    font-weight: 600;
}

.total-card .value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-text);
}

.total-card .subtitle {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    margin-top: 0.25rem;
}
</style>

<div class="relatorio-print">
    <!-- Cabeçalho para impressão -->
    <div class="print-header">
        <div class="print-header-logo">
            <?php
            $logoPath = __DIR__ . '/../../../public/uploads/logo.png';
            if (file_exists($logoPath)) {
                echo '<img src="' . base_path('public/uploads/logo.png') . '" alt="Logo" class="print-header-logo">';
            }
            ?>
        </div>
        <div class="print-header-info">
            <h2><?= htmlspecialchars($cfcInfo['nome']) ?></h2>
            <?php if (!empty($cfcInfo['telefone'])): ?>
            <div>Tel: <?= htmlspecialchars($cfcInfo['telefone']) ?></div>
            <?php endif; ?>
            <?php if (!empty($cfcInfo['email'])): ?>
            <div>Email: <?= htmlspecialchars($cfcInfo['email']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="print-title">Relatório de Transações Financeiras</div>
    <div class="print-period">
        Período: <?= date('d/m/Y', strtotime($dataInicio)) ?> a <?= date('d/m/Y', strtotime($dataFim)) ?>
        <?php if ($filtroFormaPagamento): ?>
        | Forma: <?= formatPaymentMethod($filtroFormaPagamento) ?>
        <?php endif; ?>
        <?php if ($filtroStatus): ?>
        | Status: <?= formatStatus($filtroStatus) ?>
        <?php endif; ?>
    </div>

    <!-- Cabeçalho -->
    <div class="page-header no-print" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: var(--spacing-md); margin-bottom: var(--spacing-lg);">
        <div>
            <h1>Relatório de Transações Financeiras</h1>
            <p class="text-muted">Período: <?= date('d/m/Y', strtotime($dataInicio)) ?> a <?= date('d/m/Y', strtotime($dataFim)) ?></p>
        </div>
        <div style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
            <button type="button" class="btn btn-primary" onclick="window.print();">
                Imprimir
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card no-print" style="margin-bottom: var(--spacing-lg);">
        <div class="card-body">
            <form method="get" action="<?= base_path('relatorio-transacoes') ?>" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: var(--spacing-md); align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="data_inicio" class="form-input" value="<?= htmlspecialchars($dataInicio) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Data Fim</label>
                    <input type="date" name="data_fim" class="form-input" value="<?= htmlspecialchars($dataFim) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Forma de Pagamento</label>
                    <select name="forma_pagamento" class="form-input" style="min-width: 180px;">
                        <option value="">Todas</option>
                        <option value="pix" <?= $filtroFormaPagamento === 'pix' ? 'selected' : '' ?>>PIX</option>
                        <option value="boleto" <?= $filtroFormaPagamento === 'boleto' ? 'selected' : '' ?>>Boleto</option>
                        <option value="cartao" <?= $filtroFormaPagamento === 'cartao' ? 'selected' : '' ?>>Cartão</option>
                        <option value="entrada_parcelas" <?= $filtroFormaPagamento === 'entrada_parcelas' ? 'selected' : '' ?>>Entrada + Parcelas</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Status Financeiro</label>
                    <select name="status" class="form-input" style="min-width: 180px;">
                        <option value="">Todos</option>
                        <option value="em_dia" <?= $filtroStatus === 'em_dia' ? 'selected' : '' ?>>Em Dia</option>
                        <option value="pendente" <?= $filtroStatus === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="bloqueado" <?= $filtroStatus === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Totais -->
    <div class="print-totals no-print" style="display: flex; flex-wrap: wrap; gap: var(--spacing-md); margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: var(--cfc-surface-muted, #f3f4f6); border-radius: var(--radius-md);">
        <span><strong>Total de Transações:</strong> <span class="badge badge-secondary"><?= $totais['quantidade'] ?></span></span>
        <span><strong>Valor Total:</strong> <span class="badge badge-secondary">R$ <?= number_format($totais['valor_total'], 2, ',', '.') ?></span></span>
        <span><strong>Valor Pago:</strong> <span class="badge badge-success">R$ <?= number_format($totais['valor_pago'], 2, ',', '.') ?></span></span>
        <span><strong>Saldo Devedor:</strong> <span class="badge badge-danger">R$ <?= number_format($totais['saldo_devedor'], 2, ',', '.') ?></span></span>
    </div>

    <!-- Totais por Forma de Pagamento -->
    <div class="card no-print" style="margin-bottom: var(--spacing-lg);">
        <div class="card-body">
            <h3 style="margin-bottom: var(--spacing-md);">Totais por Forma de Pagamento</h3>
            <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-md);">
                <?php foreach ($totais['por_forma'] as $forma => $dados): ?>
                    <?php if ($dados['quantidade'] > 0): ?>
                    <div style="background: var(--cfc-surface-muted, #f3f4f6); padding: var(--spacing-md); border-radius: var(--radius-md); border: 1px solid var(--cfc-border-subtle, #e5e7eb); min-width: 200px;">
                        <div style="font-weight: 600; margin-bottom: var(--spacing-sm); color: var(--cfc-primary, #2563eb);">
                            <?= formatPaymentMethod($forma) ?>
                        </div>
                        <div style="font-size: 0.875rem; color: var(--gray-600);">
                            <div>Qtd: <strong><?= $dados['quantidade'] ?></strong></div>
                            <div>Total: <strong>R$ <?= number_format($dados['valor'], 2, ',', '.') ?></strong></div>
                            <div>Pago: <strong style="color: #28a745;">R$ <?= number_format($dados['pago'], 2, ',', '.') ?></strong></div>
                            <div>Saldo: <strong style="color: #dc3545;">R$ <?= number_format($dados['saldo'], 2, ',', '.') ?></strong></div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Totais para impressão -->
    <div class="print-totals">
        <div class="print-totals-item">
            <strong>Total de Transações:</strong> <?= $totais['quantidade'] ?>
        </div>
        <div class="print-totals-item">
            <strong>Valor Total:</strong> R$ <?= number_format($totais['valor_total'], 2, ',', '.') ?>
        </div>
        <div class="print-totals-item">
            <strong>Valor Pago:</strong> R$ <?= number_format($totais['valor_pago'], 2, ',', '.') ?>
        </div>
        <div class="print-totals-item">
            <strong>Saldo Devedor:</strong> R$ <?= number_format($totais['saldo_devedor'], 2, ',', '.') ?>
        </div>
        <?php foreach ($totais['por_forma'] as $forma => $dados): ?>
            <?php if ($dados['quantidade'] > 0): ?>
            <div class="print-totals-item">
                <strong><?= formatPaymentMethod($forma) ?>:</strong> 
                <?= $dados['quantidade'] ?> transações | 
                R$ <?= number_format($dados['valor'], 2, ',', '.') ?>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Tabela de Transações -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <?php if (empty($transacoes)): ?>
            <div style="padding: 2rem; text-align: center; color: var(--color-text-muted);">
                Nenhuma transação encontrada no período selecionado.
            </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Aluno/Cliente</th>
                            <th>CPF</th>
                            <th>Serviço</th>
                            <th>Forma</th>
                            <th style="text-align: right;">Valor Total</th>
                            <th style="text-align: right;">Valor Pago</th>
                            <th style="text-align: right;">Saldo</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transacoes as $t): ?>
                        <?php
                        $dataTransacao = !empty($t['entry_payment_date']) && $t['entry_payment_date'] !== '0000-00-00' 
                            ? date('d/m/Y', strtotime($t['entry_payment_date']))
                            : date('d/m/Y', strtotime($t['created_at']));
                        $cpfFormatted = \App\Helpers\ValidationHelper::formatCpf($t['aluno_cpf'] ?? '');
                        $finalPrice = (float)$t['final_price'];
                        $entryAmount = (float)($t['entry_amount'] ?? 0);
                        $outstandingAmount = (float)($t['outstanding_amount'] ?? 0);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($dataTransacao) ?></td>
                            <td><?= htmlspecialchars($t['aluno_nome']) ?></td>
                            <td><?= htmlspecialchars($cpfFormatted) ?></td>
                            <td><?= htmlspecialchars($t['servico_nome']) ?></td>
                            <td><?= formatPaymentMethod($t['payment_method']) ?></td>
                            <td style="text-align: right; font-weight: 600;">
                                R$ <?= number_format($finalPrice, 2, ',', '.') ?>
                            </td>
                            <td style="text-align: right; color: #28a745; font-weight: 600;">
                                R$ <?= number_format($entryAmount, 2, ',', '.') ?>
                            </td>
                            <td style="text-align: right; color: <?= $outstandingAmount > 0 ? '#dc3545' : '#28a745' ?>; font-weight: 600;">
                                R$ <?= number_format($outstandingAmount, 2, ',', '.') ?>
                            </td>
                            <td>
                                <span class="badge <?= statusBadgeClass($t['financial_status']) ?>">
                                    <?= formatStatus($t['financial_status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background: var(--color-bg-secondary); font-weight: 700;">
                        <tr>
                            <td colspan="5" style="text-align: right; padding: 1rem;">TOTAIS:</td>
                            <td style="text-align: right; padding: 1rem;">
                                R$ <?= number_format($totais['valor_total'], 2, ',', '.') ?>
                            </td>
                            <td style="text-align: right; padding: 1rem; color: #28a745;">
                                R$ <?= number_format($totais['valor_pago'], 2, ',', '.') ?>
                            </td>
                            <td style="text-align: right; padding: 1rem; color: #dc3545;">
                                R$ <?= number_format($totais['saldo_devedor'], 2, ',', '.') ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rodapé para impressão -->
    <div class="print-footer">
        Relatório gerado em <?= date('d/m/Y H:i') ?> | <?= htmlspecialchars($cfcInfo['nome']) ?>
    </div>
</div>
