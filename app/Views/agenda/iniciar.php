<?php
$currentRole = $_SESSION['current_role'] ?? '';
$isInstrutor = ($currentRole === 'INSTRUTOR');
?>
<div class="page-header">
    <div class="page-header-content">
        <div>
            <h1>Iniciar Aula</h1>
            <p class="text-muted">Registre a quilometragem inicial do veículo</p>
        </div>
        <a href="<?= base_path("agenda/{$lesson['id']}") ?>" class="btn btn-outline">Voltar</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Informações da Aula</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; gap: var(--spacing-sm); margin-bottom: var(--spacing-md);">
            <div>
                <label class="form-label">Aluno</label>
                <div><?= htmlspecialchars($lesson['student_name']) ?></div>
            </div>
            <div>
                <label class="form-label">Data e Hora</label>
                <div><?= date('d/m/Y H:i', strtotime("{$lesson['scheduled_date']} {$lesson['scheduled_time']}")) ?></div>
            </div>
            <div>
                <label class="form-label">Veículo</label>
                <div><?= htmlspecialchars($lesson['vehicle_plate'] ?? 'N/A') ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($studentSummary)): ?>
<!-- Resumo do histórico com este aluno (atende pedido do cliente) -->
<div class="card" style="margin-bottom: var(--spacing-md); background: var(--color-bg-light, #f8fafc); border-left: 4px solid var(--color-primary, #3b82f6);">
    <div class="card-body" style="padding: var(--spacing-md);">
        <div style="display: flex; align-items: center; gap: var(--spacing-sm); margin-bottom: var(--spacing-xs);">
            <strong style="color: var(--color-text, #333);">Histórico com este aluno</strong>
        </div>
        <div style="font-size: 0.95rem; color: var(--color-text, #333);">
            <?php 
            $count = $studentSummary['completed_count'];
            $lastDate = $studentSummary['last_lesson_date'];
            $lastTime = $studentSummary['last_lesson_time'];
            $upcoming = $studentSummary['upcoming_count'];
            ?>
            <strong><?= $count ?></strong> aula<?= $count !== 1 ? 's' : '' ?> concluída<?= $count !== 1 ? 's' : '' ?>
            <?php if ($lastDate): ?>
                • Última: <strong><?= date('d/m', strtotime($lastDate)) ?></strong> às <?= date('H:i', strtotime($lastTime)) ?>
            <?php else: ?>
                • Sem aulas anteriores registradas
            <?php endif; ?>
        </div>
        <div style="font-size: 0.875rem; color: var(--color-text-muted, #666); margin-top: var(--spacing-xs);">
            Próximas agendadas: <strong><?= $upcoming ?></strong>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Dados para Início da Aula</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= base_path("agenda/{$lesson['id']}/iniciar") ?>" id="iniciarForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <div class="form-group">
                <label class="form-label">
                    Tipo de Aula <span style="color: var(--color-danger);">*</span>
                </label>
                <div class="practice-type-options" style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
                    <label class="practice-type-option" style="flex: 1; min-width: 100px;">
                        <input type="radio" name="practice_type" value="rua" required style="display: none;">
                        <div class="practice-type-card" style="padding: var(--spacing-md); border: 2px solid var(--color-border, #e2e8f0); border-radius: var(--radius-md, 8px); text-align: center; cursor: pointer; transition: all 0.2s;">
                            <div style="font-weight: 600; font-size: 1rem;">Rua</div>
                        </div>
                    </label>
                    <label class="practice-type-option" style="flex: 1; min-width: 100px;">
                        <input type="radio" name="practice_type" value="garagem" required style="display: none;">
                        <div class="practice-type-card" style="padding: var(--spacing-md); border: 2px solid var(--color-border, #e2e8f0); border-radius: var(--radius-md, 8px); text-align: center; cursor: pointer; transition: all 0.2s;">
                            <div style="font-weight: 600; font-size: 1rem;">Garagem</div>
                        </div>
                    </label>
                    <label class="practice-type-option" style="flex: 1; min-width: 100px;">
                        <input type="radio" name="practice_type" value="baliza" required style="display: none;">
                        <div class="practice-type-card" style="padding: var(--spacing-md); border: 2px solid var(--color-border, #e2e8f0); border-radius: var(--radius-md, 8px); text-align: center; cursor: pointer; transition: all 0.2s;">
                            <div style="font-weight: 600; font-size: 1rem;">Baliza</div>
                        </div>
                    </label>
                </div>
                <small class="form-hint">Selecione o tipo de aula prática</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    Quilometragem Inicial <span style="color: var(--color-danger);">*</span>
                </label>
                <input type="number" 
                       name="km_start" 
                       class="form-input" 
                       min="0" 
                       step="1"
                       required 
                       placeholder="Ex: 12345"
                       style="font-size: 1.25rem; font-weight: 600; text-align: center;">
                <small class="form-hint">Informe a quilometragem atual do veículo</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    Observação Inicial <small style="color: var(--color-text-muted, #666);">(opcional)</small>
                </label>
                <textarea name="instructor_notes" 
                          class="form-input" 
                          rows="3" 
                          placeholder="Observações sobre o início da aula (opcional)..."></textarea>
            </div>
            
            <div style="display: flex; gap: var(--spacing-sm); justify-content: flex-end; margin-top: var(--spacing-md);">
                <a href="<?= base_path("agenda/{$lesson['id']}") ?>" class="btn btn-outline">Cancelar</a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    Iniciar Aula
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Seleção de tipo de aula
document.querySelectorAll('input[name="practice_type"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        // Remover seleção de todos
        document.querySelectorAll('.practice-type-card').forEach(function(card) {
            card.style.borderColor = 'var(--color-border, #e2e8f0)';
            card.style.background = 'transparent';
        });
        // Marcar selecionado
        if (this.checked) {
            var card = this.nextElementSibling;
            card.style.borderColor = 'var(--color-primary, #3b82f6)';
            card.style.background = 'var(--color-primary-light, #eff6ff)';
        }
    });
});

// Prevenir duplo submit
document.getElementById('iniciarForm')?.addEventListener('submit', function(e) {
    // Verificar se tipo foi selecionado
    var tipoSelecionado = document.querySelector('input[name="practice_type"]:checked');
    if (!tipoSelecionado) {
        e.preventDefault();
        alert('Selecione o tipo de aula (Rua, Garagem ou Baliza).');
        return false;
    }
    
    var btn = document.getElementById('submitBtn');
    if (btn && btn.disabled) {
        e.preventDefault();
        return false;
    }
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Iniciando...';
    }
});
</script>

<style>
@media (max-width: 768px) {
    .card {
        margin-bottom: var(--spacing-md);
    }
    
    .form-input[type="number"] {
        font-size: 1.5rem !important;
        padding: var(--spacing-md);
    }
}
</style>
