<?php
$allClasses = $allClasses ?? [];
$selectedClass = $selectedClass ?? null;
$students = $students ?? [];
$disciplines = $disciplines ?? [];
$attendanceRecords = $attendanceRecords ?? [];
$studentTotals = $studentTotals ?? [];
$totals = $totals ?? ['total_sessions' => 0, 'total_presences' => 0, 'total_absences' => 0, 'total_justified' => 0, 'total_makeup' => 0, 'attendance_rate' => 0];
$classId = $classId ?? '';
$studentId = $studentId ?? '';
$disciplineId = $disciplineId ?? '';
$startDate = $startDate ?? '';
$endDate = $endDate ?? '';
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

function formatAttendanceStatus($status) {
    $map = [
        'present' => 'Presente',
        'absent' => 'Falta',
        'justified' => 'Justificada',
        'makeup' => 'Reposição'
    ];
    return $map[$status] ?? ucfirst($status);
}

function attendanceStatusBadgeClass($status) {
    $map = [
        'present' => 'badge-success',
        'absent' => 'badge-danger',
        'justified' => 'badge-warning',
        'makeup' => 'badge-info'
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
    
    .print-class-info {
        text-align: center;
        margin-bottom: 10px;
        font-size: 9pt;
        color: #666;
    }
    
    .print-totals {
        display: flex !important;
        justify-content: center;
        gap: 12px;
        margin-bottom: 15px;
        padding: 8px;
        background: #f0f0f0;
        border: 1px solid #ccc;
        font-size: 8pt;
        page-break-inside: avoid;
    }
    
    .print-totals span {
        font-weight: bold;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8pt;
        page-break-inside: auto;
    }
    
    .table thead {
        display: table-header-group;
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
        font-size: 8pt;
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
    
    .badge {
        padding: 1px 4px;
        border-radius: 2px;
        font-size: 7pt;
        font-weight: bold;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .badge-success { background: #28a745 !important; color: white !important; }
    .badge-danger { background: #dc3545 !important; color: white !important; }
    .badge-warning { background: #ffc107 !important; color: #333 !important; }
    .badge-info { background: #17a2b8 !important; color: white !important; }
    .badge-secondary { background: #6c757d !important; color: white !important; }
    
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
    
    .card { 
        box-shadow: none !important; 
        border: none !important;
    }
}

@media screen {
    .print-header, .print-footer, .print-class-info { display: none; }
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
.badge-danger { background-color: #dc3545; color: white; }
.badge-warning { background-color: #ffc107; color: #212529; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }
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

    <div class="print-title">Relatório de Presença Teórica</div>
    <?php if ($selectedClass): ?>
    <div class="print-class-info">
        <strong>Turma:</strong> <?= htmlspecialchars($selectedClass['name'] ?? $selectedClass['course_name']) ?>
        <?php if ($startDate && $endDate): ?>
        | <strong>Período:</strong> <?= date('d/m/Y', strtotime($startDate)) ?> a <?= date('d/m/Y', strtotime($endDate)) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Cabeçalho -->
    <div class="page-header no-print" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: var(--spacing-md); margin-bottom: var(--spacing-lg);">
        <div>
            <h1>Relatório de Presença Teórica</h1>
            <p class="text-muted">Acompanhamento de presença e faltas discriminando matéria/aula teórica</p>
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
            <form method="get" action="<?= base_path('relatorio-presenca-teorica') ?>" id="filterForm" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: var(--spacing-md); align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Turma *</label>
                    <select name="class_id" class="form-input" style="min-width: 180px;" required onchange="document.getElementById('filterForm').submit();">
                        <option value="">Selecione uma turma</option>
                        <?php foreach ($allClasses as $class): ?>
                            <option value="<?= (int)$class['id'] ?>" <?= $classId == $class['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['name'] ?? $class['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Aluno</label>
                    <select name="student_id" class="form-input" style="min-width: 180px;">
                        <option value="">Todos</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= (int)$student['id'] ?>" <?= $studentId == $student['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Matéria</label>
                    <select name="discipline_id" class="form-input" style="min-width: 180px;">
                        <option value="">Todas</option>
                        <?php foreach ($disciplines as $discipline): ?>
                            <option value="<?= (int)$discipline['id'] ?>" <?= $disciplineId == $discipline['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($discipline['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="start_date" class="form-input" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Data Fim</label>
                    <input type="date" name="end_date" class="form-input" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedClass): ?>
    <!-- Totais Gerais -->
    <div class="print-totals no-print" style="display: flex; flex-wrap: wrap; gap: var(--spacing-md); margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: var(--cfc-surface-muted, #f3f4f6); border-radius: var(--radius-md);">
        <span><strong>Sessões Realizadas:</strong> <span class="badge badge-secondary"><?= $totals['total_sessions'] ?></span></span>
        <span><strong>Presenças:</strong> <span class="badge badge-success"><?= $totals['total_presences'] ?></span></span>
        <span><strong>Faltas:</strong> <span class="badge badge-danger"><?= $totals['total_absences'] ?></span></span>
        <span><strong>Justificadas:</strong> <span class="badge badge-warning"><?= $totals['total_justified'] ?></span></span>
        <span><strong>Reposições:</strong> <span class="badge badge-info"><?= $totals['total_makeup'] ?></span></span>
        <span><strong>Taxa Média:</strong> <span class="badge badge-secondary"><?= $totals['attendance_rate'] ?>%</span></span>
    </div>

    <!-- Totais para impressão -->
    <div class="print-totals">
        <span><strong>Sessões:</strong> <?= $totals['total_sessions'] ?></span>
        <span><strong>Presenças:</strong> <?= $totals['total_presences'] ?></span>
        <span><strong>Faltas:</strong> <?= $totals['total_absences'] ?></span>
        <span><strong>Justificadas:</strong> <?= $totals['total_justified'] ?></span>
        <span><strong>Taxa:</strong> <?= $totals['attendance_rate'] ?>%</span>
    </div>

    <!-- Totais por Aluno -->
    <?php if (!empty($studentTotals)): ?>
    <div class="card no-print" style="margin-bottom: var(--spacing-lg);">
        <div class="card-body">
            <h3 style="margin-bottom: var(--spacing-md);">Totais por Aluno</h3>
            <div style="overflow-x: auto;">
                <table class="table" style="margin: 0; font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th style="text-align: center;">Sessões</th>
                            <th style="text-align: center;">Presenças</th>
                            <th style="text-align: center;">Faltas</th>
                            <th style="text-align: center;">Justificadas</th>
                            <th style="text-align: center;">Reposições</th>
                            <th style="text-align: center;">% Presença</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($studentTotals as $st): ?>
                        <tr>
                            <td><?= htmlspecialchars($st['student_name']) ?></td>
                            <td style="text-align: center;"><?= (int)$st['total_sessions'] ?></td>
                            <td style="text-align: center;"><?= (int)$st['presences'] ?></td>
                            <td style="text-align: center;"><?= (int)$st['absences'] ?></td>
                            <td style="text-align: center;"><?= (int)$st['justified'] ?></td>
                            <td style="text-align: center;"><?= (int)$st['makeup'] ?></td>
                            <td style="text-align: center;">
                                <span class="badge <?= $st['attendance_rate'] >= 75 ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $st['attendance_rate'] ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Registros Detalhados de Presença -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div style="padding: var(--spacing-md); border-bottom: 1px solid var(--cfc-border-subtle, #e5e7eb);">
                <strong>Registros Detalhados (<?= count($attendanceRecords) ?> registro(s))</strong>
            </div>
            <?php if (empty($attendanceRecords)): ?>
            <div style="padding: var(--spacing-xl); text-align: center; color: var(--gray-500);">
                Nenhum registro de presença encontrado com os filtros aplicados.
            </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table" style="margin: 0; font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Matéria</th>
                            <th>Data/Hora</th>
                            <th>Status</th>
                            <th>Observações</th>
                            <th>Marcado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceRecords as $record): ?>
                        <?php
                        $dateTime = !empty($record['starts_at']) && $record['starts_at'] !== '0000-00-00 00:00:00'
                            ? date('d/m/Y H:i', strtotime($record['starts_at']))
                            : '—';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($record['student_name']) ?></td>
                            <td><?= htmlspecialchars($record['discipline_name']) ?></td>
                            <td><?= $dateTime ?></td>
                            <td>
                                <span class="badge <?= attendanceStatusBadgeClass($record['status']) ?>">
                                    <?= formatAttendanceStatus($record['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($record['notes'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($record['marked_by_name'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <!-- Mensagem quando nenhuma turma selecionada -->
    <div class="card">
        <div class="card-body" style="padding: var(--spacing-xl); text-align: center; color: var(--gray-500);">
            Selecione uma turma para visualizar o relatório de presença.
        </div>
    </div>
    <?php endif; ?>

    <!-- Rodapé para impressão -->
    <div class="print-footer">
        Relatório gerado em <?= date('d/m/Y H:i') ?> | <?= htmlspecialchars($cfcInfo['nome']) ?>
    </div>
</div>
