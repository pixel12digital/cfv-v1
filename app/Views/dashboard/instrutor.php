<?php
$currentRole = $_SESSION['current_role'] ?? '';
?>

<div class="content-header">
    <h1 class="content-title">Dashboard</h1>
    <p class="content-subtitle">Bem-vindo, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Instrutor') ?>!</p>
</div>

<?php if (!$instructor): ?>
    <div class="card">
        <div class="card-body text-center" style="padding: 60px 20px;">
            <p class="text-muted">Seu cadastro ainda não está vinculado ao sistema. Entre em contato com a administração.</p>
        </div>
    </div>
<?php else: ?>
    <!-- Próxima Aula -->
    <?php 
    // Verificar se aula está atrasada (horário passou mas não foi iniciada)
    $isOverdue = false;
    $isInProgress = false;
    if ($nextLesson) {
        $lessonDateTime = new \DateTime("{$nextLesson['scheduled_date']} {$nextLesson['scheduled_time']}");
        $now = new \DateTime();
        $isOverdue = ($nextLesson['status'] === 'agendada' && $lessonDateTime < $now);
        $isInProgress = ($nextLesson['status'] === 'em_andamento');
    }
    $cardBorderColor = $isOverdue ? 'var(--color-danger, #ef4444)' : ($isInProgress ? 'var(--color-warning, #f59e0b)' : 'transparent');
    $cardTitle = $isOverdue ? 'Aula Atrasada' : ($isInProgress ? 'Aula em Andamento' : 'Próxima Aula');
    ?>
    <div class="card" style="margin-bottom: var(--spacing-md); border-left: 4px solid <?= $cardBorderColor ?>;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;"><?= $cardTitle ?></h3>
            <?php if ($isOverdue): ?>
            <span style="background: var(--color-danger, #ef4444); color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                Aguardando início
            </span>
            <?php elseif ($isInProgress): ?>
            <span style="background: var(--color-warning, #f59e0b); color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                Em andamento
            </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($nextLesson): ?>
                <?php
                $lessonDate = new \DateTime("{$nextLesson['scheduled_date']} {$nextLesson['scheduled_time']}");
                
                // Calcular duração total (incluindo consecutivas)
                $hasConsecutive = !empty($consecutiveLessons) && count($consecutiveLessons) > 1;
                $totalDuration = $nextLesson['duration_minutes'];
                if ($hasConsecutive) {
                    $totalDuration = 0;
                    foreach ($consecutiveLessons as $consLesson) {
                        $totalDuration += (int)$consLesson['duration_minutes'];
                    }
                }
                
                $endTime = clone $lessonDate;
                $endTime->modify("+{$totalDuration} minutes");
                
                // Formatar duração para exibição
                $hours = floor($totalDuration / 60);
                $mins = $totalDuration % 60;
                $durationText = $hours > 0 
                    ? ($mins > 0 ? "{$hours}h{$mins}min" : "{$hours}h") 
                    : "{$mins}min";
                ?>
                <?php if ($isOverdue): ?>
                <div style="background: #fef2f2; border: 1px solid #fecaca; padding: var(--spacing-sm); border-radius: var(--radius-sm, 4px); margin-bottom: var(--spacing-sm); color: #991b1b; font-size: 0.875rem;">
                    Esta aula estava agendada para <?= $lessonDate->format('H:i') ?> e ainda não foi iniciada.
                </div>
                <?php endif; ?>
                <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                    <div>
                        <strong style="font-size: var(--font-size-lg);">
                            <?= $lessonDate->format('d/m/Y') ?> às <?= $lessonDate->format('H:i') ?> - <?= $endTime->format('H:i') ?>
                        </strong>
                        <?php if ($hasConsecutive): ?>
                        <span style="margin-left: var(--spacing-xs); background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: 600;">
                            <?= count($consecutiveLessons) ?> aulas consecutivas (<?= $durationText ?>)
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted">
                        Aluno: <?= htmlspecialchars($nextLesson['student_name']) ?>
                    </div>
                    <?php if ($nextLesson['vehicle_plate']): ?>
                    <div class="text-muted">
                        Veículo: <?= htmlspecialchars($nextLesson['vehicle_plate']) ?>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top: var(--spacing-sm); display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
                        <a href="<?= base_path("agenda/{$nextLesson['id']}") ?>" class="btn btn-sm btn-primary">
                            Ver Detalhes
                        </a>
                        <?php if ($nextLesson['status'] === 'agendada'): ?>
                        <?php if ($hasConsecutive): ?>
                        <?php $idsStr = implode(',', array_column(array_filter($consecutiveLessons, fn($l) => ($l['status'] ?? '') !== 'cancelada'), 'id')); ?>
                        <?php if (!empty($idsStr)): ?>
                        <a href="<?= base_path("agenda/iniciar-bloco?ids=" . urlencode($idsStr)) ?>" class="btn btn-sm btn-warning">
                            Iniciar Bloco
                        </a>
                        <?php endif; ?>
                        <?php else: ?>
                        <a href="<?= base_path("agenda/{$nextLesson['id']}/iniciar") ?>" class="btn btn-sm btn-warning">
                            Iniciar Aula
                        </a>
                        <?php endif; ?>
                        <?php elseif ($nextLesson['status'] === 'em_andamento' && $hasConsecutive): ?>
                        <?php $idsStr = implode(',', array_column(array_filter($consecutiveLessons, fn($l) => ($l['status'] ?? '') === 'em_andamento'), 'id')); ?>
                        <?php if (!empty($idsStr)): ?>
                        <a href="<?= base_path("agenda/finalizar-bloco?ids=" . urlencode($idsStr)) ?>" class="btn btn-sm btn-success">
                            Finalizar Bloco
                        </a>
                        <?php endif; ?>
                        <?php elseif ($nextLesson['status'] === 'em_andamento'): ?>
                        <a href="<?= base_path("agenda/{$nextLesson['id']}/concluir") ?>" class="btn btn-sm btn-success">
                            Finalizar Aula
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted">Nenhuma aula agendada no momento.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Aulas de Hoje -->
    <div class="card" style="margin-bottom: var(--spacing-md);">
        <div class="card-header">
            <h3 style="margin: 0;">Aulas de Hoje</h3>
        </div>
        <div class="card-body">
            <?php if ($totalToday > 0): ?>
                <div style="margin-bottom: var(--spacing-md); padding: var(--spacing-sm); background: var(--color-bg-secondary, #f5f5f5); border-radius: var(--radius-sm, 4px);">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--spacing-sm);">
                        <div>
                            <strong>Você tem <?= $totalToday ?> aula<?= $totalToday > 1 ? 's' : '' ?> hoje</strong>
                        </div>
                        <div style="display: flex; gap: var(--spacing-md); font-size: 0.875rem;">
                            <span style="color: var(--color-success, #10b981);">
                                ✓ <?= $completedToday ?> concluída<?= $completedToday > 1 ? 's' : '' ?>
                            </span>
                            <span style="color: var(--color-warning, #f59e0b);">
                                ⏳ <?= $pendingToday ?> pendente<?= $pendingToday > 1 ? 's' : '' ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                    <?php foreach ($todayLessons as $group): ?>
                        <?php
                        // Dados do grupo (pode ser uma única aula ou grupo de consecutivas)
                        $firstLesson = $group['first_lesson'];
                        $lessonDate = new \DateTime("{$group['scheduled_date']} {$group['start_time']}");
                        $endTime = clone $lessonDate;
                        $endTime->modify("+{$group['total_duration']} minutes");
                        $isGroup = $group['is_group'];
                        $lessonCount = count($group['lessons']);
                        
                        // Formatar duração
                        $hours = floor($group['total_duration'] / 60);
                        $mins = $group['total_duration'] % 60;
                        $durationText = $hours > 0 
                            ? ($mins > 0 ? "{$hours}h{$mins}" : "{$hours}h") 
                            : "{$mins}min";
                        
                        $statusConfig = [
                            'agendada' => ['label' => 'Agendada', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
                            'em_andamento' => ['label' => 'Em Andamento', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
                            'concluida' => ['label' => 'Concluída', 'color' => '#10b981', 'bg' => '#d1fae5'],
                        ];
                        $status = $statusConfig[$group['status']] ?? ['label' => $group['status'], 'color' => '#666', 'bg' => '#f3f4f6'];
                        ?>
                        <div style="display: block; padding: var(--spacing-md); border: 1px solid var(--color-border, #e0e0e0); border-radius: var(--radius-md, 8px); background: white; <?= $isGroup ? 'border-left: 3px solid #3b82f6;' : '' ?>">
                            <div style="display: grid; gap: var(--spacing-xs);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: var(--spacing-sm); flex-wrap: wrap;">
                                    <div style="flex: 1; min-width: 200px;">
                                        <div style="font-weight: 600; font-size: 1rem; margin-bottom: var(--spacing-xs);">
                                            <?= $lessonDate->format('H:i') ?> – <?= $endTime->format('H:i') ?>
                                            <?php if ($isGroup): ?>
                                            <span style="margin-left: var(--spacing-xs); font-size: 0.75rem; color: #1e40af; font-weight: 500;">
                                                (<?= $durationText ?>)
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="color: var(--color-text-muted, #666); font-size: 0.875rem;">
                                            <?= htmlspecialchars($group['student_name']) ?>
                                        </div>
                                        <?php if ($isGroup): ?>
                                        <div style="margin-top: 6px; font-size: 0.75rem; color: var(--color-text-muted, #666);">
                                            <?php foreach ($group['lessons'] as $idx => $l): ?>
                                            <span style="margin-right: 8px;">Aula <?= $idx + 1 ?>: <?= ($l['status'] ?? '') === 'cancelada' ? 'cancelada' : ($l['status'] ?? 'agendada'); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; gap: var(--spacing-xs); flex-wrap: wrap;">
                                        <?php if ($isGroup): ?>
                                        <span style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: #dbeafe; color: #1e40af;">
                                            <?= $lessonCount ?> aulas
                                        </span>
                                        <?php endif; ?>
                                        <span style="display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: <?= $status['bg'] ?>; color: <?= $status['color'] ?>;">
                                            <?= $status['label'] ?>
                                        </span>
                                    </div>
                                </div>
                                <div style="display: flex; gap: var(--spacing-sm); margin-top: var(--spacing-sm); flex-wrap: wrap;">
                                    <a href="<?= base_path("agenda/{$firstLesson['id']}") ?>" class="btn btn-sm btn-outline">Ver Detalhes</a>
                                    <?php if ($group['status'] === 'agendada' && $isGroup): ?>
                                    <?php $idsStr = implode(',', array_column(array_filter($group['lessons'], fn($l) => ($l['status'] ?? '') !== 'cancelada'), 'id')); ?>
                                    <?php if (!empty($idsStr)): ?>
                                    <a href="<?= base_path("agenda/iniciar-bloco?ids=" . urlencode($idsStr)) ?>" class="btn btn-sm btn-warning">Iniciar Bloco</a>
                                    <?php endif; ?>
                                    <?php elseif ($group['status'] === 'agendada' && !$isGroup): ?>
                                    <a href="<?= base_path("agenda/{$firstLesson['id']}/iniciar") ?>" class="btn btn-sm btn-warning">Iniciar Aula</a>
                                    <?php endif; ?>
                                    <?php if ($group['status'] === 'em_andamento' && $isGroup): ?>
                                    <?php $idsStr = implode(',', array_column(array_filter($group['lessons'], fn($l) => ($l['status'] ?? '') === 'em_andamento'), 'id')); ?>
                                    <?php if (!empty($idsStr)): ?>
                                    <a href="<?= base_path("agenda/finalizar-bloco?ids=" . urlencode($idsStr)) ?>" class="btn btn-sm btn-success">Finalizar Bloco</a>
                                    <?php endif; ?>
                                    <?php elseif ($group['status'] === 'em_andamento' && !$isGroup): ?>
                                    <a href="<?= base_path("agenda/{$firstLesson['id']}/concluir") ?>" class="btn btn-sm btn-success">Finalizar Aula</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">Você não tem aulas agendadas para hoje (<?= (new \DateTime())->format('d/m/Y') ?>).</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
