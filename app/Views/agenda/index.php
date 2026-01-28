<?php
$isInstrutor = ($isInstrutor ?? false) || ($_SESSION['current_role'] ?? '') === 'INSTRUTOR';
$isAdmin = !$isAluno && !$isInstrutor;
?>
<!-- Header compacto: título + botão na mesma linha -->
<div class="page-header" style="padding: var(--spacing-sm) 0; margin-bottom: var(--spacing-sm);">
    <div class="page-header-content" style="display: flex; align-items: center; justify-content: space-between; gap: var(--spacing-md);">
        <div style="display: flex; align-items: baseline; gap: var(--spacing-md);">
            <h1 style="margin: 0; font-size: 1.5rem;"><?= ($isAluno || $isInstrutor) ? 'Minha Agenda' : 'Agenda' ?></h1>
            <span class="text-muted" style="font-size: 0.8rem;"><?= ($isAluno || $isInstrutor) ? 'Suas aulas agendadas' : 'Agendamento e controle de aulas' ?></span>
        </div>
        <?php if ($isAdmin): ?>
        <a href="<?= base_path('agenda/novo') ?>" class="btn btn-primary" style="padding: 6px 14px; font-size: 0.85rem;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nova Aula
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros e Controles (compactado) -->
<?php if ($isAdmin): ?>
<?php 
// Detectar se há filtros extras ativos
$hasExtraFilters = !empty($filters['instructor_id']) || !empty($filters['vehicle_id']) || !empty($filters['status']) || ($showCanceled ?? false);
?>
<div class="card" style="margin-bottom: var(--spacing-sm);">
    <div class="card-body" style="padding: var(--spacing-sm) var(--spacing-md);">
        <form method="GET" action="<?= base_path('agenda') ?>" id="filtersForm">
            <!-- Linha principal: filtros essenciais + navegação -->
            <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-sm); align-items: flex-end;">
                <!-- Visualização -->
                <div class="form-group" style="margin-bottom: 0; min-width: 120px;">
                    <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">Visualização</label>
                    <select name="view" class="form-input" style="padding: 6px 10px; font-size: 0.875rem;" onchange="this.form.submit()">
                        <option value="list" <?= $viewType === 'list' ? 'selected' : '' ?>>Lista</option>
                        <option value="week" <?= $viewType === 'week' ? 'selected' : '' ?>>Semanal</option>
                        <option value="day" <?= $viewType === 'day' ? 'selected' : '' ?>>Diária</option>
                    </select>
                </div>
                
                <!-- Data -->
                <div class="form-group" style="margin-bottom: 0; min-width: 140px;">
                    <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">Data</label>
                    <input type="date" name="date" class="form-input" style="padding: 6px 10px; font-size: 0.875rem;" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">
                </div>
                
                <!-- Tipo -->
                <div class="form-group" style="margin-bottom: 0; min-width: 100px;">
                    <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">Tipo</label>
                    <select name="type" class="form-input" style="padding: 6px 10px; font-size: 0.875rem;" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        <option value="pratica" <?= ($filters['type'] ?? '') === 'pratica' ? 'selected' : '' ?>>Prática</option>
                        <option value="teoria" <?= ($filters['type'] ?? '') === 'teoria' ? 'selected' : '' ?>>Teórica</option>
                    </select>
                </div>
                
                <!-- Navegação de Data (inline) -->
                <div style="display: flex; gap: 4px; align-items: center; margin-left: auto;">
                    <button type="button" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;" onclick="navigateDate(-1)" title="<?= $viewType === 'week' ? 'Semana Anterior' : 'Dia Anterior' ?>">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <button type="button" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;" onclick="navigateDate(0)">Hoje</button>
                    <button type="button" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;" onclick="navigateDate(1)" title="<?= $viewType === 'week' ? 'Próxima Semana' : 'Próximo Dia' ?>">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Botão Mais Filtros -->
                <button type="button" class="btn <?= $hasExtraFilters ? 'btn-primary' : 'btn-outline' ?>" style="padding: 6px 12px; font-size: 0.8rem;" onclick="toggleExtraFilters()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filtros<?= $hasExtraFilters ? ' (ativos)' : '' ?>
                </button>
            </div>
            
            <!-- Filtros extras (colapsável) -->
            <div id="extraFilters" style="display: <?= $hasExtraFilters ? 'block' : 'none' ?>; margin-top: var(--spacing-sm); padding-top: var(--spacing-sm); border-top: 1px solid var(--color-border, #e0e0e0);">
                <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-sm); align-items: flex-end;">
                    <!-- Instrutor -->
                    <div class="form-group" style="margin-bottom: 0; min-width: 160px; flex: 1;">
                        <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">Instrutor</label>
                        <select name="instructor_id" class="form-input" style="padding: 6px 10px; font-size: 0.875rem;" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <?php foreach ($instructors as $instructor): ?>
                            <option value="<?= $instructor['id'] ?>" <?= ($filters['instructor_id'] ?? '') == $instructor['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($instructor['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Veículo -->
                    <div class="form-group" style="margin-bottom: 0; min-width: 160px; flex: 1;">
                        <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">Veículo</label>
                        <select name="vehicle_id" id="vehicle_filter" class="form-input" style="padding: 6px 10px; font-size: 0.875rem;" onchange="this.form.submit()" <?= ($filters['type'] ?? '') === 'teoria' ? 'disabled' : '' ?>>
                            <option value="">Todos</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?= $vehicle['id'] ?>" <?= ($filters['vehicle_id'] ?? '') == $vehicle['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($vehicle['plate']) ?> - <?= htmlspecialchars($vehicle['model'] ?? '') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Status -->
                    <div class="form-group" style="margin-bottom: 0; min-width: 140px; flex: 1;">
                        <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">Status</label>
                        <select name="status" class="form-input" style="padding: 6px 10px; font-size: 0.875rem;" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="agendada" <?= ($filters['status'] ?? '') === 'agendada' ? 'selected' : '' ?>>Agendada</option>
                            <option value="scheduled" <?= ($filters['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Agendada (Teórica)</option>
                            <option value="em_andamento" <?= ($filters['status'] ?? '') === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                            <option value="in_progress" <?= ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>Em Andamento (Teórica)</option>
                            <option value="concluida" <?= ($filters['status'] ?? '') === 'concluida' ? 'selected' : '' ?>>Concluída</option>
                            <option value="done" <?= ($filters['status'] ?? '') === 'done' ? 'selected' : '' ?>>Concluída (Teórica)</option>
                            <option value="cancelada" <?= ($filters['status'] ?? '') === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                            <option value="canceled" <?= ($filters['status'] ?? '') === 'canceled' ? 'selected' : '' ?>>Cancelada (Teórica)</option>
                        </select>
                    </div>
                    
                    <!-- Exibir canceladas -->
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.8rem; white-space: nowrap;">
                        <input type="checkbox" name="show_canceled" value="1" 
                               <?= ($showCanceled ?? false) ? 'checked' : '' ?>
                               onchange="this.form.submit()">
                        <span>Exibir canceladas</span>
                    </label>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
function toggleExtraFilters() {
    const el = document.getElementById('extraFilters');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
<?php endif; ?>

<!-- Calendário / Lista -->
<div class="card">
    <div class="card-body">
        <?php if ($viewType === 'list'): ?>
            <!-- Abas para INSTRUTOR -->
            <?php if ($isInstrutor): ?>
            <?php 
            $currentTab = $tab ?? 'proximas';
            ?>
            <div class="instructor-tabs" style="display: flex; gap: var(--spacing-xs); margin-bottom: var(--spacing-md); border-bottom: 2px solid var(--color-border, #e0e0e0); overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <a href="<?= base_path('agenda?view=list&tab=proximas') ?>" 
                   class="instructor-tab <?= $currentTab === 'proximas' ? 'active' : '' ?>"
                   style="padding: var(--spacing-sm) var(--spacing-md); text-decoration: none; color: <?= $currentTab === 'proximas' ? 'var(--color-primary, #3b82f6)' : 'var(--color-text-muted, #666)' ?>; border-bottom: 2px solid <?= $currentTab === 'proximas' ? 'var(--color-primary, #3b82f6)' : 'transparent' ?>; margin-bottom: -2px; font-weight: <?= $currentTab === 'proximas' ? '600' : '400' ?>; transition: all 0.2s; white-space: nowrap; flex-shrink: 0;">
                    Próximas
                </a>
                <a href="<?= base_path('agenda?view=list&tab=historico') ?>" 
                   class="instructor-tab <?= $currentTab === 'historico' ? 'active' : '' ?>"
                   style="padding: var(--spacing-sm) var(--spacing-md); text-decoration: none; color: <?= $currentTab === 'historico' ? 'var(--color-primary, #3b82f6)' : 'var(--color-text-muted, #666)' ?>; border-bottom: 2px solid <?= $currentTab === 'historico' ? 'var(--color-primary, #3b82f6)' : 'transparent' ?>; margin-bottom: -2px; font-weight: <?= $currentTab === 'historico' ? '600' : '400' ?>; transition: all 0.2s; white-space: nowrap; flex-shrink: 0;">
                    Histórico
                </a>
                <a href="<?= base_path('agenda?view=list&tab=todas') ?>" 
                   class="instructor-tab <?= $currentTab === 'todas' ? 'active' : '' ?>"
                   style="padding: var(--spacing-sm) var(--spacing-md); text-decoration: none; color: <?= $currentTab === 'todas' ? 'var(--color-primary, #3b82f6)' : 'var(--color-text-muted, #666)' ?>; border-bottom: 2px solid <?= $currentTab === 'todas' ? 'var(--color-primary, #3b82f6)' : 'transparent' ?>; margin-bottom: -2px; font-weight: <?= $currentTab === 'todas' ? '600' : '400' ?>; transition: all 0.2s; white-space: nowrap; flex-shrink: 0;">
                    Todas
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Visualização Lista -->
            <?php
            $currentTab = $tab ?? 'proximas';
            // Ordenar aulas por data/hora (próximas primeiro, ou desc para histórico)
            if ($currentTab === 'historico' || $currentTab === 'todas') {
                usort($lessons, function($a, $b) {
                    $dateA = strtotime($a['scheduled_date'] . ' ' . $a['scheduled_time']);
                    $dateB = strtotime($b['scheduled_date'] . ' ' . $b['scheduled_time']);
                    return $dateB - $dateA; // Desc para histórico
                });
            } else {
                usort($lessons, function($a, $b) {
                    $dateA = strtotime($a['scheduled_date'] . ' ' . $a['scheduled_time']);
                    $dateB = strtotime($b['scheduled_date'] . ' ' . $b['scheduled_time']);
                    return $dateA - $dateB; // Asc para próximas
                });
            }
            
            $statusConfig = [
                'agendada' => ['label' => 'Agendada', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
                'em_andamento' => ['label' => 'Em Andamento', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
                'concluida' => ['label' => 'Concluída', 'color' => '#10b981', 'bg' => '#d1fae5'],
                'cancelada' => ['label' => 'Cancelada', 'color' => '#ef4444', 'bg' => '#fee2e2'],
                'no_show' => ['label' => 'Não Compareceu', 'color' => '#6b7280', 'bg' => '#f3f4f6']
            ];
            ?>
            
            <?php if (empty($lessons)): ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <p class="text-muted">
                        <?php if ($isInstrutor): ?>
                            <?php if ($currentTab === 'historico'): ?>
                                Nenhuma aula no histórico.
                            <?php else: ?>
                                Você não possui aulas agendadas.
                            <?php endif; ?>
                        <?php else: ?>
                            Você não possui aulas agendadas.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                    <?php foreach ($lessons as $lesson): ?>
                        <?php
                        // Detectar se é aula teórica
                        $isTheory = ($lesson['lesson_type'] ?? '') === 'teoria' || !empty($lesson['theory_session_id']);
                        
                        $lessonDate = new \DateTime($lesson['scheduled_date'] . ' ' . $lesson['scheduled_time']);
                        $endTime = clone $lessonDate;
                        $endTime->modify("+{$lesson['duration_minutes']} minutes");
                        
                        // Mapear status de teoria para exibição
                        $lessonStatus = $lesson['status'] ?? 'agendada';
                        if ($isTheory) {
                            // Mapear status de theory_sessions para formato de lessons
                            if ($lessonStatus === 'done') {
                                $lessonStatus = 'concluida';
                            } elseif ($lessonStatus === 'canceled') {
                                $lessonStatus = 'cancelada';
                            } elseif ($lessonStatus === 'scheduled') {
                                $lessonStatus = 'agendada';
                            } elseif ($lessonStatus === 'in_progress') {
                                $lessonStatus = 'em_andamento';
                            }
                        }
                        $status = $statusConfig[$lessonStatus] ?? ['label' => $lessonStatus, 'color' => '#666', 'bg' => '#f3f4f6'];
                        $isPast = $lessonDate < new \DateTime();
                        ?>
                        <div style="padding: var(--spacing-md); border: 1px solid var(--color-border, #e0e0e0); border-radius: var(--radius-md, 8px); background: <?= $isPast && $lesson['status'] !== 'agendada' ? '#f9fafb' : 'white' ?>; transition: all 0.2s;">
                            <div style="display: grid; gap: var(--spacing-xs);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: var(--spacing-sm); flex-wrap: wrap;">
                                    <div style="flex: 1; min-width: 200px;">
                                        <div style="font-weight: 600; font-size: 1rem; margin-bottom: var(--spacing-xs);">
                                            <?= $lessonDate->format('d/m/Y') ?> às <?= $lessonDate->format('H:i') ?>
                                        </div>
                                        <div style="color: var(--color-text-muted, #666); font-size: 0.875rem; margin-bottom: var(--spacing-xs);">
                                            <?php
                                            $isTheory = ($lesson['lesson_type'] ?? '') === 'teoria' || !empty($lesson['theory_session_id']);
                                            if ($isTheory):
                                                $studentCount = $lesson['student_count'] ?? 1;
                                                $disciplineName = $lesson['discipline_name'] ?? 'Disciplina';
                                                $className = $lesson['class_name'] ?? '';
                                            ?>
                                                Sessão Teórica — <?= htmlspecialchars($disciplineName) ?> — <?= $studentCount ?> aluno(s)<?= $className ? ' — ' . htmlspecialchars($className) : '' ?>
                                            <?php elseif ($isInstrutor): ?>
                                                Aluno: <?= htmlspecialchars($lesson['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($lesson['instructor_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($lesson['student_name'])): ?>
                                                <br><span style="font-size: 0.8rem;">Aluno: <?= htmlspecialchars($lesson['student_name']) ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!$isTheory && !empty($lesson['vehicle_plate'])): ?>
                                        <div style="color: var(--color-text-muted, #666); font-size: 0.875rem;">
                                            Veículo: <?= htmlspecialchars($lesson['vehicle_plate']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; gap: var(--spacing-xs); align-items: center; flex-wrap: wrap;">
                                        <span style="display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: <?= $status['bg'] ?>; color: <?= $status['color'] ?>;">
                                            <?= $status['label'] ?>
                                        </span>
                                    </div>
                                </div>
                                <div style="color: var(--color-text-muted, #666); font-size: 0.875rem; margin-top: var(--spacing-xs);">
                                    <?= $isTheory ? 'Aula Teórica' : 'Aula Prática' ?> • <?= $lessonDate->format('H:i') ?> – <?= $endTime->format('H:i') ?>
                                </div>
                                <?php if ($isInstrutor): ?>
                                <?php
                                $classId = $lesson['class_id'] ?? '';
                                $sessionId = $lesson['theory_session_id'] ?? '';
                                $lessonUrl = $isTheory && !empty($sessionId)
                                    ? base_path("turmas-teoricas/{$classId}/sessoes/{$sessionId}/presenca")
                                    : base_path("agenda/{$lesson['id']}");
                                ?>
                                <div style="display: flex; gap: var(--spacing-sm); margin-top: var(--spacing-sm); flex-wrap: wrap;">
                                    <a href="<?= $lessonUrl ?>" class="btn btn-sm btn-outline" style="flex: 1; min-width: 120px; text-align: center;">
                                        <?= $isTheory ? 'Marcar Presença' : 'Ver Detalhes' ?>
                                    </a>
                                    <?php 
                                    // Ações apenas para aulas práticas futuras (não históricas)
                                    // Sessões teóricas não têm "iniciar/concluir", apenas presença
                                    if (!$isTheory):
                                        $isHistorical = in_array($lesson['status'], ['concluida', 'cancelada', 'no_show']);
                                        $isFuture = $currentTab === 'proximas' || (!$isHistorical && $currentTab === 'todas');
                                        if ($isFuture && !$isHistorical):
                                    ?>
                                        <?php if ($lesson['status'] === 'agendada'): ?>
                                        <a href="<?= base_path("agenda/{$lesson['id']}/iniciar") ?>" class="btn btn-sm btn-warning" style="flex: 1; min-width: 120px; text-align: center;">
                                            Iniciar Aula
                                        </a>
                                        <?php elseif ($lesson['status'] === 'em_andamento'): ?>
                                        <a href="<?= base_path("agenda/{$lesson['id']}/concluir") ?>" class="btn btn-sm btn-success" style="flex: 1; min-width: 120px; text-align: center;">
                                            Concluir Aula
                                        </a>
                                        <?php endif; ?>
                                    <?php 
                                        endif;
                                    endif; 
                                    ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php elseif ($viewType === 'week'): ?>
            <!-- Visualização Semanal -->
            <?php
            // Nomes dos dias em português (PT-BR)
            $dayNames = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB'];
            
            // Semana inicia no domingo (0 = domingo)
            $dateObj = new \DateTime($startDate);
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $days[] = clone $dateObj;
                $dateObj->modify('+1 day');
            }
            
            // Configurações da grade (dia completo 00:00-24:00)
            $startHour = 0; // 00:00 - início do dia
            $endHour = 24; // 24:00 - fim do dia (loop usa < então último slot é 23:00)
            $totalMinutes = ($endHour - $startHour) * 60; // Total de minutos do dia (900 min = 15 horas)
            $pixelsPerMinute = 2; // 2px por minuto (120px por hora) - ideal para leitura
            $dayColumnHeight = $totalMinutes * $pixelsPerMinute;
            $hourHeight = 60 * $pixelsPerMinute; // Altura de cada hora
            ?>
            <!-- Container scrollável com cabeçalho sticky -->
            <div class="calendar-week" style="max-height: calc(100vh - 180px); overflow-y: auto; position: relative;">
                <div class="calendar-week-header" style="position: sticky; top: 0; z-index: 10; background: white; padding: 6px 0;">
                    <div class="calendar-hour-col"></div>
                    <?php foreach ($days as $index => $day): ?>
                    <div class="calendar-day-header" style="padding: 4px 0;">
                        <div class="calendar-day-name" style="font-size: 0.7rem; font-weight: 600; color: #1e3a5f;"><?= $dayNames[$index] ?></div>
                        <div class="calendar-day-number" style="font-size: 0.95rem; font-weight: 500; color: #374151;"><?= $day->format('d/m') ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="calendar-week-body">
                    <!-- Coluna de horas -->
                    <div class="calendar-hours-col">
                        <?php for ($h = $startHour; $h < $endHour; $h++): ?>
                        <div class="calendar-hour-mark" style="height: <?= $hourHeight ?>px;">
                            <?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00
                        </div>
                        <?php endfor; ?>
                    </div>
                    
                    <!-- Colunas dos dias -->
                    <?php foreach ($days as $day): ?>
                    <div class="calendar-day-column" style="height: <?= $dayColumnHeight ?>px;">
                        <?php
                        $dayStr = $day->format('Y-m-d');
                        $dayLessons = array_filter($lessons, function($lesson) use ($dayStr) {
                            return $lesson['scheduled_date'] === $dayStr;
                        });
                        
                        // Agrupar eventos por overlap de horário
                        $clusteredLessons = [];
                        $processed = [];
                        
                        foreach ($dayLessons as $idx => $lesson) {
                            if (isset($processed[$idx])) continue;
                            
                            $cluster = [$idx => $lesson];
                            $processed[$idx] = true;
                            
                            // Calcular horários desta lesson
                            $lessonStart = strtotime($lesson['scheduled_date'] . ' ' . $lesson['scheduled_time']);
                            $lessonEnd = $lessonStart + (($lesson['duration_minutes'] ?? 50) * 60);
                            
                            // Buscar todas as lessons que se sobrepõem
                            foreach ($dayLessons as $otherIdx => $otherLesson) {
                                if (isset($processed[$otherIdx]) || $idx === $otherIdx) continue;
                                
                                $otherStart = strtotime($otherLesson['scheduled_date'] . ' ' . $otherLesson['scheduled_time']);
                                $otherEnd = $otherStart + (($otherLesson['duration_minutes'] ?? 50) * 60);
                                
                                // Verificar overlap: A.start < B.end AND A.end > B.start
                                if ($lessonStart < $otherEnd && $lessonEnd > $otherStart) {
                                    $cluster[$otherIdx] = $otherLesson;
                                    $processed[$otherIdx] = true;
                                }
                            }
                            
                            $clusteredLessons[] = $cluster;
                        }
                        
                        // Renderizar clusters
                        foreach ($clusteredLessons as $clusterIdx => $cluster):
                            // Ordenar cluster por horário ANTES de fazer slice
                            uasort($cluster, function($a, $b) {
                                $timeA = strtotime($a['scheduled_date'] . ' ' . $a['scheduled_time']);
                                $timeB = strtotime($b['scheduled_date'] . ' ' . $b['scheduled_time']);
                                return $timeA <=> $timeB;
                            });
                            
                            $clusterSize = count($cluster);
                            $maxVisible = 2; // Máximo de cards visíveis
                            $visibleLessons = array_slice($cluster, 0, $maxVisible, true);
                            $hiddenCount = max(0, $clusterSize - $maxVisible);
                            
                            foreach ($visibleLessons as $lessonIdx => $lesson):
                            // Detectar se é aula teórica
                            $isTheory = ($lesson['lesson_type'] ?? '') === 'teoria' || !empty($lesson['theory_session_id']);
                            
                            // Calcular posição e altura baseado em minutos
                            $lessonTime = strtotime($lesson['scheduled_time']);
                            $lessonHour = (int)date('H', $lessonTime);
                            $lessonMinute = (int)date('i', $lessonTime);
                            
                            // Minutos desde o início do dia (7:00)
                            $minutesFromStart = (($lessonHour - $startHour) * 60) + $lessonMinute;
                            
                            // Altura proporcional à duração
                            $durationMinutes = (int)$lesson['duration_minutes'];
                            $height = $durationMinutes * $pixelsPerMinute;
                            
                            // Posição top
                            $top = $minutesFromStart * $pixelsPerMinute;
                            
                            // Calcular horário de término
                            $startDateTime = new \DateTime($lesson['scheduled_date'] . ' ' . $lesson['scheduled_time']);
                            $endDateTime = clone $startDateTime;
                            $endDateTime->modify("+{$durationMinutes} minutes");
                            $startTime = $startDateTime->format('H:i');
                            $endTime = $endDateTime->format('H:i');
                            
                            // Status para teóricas usa theory_sessions.status
                            $lessonStatus = $lesson['status'] ?? 'agendada';
                            if ($isTheory && isset($lesson['status'])) {
                                // Mapear status de teoria para prática quando necessário
                                if ($lessonStatus === 'done') $lessonStatus = 'concluida';
                                elseif ($lessonStatus === 'canceled') $lessonStatus = 'cancelada';
                                elseif ($lessonStatus === 'scheduled') $lessonStatus = 'agendada';
                                elseif ($lessonStatus === 'in_progress') $lessonStatus = 'em_andamento';
                            }
                            
                            $statusClass = [
                                'agendada' => 'lesson-scheduled',
                                'em_andamento' => 'lesson-in-progress',
                                'concluida' => 'lesson-completed',
                                'cancelada' => 'lesson-cancelled',
                                'no_show' => 'lesson-no-show'
                            ][$lessonStatus] ?? 'lesson-scheduled';
                        ?>
                        <?php
                        $isTheory = ($lesson['lesson_type'] ?? '') === 'teoria' || !empty($lesson['theory_session_id']);
                        $studentCount = $lesson['student_count'] ?? 1;
                        $disciplineName = $lesson['discipline_name'] ?? '';
                        $className = $lesson['class_name'] ?? '';
                        $classId = $lesson['class_id'] ?? '';
                        $sessionId = $lesson['theory_session_id'] ?? '';
                        
                        if ($isTheory && !empty($sessionId)) {
                            $lessonUrl = base_path("turmas-teoricas/{$classId}/sessoes/{$sessionId}/presenca");
                        } elseif ($isTheory && !empty($classId)) {
                            $lessonUrl = base_path("turmas-teoricas/{$classId}");
                        } else {
                            $lessonUrl = base_path("agenda/{$lesson['id']}");
                        }
                        ?>
                        <a href="<?= $lessonUrl ?>" 
                           class="lesson-card <?= $statusClass ?>" 
                           style="position: absolute; top: <?= $top ?>px; height: <?= $height ?>px; left: 0; right: 4px; margin-bottom: 2px;"
                           title="<?= $isTheory ? "Sessão Teórica - " . htmlspecialchars($disciplineName) . " - {$studentCount} aluno(s)" : htmlspecialchars($lesson['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="lesson-card-time"><?= $startTime ?> – <?= $endTime ?></div>
                            <?php if ($isTheory): ?>
                                <div class="lesson-card-title"><?= htmlspecialchars($disciplineName ?: 'Sessão Teórica') ?></div>
                                <div class="lesson-card-instructor"><?= $studentCount ?> aluno(s)<?= $className ? ' — ' . htmlspecialchars($className) : '' ?></div>
                            <?php else: ?>
                                <div class="lesson-card-title"><?= htmlspecialchars($lesson['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="lesson-card-instructor"><?= htmlspecialchars($lesson['instructor_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if ($lesson['vehicle_plate'] ?? ''): ?>
                                <div class="lesson-card-vehicle"><?= htmlspecialchars($lesson['vehicle_plate'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($lessonStatus === 'cancelada'): ?>
                            <div class="lesson-card-status" style="font-size: 0.7rem; padding: 2px 6px; margin-top: 2px;">Cancelada</div>
                            <?php endif; ?>
                        </a>
                            <?php 
                            // Se é o último card visível e há mais eventos no cluster, mostrar indicador
                            $lastVisibleKey = array_keys($visibleLessons)[count($visibleLessons) - 1] ?? null;
                            if ($lessonIdx === $lastVisibleKey && $hiddenCount > 0):
                                // Calcular posição do indicador (mesma posição do último card)
                                $lastLesson = end($visibleLessons);
                                $lastLessonTime = strtotime($lastLesson['scheduled_date'] . ' ' . $lastLesson['scheduled_time']);
                                $lastLessonHour = (int)date('H', $lastLessonTime);
                                $lastLessonMinute = (int)date('i', $lastLessonTime);
                                $lastMinutesFromStart = (($lastLessonHour - $startHour) * 60) + $lastLessonMinute;
                                $lastTop = $lastMinutesFromStart * $pixelsPerMinute;
                                $lastDuration = (int)($lastLesson['duration_minutes'] ?? 50);
                                $lastHeight = $lastDuration * $pixelsPerMinute;
                            ?>
                            <div class="lesson-cluster-indicator" 
                                 data-cluster-id="cluster-<?= $clusterIdx ?>"
                                 data-cluster-data="<?= htmlspecialchars(json_encode(array_values($cluster)), ENT_QUOTES, 'UTF-8') ?>"
                                 data-cluster-date="<?= htmlspecialchars($dayStr, ENT_QUOTES, 'UTF-8') ?>"
                                 style="position: absolute; top: <?= $lastTop + $lastHeight + 2 ?>px; left: 0; right: 4px; padding: 4px 6px; background: #f3f4f6; border: 1px dashed #9ca3af; border-radius: 4px; cursor: pointer; font-size: 0.7rem; text-align: center; z-index: 5; user-select: none; box-sizing: border-box;"
                                 onclick="handleClusterClick(this)">
                                <strong>+<?= $hiddenCount ?> agendamento(s) neste horário</strong>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Visualização Diária -->
            <div class="calendar-day">
                <div class="calendar-day-header">
                    <?php
                    $dateObj = new \DateTime($date);
                    $dayNames = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                    $dayName = $dayNames[(int)$dateObj->format('w')];
                    ?>
                    <h2><?= date('d/m/Y', strtotime($date)) ?> - <?= $dayName ?></h2>
                </div>
                
                <div class="calendar-day-body">
                    <?php
                    // Configurações da grade diária (dia completo 00:00-24:00)
                    $startHour = 0; // 00:00 - início do dia
                    $endHour = 24; // 24:00 - fim do dia
                    $totalMinutes = ($endHour - $startHour) * 60;
                    $pixelsPerMinute = 2; // 2px por minuto (120px por hora) - ideal para leitura
                    $dayColumnHeight = $totalMinutes * $pixelsPerMinute;
                    
                    $dayLessons = array_filter($lessons, function($lesson) use ($date) {
                        return $lesson['scheduled_date'] === $date;
                    });
                    
                    // Agrupar eventos por overlap de horário
                    $clusteredLessons = [];
                    $processed = [];
                    
                    foreach ($dayLessons as $idx => $lesson) {
                        if (isset($processed[$idx])) continue;
                        
                        $cluster = [$idx => $lesson];
                        $processed[$idx] = true;
                        
                        // Calcular horários desta lesson
                        $lessonStart = strtotime($lesson['scheduled_date'] . ' ' . $lesson['scheduled_time']);
                        $lessonEnd = $lessonStart + (($lesson['duration_minutes'] ?? 50) * 60);
                        
                        // Buscar todas as lessons que se sobrepõem
                        foreach ($dayLessons as $otherIdx => $otherLesson) {
                            if (isset($processed[$otherIdx]) || $idx === $otherIdx) continue;
                            
                            $otherStart = strtotime($otherLesson['scheduled_date'] . ' ' . $otherLesson['scheduled_time']);
                            $otherEnd = $otherStart + (($otherLesson['duration_minutes'] ?? 50) * 60);
                            
                            // Verificar overlap: A.start < B.end AND A.end > B.start
                            if ($lessonStart < $otherEnd && $lessonEnd > $otherStart) {
                                $cluster[$otherIdx] = $otherLesson;
                                $processed[$otherIdx] = true;
                            }
                        }
                        
                        $clusteredLessons[] = $cluster;
                    }
                    ?>
                    <div class="calendar-day-timeline" style="position: relative; height: <?= $dayColumnHeight ?>px;">
                        <!-- Marcações de hora -->
                        <?php for ($h = $startHour; $h <= $endHour; $h++): ?>
                        <div class="calendar-day-hour-mark" style="position: absolute; top: <?= (($h - $startHour) * 60) * $pixelsPerMinute ?>px; left: 0; width: 80px; border-top: 1px solid var(--color-border, #e0e0e0); z-index: 3; pointer-events: none;">
                            <div class="calendar-day-hour-label" style="position: absolute; left: 0; top: -12px; padding: 0 var(--spacing-sm); background: white; z-index: 4; font-weight: 600; color: #374151;">
                                <?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00
                            </div>
                        </div>
                        <?php endfor; ?>
                        
                        <!-- Aulas agrupadas -->
                        <?php foreach ($clusteredLessons as $clusterIdx => $cluster):
                            // Ordenar cluster por horário ANTES de fazer slice
                            uasort($cluster, function($a, $b) {
                                $timeA = strtotime($a['scheduled_date'] . ' ' . $a['scheduled_time']);
                                $timeB = strtotime($b['scheduled_date'] . ' ' . $b['scheduled_time']);
                                return $timeA <=> $timeB;
                            });
                            
                            $clusterSize = count($cluster);
                            $maxVisible = 2; // Máximo de cards visíveis
                            $visibleLessons = array_slice($cluster, 0, $maxVisible, true);
                            $hiddenCount = max(0, $clusterSize - $maxVisible);
                            
                            foreach ($visibleLessons as $lessonIdx => $lesson):
                            $lessonTime = strtotime($lesson['scheduled_time']);
                            $lessonHour = (int)date('H', $lessonTime);
                            $lessonMinute = (int)date('i', $lessonTime);
                            
                            $minutesFromStart = (($lessonHour - $startHour) * 60) + $lessonMinute;
                            $durationMinutes = (int)$lesson['duration_minutes'];
                            $height = $durationMinutes * $pixelsPerMinute;
                            $top = $minutesFromStart * $pixelsPerMinute;
                            
                            // Calcular horário de término
                            $startDateTime = new \DateTime($lesson['scheduled_date'] . ' ' . $lesson['scheduled_time']);
                            $endDateTime = clone $startDateTime;
                            $endDateTime->modify("+{$durationMinutes} minutes");
                            $startTime = $startDateTime->format('H:i');
                            $endTime = $endDateTime->format('H:i');
                            
                            // Mapear status de teoria
                            $isTheory = ($lesson['lesson_type'] ?? '') === 'teoria' || !empty($lesson['theory_session_id']);
                            $lessonStatus = $lesson['status'] ?? 'agendada';
                            if ($isTheory) {
                                if ($lessonStatus === 'done') $lessonStatus = 'concluida';
                                elseif ($lessonStatus === 'canceled') $lessonStatus = 'cancelada';
                                elseif ($lessonStatus === 'scheduled') $lessonStatus = 'agendada';
                                elseif ($lessonStatus === 'in_progress') $lessonStatus = 'em_andamento';
                            }
                            
                            $statusClass = [
                                'agendada' => 'lesson-scheduled',
                                'em_andamento' => 'lesson-in-progress',
                                'concluida' => 'lesson-completed',
                                'cancelada' => 'lesson-cancelled',
                                'no_show' => 'lesson-no-show'
                            ][$lessonStatus] ?? 'lesson-scheduled';
                            
                            // URL e dados para teóricas
                            $studentCount = $lesson['student_count'] ?? 1;
                            $disciplineName = $lesson['discipline_name'] ?? '';
                            $className = $lesson['class_name'] ?? '';
                            $classId = $lesson['class_id'] ?? '';
                            $sessionId = $lesson['theory_session_id'] ?? '';
                            
                            if ($isTheory && !empty($sessionId)) {
                                $lessonUrl = base_path("turmas-teoricas/{$classId}/sessoes/{$sessionId}/presenca");
                            } elseif ($isTheory && !empty($classId)) {
                                $lessonUrl = base_path("turmas-teoricas/{$classId}");
                            } else {
                                $lessonUrl = base_path("agenda/{$lesson['id']}");
                            }
                        ?>
                        <a href="<?= $lessonUrl ?>" 
                           class="lesson-card <?= $statusClass ?>" 
                           style="position: absolute; top: <?= $top ?>px; height: <?= $height ?>px; left: 80px; right: 0; margin-bottom: 2px; padding-left: 12px; padding-right: 8px; box-sizing: border-box;">
                            <div class="lesson-card-time"><?= $startTime ?> – <?= $endTime ?></div>
                            <?php if ($isTheory): ?>
                                <div class="lesson-card-title"><?= htmlspecialchars($disciplineName ?: 'Sessão Teórica') ?></div>
                                <div class="lesson-card-instructor"><?= $studentCount ?> aluno(s)<?= $className ? ' — ' . htmlspecialchars($className) : '' ?></div>
                            <?php else: ?>
                                <div class="lesson-card-title"><?= htmlspecialchars($lesson['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="lesson-card-instructor"><?= htmlspecialchars($lesson['instructor_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if ($lesson['vehicle_plate'] ?? ''): ?>
                                <div class="lesson-card-vehicle"><?= htmlspecialchars($lesson['vehicle_plate'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($lessonStatus === 'cancelada'): ?>
                            <div class="lesson-card-status" style="font-size: 0.7rem; padding: 2px 6px; margin-top: 2px;">Cancelada</div>
                            <?php endif; ?>
                        </a>
                            <?php 
                            // Se é o último card visível e há mais eventos no cluster, mostrar indicador
                            $lastVisibleKey = array_keys($visibleLessons)[count($visibleLessons) - 1] ?? null;
                            if ($lessonIdx === $lastVisibleKey && $hiddenCount > 0):
                                // Calcular posição do indicador (mesma posição do último card)
                                $lastLesson = end($visibleLessons);
                                $lastLessonTime = strtotime($lastLesson['scheduled_date'] . ' ' . $lastLesson['scheduled_time']);
                                $lastLessonHour = (int)date('H', $lastLessonTime);
                                $lastLessonMinute = (int)date('i', $lastLessonTime);
                                $lastMinutesFromStart = (($lastLessonHour - $startHour) * 60) + $lastLessonMinute;
                                $lastTop = $lastMinutesFromStart * $pixelsPerMinute;
                                $lastDuration = (int)($lastLesson['duration_minutes'] ?? 50);
                                $lastHeight = $lastDuration * $pixelsPerMinute;
                            ?>
                            <div class="lesson-cluster-indicator" 
                                 data-cluster-id="cluster-day-<?= $clusterIdx ?>"
                                 data-cluster-data="<?= htmlspecialchars(json_encode(array_values($cluster)), ENT_QUOTES, 'UTF-8') ?>"
                                 data-cluster-date="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>"
                                 style="position: absolute; top: <?= $lastTop + $lastHeight + 2 ?>px; left: 80px; right: 0; padding: 4px 6px; background: #f3f4f6; border: 1px dashed #9ca3af; border-radius: 4px; cursor: pointer; font-size: 0.7rem; text-align: center; z-index: 5; user-select: none; box-sizing: border-box; padding-left: 12px;"
                                 onclick="handleClusterClick(this)">
                                <strong>+<?= $hiddenCount ?> agendamento(s) neste horário</strong>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Modal para exibir cluster de eventos -->
        <div id="clusterModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 8px; padding: var(--spacing-lg); max-width: 600px; max-height: 80vh; overflow-y: auto; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-md);">
                    <h3 style="margin: 0;">Agendamentos no Horário</h3>
                    <button onclick="closeClusterModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666;">&times;</button>
                </div>
                <div id="clusterModalContent">
                    <!-- Conteúdo será preenchido via JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.calendar-week {
    overflow-x: auto;
}

.calendar-week-header {
    display: grid;
    grid-template-columns: 80px repeat(7, 1fr);
    border-bottom: 2px solid var(--color-border, #e0e0e0);
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
}

.calendar-hour-col {
    padding: var(--spacing-sm);
    font-weight: 600;
    text-align: center;
    border-right: 1px solid var(--color-border, #e0e0e0);
}

.calendar-day-header {
    padding: var(--spacing-sm);
    text-align: center;
    border-right: 1px solid var(--color-border, #e0e0e0);
}

.calendar-day-name {
    font-size: 0.875rem;
    color: var(--color-text-muted, #666);
    text-transform: uppercase;
    font-weight: 600;
}

.calendar-day-number {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: var(--spacing-xs);
}

.calendar-week-body {
    display: grid;
    grid-template-columns: 80px repeat(7, 1fr);
    position: relative;
}

.calendar-hours-col {
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--color-border, #e0e0e0);
}

.calendar-hour-mark {
    padding: var(--spacing-xs);
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
    color: var(--color-text-muted, #666);
    border-bottom: 1px solid var(--color-border, #e0e0e0);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 10;
    background: white;
}

.calendar-day-column {
    position: relative;
    border-right: 1px solid var(--color-border, #e0e0e0);
    border-bottom: 1px solid var(--color-border, #e0e0e0);
    overflow: visible;
    padding-left: 8px;
    padding-right: 4px;
}

.calendar-day {
    max-width: 1000px;
    margin: 0 auto;
}

.calendar-day-header h2 {
    margin-bottom: var(--spacing-md);
    text-align: center;
}

.calendar-day-body {
    border: 1px solid var(--color-border, #e0e0e0);
    border-radius: var(--radius-md, 8px);
    overflow: hidden;
    background: white;
}

.calendar-day-timeline {
    position: relative;
    padding-left: 80px;
    padding-right: 0;
    overflow: visible;
}

.calendar-day-timeline::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 80px;
    background: white;
    z-index: 2;
    pointer-events: none;
}

.calendar-day-hour-mark {
    position: absolute;
    left: 0;
    width: 80px;
    z-index: 3;
    pointer-events: none;
}

.calendar-day-hour-label {
    position: absolute;
    left: 0;
    top: -12px;
    padding: 0 var(--spacing-sm);
    background: white;
    font-weight: 600;
    font-size: 0.875rem;
    color: #374151;
    z-index: 4;
}

.lesson-card {
    display: block;
    padding: 4px 6px;
    border-radius: var(--radius-sm, 4px);
    text-decoration: none;
    color: inherit;
    border-left: 3px solid;
    background: var(--color-bg-secondary, #f5f5f5);
    transition: all 0.2s;
    font-size: 0.75rem;
    overflow: hidden;
    box-sizing: border-box;
    z-index: 5;
}

/* Ajuste de padding para cards na view semanal */
.calendar-day-column .lesson-card {
    padding-left: 8px;
    padding-right: 8px;
}

.lesson-card:hover {
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    transform: translateX(2px);
}

.lesson-scheduled {
    border-left-color: #3b82f6;
    background: #eff6ff;
}

.lesson-in-progress {
    border-left-color: #f59e0b;
    background: #fffbeb;
}

.lesson-completed {
    border-left-color: #10b981;
    background: #f0fdf4;
}

.lesson-cancelled {
    border-left-color: #ef4444;
    background: #fef2f2;
    opacity: 0.7;
}

.lesson-no-show {
    border-left-color: #6b7280;
    background: #f9fafb;
    opacity: 0.7;
}

.lesson-card-time {
    font-weight: 600;
    font-size: 0.7rem;
    color: var(--color-text-muted, #666);
    margin-bottom: 2px;
}

.lesson-card-title {
    font-weight: 600;
    margin: 2px 0;
    font-size: 0.8rem;
    line-height: 1.2;
    word-wrap: break-word;
    overflow-wrap: break-word;
    /* Garantir que nome completo seja visível */
    white-space: normal;
}

.lesson-card-instructor,
.lesson-card-vehicle {
    font-size: 0.7rem;
    color: var(--color-text-muted, #666);
    margin-top: 2px;
    line-height: 1.2;
}

.lesson-card-status {
    font-size: 0.65rem;
    color: #ef4444;
    font-weight: 600;
    margin-top: 2px;
}

.lesson-cluster-indicator {
    transition: all 0.2s;
    pointer-events: auto;
    z-index: 5;
}

.lesson-cluster-indicator:hover {
    background: #e5e7eb !important;
    border-color: #6b7280 !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 7;
}

/* JavaScript para desabilitar veículo quando tipo=teoria */
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.querySelector('select[name="type"]');
    const vehicleSelect = document.getElementById('vehicle_filter');
    
    if (typeSelect && vehicleSelect) {
        function updateVehicleFilter() {
            if (typeSelect.value === 'teoria') {
                vehicleSelect.disabled = true;
                vehicleSelect.value = ''; // Limpar seleção
            } else {
                vehicleSelect.disabled = false;
            }
        }
        
        typeSelect.addEventListener('change', updateVehicleFilter);
        updateVehicleFilter(); // Executar ao carregar
    }
});
</script>

/* Estilos para abas do instrutor (desktop e mobile) */
.instructor-tabs {
    position: relative;
}

.instructor-tab {
    cursor: pointer;
    user-select: none;
}

.instructor-tab:hover {
    color: var(--color-primary, #3b82f6) !important;
    background: var(--color-bg-secondary, #f5f5f5);
    border-radius: var(--radius-sm, 4px) var(--radius-sm, 4px) 0 0;
}

.instructor-tab.active {
    background: var(--color-bg-secondary, #f5f5f5);
    border-radius: var(--radius-sm, 4px) var(--radius-sm, 4px) 0 0;
}

/* Estilos mobile para abas do instrutor */
@media (max-width: 768px) {
    .instructor-tabs {
        gap: 0 !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    
    .instructor-tabs::-webkit-scrollbar {
        display: none;
    }
    
    .instructor-tab {
        padding: var(--spacing-sm) var(--spacing-md) !important;
        min-width: auto;
        flex: 1;
        text-align: center;
        font-size: 0.875rem;
    }
}
</style>

<script>
function navigateDate(direction) {
    const dateInput = document.querySelector('input[name="date"]');
    const currentDate = new Date(dateInput.value);
    const viewType = '<?= $viewType ?>';
    
    if (direction === 0) {
        // Hoje
        currentDate.setTime(Date.now());
    } else {
        // Navegar
        const days = viewType === 'week' ? 7 : 1;
        currentDate.setDate(currentDate.getDate() + (direction * days));
    }
    
    dateInput.value = currentDate.toISOString().split('T')[0];
    document.getElementById('filtersForm').submit();
}

// Desabilitar filtro de veículo quando tipo=teoria
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.querySelector('select[name="type"]');
    const vehicleSelect = document.getElementById('vehicle_filter');
    
    if (typeSelect && vehicleSelect) {
        function updateVehicleFilter() {
            if (typeSelect.value === 'teoria') {
                vehicleSelect.disabled = true;
                vehicleSelect.value = ''; // Limpar seleção
            } else {
                vehicleSelect.disabled = false;
            }
        }
        
        typeSelect.addEventListener('change', updateVehicleFilter);
        updateVehicleFilter(); // Executar ao carregar
    }
});

// Função para tratar clique no indicador de cluster
function handleClusterClick(element) {
    event.preventDefault();
    event.stopPropagation();
    
    const clusterData = element.getAttribute('data-cluster-data');
    const clusterDate = element.getAttribute('data-cluster-date');
    
    if (!clusterData) {
        console.error('Dados do cluster não encontrados');
        return;
    }
    
    try {
        const clusterLessons = JSON.parse(clusterData);
        showClusterModal(clusterLessons, clusterDate);
    } catch (e) {
        console.error('Erro ao parsear dados do cluster:', e);
        alert('Erro ao carregar agendamentos. Tente novamente.');
    }
}

// Função para exibir modal com cluster de eventos
function showClusterModal(clusterLessons, date) {
    const modal = document.getElementById('clusterModal');
    const content = document.getElementById('clusterModalContent');
    
    if (!modal || !content) {
        console.error('Modal não encontrado');
        return;
    }
    
    if (!clusterLessons || clusterLessons.length === 0) {
        alert('Nenhum agendamento encontrado.');
        return;
    }
    
    // Ordenar por horário
    clusterLessons.sort(function(a, b) {
        const timeA = new Date(a.scheduled_date + ' ' + a.scheduled_time).getTime();
        const timeB = new Date(b.scheduled_date + ' ' + b.scheduled_time).getTime();
        return timeA - timeB;
    });
    
    let html = '<div style="display: flex; flex-direction: column; gap: 12px;">';
    
    clusterLessons.forEach(function(lesson) {
        const isTheory = (lesson.lesson_type === 'teoria') || lesson.theory_session_id;
        const startTime = lesson.scheduled_time ? lesson.scheduled_time.substring(0, 5) : '00:00';
        const duration = lesson.duration_minutes || 50;
        const startDateTime = new Date(lesson.scheduled_date + ' ' + lesson.scheduled_time);
        const endDateTime = new Date(startDateTime.getTime() + (duration * 60000));
        const endTime = endDateTime.toTimeString().substring(0, 5);
        
        let lessonUrl = '';
        if (isTheory && lesson.theory_session_id) {
            lessonUrl = '<?= base_path("turmas-teoricas") ?>/' + (lesson.class_id || '') + '/sessoes/' + lesson.theory_session_id + '/presenca';
        } else if (isTheory && lesson.class_id) {
            lessonUrl = '<?= base_path("turmas-teoricas") ?>/' + lesson.class_id;
        } else {
            lessonUrl = '<?= base_path("agenda") ?>/' + lesson.id;
        }
        
        const statusLabels = {
            'agendada': 'Agendada',
            'scheduled': 'Agendada',
            'em_andamento': 'Em Andamento',
            'in_progress': 'Em Andamento',
            'concluida': 'Concluída',
            'done': 'Concluída',
            'cancelada': 'Cancelada',
            'canceled': 'Cancelada'
        };
        const status = lesson.status || lesson.lesson_status || 'agendada';
        const statusLabel = statusLabels[status] || status;
        
        html += '<div style="padding: 12px; border: 1px solid #e0e0e0; border-radius: 6px; background: #f9fafb;">';
        html += '<div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">';
        html += '<div style="flex: 1;">';
        html += '<div style="font-weight: 600; margin-bottom: 4px; color: #111;">' + startTime + ' – ' + endTime + '</div>';
        
        if (isTheory) {
            html += '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">';
            html += '<span style="display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 0.7rem; font-weight: 600; background: #e0e7ff; color: #3730a3; text-transform: uppercase;">TEÓRICA</span>';
            html += '<span style="color: #111; font-size: 0.875rem; font-weight: 500;">' + (lesson.discipline_name || 'Sessão Teórica') + '</span>';
            html += '</div>';
            html += '<div style="color: #666; font-size: 0.8rem;">' + (lesson.student_count || 1) + ' aluno(s)';
            if (lesson.class_name) {
                html += ' — ' + lesson.class_name;
            }
            html += '</div>';
        } else {
            html += '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">';
            html += '<span style="display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 0.7rem; font-weight: 600; background: #f3f4f6; color: #374151; text-transform: uppercase;">PRÁTICA</span>';
            html += '<span style="color: #111; font-size: 0.875rem; font-weight: 500;">' + (lesson.student_name || 'Aluno') + '</span>';
            html += '</div>';
            html += '<div style="color: #666; font-size: 0.8rem;">Instrutor: ' + (lesson.instructor_name || 'N/A');
            if (lesson.vehicle_plate) {
                html += ' • Veículo: ' + lesson.vehicle_plate;
            }
            html += '</div>';
        }
        
        html += '</div>';
        html += '<div style="margin-left: 12px; flex-shrink: 0;">';
        html += '<span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; background: #e5e7eb; color: #374151; white-space: nowrap;">' + statusLabel + '</span>';
        html += '</div>';
        html += '</div>';
        html += '<div style="margin-top: 8px;">';
        html += '<a href="' + lessonUrl + '" style="display: inline-block; padding: 6px 12px; background: #3b82f6; color: white; border-radius: 4px; text-decoration: none; font-size: 0.875rem; transition: background 0.2s;" onmouseover="this.style.background=\'#2563eb\'" onmouseout="this.style.background=\'#3b82f6\'">Ver Detalhes</a>';
        html += '</div>';
        html += '</div>';
    });
    
    html += '</div>';
    content.innerHTML = html;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Prevenir scroll da página
}

// Fechar modal
function closeClusterModal() {
    const modal = document.getElementById('clusterModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = ''; // Restaurar scroll
    }
}

// Fechar modal ao clicar fora
document.addEventListener('click', function(e) {
    const modal = document.getElementById('clusterModal');
    if (modal && e.target === modal) {
        closeClusterModal();
    }
});
</script>
