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
    .no-print { display: none !important; }
    body { margin: 0; padding: 20px; }
}
.stats-card {
    padding: 1.25rem;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
}
.stats-number {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}
.stats-label {
    font-size: 0.875rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.progress-bar-custom {
    height: 20px;
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
    font-size: 0.75rem;
    font-weight: 600;
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h1 class="h3 mb-0">
            <i class="fas fa-users-cog me-2"></i>Relatório de Alunos por Status
        </h1>
        <div>
            <a href="<?= base_url('relatorio-alunos-status/exportar?' . http_build_query($_GET)) ?>" class="btn btn-success me-2">
                <i class="fas fa-file-csv me-1"></i>Exportar CSV
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print();">
                <i class="fas fa-print me-1"></i>Imprimir
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4 no-print">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="<?= base_url('relatorio-alunos-status') ?>" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status do Aluno</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="lead" <?= $filtroStatus === 'lead' ? 'selected' : '' ?>>Lead</option>
                        <option value="matriculado" <?= $filtroStatus === 'matriculado' ? 'selected' : '' ?>>Matriculado</option>
                        <option value="em_andamento" <?= $filtroStatus === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                        <option value="concluido" <?= $filtroStatus === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                        <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="cfc_id" class="form-label">Unidade/CFC</label>
                    <select class="form-select" id="cfc_id" name="cfc_id">
                        <option value="">Todas</option>
                        <?php foreach ($cfcs as $cfc): ?>
                            <option value="<?= $cfc['id'] ?>" <?= $filtroCfc == $cfc['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cfc['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="data_inicio" class="form-label">Data Matrícula Início</label>
                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($filtroDataInicio) ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="data_fim" class="form-label">Data Matrícula Fim</label>
                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= htmlspecialchars($filtroDataFim) ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stats-card" style="background: #e3f2fd;">
                <div class="stats-number text-primary"><?= $stats['total_alunos'] ?></div>
                <div class="stats-label">Total</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card" style="background: #fff3e0;">
                <div class="stats-number text-warning"><?= $stats['em_andamento'] ?></div>
                <div class="stats-label">Em Andamento</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card" style="background: #e8f5e8;">
                <div class="stats-number text-success"><?= $stats['concluido'] ?></div>
                <div class="stats-label">Concluídos</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card" style="background: #f3e5f5;">
                <div class="stats-number text-info"><?= $stats['matriculado'] ?></div>
                <div class="stats-label">Matriculados</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card" style="background: #ffebee;">
                <div class="stats-number text-danger"><?= $stats['bloqueados'] ?></div>
                <div class="stats-label">Bloqueados</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card" style="background: #fce4ec;">
                <div class="stats-number text-secondary"><?= $stats['cancelado'] ?></div>
                <div class="stats-label">Cancelados</div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Listagem de Alunos (<?= count($alunos) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($alunos)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p class="mb-0">Nenhum aluno encontrado com os filtros aplicados.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
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
</div>
