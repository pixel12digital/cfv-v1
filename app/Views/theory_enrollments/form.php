<div class="page-header">
    <div>
        <h1>Matricular Aluno na Turma</h1>
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
        <form method="POST" action="<?= base_path("turmas-teoricas/{$class['id']}/matriculas/criar") ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label class="form-label" for="student_id">Aluno *</label>
                <select id="student_id" name="student_id" class="form-input" required onchange="loadEnrollments(this.value)">
                    <option value="">Selecione um aluno</option>
                    <?php foreach ($students as $student): ?>
                        <?php $displayName = $student['full_name'] ?: $student['name']; ?>
                        <option value="<?= $student['id'] ?>">
                            <?= htmlspecialchars($displayName) ?>
                            <?php if ($student['cpf']): ?>
                                - CPF: <?= htmlspecialchars($student['cpf']) ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($students)): ?>
                    <small class="form-hint" style="color: var(--color-danger);">
                        Nenhum aluno disponível para matrícula.
                    </small>
                <?php endif; ?>
            </div>

            <!-- Matrícula Principal (carregada dinamicamente) -->
            <div id="enrollment-section" style="display: none;">
                <div class="form-group" id="enrollment-single" style="display: none;">
                    <label class="form-label">Matrícula Principal</label>
                    <div style="padding: var(--spacing-sm); background: var(--color-bg-light); border-radius: var(--border-radius);">
                        <span id="enrollment-single-label"></span>
                    </div>
                    <input type="hidden" id="enrollment_id_single" name="enrollment_id" value="">
                    <small class="form-hint">Vincular automaticamente à matrícula ativa mais recente</small>
                </div>

                <div class="form-group" id="enrollment-multiple" style="display: none;">
                    <label class="form-label" for="enrollment_id_select">Vincular à Matrícula *</label>
                    <select id="enrollment_id_select" name="enrollment_id" class="form-input" required>
                        <option value="">Selecione uma matrícula</option>
                    </select>
                    <small class="form-hint">O aluno possui múltiplas matrículas ativas. Escolha qual vincular.</small>
                </div>

                <div class="form-group" id="enrollment-none" style="display: none;">
                    <div style="padding: var(--spacing-md); background: var(--color-danger-light, #fee); border: 1px solid var(--color-danger); border-radius: var(--border-radius); color: var(--color-danger);">
                        <strong>⚠️ Aluno sem matrícula ativa</strong>
                        <p style="margin: var(--spacing-xs) 0 0 0; font-size: var(--font-size-sm);">
                            Crie/ative uma matrícula antes de vincular à turma.
                        </p>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" id="submit-btn" class="btn btn-primary" <?= empty($students) ? 'disabled' : '' ?>>
                    Matricular Aluno
                </button>
                <a href="<?= base_path("turmas-teoricas/{$class['id']}") ?>" class="btn btn-outline">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function loadEnrollments(studentId) {
    const enrollmentSection = document.getElementById('enrollment-section');
    const enrollmentSingle = document.getElementById('enrollment-single');
    const enrollmentMultiple = document.getElementById('enrollment-multiple');
    const enrollmentNone = document.getElementById('enrollment-none');
    const submitBtn = document.getElementById('submit-btn');
    
    // Reset
    enrollmentSection.style.display = 'none';
    enrollmentSingle.style.display = 'none';
    enrollmentMultiple.style.display = 'none';
    enrollmentNone.style.display = 'none';
    submitBtn.disabled = false;
    // Reset campos
    document.getElementById('enrollment_id_single').disabled = false;
    document.getElementById('enrollment_id_single').value = '';
    const selectMultiple = document.getElementById('enrollment_id_select');
    if (selectMultiple) {
        selectMultiple.value = '';
        selectMultiple.required = false;
    }
    
    if (!studentId) {
        return;
    }
    
    // Buscar matrículas via AJAX
    fetch('<?= base_path("turmas-teoricas/{$class['id']}/matriculas/buscar") ?>?student_id=' + studentId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro ao buscar matrículas: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                console.error('Erro:', data.error);
                enrollmentSection.style.display = 'none';
                return;
            }
            
            enrollmentSection.style.display = 'block';
            
            if (!data.enrollments || data.count === 0) {
                // Sem matrícula ativa
                enrollmentNone.style.display = 'block';
                submitBtn.disabled = true;
            } else if (data.count === 1) {
                // Uma única matrícula → preencher automaticamente
                const enrollment = data.enrollments[0];
                enrollmentSingle.style.display = 'block';
                document.getElementById('enrollment-single-label').textContent = enrollment.label;
                const hiddenInput = document.getElementById('enrollment_id_single');
                hiddenInput.value = enrollment.id;
                hiddenInput.disabled = false;
                // Desabilitar select múltiplo se existir
                const selectMultiple = document.getElementById('enrollment_id_select');
                if (selectMultiple) {
                    selectMultiple.required = false;
                    selectMultiple.value = '';
                }
            } else {
                // Múltiplas matrículas → mostrar select
                enrollmentMultiple.style.display = 'block';
                // Desabilitar o campo hidden da matrícula única
                document.getElementById('enrollment_id_single').disabled = true;
                document.getElementById('enrollment_id_single').value = '';
                const select = document.getElementById('enrollment_id_select');
                select.required = true;
                select.innerHTML = '<option value="">Selecione uma matrícula</option>';
                if (data.enrollments && Array.isArray(data.enrollments)) {
                    data.enrollments.forEach(enrollment => {
                        const option = document.createElement('option');
                        option.value = enrollment.id;
                        option.textContent = enrollment.label;
                        select.appendChild(option);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Erro ao buscar matrículas:', error);
            enrollmentSection.style.display = 'none';
        });
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

.form-hint {
    display: block;
    margin-top: var(--spacing-xs);
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
}
</style>
