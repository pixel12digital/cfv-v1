<?php
$isEdit = isset($lesson) && $lesson;
$pageTitle = $isEdit ? 'Remarcar Aula' : 'Nova Aula';
?>

<div class="page-header">
    <div class="page-header-content">
        <div>
            <h1><?= $pageTitle ?></h1>
            <p class="text-muted"><?= $isEdit ? 'Altere os dados da aula' : 'Agende uma nova aula' ?></p>
        </div>
        <a href="<?= base_path('agenda') ?>" class="btn btn-outline">
            Voltar
        </a>
    </div>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-body">
        <form method="POST" action="<?= base_path($isEdit ? "agenda/{$lesson['id']}/atualizar" : 'agenda/criar') ?>" id="instructorForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <?php if ($isEdit): ?>
            <input type="hidden" name="lesson_id" value="<?= $lesson['id'] ?>">
            <?php endif; ?>
            
            <!-- Aluno e Matrícula -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                <div class="form-group">
                    <label class="form-label">Aluno *</label>
                    <?php if ($isEdit): ?>
                        <input type="text" class="form-input" value="<?= htmlspecialchars($lesson['student_name'] ?? '') ?>" disabled>
                        <input type="hidden" name="student_id" value="<?= $lesson['student_id'] ?>">
                    <?php else: ?>
                        <?php if ($student): ?>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($student['name'] ?? $student['full_name'] ?? '') ?>" disabled>
                            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                        <?php else: ?>
                            <select name="student_id" id="student_id" class="form-input" required onchange="loadEnrollments(this.value)">
                                <option value="">Selecione um aluno</option>
                                <?php if (!empty($students)): ?>
                                    <?php foreach ($students as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= ($studentId ?? '') == $s['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['full_name'] ?? $s['name']) ?> 
                                            <?= !empty($s['cpf']) ? ' - ' . htmlspecialchars($s['cpf']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>Nenhum aluno cadastrado</option>
                                <?php endif; ?>
                            </select>
                            <?php if (empty($students)): ?>
                                <small class="form-hint" style="color: #ef4444;">
                                    ⚠️ Nenhum aluno cadastrado. <a href="<?= base_path('alunos/novo') ?>">Cadastre um aluno primeiro</a>.
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Matrícula *</label>
                    <?php if ($isEdit): ?>
                        <input type="text" class="form-input" value="Matrícula #<?= $lesson['enrollment_id'] ?>" disabled>
                        <input type="hidden" name="enrollment_id" value="<?= $lesson['enrollment_id'] ?>">
                    <?php else: ?>
                        <select name="enrollment_id" id="enrollment_id" class="form-input" required onchange="updateEnrollmentCounter()">
                            <option value="">Selecione uma matrícula</option>
                            <?php if (!empty($enrollments)): ?>
                                <?php foreach ($enrollments as $enr): ?>
                                <option value="<?= $enr['id'] ?>"
                                    data-aulas-contratadas="<?= $enr['aulas_contratadas'] ?? '' ?>"
                                    data-aulas-agendadas="<?= $enr['aulas_agendadas'] ?? 0 ?>"
                                    data-aulas-faltantes="<?= $enr['aulas_faltantes'] ?? '' ?>"
                                    <?= ($enrollment && $enrollment['id'] == $enr['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($enr['service_name'] ?? 'Matrícula') ?> - 
                                    <?= $enr['financial_status'] === 'bloqueado' ? '⚠️ BLOQUEADO' : '✅ Ativa' ?>
                                </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>Selecione um aluno primeiro</option>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($enrollments) && !$student): ?>
                            <small class="form-hint">
                                Selecione um aluno para carregar as matrículas
                            </small>
                        <?php elseif (empty($enrollments) && $student): ?>
                            <small class="form-hint" style="color: #ef4444;">
                                ⚠️ Este aluno não possui matrículas. <a href="<?= base_path('matriculas/novo?student_id=' . $student['id']) ?>">Criar matrícula</a>.
                            </small>
                        <?php endif; ?>
                        <?php if (!empty($enrollments)): ?>
                        <small class="form-hint">
                            <?php 
                            $blocked = array_filter($enrollments, fn($e) => $e['financial_status'] === 'bloqueado');
                            if (!empty($blocked)): 
                            ?>
                            ⚠️ Algumas matrículas estão bloqueadas financeiramente
                            <?php endif; ?>
                        </small>
                        <?php endif; ?>
                        <div id="enrollment_counter" class="alert alert-info" style="margin-top: var(--spacing-sm); display: none;">
                            <strong>Aulas:</strong> <span id="counter_text"></span>
                        </div>
                        <div id="enrollment_no_aulas_warning" class="alert alert-warning" style="margin-top: var(--spacing-sm); display: none;">
                            <strong>⚠️ Esta matrícula não tem aulas práticas contratadas.</strong> Edite a matrícula e defina a quantidade antes de agendar.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!$isEdit): ?>
            <!-- Blocos de agendamento (permite múltiplos blocos em um único envio) -->
            <div id="blocks_container">
                <div class="block-item" data-block-index="0">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-sm);">
                        <strong>Bloco 1</strong>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                        <div class="form-group">
                            <label class="form-label">Instrutor *</label>
                            <select name="blocks[0][instructor_id]" class="form-input block-instructor" required>
                                <option value="">Selecione um instrutor</option>
                                <?php foreach ($instructors as $instructor): ?>
                                <option value="<?= $instructor['id'] ?>"><?= htmlspecialchars($instructor['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Veículo *</label>
                            <select name="blocks[0][vehicle_id]" class="form-input block-vehicle" required>
                                <option value="">Selecione um veículo</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['plate']) ?> - <?= htmlspecialchars($vehicle['model'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Data *</label>
                            <input type="date" name="blocks[0][scheduled_date]" class="form-input block-date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Hora *</label>
                            <input type="time" name="blocks[0][scheduled_time]" class="form-input block-time" value="08:00" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Quantidade *</label>
                            <select name="blocks[0][lesson_count]" class="form-input block-qty" onchange="updateTotalAndCounter()" required>
                                <?php for ($q = 1; $q <= 6; $q++): ?>
                                <option value="<?= $q ?>" <?= $q === 1 ? 'selected' : '' ?>><?= $q ?></option>
                                <?php endfor; ?>
                            </select>
                            <small class="form-hint block-hint">1 aula de 50 min</small>
                        </div>
                    </div>
                </div>
            </div>
            <div style="margin-bottom: var(--spacing-md);">
                <button type="button" id="btn_add_block" class="btn btn-outline btn-sm">+ Adicionar outro bloco</button>
            </div>
            <input type="hidden" name="duration_minutes" value="<?= \App\Config\Constants::DURACAO_AULA_PADRAO ?>">
            <?php endif; ?>
            
            <?php if ($isEdit): ?>
            <!-- Instrutor (modo edição) -->
            <div class="form-group" style="margin-bottom: var(--spacing-md);">
                <label class="form-label">Instrutor *</label>
                <select name="instructor_id" class="form-input" required>
                    <option value="">Selecione um instrutor</option>
                    <?php foreach ($instructors as $instructor): ?>
                    <option value="<?= $instructor['id'] ?>" <?= ($isEdit && $lesson['instructor_id'] == $instructor['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($instructor['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Veículo -->
            <div class="form-group" style="margin-bottom: var(--spacing-md);">
                <label class="form-label">Veículo *</label>
                <select name="vehicle_id" class="form-input" required>
                    <option value="">Selecione um veículo</option>
                    <?php foreach ($vehicles as $vehicle): ?>
                    <option value="<?= $vehicle['id'] ?>" <?= ($isEdit && $lesson['vehicle_id'] == $vehicle['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($vehicle['plate']) ?> - <?= htmlspecialchars($vehicle['model'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Data e Hora -->
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                <div class="form-group">
                    <label class="form-label">Data *</label>
                    <input type="date" name="scheduled_date" class="form-input" 
                           value="<?= $isEdit ? htmlspecialchars($lesson['scheduled_date']) : date('Y-m-d') ?>" 
                           min="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Hora *</label>
                    <input type="time" name="scheduled_time" class="form-input" 
                           value="<?= $isEdit ? htmlspecialchars($lesson['scheduled_time']) : '08:00' ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Duração (minutos)</label>
                    <input type="number" name="duration_minutes" class="form-input" 
                           value="<?= htmlspecialchars($lesson['duration_minutes']) ?>" 
                           min="30" max="120" step="10" required>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Observações -->
            <div class="form-group" style="margin-bottom: var(--spacing-md);">
                <label class="form-label">Observações</label>
                <textarea name="notes" class="form-input" rows="3" placeholder="Observações sobre a aula..."><?= $isEdit ? htmlspecialchars($lesson['notes'] ?? '') : '' ?></textarea>
            </div>
            
            <?php if ($isEdit && in_array($lesson['status'], ['concluida', 'cancelada'])): ?>
            <div class="alert alert-warning">
                Esta aula já foi <?= $lesson['status'] === 'concluida' ? 'concluída' : 'cancelada' ?>. Não é possível editá-la.
            </div>
            <?php endif; ?>
            
            <!-- Botões -->
            <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-lg);">
                <a href="<?= base_path('agenda') ?>" class="btn btn-outline">Cancelar</a>
                <button type="submit" id="submitBtn" class="btn btn-primary" <?= $isEdit && in_array($lesson['status'], ['concluida', 'cancelada']) ? 'disabled' : '' ?>>
                    <?= $isEdit ? 'Remarcar Aula' : 'Agendar Aula' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Prevenir duplo submit e validar limite de aulas
const form = document.getElementById('instructorForm');
if (form) {
    form.addEventListener('submit', function(e) {
        <?php if (!$isEdit): ?>
        const data = getEnrollmentData();
        if (data) {
            if (data.contratadas === null || data.contratadas <= 0) {
                e.preventDefault();
                alert('Esta matrícula não tem aulas práticas contratadas. Edite a matrícula e defina a quantidade antes de agendar.');
                return;
            }
            if (data.faltantes !== null) {
                const total = getTotalLessonsInBlocks();
                if (total > data.faltantes) {
                    e.preventDefault();
                    alert('Total de aulas neste agendamento (' + total + ') excede o permitido pela matrícula (' + data.faltantes + ' faltantes).');
                    return;
                }
            }
        }
        <?php endif; ?>
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn && !submitBtn.disabled) {
            submitBtn.disabled = true;
            submitBtn.textContent = '<?= $isEdit ? 'Remarcando...' : 'Agendando...' ?>';
        }
    });
}

function getEnrollmentData() {
    const sel = document.getElementById('enrollment_id');
    if (!sel || !sel.selectedOptions[0]) return null;
    const opt = sel.selectedOptions[0];
    const contratadas = opt.dataset.aulasContratadas;
    const agendadas = parseInt(opt.dataset.aulasAgendadas || '0');
    const faltantes = opt.dataset.aulasFaltantes;
    return {
        contratadas: contratadas !== '' && contratadas !== undefined ? parseInt(contratadas) : null,
        agendadas: agendadas,
        faltantes: faltantes !== '' && faltantes !== undefined ? parseInt(faltantes) : null
    };
}

function updateEnrollmentCounter() {
    const counter = document.getElementById('enrollment_counter');
    const text = document.getElementById('counter_text');
    const warning = document.getElementById('enrollment_no_aulas_warning');
    if (!counter || !text) return;
    const data = getEnrollmentData();
    if (!data) {
        counter.style.display = 'none';
        if (warning) warning.style.display = 'none';
        return;
    }
    if (data.contratadas === null || data.contratadas <= 0) {
        counter.style.display = 'none';
        if (warning) warning.style.display = 'block';
        return;
    }
    if (warning) warning.style.display = 'none';
    counter.style.display = 'block';
    const totalNesteEnvio = getTotalLessonsInBlocks();
    const faltantesApos = data.faltantes - totalNesteEnvio;
    text.textContent = data.contratadas + ' contratadas, ' + data.agendadas + ' já agendadas, ' + data.faltantes + ' faltantes. Neste envio: ' + totalNesteEnvio + ' (restarão ' + Math.max(0, faltantesApos) + ')';
}

function getTotalLessonsInBlocks() {
    let total = 0;
    document.querySelectorAll('.block-qty').forEach(function(el) {
        total += parseInt(el.value || '0');
    });
    return total;
}

function updateBlockHints() {
    document.querySelectorAll('.block-item').forEach(function(block) {
        const qtyEl = block.querySelector('.block-qty');
        const hintEl = block.querySelector('.block-hint');
        if (qtyEl && hintEl) {
            const q = parseInt(qtyEl.value || '1');
            hintEl.textContent = q === 1 ? '1 aula de 50 min' : q + ' aulas de 50 min consecutivas';
        }
    });
}

function updateTotalAndCounter() {
    updateBlockHints();
    updateEnrollmentCounter();
}

// Inicializar
<?php if (!$isEdit): ?>
document.addEventListener('DOMContentLoaded', function() {
    updateEnrollmentCounter();
    updateBlockHints();
    document.getElementById('btn_add_block').addEventListener('click', function() {
        const container = document.getElementById('blocks_container');
        const blocks = container.querySelectorAll('.block-item');
        const lastBlock = blocks[blocks.length - 1];
        const nextIndex = blocks.length;
        const clone = lastBlock.cloneNode(true);
        clone.setAttribute('data-block-index', nextIndex);
        clone.querySelector('strong').textContent = 'Bloco ' + (nextIndex + 1);
        clone.querySelectorAll('[name]').forEach(function(el) {
            el.name = el.name.replace(/blocks\[\d+\]/, 'blocks[' + nextIndex + ']');
        });
        clone.querySelector('.block-qty').value = '1';
        clone.querySelector('.block-hint').textContent = '1 aula de 50 min';
        clone.querySelector('.block-date').value = '<?= date("Y-m-d") ?>';
        clone.querySelector('.block-time').value = '08:00';
        clone.querySelector('.block-instructor').value = '';
        clone.querySelector('.block-vehicle').value = '';
        clone.querySelector('.block-instructor').required = true;
        clone.querySelector('.block-vehicle').required = true;
        container.appendChild(clone);
        updateTotalAndCounter();
    });
    document.getElementById('enrollment_id').addEventListener('change', updateEnrollmentCounter);
});
<?php endif; ?>

// Carregar matrículas quando aluno for selecionado
function loadEnrollments(studentId) {
    const enrollmentSelect = document.getElementById('enrollment_id');
    
    if (!studentId) {
        enrollmentSelect.innerHTML = '<option value="">Selecione um aluno primeiro</option>';
        enrollmentSelect.disabled = true;
        return;
    }
    
    enrollmentSelect.innerHTML = '<option value="">Carregando...</option>';
    enrollmentSelect.disabled = true;
    
    // Buscar matrículas do aluno via AJAX
    fetch('<?= base_path("api/students") ?>/' + studentId + '/enrollments')
        .then(response => response.json())
        .then(data => {
            enrollmentSelect.innerHTML = '<option value="">Selecione uma matrícula</option>';
            
            if (data.success && data.enrollments && data.enrollments.length > 0) {
                data.enrollments.forEach(function(enr) {
                    const option = document.createElement('option');
                    option.value = enr.id;
                    option.dataset.aulasContratadas = enr.aulas_contratadas != null ? enr.aulas_contratadas : '';
                    option.dataset.aulasAgendadas = enr.aulas_agendadas != null ? enr.aulas_agendadas : 0;
                    option.dataset.aulasFaltantes = enr.aulas_faltantes != null ? enr.aulas_faltantes : '';
                    const status = enr.financial_status === 'bloqueado' ? '⚠️ BLOQUEADO' : '✅ Ativa';
                    option.textContent = (enr.service_name || 'Matrícula') + ' - ' + status;
                    enrollmentSelect.appendChild(option);
                });
                enrollmentSelect.disabled = false;
                updateEnrollmentCounter();
            } else {
                enrollmentSelect.innerHTML = '<option value="" disabled>Nenhuma matrícula encontrada</option>';
                enrollmentSelect.disabled = true;
                
                // Mostrar mensagem
                const hint = document.createElement('small');
                hint.className = 'form-hint';
                hint.style.color = '#ef4444';
                hint.innerHTML = '⚠️ Este aluno não possui matrículas. <a href="<?= base_path("matriculas/novo") ?>?student_id=' + studentId + '">Criar matrícula</a>.';
                
                // Remover hint anterior se existir
                const existingHint = enrollmentSelect.parentElement.querySelector('.form-hint');
                if (existingHint) {
                    existingHint.remove();
                }
                
                enrollmentSelect.parentElement.appendChild(hint);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar matrículas:', error);
            enrollmentSelect.innerHTML = '<option value="">Erro ao carregar matrículas</option>';
            enrollmentSelect.disabled = true;
        });
}

// Inicializar estado do select de matrículas
<?php if (!$isEdit && !$student): ?>
document.getElementById('enrollment_id').disabled = true;
<?php endif; ?>
</script>

