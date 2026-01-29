<?php
use App\Helpers\TheoryHelper;
?>
<div class="page-header">
    <div>
        <h1><?= $session ? 'Editar' : 'Nova' ?> Aula Teórica</h1>
        <p class="text-muted">Turma: <?= htmlspecialchars($class['name'] ?: $class['course_name']) ?></p>
    </div>
    <a href="<?= base_path("turmas-teoricas/{$class['id']}") ?>" class="btn btn-outline">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Voltar
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= base_path($session ? "turmas-teoricas/{$class['id']}/sessoes/{$session['id']}/atualizar" : "turmas-teoricas/{$class['id']}/sessoes/criar") ?>" id="session-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label class="form-label" for="discipline_id">Disciplina *</label>
                <select id="discipline_id" name="discipline_id" class="form-input" required <?= $session ? 'disabled' : '' ?> onchange="updateDisciplineContext(this.value)">
                    <option value="">Selecione uma disciplina</option>
                    <?php foreach ($course['disciplines'] ?? [] as $cd): ?>
                        <?php
                        $disciplineId = $cd['discipline_id'];
                        $stats = $sessionsByDiscipline[$disciplineId] ?? null;
                        $lessonMinutes = $cd['lesson_minutes'] ?? $cd['default_lesson_minutes'] ?? 50;
                        
                        // Calcular aulas previstas
                        $minutes = $cd['minutes'] ?? $cd['default_minutes'] ?? 0;
                        $lessonsCount = $cd['lessons_count'] ?? ($minutes > 0 ? ceil($minutes / $lessonMinutes) : 0);
                        
                        // Formatar texto: apenas "X aulas" (sem minutos)
                        $displayText = htmlspecialchars($cd['discipline_name']);
                        if ($lessonsCount > 0) {
                            $displayText .= " — {$lessonsCount} " . ($lessonsCount == 1 ? 'aula' : 'aulas');
                        }
                        ?>
                        <option 
                            value="<?= $disciplineId ?>" 
                            <?= ($session && $session['discipline_id'] == $disciplineId) ? 'selected' : '' ?>
                            data-lesson-minutes="<?= $lessonMinutes ?>"
                            data-stats='<?= json_encode($stats) ?>'
                            data-minutes="<?= $minutes ?>"
                            data-lessons-count="<?= $lessonsCount ?>"
                        >
                            <?= $displayText ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($session): ?>
                <input type="hidden" name="discipline_id" value="<?= $session['discipline_id'] ?>">
                <small class="form-hint">A disciplina não pode ser alterada após a criação da aula</small>
                <?php endif; ?>
            </div>

            <!-- Card de Status da Disciplina na Turma -->
            <div id="discipline-context" class="card" style="display: none; margin-bottom: var(--spacing-md); background: var(--color-bg-light); border: 1px solid var(--color-border);">
                <div class="card-body" style="padding: var(--spacing-md);">
                    <h4 style="margin: 0 0 var(--spacing-sm) 0; font-size: var(--font-size-base);">
                        Status da Disciplina na Turma
                        <span id="context-info-icon" style="margin-left: var(--spacing-xs); cursor: help; opacity: 0.6;" title="" onmouseover="showContextTooltip(event)" onmouseout="hideContextTooltip()">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                        <div>
                            <div style="font-size: var(--font-size-sm); color: var(--color-text-muted); margin-bottom: var(--spacing-xs);">Previsto no Curso</div>
                            <div style="font-size: var(--font-size-lg); font-weight: var(--font-weight-semibold);" id="context-planned">-</div>
                        </div>
                        <div>
                            <div style="font-size: var(--font-size-sm); color: var(--color-text-muted); margin-bottom: var(--spacing-xs);">Já Agendadas</div>
                            <div style="font-size: var(--font-size-lg); font-weight: var(--font-weight-semibold);" id="context-scheduled">-</div>
                        </div>
                        <div>
                            <div style="font-size: var(--font-size-sm); color: var(--color-text-muted); margin-bottom: var(--spacing-xs);">Pendentes</div>
                            <div style="font-size: var(--font-size-lg); font-weight: var(--font-weight-semibold); color: var(--color-primary);" id="context-remaining">-</div>
                        </div>
                    </div>
                    <div id="context-last-session" style="display: none; padding: var(--spacing-sm); background: var(--color-bg, #fff); border-radius: var(--radius-md); margin-bottom: var(--spacing-sm);">
                        <div style="font-size: var(--font-size-sm); color: var(--color-text-muted);">Última aula agendada:</div>
                        <div style="font-size: var(--font-size-base); font-weight: var(--font-weight-semibold);" id="context-last-session-date">-</div>
                    </div>
                    <div id="context-sessions-link" style="display: none;">
                        <a href="#" id="view-sessions-link" style="font-size: var(--font-size-sm); color: var(--color-primary); text-decoration: none;">
                            Ver todas as aulas agendadas →
                        </a>
                    </div>
                    <div id="context-warning" style="display: none; padding: var(--spacing-sm); background: var(--color-warning-light, #fff3cd); border: 1px solid var(--color-warning, #ffc107); border-radius: var(--radius-md); margin-top: var(--spacing-sm); color: var(--color-warning-dark, #856404);">
                        <strong>⚠️ Atenção:</strong> <span id="context-warning-text"></span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="starts_at">Data/Hora de Início *</label>
                <input 
                    type="datetime-local" 
                    id="starts_at" 
                    name="starts_at" 
                    class="form-input" 
                    value="<?= $session ? date('Y-m-d\TH:i', strtotime($session['starts_at'])) : '' ?>"
                    required
                    onchange="validateLessonsCount(); calculateEndTime();"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="lessons_count">Quantidade de aulas *</label>
                <input 
                    type="number" 
                    id="lessons_count" 
                    name="lessons_count" 
                    class="form-input" 
                    value="<?php
                        if ($session && $session['ends_at'] && $session['starts_at']) {
                            $durationMinutes = (strtotime($session['ends_at']) - strtotime($session['starts_at'])) / 60;
                            $lessonMinutes = 50; // Default
                            echo max(1, round($durationMinutes / $lessonMinutes));
                        } else {
                            echo '1';
                        }
                    ?>"
                    min="1"
                    max="999"
                    required
                    onchange="validateLessonsCount(); calculateEndTime();"
                >
                <small class="form-hint" id="lessons-hint">Número de aulas consecutivas</small>
                <div id="lessons-error" style="display: none; margin-top: var(--spacing-xs); padding: var(--spacing-xs); background: var(--color-danger-light, #fee); border: 1px solid var(--color-danger); border-radius: var(--radius-sm); color: var(--color-danger); font-size: var(--font-size-sm);"></div>
            </div>

            <div class="form-group">
                <label class="form-label" for="lesson_minutes">Minutos por Aula *</label>
                <input 
                    type="number" 
                    id="lesson_minutes" 
                    name="lesson_minutes" 
                    class="form-input" 
                    value="<?php
                        if ($session && $session['ends_at'] && $session['starts_at']) {
                            $durationMinutes = (strtotime($session['ends_at']) - strtotime($session['starts_at'])) / 60;
                            $lessonsCount = max(1, round($durationMinutes / 50));
                            echo round($durationMinutes / $lessonsCount);
                        } else {
                            echo '50';
                        }
                    ?>"
                    min="1"
                    max="180"
                    required
                    onchange="calculateEndTime()"
                >
                <small class="form-hint">Duração de cada aula (hora-aula padrão: 50 min)</small>
            </div>

            <div class="form-group">
                <label class="form-label">Data/Hora de Término (calculado)</label>
                <input 
                    type="datetime-local" 
                    id="ends_at_display" 
                    class="form-input" 
                    readonly
                    style="background: var(--color-bg-light); cursor: not-allowed;"
                >
                <input type="hidden" id="ends_at" name="ends_at" value="">
                <small class="form-hint">Calculado automaticamente: Início + (Quantidade de Aulas × Minutos por Aula)</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="location">Local</label>
                <?php 
                    $locationOptions = ['Online', 'Sala de aula', 'Online + Sala de aula'];
                    $currentLocation = $session['location'] ?? '';
                ?>
                <select id="location" name="location" class="form-input">
                    <option value="">Selecione o local</option>
                    <?php foreach ($locationOptions as $option): ?>
                        <option value="<?= htmlspecialchars($option) ?>" <?= $currentLocation === $option ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option) ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($currentLocation && !in_array($currentLocation, $locationOptions)): ?>
                        <!-- Valor legado (texto livre anterior) -->
                        <option value="<?= htmlspecialchars($currentLocation) ?>" selected>
                            <?= htmlspecialchars($currentLocation) ?> (anterior)
                        </option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $session ? 'Atualizar' : 'Criar' ?> Aula
                </button>
                <a href="<?= base_path("turmas-teoricas/{$class['id']}") ?>" class="btn btn-outline">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
