<?php
/**
 * Relatório de Aulas por Período
 * Filtros: período, instrutor, aluno, status. Totais no topo. Exportação e impressão.
 * Acesso: apenas ADMIN e SECRETARIA.
 */

if (!defined('ADMIN_ROUTING')) {
    header('Location: ../index.php');
    exit;
}

$user = getCurrentUser();
$userType = $user['tipo'] ?? null;
if (!in_array($userType, ['admin', 'secretaria'], true)) {
    $_SESSION['flash_message'] = 'Acesso negado. Apenas administradores e secretárias podem acessar o Relatório de Aulas.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: index.php');
    exit;
}

if (!isset($relatorio_aulas_lista)) {
    $relatorio_aulas_lista = [];
}
if (!isset($relatorio_totais)) {
    $relatorio_totais = ['total' => 0, 'agendadas' => 0, 'realizadas' => 0, 'canceladas' => 0, 'em_andamento' => 0];
}
$dataInicio = $relatorio_data_inicio ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $relatorio_data_fim ?? date('Y-m-d');
$filtroInstrutor = $relatorio_instrutor_id ?? '';
$filtroAluno = $relatorio_aluno_id ?? '';
$filtroStatus = $relatorio_status ?? '';
$instrutoresLista = $relatorio_instrutores_lista ?? [];
$alunosLista = $relatorio_alunos_lista ?? [];
?>

<style>
.relatorio-aulas-print .no-print { display: none !important; }
@media print {
    .no-print, .nav-sidebar, .topbar, .btn-toolbar .btn { display: none !important; }
    .relatorio-aulas-print { padding: 0; }
    .card { border: 1px solid #ddd; box-shadow: none; }
}
.stats-mini { font-size: 0.95rem; }
.table-relatorio { font-size: 0.9rem; }
.table-relatorio th { white-space: nowrap; }
</style>

<div class="relatorio-aulas-print">
    <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
        <h1 class="h2">
            <i class="fas fa-chart-bar me-2"></i>Relatório de Aulas por Período
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0 no-print">
            <a href="api/exportar-relatorio-aulas.php?data_inicio=<?php echo urlencode($dataInicio); ?>&data_fim=<?php echo urlencode($dataFim); ?>&instrutor_id=<?php echo urlencode($filtroInstrutor); ?>&aluno_id=<?php echo urlencode($filtroAluno); ?>&status=<?php echo urlencode($filtroStatus); ?>" class="btn btn-outline-success me-2" target="_blank">
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
            <form method="GET" class="row g-3">
                <input type="hidden" name="page" value="relatorio-aulas">
                <div class="col-md-2">
                    <label for="data_inicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($dataInicio); ?>">
                </div>
                <div class="col-md-2">
                    <label for="data_fim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($dataFim); ?>">
                </div>
                <div class="col-md-2">
                    <label for="instrutor_id" class="form-label">Instrutor</label>
                    <select class="form-select" id="instrutor_id" name="instrutor_id">
                        <option value="">Todos</option>
                        <?php foreach ($instrutoresLista as $inst): ?>
                            <option value="<?php echo (int)$inst['id']; ?>" <?php echo ($filtroInstrutor === (string)$inst['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($inst['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="aluno_id" class="form-label">Aluno</label>
                    <select class="form-select" id="aluno_id" name="aluno_id">
                        <option value="">Todos</option>
                        <?php foreach ($alunosLista as $al): ?>
                            <option value="<?php echo (int)$al['id']; ?>" <?php echo ($filtroAluno === (string)$al['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($al['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="agendada" <?php echo $filtroStatus === 'agendada' ? 'selected' : ''; ?>>Agendada</option>
                        <option value="em_andamento" <?php echo $filtroStatus === 'em_andamento' ? 'selected' : ''; ?>>Em andamento</option>
                        <option value="concluida" <?php echo $filtroStatus === 'concluida' ? 'selected' : ''; ?>>Realizada</option>
                        <option value="cancelada" <?php echo $filtroStatus === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Totais -->
    <div class="row mb-4">
        <div class="col">
            <div class="card border-primary">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap gap-4 stats-mini">
                        <span><strong>Total:</strong> <span class="badge bg-secondary"><?php echo (int)$relatorio_totais['total']; ?></span></span>
                        <span><strong>Agendadas:</strong> <span class="badge bg-info"><?php echo (int)$relatorio_totais['agendadas']; ?></span></span>
                        <span><strong>Realizadas:</strong> <span class="badge bg-success"><?php echo (int)$relatorio_totais['realizadas']; ?></span></span>
                        <span><strong>Canceladas:</strong> <span class="badge bg-danger"><?php echo (int)$relatorio_totais['canceladas']; ?></span></span>
                        <span><strong>Em andamento:</strong> <span class="badge bg-warning text-dark"><?php echo (int)$relatorio_totais['em_andamento']; ?></span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Listagem (<?php echo count($relatorio_aulas_lista); ?> registro(s))</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($relatorio_aulas_lista)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p class="mb-0">Nenhuma aula encontrada no período com os filtros aplicados.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped table-relatorio mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Horário</th>
                                <th>Aluno</th>
                                <th>Instrutor</th>
                                <th>Tipo</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($relatorio_aulas_lista as $aula): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($aula['data_aula'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($aula['hora_inicio'])); ?> - <?php echo date('H:i', strtotime($aula['hora_fim'])); ?></td>
                                    <td><?php echo htmlspecialchars($aula['aluno_nome'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($aula['instrutor_nome'] ?? '-'); ?></td>
                                    <td><span class="badge bg-<?php echo ($aula['tipo_aula'] ?? '') === 'teorica' ? 'info' : 'primary'; ?>"><?php echo ucfirst($aula['tipo_aula'] ?? '-'); ?></span></td>
                                    <td>
                                        <?php
                                        $st = $aula['status'] ?? '';
                                        $badge = $st === 'agendada' ? 'info' : ($st === 'concluida' ? 'success' : ($st === 'cancelada' ? 'danger' : 'warning'));
                                        $label = $st === 'concluida' ? 'Realizada' : ucfirst(str_replace('_', ' ', $st));
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo $label; ?></span>
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
