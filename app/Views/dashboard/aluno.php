<?php
use App\Models\Student;
$studentModel = new Student();
?>

<div class="content-header">
    <h1 class="content-title">Meu Progresso</h1>
    <p class="content-subtitle">Bem-vindo, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Aluno') ?>!</p>
</div>

<?php if (!$student): ?>
    <div class="card">
        <div class="card-body text-center" style="padding: 60px 20px;">
            <p class="text-muted">Seu cadastro ainda não está vinculado ao sistema. Entre em contato com a secretaria.</p>
        </div>
    </div>
<?php else: ?>
    <?php
    $fullName = $studentModel->getFullName($student);
    $studentStepsMap = [];
    foreach ($studentSteps ?? [] as $ss) {
        $studentStepsMap[$ss['step_id']] = $ss;
    }
    ?>

    <!-- Status Geral -->
    <div class="card" style="margin-bottom: var(--spacing-md);">
        <div class="card-body">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--spacing-md);">
                <div>
                    <label class="form-label" style="margin-bottom: var(--spacing-xs);">Status do Processo</label>
                    <div style="font-size: var(--font-size-lg); font-weight: var(--font-weight-semibold);">
                        <?= htmlspecialchars($statusGeral) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Próxima Aula / Aula Atual -->
    <?php 
    $lessonStatus = $nextLesson['status'] ?? '';
    $isInProgress = $lessonStatus === 'em_andamento';
    $cardTitle = $isInProgress ? 'Aula em Andamento' : 'Próxima Aula';
    $cardBorderColor = $isInProgress ? 'var(--color-warning, #f59e0b)' : 'transparent';
    ?>
    <div class="card" style="margin-bottom: var(--spacing-md); border-left: 4px solid <?= $cardBorderColor ?>;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;"><?= $cardTitle ?></h3>
            <?php if ($nextLesson && $isInProgress): ?>
            <span style="background: var(--color-warning, #f59e0b); color: white; padding: 4px 10px; border-radius: 12px; font-size: var(--font-size-sm); font-weight: 600; animation: pulse 2s infinite;">
                Em andamento
            </span>
            <?php elseif ($nextLesson): ?>
            <span style="background: var(--color-primary, #3b82f6); color: white; padding: 4px 10px; border-radius: 12px; font-size: var(--font-size-sm);">
                Agendada
            </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($nextLesson): ?>
                <?php
                $lessonDate = new \DateTime("{$nextLesson['scheduled_date']} {$nextLesson['scheduled_time']}");
                $endTime = clone $lessonDate;
                $endTime->modify("+{$nextLesson['duration_minutes']} minutes");
                $now = new \DateTime();
                ?>
                <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                    <?php if ($isInProgress): ?>
                    <div style="background: var(--color-warning-light, #fef3c7); padding: var(--spacing-sm); border-radius: var(--radius-md, 8px); margin-bottom: var(--spacing-xs);">
                        <strong style="color: var(--color-warning-dark, #92400e);">
                            Sua aula está acontecendo agora!
                        </strong>
                        <p style="margin: var(--spacing-xs) 0 0 0; color: var(--color-warning-dark, #92400e); font-size: var(--font-size-sm);">
                            Iniciada às <?= $nextLesson['started_at'] ? (new \DateTime($nextLesson['started_at']))->format('H:i') : $lessonDate->format('H:i') ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <strong style="font-size: var(--font-size-lg);">
                            <?= $lessonDate->format('d/m/Y') ?> às <?= $lessonDate->format('H:i') ?>
                        </strong>
                        <span style="color: var(--color-text-muted); font-size: var(--font-size-sm);">
                            (<?= $nextLesson['duration_minutes'] ?> min)
                        </span>
                    </div>
                    <?php if ($nextLesson['instructor_name']): ?>
                    <div class="text-muted">
                        Instrutor: <?= htmlspecialchars($nextLesson['instructor_name']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($nextLesson['vehicle_plate']): ?>
                    <div class="text-muted">
                        Veículo: <?= htmlspecialchars($nextLesson['vehicle_plate']) ?>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top: var(--spacing-sm); display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
                        <a href="<?= base_path("agenda/{$nextLesson['id']}?from=dashboard") ?>" class="btn btn-sm btn-outline">
                            Ver detalhes
                        </a>
                        <?php
                        $isFuture = $lessonDate > $now;
                        $isScheduled = $lessonStatus === 'agendada';
                        $canRequestReschedule = $isFuture && $isScheduled && !($hasPendingRequest ?? false);
                        ?>
                        <?php if ($canRequestReschedule): ?>
                        <button type="button" class="btn btn-sm btn-primary" onclick="showRescheduleModal(<?= $nextLesson['id'] ?>)">
                            Solicitar reagendamento
                        </button>
                        <?php elseif ($hasPendingRequest ?? false): ?>
                        <span class="text-muted" style="font-size: var(--font-size-sm); align-self: center;">
                            Solicitação pendente
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted">Nenhuma aula agendada no momento.</p>
                <p class="text-muted" style="font-size: var(--font-size-sm); margin-top: var(--spacing-xs);">
                    Aguarde contato da secretaria ou consulte sua agenda.
                </p>
                <div style="margin-top: var(--spacing-md);">
                    <a href="<?= base_path('agenda') ?>" class="btn btn-sm btn-outline">
                        Ver minha agenda
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    </style>

    <!-- Minhas Aulas Práticas -->
    <?php 
    $hasAnyLesson = !empty($upcomingLessons) || !empty($inProgressLessons) || !empty($recentCompletedLessons);
    ?>
    <?php if ($hasAnyLesson): ?>
    <div class="card" style="margin-bottom: var(--spacing-md);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">Minhas Aulas Práticas</h3>
            <a href="<?= base_path('agenda') ?>" class="btn btn-sm btn-outline">
                Ver agenda completa
            </a>
        </div>
        <div class="card-body">
            <?php 
            // Combinar todas as aulas para exibição
            $allDisplayLessons = [];
            
            // Adicionar em andamento primeiro
            foreach ($inProgressLessons as $l) {
                $l['_display_status'] = 'em_andamento';
                $l['_display_order'] = 0;
                $allDisplayLessons[] = $l;
            }
            
            // Adicionar próximas (máximo 3)
            $count = 0;
            foreach ($upcomingLessons as $l) {
                if ($count >= 3) break;
                $l['_display_status'] = 'agendada';
                $l['_display_order'] = 1;
                $allDisplayLessons[] = $l;
                $count++;
            }
            
            // Adicionar concluídas recentes (máximo 2)
            $count = 0;
            foreach ($recentCompletedLessons as $l) {
                if ($count >= 2) break;
                $l['_display_status'] = 'concluida';
                $l['_display_order'] = 2;
                $allDisplayLessons[] = $l;
                $count++;
            }
            
            // Ordenar: em_andamento > agendada > concluída
            usort($allDisplayLessons, function($a, $b) {
                if ($a['_display_order'] !== $b['_display_order']) {
                    return $a['_display_order'] - $b['_display_order'];
                }
                // Mesmo status: ordenar por data
                return strcmp($a['scheduled_date'] . $a['scheduled_time'], $b['scheduled_date'] . $b['scheduled_time']);
            });
            ?>
            
            <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                <?php foreach ($allDisplayLessons as $lesson): ?>
                <?php
                $displayStatus = $lesson['_display_status'];
                $lessonDateTime = new \DateTime("{$lesson['scheduled_date']} {$lesson['scheduled_time']}");
                
                // Definir cores por status (sem emojis)
                $statusConfig = [
                    'em_andamento' => ['bg' => '#fef3c7', 'border' => '#f59e0b', 'label' => 'Em andamento'],
                    'agendada' => ['bg' => '#dbeafe', 'border' => '#3b82f6', 'label' => 'Agendada'],
                    'concluida' => ['bg' => '#d1fae5', 'border' => '#10b981', 'label' => 'Concluída'],
                ];
                $config = $statusConfig[$displayStatus] ?? $statusConfig['agendada'];
                ?>
                <a href="<?= base_path("agenda/{$lesson['id']}") ?>" 
                   style="display: block; padding: var(--spacing-sm); background: <?= $config['bg'] ?>; border-left: 4px solid <?= $config['border'] ?>; border-radius: var(--radius-sm, 6px); text-decoration: none; color: inherit; transition: transform 0.2s;"
                   onmouseover="this.style.transform='translateX(4px)'" 
                   onmouseout="this.style.transform='translateX(0)'">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--spacing-xs);">
                        <div>
                            <span style="font-weight: 600;">
                                <?= $lessonDateTime->format('d/m/Y') ?> às <?= $lessonDateTime->format('H:i') ?>
                            </span>
                            <span style="font-size: var(--font-size-sm); color: var(--color-text-muted); margin-left: var(--spacing-xs);">
                                (<?= $lesson['duration_minutes'] ?? 50 ?> min)
                            </span>
                        </div>
                        <span style="font-size: var(--font-size-xs); padding: 2px 8px; border-radius: 10px; background: <?= $config['border'] ?>; color: white;">
                            <?= $config['label'] ?>
                        </span>
                    </div>
                    <?php if ($lesson['instructor_name'] || $lesson['vehicle_plate']): ?>
                    <div style="font-size: var(--font-size-sm); color: var(--color-text-muted); margin-top: 4px;">
                        <?php if ($lesson['instructor_name']): ?>
                        Instrutor: <?= htmlspecialchars($lesson['instructor_name']) ?>
                        <?php endif; ?>
                        <?php if ($lesson['instructor_name'] && $lesson['vehicle_plate']): ?> • <?php endif; ?>
                        <?php if ($lesson['vehicle_plate']): ?>
                        Veículo: <?= htmlspecialchars($lesson['vehicle_plate']) ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($upcomingLessons) > 3 || count($recentCompletedLessons) > 2): ?>
            <div style="margin-top: var(--spacing-md); text-align: center;">
                <a href="<?= base_path('agenda') ?>" class="btn btn-sm btn-primary">
                    Ver todas as aulas
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Curso Teórico (Detalhes) -->
    <?php if (!empty($theoryClass) && !empty($theoryProgress)): ?>
    <div class="card" style="margin-bottom: var(--spacing-md);">
        <div class="card-header">
            <h3 style="margin: 0;">Curso Teórico</h3>
        </div>
        <div class="card-body">
            <div style="margin-bottom: var(--spacing-md);">
                <strong><?= htmlspecialchars($theoryClass['course_name']) ?></strong>
                <?php if ($theoryClass['name']): ?>
                    <div class="text-muted" style="font-size: var(--font-size-sm); margin-top: var(--spacing-xs);">
                        Turma: <?= htmlspecialchars($theoryClass['name']) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-bottom: var(--spacing-md);">
                <label class="form-label" style="margin-bottom: var(--spacing-xs);">Progresso</label>
                <div style="background: var(--color-bg-light); border-radius: var(--radius-md); padding: var(--spacing-xs);">
                    <div style="background: var(--color-primary); height: 24px; border-radius: var(--radius-sm); width: <?= $theoryProgress['progress_percent'] ?>%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold);">
                        <?= $theoryProgress['progress_percent'] ?>%
                    </div>
                </div>
                <div style="margin-top: var(--spacing-xs); font-size: var(--font-size-sm); color: var(--color-text-muted);">
                    <?= $theoryProgress['attended_sessions'] ?> de <?= $theoryProgress['total_sessions'] ?> sessões concluídas
                </div>
            </div>
            
            <?php if ($theoryProgress['is_completed']): ?>
            <div style="padding: var(--spacing-sm); background: var(--color-success-light, #d1fae5); border: 1px solid var(--color-success); border-radius: var(--radius-md); color: var(--color-success); font-weight: var(--font-weight-semibold);">
                ✅ Curso Teórico Concluído!
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Progresso (Etapas) -->
    <?php if (!empty($activeEnrollment) && !empty($steps)): ?>
    <div class="card" style="margin-bottom: var(--spacing-md);">
        <div class="card-header">
            <h3 style="margin: 0;">Progresso da CNH</h3>
        </div>
        <div class="card-body">
            <div class="timeline">
                <?php foreach ($steps as $step): ?>
                <?php 
                $studentStep = $studentStepsMap[$step['id']] ?? null;
                $isCompleted = $studentStep && $studentStep['status'] === 'concluida';
                $isTheoryStep = $step['code'] === 'CURSO_TEORICO';
                ?>
                <div class="timeline-item <?= $isCompleted ? 'completed' : '' ?>">
                    <div class="timeline-marker">
                        <?php if ($isCompleted): ?>
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        <?php else: ?>
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="timeline-content">
                        <div>
                            <h4 style="margin: 0 0 var(--spacing-xs) 0; font-size: var(--font-size-base);">
                                <?= htmlspecialchars($step['name']) ?>
                                <?php if ($isTheoryStep && !empty($theoryProgress)): ?>
                                    <span style="font-size: var(--font-size-sm); color: var(--color-text-muted); font-weight: normal;">
                                        (<?= $theoryProgress['progress_percent'] ?>%)
                                    </span>
                                <?php endif; ?>
                            </h4>
                            <?php if ($step['description']): ?>
                            <p class="text-muted" style="margin: 0; font-size: var(--font-size-sm);">
                                <?= htmlspecialchars($step['description']) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Situação Financeira -->
    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">Situação Financeira</h3>
        </div>
        <div class="card-body">
            <?php if ($hasPending): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                    <div>
                        <label class="form-label" style="margin-bottom: var(--spacing-xs); font-size: var(--font-size-sm);">Em aberto</label>
                        <div style="color: var(--color-danger); font-weight: var(--font-weight-semibold); font-size: var(--font-size-lg);">
                            R$ <?= number_format($totalDebt, 2, ',', '.') ?>
                        </div>
                    </div>
                    <?php if (!empty($nextDueDate)): ?>
                    <div>
                        <label class="form-label" style="margin-bottom: var(--spacing-xs); font-size: var(--font-size-sm);">Próximo vencimento</label>
                        <div style="font-weight: var(--font-weight-semibold); font-size: var(--font-size-lg);">
                            <?= date('d/m/Y', strtotime($nextDueDate)) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($overdueCount > 0): ?>
                    <div>
                        <label class="form-label" style="margin-bottom: var(--spacing-xs); font-size: var(--font-size-sm);">Parcelas em atraso</label>
                        <div style="color: var(--color-danger); font-weight: var(--font-weight-semibold); font-size: var(--font-size-lg);">
                            <?= $overdueCount ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <p class="text-muted" style="font-size: var(--font-size-sm); margin-bottom: var(--spacing-md);">
                    Entre em contato com a secretaria para regularizar.
                </p>
            <?php else: ?>
                <div style="color: var(--color-success); font-weight: var(--font-weight-semibold); margin-bottom: var(--spacing-md);">
                    ✅ Sem pendências
                </div>
            <?php endif; ?>
            <div style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
                <a href="<?= base_path('financeiro') ?>" class="btn btn-sm btn-outline">
                    Ver detalhes financeiros
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.timeline {
    position: relative;
    padding-left: var(--spacing-lg);
}