let disciplineStats = {};

function updateDisciplineContext(disciplineId) {
    const select = document.getElementById('discipline_id');
    const option = select.options[select.selectedIndex];
    
    if (!disciplineId || !option) {
        document.getElementById('discipline-context').style.display = 'none';
        return;
    }
    
    const statsJson = option.getAttribute('data-stats');
    if (!statsJson || statsJson === 'null') {
        document.getElementById('discipline-context').style.display = 'none';
        return;
    }
    
    const stats = JSON.parse(statsJson);
    disciplineStats[disciplineId] = stats;
    
    // Atualizar contexto (apenas aulas, sem minutos)
    let planned;
    if (stats.lessons_planned > 0) {
        planned = `${stats.lessons_planned} ${stats.lessons_planned === 1 ? 'aula' : 'aulas'}`;
    } else {
        planned = 'Não definido no curso';
    }
    document.getElementById('context-planned').textContent = planned;
    document.getElementById('context-scheduled').textContent = `${stats.lessons_scheduled} ${stats.lessons_scheduled === 1 ? 'aula' : 'aulas'}`;
    document.getElementById('context-remaining').textContent = `${stats.lessons_remaining} ${stats.lessons_remaining === 1 ? 'aula' : 'aulas'}`;
    
    // Atualizar última sessão agendada
    const lastSessionDiv = document.getElementById('context-last-session');
    const lastSessionDate = document.getElementById('context-last-session-date');
    if (stats.last_session && stats.last_session.starts_at) {
        const lastDate = new Date(stats.last_session.starts_at);
        const formattedDate = lastDate.toLocaleDateString('pt-BR', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        lastSessionDate.textContent = formattedDate;
        lastSessionDiv.style.display = 'block';
    } else {
        lastSessionDiv.style.display = 'none';
    }
    
    // Mostrar link para ver sessões se houver
    const sessionsLinkDiv = document.getElementById('context-sessions-link');
    const viewSessionsLink = document.getElementById('view-sessions-link');
    if (stats.lessons_scheduled > 0) {
        viewSessionsLink.href = '<?= base_path("turmas-teoricas/{$class['id']}") ?>#sessions';
        sessionsLinkDiv.style.display = 'block';
    } else {
        sessionsLinkDiv.style.display = 'none';
    }
    
    // Atualizar tooltip com detalhes de minutos
    const minutes = parseInt(option.getAttribute('data-minutes')) || 0;
    const optionLessonMinutes = parseInt(option.getAttribute('data-lesson-minutes')) || 50;
    const totalMinutes = minutes || (stats.lessons_planned * optionLessonMinutes);
    
    let tooltipText = '';
    if (stats.lessons_planned > 0) {
        tooltipText = `${stats.lessons_planned} ${stats.lessons_planned === 1 ? 'aula' : 'aulas'}`;
        if (totalMinutes > 0) {
            tooltipText += `\nTotal: ${totalMinutes} min`;
        }
        if (optionLessonMinutes > 0) {
            tooltipText += `\n${optionLessonMinutes} min por aula`;
        }
    } else {
        tooltipText = 'Carga horária não definida';
    }
    
    const infoIcon = document.getElementById('context-info-icon');
    if (infoIcon) {
        infoIcon.setAttribute('title', tooltipText);
    }
    
    // Atualizar max do input de quantidade de aulas
    const lessonsCountInput = document.getElementById('lessons_count');
    if (stats.lessons_remaining > 0) {
        lessonsCountInput.setAttribute('max', stats.lessons_remaining);
        lessonsCountInput.removeAttribute('disabled');
    } else {
        lessonsCountInput.setAttribute('max', '0');
        lessonsCountInput.setAttribute('disabled', 'disabled');
    }
    
    // Atualizar hint e validar
    const lessonsHint = document.getElementById('lessons-hint');
    if (stats.lessons_remaining > 0) {
        lessonsHint.textContent = `Número de aulas consecutivas (máximo: ${stats.lessons_remaining} aulas pendentes)`;
    } else {
        lessonsHint.textContent = 'Número de aulas consecutivas';
    }
    
    // Mostrar aviso se não há aulas pendentes
    const warningDiv = document.getElementById('context-warning');
    const warningText = document.getElementById('context-warning-text');
    if (stats.lessons_remaining === 0 && stats.lessons_planned > 0) {
        warningText.textContent = 'Disciplina já totalmente agendada. Não é possível criar novas sessões.';
        warningDiv.style.display = 'block';
    } else {
        warningDiv.style.display = 'none';
    }
    
    document.getElementById('discipline-context').style.display = 'block';
    
    // Atualizar minutos por aula padrão
    document.getElementById('lesson_minutes').value = optionLessonMinutes;
    
    // Validar quantidade atual
    validateLessonsCount();
    calculateEndTime();
}

function validateLessonsCount() {
    const lessonsCountInput = document.getElementById('lessons_count');
    const lessonsError = document.getElementById('lessons-error');
    const submitBtn = document.querySelector('button[type="submit"]');
    
    const selectedDiscipline = document.getElementById('discipline_id').value;
    if (!selectedDiscipline) {
        lessonsError.style.display = 'none';
        if (submitBtn) submitBtn.disabled = false;
        return;
    }
    
    const select = document.getElementById('discipline_id');
    const option = select.options[select.selectedIndex];
    const statsJson = option.getAttribute('data-stats');
    
    if (!statsJson || statsJson === 'null') {
        lessonsError.style.display = 'none';
        if (submitBtn) submitBtn.disabled = false;
        return;
    }
    
    const stats = JSON.parse(statsJson);
    const lessonsCount = parseInt(lessonsCountInput.value) || 0;
    const maxLessons = stats.lessons_remaining || 0;
    
    if (maxLessons === 0 && stats.lessons_planned > 0) {
        lessonsError.textContent = 'Disciplina já totalmente agendada. Não é possível criar novas aulas.';
        lessonsError.style.display = 'block';
        lessonsCountInput.setAttribute('disabled', 'disabled');
        if (submitBtn) submitBtn.disabled = true;
        return;
    }
    
    if (lessonsCount > maxLessons && maxLessons > 0) {
        lessonsError.textContent = `Você não pode agendar mais de ${maxLessons} ${maxLessons === 1 ? 'aula' : 'aulas'} pendente${maxLessons === 1 ? '' : 's'}.`;
        lessonsError.style.display = 'block';
        if (submitBtn) submitBtn.disabled = true;
        return;
    }
    
    lessonsError.style.display = 'none';
    if (submitBtn) submitBtn.disabled = false;
}

function calculateEndTime() {
    const startsAt = document.getElementById('starts_at').value;
    const lessonsCount = parseInt(document.getElementById('lessons_count').value) || 1;
    const lessonMinutes = parseInt(document.getElementById('lesson_minutes').value) || 50;
    
    if (!startsAt) {
        document.getElementById('ends_at_display').value = '';
        document.getElementById('ends_at').value = '';
        return;
    }
    
    // Calcular término: início + (quantidade de aulas × minutos por aula)
    const startDate = new Date(startsAt);
    const totalMinutes = lessonsCount * lessonMinutes;
    const endDate = new Date(startDate.getTime() + totalMinutes * 60000);
    
    // Formatar para datetime-local (YYYY-MM-DDTHH:mm)
    const year = endDate.getFullYear();
    const month = String(endDate.getMonth() + 1).padStart(2, '0');
    const day = String(endDate.getDate()).padStart(2, '0');
    const hours = String(endDate.getHours()).padStart(2, '0');
    const minutes = String(endDate.getMinutes()).padStart(2, '0');
    
    const endDateTimeLocal = `${year}-${month}-${day}T${hours}:${minutes}`;
    document.getElementById('ends_at_display').value = endDateTimeLocal;
    
    // Formatar para backend (YYYY-MM-DD HH:mm:ss)
    const endDateTime = endDate.toISOString().slice(0, 19).replace('T', ' ');
    document.getElementById('ends_at').value = endDateTime;
}

// Inicializar ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    const disciplineId = document.getElementById('discipline_id').value;
    if (disciplineId) {
        updateDisciplineContext(disciplineId);
    }
    
    // Validar quantidade ao carregar
    validateLessonsCount();
    calculateEndTime();
    
    // Validar antes de submeter
    const form = document.getElementById('session-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            validateLessonsCount();
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && submitBtn.disabled) {
                e.preventDefault();
                return false;
            }
        });
    }
});

function showContextTooltip(event) {
    // Tooltip já é gerenciado pelo atributo title nativo do HTML
    // Esta função pode ser usada para tooltip customizado se necessário no futuro
}

function hideContextTooltip() {
    // Tooltip já é gerenciado pelo atributo title nativo do HTML
}
</script>

<style>
.form-actions {
    display: flex;
    gap: var(--spacing-md);
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--color-border);
}
</style>
