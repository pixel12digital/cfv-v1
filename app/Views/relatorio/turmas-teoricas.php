<?php
$allClasses = $allClasses ?? [];
$selectedClass = $selectedClass ?? null;
$students = $students ?? [];
$totals = $totals ?? ['total_students' => 0, 'active' => 0, 'inactive' => 0, 'completed' => 0];
$classId = $classId ?? '';
$statusFilter = $statusFilter ?? '';
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

function formatClassStatus($status) {
    $map = [
        'scheduled' => 'Agendada',
        'in_progress' => 'Em Andamento',
        'completed' => 'Concluída',
        'cancelled' => 'Cancelada'
    ];
    return $map[$status] ?? ucfirst($status);
}

function formatEnrollmentStatus($status) {
    $map = [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'completed' => 'Concluído'
    ];
    return $map[$status] ?? ucfirst($status);
}

function statusBadgeClass($status) {
    $map = [
        'active' => 'badge-success',
        'inactive' => 'badge-warning',
        'completed' => 'badge-info',
        'scheduled' => 'badge-info',
        'in_progress' => 'badge-primary',
        'cancelled' => 'badge-danger'
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
        size: A4 portrait;
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
        font-size: 10pt;
        color: #666;
    }
    
    .print-totals {
        display: flex !important;
        justify-content: center;
        gap: 15px;
        margin-bottom: 15px;
        padding: 8px;
        background: #f0f0f0;
        border: 1px solid #ccc;
        font-size: 9pt;
        page-break-inside: avoid;
    }
    
    .print-totals span {
        font-weight: bold;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
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
        padding: 6px 4px;
        text-align: left;
        font-weight: bold;
        border: 1px solid #333;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .table td {
        padding: 5px 4px;
        border: 1px solid #ccc;
        vertical-align: middle;
    }
    
    .table tbody tr:nth-child(even) {
        background: #f9f9f9 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .badge {
        padding: 2px 5px;
        border-radius: 2px;
        font-size: 8pt;
        font-weight: bold;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .badge-success { background: #28a745 !important; color: white !important; }
    .badge-warning { background: #ffc107 !important; color: #333 !important; }
    .badge-info { background: #17a2b8 !important; color: white !important; }
    .badge-primary { background: #007bff !important; color: white !important; }
    .badge-danger { background: #dc3545 !important; color: white !important; }
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
.badge-warning { background-color: #ffc107; color: #212529; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-primary { background-color: #007bff; color: white; }
.badge-danger { background-color: #dc3545; color: white; }
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

    <div class="print-title">Relatório de Turmas Teóricas</div>
    <?php if ($selectedClass): ?>
    <div class="print-class-info">
        <strong>Turma:</strong> <?= htmlspecialchars($selectedClass['name'] ?? $selectedClass['course_name']) ?> | 
        <strong>Curso:</strong> <?= htmlspecialchars($selectedClass['course_name']) ?> | 
        <strong>Instrutor:</strong> <?= htmlspecialchars($selectedClass['instructor_name']) ?>
        <?php if (!empty($selectedClass['start_date']) && $selectedClass['start_date'] !== '0000-00-00'): ?>
        | <strong>Início:</strong> <?= date('d/m/Y', strtotime($selectedClass['start_date'])) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Cabeçalho -->
    <div class="page-header no-print" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: var(--spacing-md); margin-bottom: var(--spacing-lg);">
        <div>
            <h1>Relatório de Turmas Teóricas</h1>
            <p class="text-muted">Listagem de alunos por turma com totalizadores</p>
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
            <form method="get" action="<?= base_path('relatorio-turmas-teoricas') ?>" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: var(--spacing-md); align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Turma</label>
                    <select name="class_id" class="form-input" style="min-width: 200px;" required>
                        <option value="">Selecione uma turma</option>
                        <?php foreach ($allClasses as $class): ?>
                            <option value="<?= (int)$class['id'] ?>" <?= $classId == $class['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['name'] ?? $class['course_name']) ?> 
                                (<?= htmlspecialchars($class['instructor_name']) ?>) 
                                - <?= (int)$class['enrolled_count'] ?> alunos
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Status da Turma</label>
                    <select name="status" class="form-input" style="min-width: 180px;">
                        <option value="">Todas</option>
                        <option value="scheduled" <?= $statusFilter === 'scheduled' ? 'selected' : '' ?>>Agendada</option>
                        <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>Em Andamento</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Concluída</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedClass): ?>
    <!-- Informações da Turma -->
    <div class="card no-print" style="margin-bottom: var(--spacing-lg);">
        <div class="card-body">
            <h3 style="margin-bottom: var(--spacing-md);">Informações da Turma</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-md);">
                <div>
                    <strong>Nome/Código:</strong><br>
                    <?= htmlspecialchars($selectedClass['name'] ?? $selectedClass['course_name']) ?>
                </div>
                <div>
                    <strong>Curso:</strong><br>
                    <?= htmlspecialchars($selectedClass['course_name']) ?>
                </div>
                <div>
                    <strong>Instrutor:</strong><br>
                    <?= htmlspecialchars($selectedClass['instructor_name']) ?>
                </div>
                <div>
                    <strong>Data de Início:</strong><br>
                    <?= !empty($selectedClass['start_date']) && $selectedClass['start_date'] !== '0000-00-00' 
                        ? date('d/m/Y', strtotime($selectedClass['start_date'])) 
                        : '—' ?>
                </div>
                <div>
                    <strong>Status:</strong><br>
                    <span class="badge <?= statusBadgeClass($selectedClass['status']) ?>">
                        <?= formatClassStatus($selectedClass['status']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Totais -->
    <div class="print-totals no-print" style="display: flex; flex-wrap: wrap; gap: var(--spacing-md); margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: var(--cfc-surface-muted, #f3f4f6); border-radius: var(--radius-md);">
        <span><strong>Total de Alunos:</strong> <span class="badge badge-secondary"><?= $totals['total_students'] ?></span></span>
        <span><strong>Ativos:</strong> <span class="badge badge-success"><?= $totals['active'] ?></span></span>
        <span><strong>Inativos:</strong> <span class="badge badge-warning"><?= $totals['inactive'] ?></span></span>
        <span><strong>Concluídos:</strong> <span class="badge badge-info"><?= $totals['completed'] ?></span></span>
    </div>

    <!-- Totais para impressão -->
    <div class="print-totals">
        <span><strong>Total:</strong> <?= $totals['total_students'] ?></span>
        <span><strong>Ativos:</strong> <?= $totals['active'] ?></span>
        <span><strong>Inativos:</strong> <?= $totals['inactive'] ?></span>
        <span><strong>Concluídos:</strong> <?= $totals['completed'] ?></span>
    </div>

    <!-- Tabela de Alunos -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div style="padding: var(--spacing-md); border-bottom: 1px solid var(--cfc-border-subtle, #e5e7eb);">
                <strong>Listagem de Alunos (<?= count($students) ?> registro(s))</strong>
            </div>
            <?php if (empty($students)): ?>
            <div style="padding: var(--spacing-xl); text-align: center; color: var(--gray-500);">
                Nenhum aluno matriculado nesta turma.
            </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table" style="margin: 0; font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>CPF</th>
                            <th>Data de Matrícula</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <?php
                        $cpfFormatted = \App\Helpers\ValidationHelper::formatCpf($student['student_cpf'] ?? '');
                        $enrolledDate = !empty($student['enrolled_at']) && $student['enrolled_at'] !== '0000-00-00 00:00:00'
                            ? date('d/m/Y', strtotime($student['enrolled_at']))
                            : '—';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($student['student_name']) ?></td>
                            <td><?= htmlspecialchars($cpfFormatted) ?></td>
                            <td><?= $enrolledDate ?></td>
                            <td>
                                <span class="badge <?= statusBadgeClass($student['status']) ?>">
                                    <?= formatEnrollmentStatus($student['status']) ?>
                                </span>
                            </td>
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
            Selecione uma turma para visualizar os alunos matriculados.
        </div>
    </div>
    <?php endif; ?>

    <!-- Rodapé para impressão -->
    <div class="print-footer">
        Relatório gerado em <?= date('d/m/Y H:i') ?> | <?= htmlspecialchars($cfcInfo['nome']) ?>
    </div>
</div>