.timeline-item {
    position: relative;
    padding-bottom: var(--spacing-lg);
    padding-left: var(--spacing-lg);
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 9px;
    top: 32px;
    bottom: -var(--spacing-lg);
    width: 2px;
    background: var(--color-border);
}

.timeline-item.completed:not(:last-child)::before {
    background: var(--color-success);
}

.timeline-marker {
    position: absolute;
    left: 0;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--color-bg);
    border: 2px solid var(--color-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
}

.timeline-item.completed .timeline-marker {
    background: var(--color-success);
    border-color: var(--color-success);
    color: white;
}

.timeline-content {
    min-height: 40px;
}
</style>

<!-- Modal de Solicitação de Reagendamento -->
<div id="rescheduleModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: var(--spacing-md);">
    <div class="card" style="max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto;">
        <div class="card-header">
            <h3 style="margin: 0;">Solicitar Reagendamento</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="rescheduleForm">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="form-group">
                    <label class="form-label">Motivo <span style="color: var(--color-danger);">*</span></label>
                    <select name="reason" class="form-input" required>
                        <option value="">Selecione um motivo</option>
                        <option value="imprevisto">Imprevisto</option>
                        <option value="trabalho">Trabalho</option>
                        <option value="saude">Saúde</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Observação <small style="color: var(--color-text-muted, #666);">(opcional)</small></label>
                    <textarea name="message" class="form-input" rows="4" placeholder="Informe detalhes adicionais, se necessário..."></textarea>
                </div>
                <div style="display: flex; gap: var(--spacing-sm); justify-content: flex-end; margin-top: var(--spacing-md);">
                    <button type="button" class="btn btn-outline" onclick="hideRescheduleModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="submitRescheduleBtn">Enviar Solicitação</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentLessonId = null;

function showRescheduleModal(lessonId) {
    currentLessonId = lessonId;
    const form = document.getElementById('rescheduleForm');
    form.action = '<?= base_path("agenda/") ?>' + lessonId + '/solicitar-reagendamento';
    document.getElementById('rescheduleModal').style.display = 'flex';
}

function hideRescheduleModal() {
    document.getElementById('rescheduleModal').style.display = 'none';
    const form = document.getElementById('rescheduleForm');
    form.reset();
    currentLessonId = null;
}

// Fechar modal ao clicar fora
document.getElementById('rescheduleModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideRescheduleModal();
    }
});

// Prevenir duplo submit
document.getElementById('rescheduleForm')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('submitRescheduleBtn');
    if (btn.disabled) {
        e.preventDefault();
        return false;
    }
    btn.disabled = true;
    btn.textContent = 'Enviando...';
});
</script>
