<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($class['name'] ?: $class['course_name']) ?></h1>
        <p class="text-muted">Detalhes da turma teórica</p>
    </div>
    <div style="display: flex; gap: var(--spacing-sm);">
        <a href="<?= base_path('turmas-teoricas') ?>" class="btn btn-outline">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Listar Turmas
        </a>
        <?php if (\App\Services\PermissionService::check('turmas_teoricas', 'update')): ?>
        <a href="<?= base_path("turmas-teoricas/{$class['id']}/editar") ?>" class="btn btn-outline">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Editar
        </a>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--spacing-md); margin-bottom: var(--spacing-lg);">
    <div class="card">
        <div class="card-body">
            <h3 style="margin: 0 0 var(--spacing-sm) 0; font-size: var(--font-size-base);">Informações</h3>
            <div style="display: flex; flex-direction: column; gap: var(--spacing-xs);">
                <div>
                    <strong>Curso:</strong> <?= htmlspecialchars($class['course_name']) ?>
                </div>
                <div>
                    <strong>Instrutor:</strong> <?= htmlspecialchars($class['instructor_name']) ?>
                </div>
                <?php if ($class['start_date']): ?>
                <div>
                    <strong>Data Início:</strong> <?= date('d/m/Y', strtotime($class['start_date'])) ?>
                </div>
                <?php endif; ?>
                <div>
                    <strong>Status:</strong>
                    <?php
                    $statusLabels = [
                        'scheduled' => ['label' => 'Agendada', 'class' => 'badge-secondary'],
                        'in_progress' => ['label' => 'Em Andamento', 'class' => 'badge-primary'],
                        'completed' => ['label' => 'Concluída', 'class' => 'badge-success'],
                        'cancelled' => ['label' => 'Cancelada', 'class' => 'badge-danger']
                    ];
                    $statusInfo = $statusLabels[$class['status']] ?? ['label' => $class['status'], 'class' => 'badge-secondary'];
                    ?>
                    <span class="badge <?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
    <!-- Aulas -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">Aulas</h3>
            <?php if (\App\Services\PermissionService::check('turmas_teoricas', 'create')): ?>
            <a href="<?= base_path("turmas-teoricas/{$class['id']}/sessoes/novo") ?>" class="btn btn-sm btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nova Aula
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($sessions)): ?>
                <p class="text-muted">Nenhuma aula cadastrada.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                    <?php foreach ($sessions as $session): ?>
                        <div style="padding: var(--spacing-sm); background: var(--color-bg-light); border-radius: var(--radius-md);">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <strong><?= htmlspecialchars($session['discipline_name']) ?></strong>
                                    <div style="font-size: var(--font-size-sm); color: var(--color-text-muted); margin-top: var(--spacing-xs);">
                                        <?= date('d/m/Y H:i', strtotime($session['starts_at'])) ?> - 
                                        <?= date('H:i', strtotime($session['ends_at'])) ?>
                                    </div>
                                    <?php if ($session['location']): ?>
                                    <div style="font-size: var(--font-size-sm); color: var(--color-text-muted);">
                                        📍 <?= htmlspecialchars($session['location']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; gap: var(--spacing-xs);">
                                    <a href="<?= base_path("turmas-teoricas/{$class['id']}/sessoes/{$session['id']}/presenca") ?>" class="btn btn-sm btn-outline" title="Marcar presença">
                                        ✓
                                    </a>
                                    <?php if (\App\Services\PermissionService::check('turmas_teoricas', 'update') && $session['status'] === 'scheduled'): ?>
                                    <a href="<?= base_path("turmas-teoricas/{$class['id']}/sessoes/{$session['id']}/editar") ?>" class="btn btn-sm btn-outline" title="Editar aula">
                                        ✏️
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alunos Matriculados -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">Alunos (<?= count($enrollments) ?>)</h3>
            <?php if (\App\Services\PermissionService::check('turmas_teoricas', 'create')): ?>
            <a href="<?= base_path("turmas-teoricas/{$class['id']}/matricular") ?>" class="btn btn-sm btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Matricular
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($enrollments)): ?>
                <p class="text-muted">Nenhum aluno matriculado.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: var(--spacing-xs);">
                    <?php foreach ($enrollments as $enrollment): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-sm); background: var(--color-bg-light); border-radius: var(--radius-md);">
                            <div>
                                <strong><?= htmlspecialchars($enrollment['student_name']) ?></strong>
                                <?php if ($enrollment['student_cpf']): ?>
                                <div style="font-size: var(--font-size-sm); color: var(--color-text-muted);">
                                    CPF: <?= htmlspecialchars($enrollment['student_cpf']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($enrollment['status'] === 'active' && \App\Services\PermissionService::check('turmas_teoricas', 'update')): ?>
                            <form method="POST" action="<?= base_path("turmas-teoricas/{$class['id']}/matriculas/{$enrollment['id']}/remover") ?>" style="display: inline;" onsubmit="return confirm('Deseja realmente remover este aluno da turma?');">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn-sm btn-outline" style="color: var(--color-danger);">
                                    Remover
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.btn-sm {
    padding: var(--spacing-xs) var(--spacing-sm);
    font-size: var(--font-size-sm);
}
</style>
