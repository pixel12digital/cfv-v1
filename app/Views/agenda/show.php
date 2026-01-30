<?php
$currentRole = $currentRole ?? $_SESSION['current_role'] ?? '';
$isAluno = ($currentRole === 'ALUNO');
$isInstrutor = ($currentRole === 'INSTRUTOR');
$isAdmin = !$isAluno && !$isInstrutor; // Admin ou Secretaria
?>
<div class="page-header">
    <div class="page-header-content" style="display: flex; flex-wrap: wrap; gap: var(--spacing-sm); justify-content: space-between; align-items: flex-start;">
        <div style="flex: 1; min-width: 200px;">
            <h1 style="margin: 0; font-size: clamp(1.25rem, 4vw, 1.5rem);">Detalhes da Aula</h1>
            <p class="text-muted" style="margin: var(--spacing-xs) 0 0 0; font-size: 0.875rem;">Informações completas da aula</p>
        </div>
        <div class="header-actions" style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
            <?php
            $backUrl = 'agenda';
            if (isset($from) && $from === 'dashboard') {
                $backUrl = 'dashboard';
            }
            ?>
            <a href="<?= base_path($backUrl) ?>" class="btn btn-outline" style="flex: 1; min-width: 80px; text-align: center;">Voltar</a>
            <?php if (!$isAluno && !in_array($lesson['status'], ['concluida', 'cancelada'])): ?>
            <a href="<?= base_path("agenda/{$lesson['id']}/editar") ?>" class="btn btn-primary" style="flex: 1; min-width: 100px; text-align: center;">Remarcar</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($isInstrutor && isset($studentSummary) && $studentSummary): ?>
