<?php
$list = $list ?? [];
$consolidated = $consolidated ?? [];
$totais = $totais ?? ['total_aulas' => 0, 'km_total' => 0, 'inconsistencias' => 0, 'km_medio_por_aula' => 0];
$dataInicio = $dataInicio ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $dataFim ?? date('Y-m-d');
$visao = $visao ?? 'diario';
$filtroInstrutor = $filtroInstrutor ?? '';
$filtroVeiculo = $filtroVeiculo ?? '';
$filtroAluno = $filtroAluno ?? '';
$instrutores = $instrutores ?? [];
$veiculos = $veiculos ?? [];
$alunos = $alunos ?? [];

// Buscar informações do CFC para o cabeçalho
$cfcInfo = ['nome' => 'CFC', 'telefone' => '', 'email' => ''];
try {
    $db = \App\Config\Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT nome, telefone, email FROM cfcs WHERE id = 1 LIMIT 1");
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($result) {
        $cfcInfo = $result;
    }
} catch (\Exception $e) {
    // Silenciar erro se tabela não existir
}

function visaoLabel($v) {
    $labels = [
        'diario' => 'Diário',
        'semanal' => 'Semanal',
        'mensal' => 'Mensal',
        'anual' => 'Anual'
    ];
    return $labels[$v] ?? ucfirst($v);
}
?>
<style>
@media print {
    .no-print, .sidebar, .topbar, .btn { display: none !important; }
    .relatorio-print { padding: 0; margin: 0; }
    body { margin: 0; padding: 20px; }
    
    .print-header {
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 15px;
        margin-bottom: 20px;
        border-bottom: 2px solid #333;
    }
    
    .print-header-logo {
        max-height: 60px;
        max-width: 150px;
    }
    
    .print-header-info {
        text-align: right;
        font-size: 11px;
        line-height: 1.4;
    }
    
    .print-header-info h2 {
        margin: 0 0 5px 0;
        font-size: 18px;
        font-weight: bold;
        color: #333;
    }
    
    .print-title {
        text-align: center;
        margin: 20px 0;
        font-size: 16px;
        font-weight: bold;
        text-transform: uppercase;
    }
    
    .print-period {
        text-align: center;
        margin-bottom: 15px;
        font-size: 12px;
        color: #666;
    }
    
    .print-totals {
        display: flex !important;
        justify-content: center;
        gap: 20px;
        margin-bottom: 20px;
        padding: 10px;
        background: #f5f5f5;
        border-radius: 5px;
        font-size: 11px;
    }
    
    .print-totals span {
        font-weight: bold;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }
    
    .table th {
        background: #333;
        color: white;
        padding: 8px 5px;
        text-align: left;
        font-weight: bold;
        border: 1px solid #333;
    }
    
    .table td {
        padding: 6px 5px;
        border: 1px solid #ddd;
    }
    
    .table tbody tr:nth-child(even) {
        background: #f9f9f9;
    }
    
    .badge {
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 9px;
        font-weight: bold;
    }
    
    .badge-danger { background: #dc3545; color: white; }
    .badge-warning { background: #ffc107; color: #333; }
    .badge-success { background: #28a745; color: white; }
    .badge-info { background: #17a2b8; color: white; }
    .badge-secondary { background: #6c757d; color: white; }
    
    .print-footer {
        display: block !important;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 10px 20px;
        border-top: 1px solid #ddd;
        font-size: 9px;
        color: #666;
        background: white;
    }
    
    .print-footer-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    @page {
        margin: 20mm 15mm;
    }
}

.print-header,
.print-footer {
    display: none;
}

.alert-inconsistencia {
    background: #fff3cd;
    border: 1px solid #ffc107;
    padding: 8px 12px;
    border-radius: 4px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>

<div class="relatorio-print">
    <!-- Cabeçalho para Impressão -->
    <div class="print-header">
        <div>
            <img src="<?= base_url('configuracoes/cfc/logo') ?>" alt="Logo CFC" class="print-header-logo" onerror="this.style.display='none'">
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
    
    <div class="print-title">Relatório de Quilometragem</div>
    <div class="print-period">
        Período: <?= date('d/m/Y', strtotime($dataInicio)) ?> a <?= date('d/m/Y', strtotime($dataFim)) ?>
        | Visão: <?= visaoLabel($visao) ?>
        <?php if ($filtroInstrutor): ?>
            | Instrutor: <?php 
                foreach ($instrutores as $inst) {
                    if ((string)($inst['id'] ?? '') === (string)$filtroInstrutor) {
                        echo htmlspecialchars($inst['name'] ?? $inst['nome'] ?? '');
                        break;
                    }
                }
            ?>
        <?php endif; ?>
        <?php if ($filtroVeiculo): ?>
            | Veículo: <?php 
                foreach ($veiculos as $vei) {
                    if ((string)($vei['id'] ?? '') === (string)$filtroVeiculo) {
                        echo htmlspecialchars($vei['plate'] ?? '');
                        break;
                    }
                }
            ?>
        <?php endif; ?>
        <?php if ($filtroAluno): ?>
            | Aluno: <?php 
                foreach ($alunos as $al) {
                    if ((string)($al['id'] ?? '') === (string)$filtroAluno) {
                        echo htmlspecialchars($al['full_name'] ?? $al['name'] ?? $al['nome'] ?? '');
                        break;
                    }
                }
            ?>
        <?php endif; ?>
    </div>
    
    <div class="page-header no-print" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: var(--spacing-md); margin-bottom: var(--spacing-lg);">
        <div>
            <h1>Relatório de Quilometragem</h1>
            <p class="text-muted">Consolidação de KM rodado por período com filtros e detecção de inconsistências.</p>
        </div>
        <div style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
            <a href="<?= base_url('relatorio-quilometragem/exportar') ?>?data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>&visao=<?= urlencode($visao) ?>&instrutor_id=<?= urlencode((string)$filtroInstrutor) ?>&veiculo_id=<?= urlencode((string)$filtroVeiculo) ?>&aluno_id=<?= urlencode((string)$filtroAluno) ?>" class="btn btn-outline" target="_blank" rel="noopener">
                Exportar CSV
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print();">
                Imprimir
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card no-print" style="margin-bottom: var(--spacing-lg);">
        <div class="card-body">
            <form method="get" action="<?= base_url('relatorio-quilometragem') ?>" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: var(--spacing-md); align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="data_inicio" class="form-input" value="<?= htmlspecialchars($dataInicio) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Data Fim</label>
                    <input type="date" name="data_fim" class="form-input" value="<?= htmlspecialchars($dataFim) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Visão</label>
                    <select name="visao" class="form-input" style="min-width: 140px;">
                        <option value="diario" <?= $visao === 'diario' ? 'selected' : '' ?>>Diário</option>
                        <option value="semanal" <?= $visao === 'semanal' ? 'selected' : '' ?>>Semanal</option>
                        <option value="mensal" <?= $visao === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                        <option value="anual" <?= $visao === 'anual' ? 'selected' : '' ?>>Anual</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Instrutor</label>
                    <select name="instrutor_id" class="form-input" style="min-width: 140px;">
                        <option value="">Todos</option>
                        <?php foreach ($instrutores as $inst): ?>
                            <option value="<?= (int)($inst['id'] ?? 0) ?>" <?= $filtroInstrutor === (string)($inst['id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($inst['name'] ?? $inst['nome'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Veículo</label>
                    <select name="veiculo_id" class="form-input" style="min-width: 140px;">
                        <option value="">Todos</option>
                        <?php foreach ($veiculos as $vei): ?>
                            <option value="<?= (int)($vei['id'] ?? 0) ?>" <?= $filtroVeiculo === (string)($vei['id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($vei['plate'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Aluno</label>
                    <select name="aluno_id" class="form-input" style="min-width: 140px;">
                        <option value="">Todos</option>
                        <?php foreach ($alunos as $al): ?>
                            <option value="<?= (int)($al['id'] ?? 0) ?>" <?= $filtroAluno === (string)($al['id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($al['full_name'] ?? $al['name'] ?? $al['nome'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Alerta de Inconsistências -->
    <?php if ($totais['inconsistencias'] > 0): ?>
    <div class="alert-inconsistencia no-print">
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" style="color: #856404;">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <strong>Atenção:</strong> <?= $totais['inconsistencias'] ?> aula(s) com inconsistência detectada (KM final menor que KM inicial).
    </div>
    <?php endif; ?>

    <!-- Totais -->
    <div class="print-totals" style="display: flex; flex-wrap: wrap; gap: var(--spacing-md); margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: var(--cfc-surface-muted, #f3f4f6); border-radius: var(--radius-md);">
        <span><strong>Total de Aulas:</strong> <span class="badge badge-secondary"><?= (int)$totais['total_aulas'] ?></span></span>
        <span><strong>KM Total:</strong> <span class="badge badge-info"><?= number_format($totais['km_total'], 0, ',', '.') ?> km</span></span>
        <span><strong>KM Médio/Aula:</strong> <span class="badge badge-success"><?= number_format($totais['km_medio_por_aula'], 1, ',', '.') ?> km</span></span>
        <?php if ($totais['inconsistencias'] > 0): ?>
        <span><strong>Inconsistências:</strong> <span class="badge badge-danger"><?= (int)$totais['inconsistencias'] ?></span></span>
        <?php endif; ?>
    </div>

    <!-- Tabela Consolidada -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div style="padding: var(--spacing-md); border-bottom: 1px solid var(--cfc-border-subtle, #e5e7eb);">
                <strong>Consolidação por <?= visaoLabel($visao) ?> (<?= count($consolidated) ?> período(s))</strong>
            </div>
            <?php if (empty($consolidated)): ?>
                <div style="padding: var(--spacing-xl); text-align: center; color: var(--gray-500);">
                    Nenhum dado de quilometragem encontrado no período com os filtros aplicados.
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table" style="margin: 0; font-size: 0.9rem;">
                        <thead>
                            <tr>
                                <th>Período</th>
                                <th style="text-align: right;">KM Inicial</th>
                                <th style="text-align: right;">KM Final</th>
                                <th style="text-align: right;">KM Rodado</th>
                                <th style="text-align: center;">Aulas</th>
                                <th style="text-align: center;">Inconsistências</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consolidated as $row): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['periodo_label']) ?></strong></td>
                                    <td style="text-align: right;"><?= number_format($row['km_inicial'], 0, ',', '.') ?> km</td>
                                    <td style="text-align: right;"><?= number_format($row['km_final'], 0, ',', '.') ?> km</td>
                                    <td style="text-align: right;"><strong><?= number_format($row['km_rodado'], 0, ',', '.') ?> km</strong></td>
                                    <td style="text-align: center;"><span class="badge badge-info"><?= $row['total_aulas'] ?></span></td>
                                    <td style="text-align: center;">
                                        <?php if ($row['inconsistencias'] > 0): ?>
                                            <span class="badge badge-danger"><?= $row['inconsistencias'] ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success">✓</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f5f5f5; font-weight: bold;">
                                <td>TOTAL</td>
                                <td style="text-align: right;">—</td>
                                <td style="text-align: right;">—</td>
                                <td style="text-align: right;"><?= number_format($totais['km_total'], 0, ',', '.') ?> km</td>
                                <td style="text-align: center;"><?= $totais['total_aulas'] ?></td>
                                <td style="text-align: center;"><?= $totais['inconsistencias'] ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Rodapé para Impressão -->
    <div class="print-footer">
        <div class="print-footer-content">
            <div>Gerado em: <?= date('d/m/Y H:i:s') ?></div>
            <div>Relatório de Quilometragem</div>
            <div>Página <span class="page-number"></span></div>
        </div>
    </div>
</div>

<script>
// Adicionar número de página na impressão
window.addEventListener('beforeprint', function() {
    const pageNumbers = document.querySelectorAll('.page-number');
    pageNumbers.forEach(el => el.textContent = '1');
});
</script>
