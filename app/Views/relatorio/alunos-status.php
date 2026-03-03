<?php
$alunos = $alunos ?? [];
$stats = $stats ?? ['total_alunos' => 0, 'em_andamento' => 0, 'concluido' => 0, 'matriculado' => 0, 'cancelado' => 0, 'bloqueados' => 0];
$cfcs = $cfcs ?? [];
$filtroStatus = $filtroStatus ?? '';
$filtroCfc = $filtroCfc ?? '';
$filtroDataInicio = $filtroDataInicio ?? '';
$filtroDataFim = $filtroDataFim ?? '';

function getStatusBadge($status) {
    $badges = [
        'lead' => '<span class="badge bg-secondary">Lead</span>',
        'matriculado' => '<span class="badge bg-primary">Matriculado</span>',
        'em_andamento' => '<span class="badge bg-warning text-dark">Em Andamento</span>',
        'concluido' => '<span class="badge bg-success">Concluído</span>',
        'cancelado' => '<span class="badge bg-danger">Cancelado</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
}

function getFinancialBadge($financialStatus, $bloqueado) {
    if ($bloqueado) {
        return '<span class="badge bg-danger"><i class="fas fa-lock me-1"></i>Bloqueado</span>';
    }
    $badges = [
        'em_dia' => '<span class="badge bg-success">Em Dia</span>',
        'pendente' => '<span class="badge bg-warning text-dark">Pendente</span>',
        'bloqueado' => '<span class="badge bg-danger">Bloqueado</span>'
    ];
    return $badges[$financialStatus] ?? '<span class="badge bg-secondary">-</span>';
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
}

.print-header,
.print-footer {
    display: none;
}

.progress-bar-custom {
    height: 18px;
    border-radius: 4px;
    background: #e9ecef;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
}
</style>

<div class="relatorio-print">
    <!-- Cabeçalho para Impressão -->
    <div class="print-header">
        <div>
            <img src="<?= base_url('configuracoes/cfc/logo') ?>" alt="Logo CFC" class="print-header-logo" onerror="this.style.display='none'">
        </div>
        <div class="print-header-info">
            <h2>CFC</h2>
        </div>
    </div>
    
    <div class="print-title">Relatório de Alunos por Status</div>
    <div class="print-period">
        <?php if ($filtroStatus): ?>Status: <?= ucfirst($filtroStatus) ?><?php endif; ?>
        <?php if ($filtroDataInicio && $filtroDataFim): ?>
            | Período: <?= date('d/m/Y', strtotime($filtroDataInicio)) ?> a <?= date('d/m/Y', strtotime($filtroDataFim)) ?>
        <?php endif; ?>
    </div>
    
    <div class="page-header no-print" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: var(--spacing-md); margin-bottom: var(--spacing-lg);">
        <div>
            <h1>Relatório de Alunos por Status</h1>
            <p class="text-muted">Controle de aulas contratadas, realizadas, agendadas e restantes por aluno.</p>
        </div>
        <div style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
            <a href="<?= base_url('relatorio-alunos-status/exportar?' . http_build_query($_GET)) ?>" class="btn btn-outline" target="_blank" rel="noopener">
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
            <form method="GET" action="<?= base_url('relatorio-alunos-status') ?>" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: var(--spacing-md); align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input" style="min-width: 140px;">
                        <option value="">Todos</option>
                        <option value="lead" <?= $filtroStatus === 'lead' ? 'selected' : '' ?>>Lead</option>
                        <option value="matriculado" <?= $filtroStatus === 'matriculado' ? 'selected' : '' ?>>Matriculado</option>
                        <option value="em_andamento" <?= $filtroStatus === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                        <option value="concluido" <?= $filtroStatus === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                        <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Unidade/CFC</label>
                    <select name="cfc_id" class="form-input" style="min-width: 140px;">
                        <option value="">Todas</option>
                        <?php foreach ($cfcs as $cfc): ?>
                            <option value="<?= $cfc['id'] ?>" <?= $filtroCfc == $cfc['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cfc['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="data_inicio" class="form-input" value="<?= htmlspecialchars($filtroDataInicio) ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Data Fim</label>
                    <input type="date" name="data_fim" class="form-input" value="<?= htmlspecialchars($filtroDataFim) ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Totais -->
    <div class="print-totals" style="display: flex; flex-wrap: wrap; gap: var(--spacing-md); margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: var(--cfc-surface-muted, #f3f4f6); border-radius: var(--radius-md);">
        <span><strong>Total:</strong> <span class="badge badge-secondary"><?= $stats['total_alunos'] ?></span></span>
        <span><strong>Em Andamento:</strong> <span class="badge badge-warning"><?= $stats['em_andamento'] ?></span></span>
        <span><strong>Concluídos:</strong> <span class="badge badge-success"><?= $stats['concluido'] ?></span></span>
        <span><strong>Matriculados:</strong> <span class="badge badge-info"><?= $stats['matriculado'] ?></span></span>
        <span><strong>Bloqueados:</strong> <span class="badge badge-danger"><?= $stats['bloqueados'] ?></span></span>
        <span><strong>Cancelados:</strong> <span class="badge badge-secondary"><?= $stats['cancelado'] ?></span></span>
    </div>

    <!-- Tabela -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div style="padding: var(--spacing-md); border-bottom: 1px solid var(--cfc-border-subtle, #e5e7eb);">
                <strong>Listagem (<?= count($alunos) ?> aluno(s))</strong>
            </div>
            <?php if (empty($alunos)): ?>
                <div style="padding: var(--spacing-xl); text-align: center; color: var(--gray-500);">
                    Nenhum aluno encontrado com os filtros aplicados.
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table" style="margin: 0; font-size: 0.9rem;">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>Status</th>
                                <th>Status Financeiro</th>
                                <th>Serviço</th>
                                <th class="text-center">Contratadas</th>
                                <th class="text-center">Realizadas</th>
                                <th class="text-center">Agendadas</th>
                                <th class="text-center">Restantes</th>
                                <th style="min-width: 150px;">Progresso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alunos as $aluno): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($aluno['nome']) ?></strong>
                                        <?php if ($aluno['bloqueado']): ?>
                                            <i class="fas fa-lock text-danger ms-1" title="Bloqueado financeiramente"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($aluno['cpf'] ?? '-') ?></td>
                                    <td><?= getStatusBadge($aluno['status']) ?></td>
                                    <td><?= getFinancialBadge($aluno['financial_status'], $aluno['bloqueado']) ?></td>
                                    <td><small><?= htmlspecialchars($aluno['servico'] ?? '-') ?></small></td>
                                    <td class="text-center">
                                        <strong><?= $aluno['aulas_contratadas'] === null ? 'Sem limite' : $aluno['aulas_contratadas'] ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?= $aluno['aulas_realizadas'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?= $aluno['aulas_agendadas'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($aluno['aulas_restantes'] === null): ?>
                                            <span class="badge bg-secondary">Sem limite</span>
                                        <?php elseif ($aluno['aulas_restantes'] <= 0): ?>
                                            <span class="badge bg-danger">0</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><?= $aluno['aulas_restantes'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($aluno['percentual_conclusao'] > 0): ?>
                                            <div class="progress-bar-custom">
                                                <div class="progress-fill" style="width: <?= min($aluno['percentual_conclusao'], 100) ?>%;">
                                                    <?= $aluno['percentual_conclusao'] ?>%
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="progress-bar-custom">
                                                <div class="progress-fill" style="width: 0%;">0%</div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Rodapé para Impressão -->
    <div class="print-footer">
        <div class="print-footer-content">
            <div>Gerado em: <?= date('d/m/Y H:i:s') ?></div>
            <div>Relatório de Alunos por Status</div>
        </div>
    </div>
</div>