<!-- Resumo do histórico com este aluno (para instrutor) -->
<div class="card" style="margin-bottom: var(--spacing-md); background: var(--color-bg-light, #f8fafc); border-left: 4px solid var(--color-primary, #3b82f6);">
    <div class="card-body" style="padding: var(--spacing-md);">
        <div style="display: flex; align-items: center; gap: var(--spacing-sm); margin-bottom: var(--spacing-xs);">
            <strong style="color: var(--color-text, #333);">Histórico com este aluno</strong>
        </div>
        <?php 
        $count = $studentSummary['completed_count'];
        $lastDate = $studentSummary['last_lesson_date'];
        $lastTime = $studentSummary['last_lesson_time'];
        $lastType = $studentSummary['last_lesson_type'] ?? null;
        $upcoming = $studentSummary['upcoming_count'];
        $typeCounts = $studentSummary['type_counts'] ?? ['rua' => 0, 'garagem' => 0, 'baliza' => 0];
        $typeLabels = ['rua' => 'Rua', 'garagem' => 'Garagem', 'baliza' => 'Baliza'];
        ?>
        <div style="font-size: 0.95rem; color: var(--color-text, #333);">
            <strong><?= $count ?></strong> aula<?= $count !== 1 ? 's' : '' ?> concluída<?= $count !== 1 ? 's' : '' ?>
            <?php if ($lastDate): ?>
                • Última: <strong><?= date('d/m', strtotime($lastDate)) ?></strong><?php if ($lastType): ?> (<?= $typeLabels[$lastType] ?? $lastType ?>)<?php endif; ?>
            <?php else: ?>
                • Sem aulas anteriores registradas
            <?php endif; ?>
        </div>
        <?php if ($count > 0 && array_sum($typeCounts) > 0): ?>
        <div style="font-size: 0.875rem; color: var(--color-text, #333); margin-top: var(--spacing-xs);">
            <?php 
            $typeDisplay = [];
            foreach ($typeCounts as $type => $typeCount) {
                if ($typeCount > 0) {
                    $typeDisplay[] = "{$typeLabels[$type]}: {$typeCount}";
                }
            }
            echo implode(' | ', $typeDisplay);
            ?>
        </div>
        <?php endif; ?>
        <div style="font-size: 0.875rem; color: var(--color-text-muted, #666); margin-top: var(--spacing-xs);">
            Próximas agendadas: <strong><?= $upcoming ?></strong>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="lesson-details-grid" style="display: grid; grid-template-columns: <?= $isAluno ? '1fr' : '2fr 1fr' ?>; gap: var(--spacing-md);">
    <!-- Informações Principais -->
    <div class="card">
        <div class="card-header">
            <h2>Informações da Aula</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; gap: var(--spacing-md);">
                <!-- Status -->
                <div>
                    <label class="form-label">Status</label>
                    <div>
                        <?php
                        $statusConfig = [
                            'agendada' => ['label' => 'Agendada', 'class' => 'badge badge-info'],
                            'em_andamento' => ['label' => 'Em Andamento', 'class' => 'badge badge-warning'],
                            'concluida' => ['label' => 'Concluída', 'class' => 'badge badge-success'],
                            'cancelada' => ['label' => 'Cancelada', 'class' => 'badge badge-danger'],
                            'no_show' => ['label' => 'Não Compareceu', 'class' => 'badge badge-secondary']
                        ];
                        $status = $statusConfig[$lesson['status']] ?? ['label' => $lesson['status'], 'class' => 'badge'];
                        ?>
                        <span class="<?= $status['class'] ?>"><?= $status['label'] ?></span>
                    </div>
                </div>
                
                <!-- Tipo -->
                <div>
                    <label class="form-label">Tipo</label>
                    <div>
                        Aula Prática
                        <?php if (!empty($lesson['practice_type'])): 
                            $practiceTypeLabels = ['rua' => 'Rua', 'garagem' => 'Garagem', 'baliza' => 'Baliza'];
                            $practiceLabel = $practiceTypeLabels[$lesson['practice_type']] ?? $lesson['practice_type'];
                        ?>
                        <span style="margin-left: var(--spacing-xs); padding: 2px 8px; background: var(--color-bg-light, #f1f5f9); border-radius: 4px; font-size: 0.875rem;">
                            <?= htmlspecialchars($practiceLabel) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Data e Hora -->
                <?php 
                $hasConsecutive = !empty($consecutiveBlock);
                if ($hasConsecutive) {
                    $blockStartTime = $consecutiveBlock['start_time'];
                    $blockEndTime = $consecutiveBlock['end_time'];
                    $blockDuration = $consecutiveBlock['total_duration'];
                    $blockCount = $consecutiveBlock['count'];
                    $hours = floor($blockDuration / 60);
                    $mins = $blockDuration % 60;
                    $durationText = $hours > 0 
                        ? ($mins > 0 ? "{$hours}h{$mins}min" : "{$hours}h") 
                        : "{$mins} minutos";
                }
                ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                    <div>
                        <label class="form-label">Data</label>
                        <div><?= date('d/m/Y', strtotime($lesson['scheduled_date'])) ?></div>
                    </div>
                    <div>
                        <label class="form-label">Hora</label>
                        <?php if ($hasConsecutive): ?>
                        <div>
                            <?= $blockStartTime ?> - <?= $blockEndTime ?>
                        </div>
                        <?php else: ?>
                        <div><?= date('H:i', strtotime($lesson['scheduled_time'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Duração -->
                <div>
                    <label class="form-label">Duração</label>
                    <?php if ($hasConsecutive): ?>
                    <div>
                        <?= $durationText ?>
                        <span style="margin-left: var(--spacing-xs); background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: 600;">
                            <?= $blockCount ?> aulas consecutivas
                        </span>
                    </div>
                    <?php else: ?>
                    <div><?= $lesson['duration_minutes'] ?> minutos</div>
                    <?php endif; ?>
                </div>
                
                <!-- Aluno (link apenas para não-alunos) -->
                <div>
                    <label class="form-label">Aluno</label>
                    <div>
                        <?php if (!$isAluno): ?>
                        <a href="<?= base_path("alunos/{$lesson['student_id']}") ?>" style="color: var(--color-primary); text-decoration: none;">
                            <?= htmlspecialchars($lesson['student_name']) ?>
                        </a>
                        <?php else: ?>
                        <?= htmlspecialchars($lesson['student_name']) ?>
                        <?php endif; ?>
                        <br>
                        <small class="text-muted">CPF: <?= htmlspecialchars($lesson['student_cpf']) ?></small>
                    </div>
                </div>
                
                <!-- Matrícula e Status Financeiro -->
                <?php if ($isAdmin): ?>
                <!-- Admin/Secretaria: informações completas -->
                <div>
                    <label class="form-label">Matrícula</label>
                    <div>
                        <a href="<?= base_path("matriculas/{$lesson['enrollment_id']}") ?>" style="color: var(--color-primary); text-decoration: none;">
                            Matrícula #<?= $lesson['enrollment_id'] ?>
                        </a>
                        <br>
                        <small class="text-muted">
                            Status Financeiro: 
                            <?php
                            $finStatus = [
                                'em_dia' => ['label' => 'Em Dia', 'class' => 'text-success'],
                                'pendente' => ['label' => 'Pendente', 'class' => 'text-warning'],
                                'bloqueado' => ['label' => 'Bloqueado', 'class' => 'text-danger']
                            ];
                            $fin = $finStatus[$lesson['financial_status']] ?? ['label' => $lesson['financial_status'], 'class' => ''];
                            ?>
                            <span class="<?= $fin['class'] ?>"><?= $fin['label'] ?></span>
                        </small>
                    </div>
                </div>
                <?php elseif ($isInstrutor && $lesson['financial_status'] === 'bloqueado'): ?>
                <!-- Instrutor: alerta apenas quando bloqueado -->
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-sm, 4px); padding: var(--spacing-sm); margin-top: var(--spacing-xs);">
                    <div style="display: flex; align-items: center; gap: var(--spacing-xs);">
                        <svg width="18" height="18" fill="none" stroke="#dc2626" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span style="color: #dc2626; font-weight: 500; font-size: 0.875rem;">Pendência financeira</span>
                    </div>
                    <p style="margin: var(--spacing-xs) 0 0 0; font-size: 0.8125rem; color: #7f1d1d;">
                        Aluno com situação financeira irregular. Orientar a entrar em contato com a secretaria.
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- Instrutor -->
                <div>
                    <label class="form-label">Instrutor</label>
                    <div><?= htmlspecialchars($lesson['instructor_name']) ?></div>
                </div>
                
                <!-- Veículo -->
                <?php if ($lesson['vehicle_plate']): ?>
                <div>
                    <label class="form-label">Veículo</label>
                    <div>
                        <?= htmlspecialchars($lesson['vehicle_plate']) ?>
                        <?php if ($lesson['vehicle_model']): ?>
                        <br>
                        <small class="text-muted"><?= htmlspecialchars($lesson['vehicle_model']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quilometragem (visível para ADMIN/SECRETARIA/INSTRUTOR) -->
                <?php if (!$isAluno && (!empty($lesson['km_start']) || !empty($lesson['km_end']))): ?>
                <div style="margin-top: var(--spacing-md); padding-top: var(--spacing-md); border-top: 2px solid var(--color-border, #e0e0e0);">
                    <h3 style="margin: 0 0 var(--spacing-md) 0; font-size: 1rem; font-weight: 600; color: var(--color-text, #333);">Dados da Aula (Instrutor)</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                        <?php if (!empty($lesson['km_start'])): ?>
                        <div>
                            <label class="form-label">KM Inicial</label>
                            <div style="font-size: 1.25rem; font-weight: 600; color: var(--color-primary, #3b82f6);">
                                <?= number_format($lesson['km_start'], 0, ',', '.') ?> km
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lesson['km_end'])): ?>
                        <div>
                            <label class="form-label">KM Final</label>
                            <div style="font-size: 1.25rem; font-weight: 600; color: var(--color-success, #10b981);">
                                <?= number_format($lesson['km_end'], 0, ',', '.') ?> km
                            </div>
                            <?php if (!empty($lesson['km_start'])): ?>
                            <div style="margin-top: var(--spacing-xs); padding: var(--spacing-xs) var(--spacing-sm); background: var(--color-bg-secondary, #f5f5f5); border-radius: var(--radius-sm, 4px);">
                                <strong style="color: var(--color-text, #333);">Distância percorrida: <?= number_format($lesson['km_end'] - $lesson['km_start'], 0, ',', '.') ?> km</strong>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Observações do Instrutor (PRIVADA - não visível para alunos) -->
                <?php if (!$isAluno && !empty($lesson['instructor_notes'])): ?>
                <div style="margin-top: var(--spacing-md); padding-top: var(--spacing-md); border-top: 1px solid var(--color-border, #e0e0e0);">
                    <label class="form-label" style="font-weight: 600; margin-bottom: var(--spacing-sm);">
                        Observações do Instrutor
                        <small style="color: var(--color-text-muted, #666); font-weight: normal;">(privada)</small>
                    </label>
                    <div style="white-space: pre-wrap; background: var(--color-bg-secondary, #f5f5f5); padding: var(--spacing-md); border-radius: var(--radius-sm, 4px); border-left: 4px solid var(--color-primary, #3b82f6); font-size: 0.9375rem; line-height: 1.6;">
                        <?= htmlspecialchars($lesson['instructor_notes']) ?>
                    </div>
                    <small class="text-muted" style="display: block; margin-top: var(--spacing-xs); font-size: 0.8125rem;">
                        Esta observação é privada e não é visível para o aluno.
                    </small>
                </div>
                <?php endif; ?>
                
                <!-- Observações Gerais -->
                <?php if ($lesson['notes']): ?>
                <div style="margin-top: var(--spacing-md);">
                    <label class="form-label">Observações Gerais</label>
                    <div style="white-space: pre-wrap; background: var(--color-bg-secondary, #f5f5f5); padding: var(--spacing-sm); border-radius: var(--radius-sm, 4px);">
                        <?= htmlspecialchars($lesson['notes']) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Timestamps -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md); margin-top: var(--spacing-md); padding-top: var(--spacing-md); border-top: 1px solid var(--color-border, #e0e0e0);">
                    <?php if ($lesson['started_at']): ?>
                    <div>
                        <label class="form-label">Iniciada em</label>
                        <div><small><?= date('d/m/Y H:i:s', strtotime($lesson['started_at'])) ?></small></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($lesson['completed_at']): ?>
                    <div>
                        <label class="form-label">Concluída em</label>
                        <div><small><?= date('d/m/Y H:i:s', strtotime($lesson['completed_at'])) ?></small></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($lesson['canceled_at']): ?>
                    <div>
                        <label class="form-label">Cancelada em</label>
                        <div><small><?= date('d/m/Y H:i:s', strtotime($lesson['canceled_at'])) ?></small></div>
                        <?php if ($lesson['canceled_by_name']): ?>
                        <div><small class="text-muted">Por: <?= htmlspecialchars($lesson['canceled_by_name']) ?></small></div>
                        <?php endif; ?>
                        <?php if ($lesson['cancel_reason']): ?>
                        <div style="margin-top: var(--spacing-xs);">
                            <strong>Motivo:</strong>
                            <div style="background: var(--color-bg-secondary, #f5f5f5); padding: var(--spacing-sm); border-radius: var(--radius-sm, 4px); margin-top: var(--spacing-xs);">
                                <?= nl2br(htmlspecialchars($lesson['cancel_reason'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ações (apenas para perfis administrativos/instrutor) -->
    <?php if (!$isAluno): ?>
    <div>
        <div class="card" style="margin-bottom: var(--spacing-md);">
            <div class="card-header">
                <h3>Ações</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                    <?php if ($lesson['status'] === 'agendada'): ?>
                        <!-- Iniciar Aula -->
                        <a href="<?= base_path("agenda/{$lesson['id']}/iniciar") ?>" class="btn btn-warning" style="width: 100%; text-align: center; display: block;">
                            Iniciar Aula
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($lesson['status'] === 'em_andamento'): ?>
                        <!-- Concluir Aula -->
                        <a href="<?= base_path("agenda/{$lesson['id']}/concluir") ?>" class="btn btn-success" style="width: 100%; text-align: center; display: block;">
                            Concluir Aula
                        </a>
                    <?php endif; ?>
                    
                    <?php if (in_array($lesson['status'], ['agendada', 'em_andamento'])): ?>
                        <!-- Cancelar Aula -->
                        <button type="button" class="btn btn-outline btn-danger" style="width: 100%;" onclick="showCancelModal()">
                            Cancelar Aula
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Informações Adicionais -->
        <div class="card">
            <div class="card-header">
                <h3>Informações Adicionais</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; gap: var(--spacing-sm); font-size: 0.875rem;">
                    <div>
                        <strong>Criada por:</strong><br>
                        <span class="text-muted"><?= htmlspecialchars($lesson['created_by_name'] ?? 'Sistema') ?></span>
                    </div>
                    <div>
                        <strong>Criada em:</strong><br>
                        <span class="text-muted"><?= date('d/m/Y H:i', strtotime($lesson['created_at'])) ?></span>
                    </div>
                    <?php if ($lesson['updated_at'] && $lesson['updated_at'] !== $lesson['created_at']): ?>
                    <div>
                        <strong>Atualizada em:</strong><br>
                        <span class="text-muted"><?= date('d/m/Y H:i', strtotime($lesson['updated_at'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Ações para ALUNO (Solicitar Reagendamento) - apenas para aula prática -->
    <?php if ($isAluno): ?>
    <?php
    $lessonDateTime = new \DateTime("{$lesson['scheduled_date']} {$lesson['scheduled_time']}");
    $now = new \DateTime();
    $isFuture = $lessonDateTime > $now;
    $isScheduled = ($lesson['status'] ?? '') === 'agendada';
    $isTheory = ($lesson['type'] ?? '') === 'teoria' || !empty($lesson['theory_session_id']);
    $canRequestReschedule = $isFuture && $isScheduled && !($hasPendingRequest ?? false) && !$isTheory;
    ?>
    <?php if ((($canRequestReschedule || ($hasPendingRequest ?? false)) && !$isTheory)): ?>
    <div class="card" style="margin-top: var(--spacing-md);">
        <div class="card-header">
            <h3>Ações</h3>
        </div>
        <div class="card-body">
            <?php if ($canRequestReschedule): ?>
            <button type="button" class="btn btn-primary" style="width: 100%;" onclick="showRescheduleModal(<?= $lesson['id'] ?>)">
                Solicitar reagendamento
            </button>
            <?php elseif ($hasPendingRequest ?? false): ?>
            <div class="text-muted" style="text-align: center; padding: var(--spacing-sm);">
                Solicitação de reagendamento pendente. A secretaria entrará em contato.
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

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

<!-- Modal de Cancelamento -->
<div id="cancelModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="max-width: 500px; margin: var(--spacing-lg);">
        <div class="card-header">
            <h3>Cancelar Aula</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= base_path("agenda/{$lesson['id']}/cancelar") ?>" id="cancelForm">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="form-group">
                    <label class="form-label">Motivo do cancelamento <small style="color: var(--color-text-muted, #666);">(opcional)</small></label>
                    <textarea name="reason" class="form-input" rows="3" placeholder="Informe o motivo do cancelamento (opcional)..."></textarea>
                    <small class="form-hint">Se não informar, será registrado como "Sem motivo informado".</small>
                </div>
                <div style="display: flex; gap: var(--spacing-sm); justify-content: flex-end; margin-top: var(--spacing-md);">
                    <button type="button" class="btn btn-outline" onclick="hideCancelModal()">Voltar</button>
                    <button type="submit" class="btn btn-danger" id="confirmCancelBtn">Confirmar Cancelamento</button>
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

// Fechar modal de solicitação ao clicar fora
document.getElementById('rescheduleModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideRescheduleModal();
    }
});

// Prevenir duplo submit no formulário de solicitação
document.getElementById('rescheduleForm')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('submitRescheduleBtn');
    if (btn && btn.disabled) {
        e.preventDefault();
        return false;
    }
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Enviando...';
    }
});

function showCancelModal() {
    document.getElementById('cancelModal').style.display = 'flex';
}

function hideCancelModal() {
    document.getElementById('cancelModal').style.display = 'none';
}

// Fechar modal ao clicar fora
document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideCancelModal();
    }
});

// Prevenir duplo submit no formulário de cancelamento
document.getElementById('cancelForm')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('confirmCancelBtn');
    if (btn && !btn.disabled) {
        btn.disabled = true;
        btn.textContent = 'Cancelando...';
    }
});
</script>

<style>
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
}

.badge-info {
    background: #dbeafe;
    color: #1e40af;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-success {
    background: #d1fae5;
    color: #065f46;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

.badge-secondary {
    background: #f3f4f6;
    color: #374151;
}

/* Responsividade mobile para layout principal */
@media (max-width: 768px) {
    .lesson-details-grid {
        grid-template-columns: 1fr !important;
    }
    
    /* Header responsivo */
    .page-header-content {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    
    .header-actions {
        width: 100%;
        justify-content: stretch;
    }
    
    .header-actions .btn {
        flex: 1;
    }
}

/* Responsividade mobile para grids internos (data/hora, km, timestamps) */
@media (max-width: 480px) {
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
        gap: var(--spacing-sm) !important;
    }
}
</style>
