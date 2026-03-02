<?php
$list = $list ?? [];
$totais = $totais ?? ['total' => 0, 'agendadas' => 0, 'realizadas' => 0, 'canceladas' => 0, 'em_andamento' => 0];
$dataInicio = $dataInicio ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $dataFim ?? date('Y-m-d');
$filtroInstrutor = $filtroInstrutor ?? '';
$filtroAluno = $filtroAluno ?? '';
$filtroStatus = $filtroStatus ?? '';
$instrutores = $instrutores ?? [];
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

function statusLabel($status) {
    $s = strtolower((string) $status);
    if ($s === 'scheduled' || $s === 'agendada') return 'Agendada';
    if ($s === 'done' || $s === 'concluida') return 'Realizada';
    if ($s === 'canceled' || $s === 'cancelada') return 'Cancelada';
    if ($s === 'in_progress' || $s === 'em_andamento') return 'Em andamento';
    return $s ? ucfirst(str_replace('_', ' ', $s)) : '—';
}

function statusBadgeClass($status) {
    $s = strtolower((string) $status);
    if ($s === 'scheduled' || $s === 'agendada') return 'badge-info';
    if ($s === 'done' || $s === 'concluida') return 'badge-success';
    if ($s === 'canceled' || $s === 'cancelada') return 'badge-danger';
    if ($s === 'in_progress' || $s === 'em_andamento') return 'badge-warning';
    return 'badge-secondary';
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
    
    .badge-info { background: #17a2b8; color: white; }
    .badge-success { background: #28a745; color: white; }
    .badge-danger { background: #dc3545; color: white; }
    .badge-warning { background: #ffc107; color: #333; }
    .badge-primary { background: #007bff; color: white; }
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
    
    <div class="print-title">Relatório de Aulas por Período</div>
    <div class="print-period">
        Período: <?= date('d/m/Y', strtotime($dataInicio)) ?> a <?= date('d/m/Y', strtotime($dataFim)) ?>
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
        <?php if ($filtroStatus): ?>
            | Status: <?= ucfirst(str_replace('_', ' ', $filtroStatus)) ?>
        <?php endif; ?>
    </div>
    <div class="page-header no-print" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: var(--spacing-md); margin-bottom: var(--spacing-lg);">
        <div>
            <h1>Relatório de Aulas por Período</h1>
            <p class="text-muted">Filtros por período, instrutor, aluno e status. Totais batendo com a lista.</p>
        </div>
        <div style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
            <a href="<?= base_url('relatorio-aulas/exportar') ?>?data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>&instrutor_id=<?= urlencode((string)$filtroInstrutor) ?>&aluno_id=<?= urlencode((string)$filtroAluno) ?>&status=<?= urlencode($filtroStatus) ?>" class="btn btn-outline" target="_blank" rel="noopener">
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
            <form method="get" action="<?= base_url('relatorio-aulas') ?>" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: var(--spacing-md); align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="data_inicio" class="form-input" value="<?= htmlspecialchars($dataInicio) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Data Fim</label>
                    <input type="date" name="data_fim" class="form-input" value="<?= htmlspecialchars($dataFim) ?>">
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
                    <label class="form-label">Aluno</label>
                    <select name="aluno_id" class="form-input" style="min-width: 140px;">
                        <option value="">Todos</option>
                        <?php foreach ($alunos as $al): ?>
                            <option value="<?= (int)($al['id'] ?? 0) ?>" <?= $filtroAluno === (string)($al['id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($al['full_name'] ?? $al['name'] ?? $al['nome'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input" style="min-width: 140px;">
                        <option value="">Todos</option>
                        <option value="agendada" <?= $filtroStatus === 'agendada' ? 'selected' : '' ?>>Agendada</option>
                        <option value="em_andamento" <?= $filtroStatus === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
                        <option value="concluida" <?= $filtroStatus === 'concluida' ? 'selected' : '' ?>>Realizada</option>
                        <option value="cancelada" <?= $filtroStatus === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Totais -->
    <div class="print-totals" style="display: flex; flex-wrap: wrap; gap: var(--spacing-md); margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: var(--cfc-surface-muted, #f3f4f6); border-radius: var(--radius-md);">
        <span><strong>Total:</strong> <span class="badge badge-secondary"><?= (int)$totais['total'] ?></span></span>
        <span><strong>Agendadas:</strong> <span class="badge badge-info"><?= (int)$totais['agendadas'] ?></span></span>
        <span><strong>Realizadas:</strong> <span class="badge badge-success"><?= (int)$totais['realizadas'] ?></span></span>
        <span><strong>Canceladas:</strong> <span class="badge badge-danger"><?= (int)$totais['canceladas'] ?></span></span>
        <span><strong>Em andamento:</strong> <span class="badge badge-warning"><?= (int)$totais['em_andamento'] ?></span></span>
    </div>

    <!-- Tabela -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div style="padding: var(--spacing-md); border-bottom: 1px solid var(--cfc-border-subtle, #e5e7eb);">
                <strong>Listagem (<?= count($list) ?> registro(s))</strong>
            </div>
            <?php if (empty($list)): ?>
                <div style="padding: var(--spacing-xl); text-align: center; color: var(--gray-500);">
                    Nenhuma aula encontrada no período com os filtros aplicados.
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table" style="margin: 0; font-size: 0.9rem;">
                        <thead>
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
                            <?php foreach ($list as $row): ?>
                                <?php
                                $dataAula = $row['scheduled_date'] ?? '';
                                $horaInicio = $row['scheduled_time'] ?? '';
                                $dur = (int)($row['duration_minutes'] ?? 50);
                                $horaFim = $horaInicio ? date('H:i', strtotime($horaInicio) + $dur * 60) : '';
                                $horario = $horaInicio && $horaFim ? (date('H:i', strtotime($horaInicio)) . ' - ' . $horaFim) : '—';
                                $alunoNome = $row['student_name'] ?? $row['student_names'] ?? '—';
                                $instrutorNome = $row['instructor_name'] ?? '—';
                                $tipo = isset($row['type']) ? ucfirst($row['type']) : '—';
                                $st = $row['status'] ?? '';
                                ?>
                                <tr>
                                    <td><?= $dataAula ? date('d/m/Y', strtotime($dataAula)) : '—' ?></td>
                                    <td><?= htmlspecialchars($horario) ?></td>
                                    <td><?= htmlspecialchars($alunoNome) ?></td>
                                    <td><?= htmlspecialchars($instrutorNome) ?></td>
                                    <td><span class="badge <?= $tipo === 'Teoria' ? 'badge-info' : 'badge-primary' ?>"><?= htmlspecialchars($tipo) ?></span></td>
                                    <td><span class="badge <?= statusBadgeClass($st) ?>"><?= statusLabel($st) ?></span></td>
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
            <div>Relatório de Aulas por Período</div>
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
