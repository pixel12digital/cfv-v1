<?php
$lessonCount = count($lessons ?? []);
?>
<div class="page-header">
    <div class="page-header-content">
        <div>
            <h1>Iniciar Bloco</h1>
            <p class="text-muted">Registre a quilometragem inicial para as <?= $lessonCount ?> aulas do bloco</p>
        </div>
        <a href="<?= base_path("agenda/{$lesson['id']}") ?>" class="btn btn-outline">Voltar</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Informações do Bloco</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; gap: var(--spacing-sm); margin-bottom: var(--spacing-md);">
            <div>
                <label class="form-label">Aluno</label>
                <div><?= htmlspecialchars($lesson['student_name']) ?></div>
            </div>
            <div>
                <label class="form-label">Veículo</label>
                <div><?= htmlspecialchars($lesson['vehicle_plate'] ?? 'N/A') ?></div>
            </div>
            <div>
                <label class="form-label">Aulas no bloco</label>
                <div>
                    <?php foreach ($lessons as $idx => $l): ?>
                    <span style="margin-right: 8px; font-size: 0.9rem;">Aula <?= $idx + 1 ?>: <?= date('H:i', strtotime($l['scheduled_time'])) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($studentSummary)): ?>
<div class="card" style="margin-bottom: var(--spacing-md); background: var(--color-bg-light, #f8fafc); border-left: 4px solid var(--color-primary, #3b82f6);">
    <div class="card-body" style="padding: var(--spacing-md);">
        <strong style="color: var(--color-text, #333);">Histórico com este aluno</strong>
        <div style="font-size: 0.9rem; margin-top: 6px;">
            <?= $studentSummary['completed_count'] ?? 0 ?> aula(s) concluída(s) • Próximas agendadas: <?= $studentSummary['upcoming_count'] ?? 0 ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Dados para Início do Bloco</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= base_path("agenda/iniciar-bloco") ?>" id="iniciarForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="ids" value="<?= htmlspecialchars($ids ?? '') ?>">
            
            <div class="form-group">
                <label class="form-label">Tipo de Aula <span style="color: var(--color-danger);">*</span></label>
                <div class="practice-type-options" style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
                    <label class="practice-type-option" style="flex: 1; min-width: 100px;">
                        <input type="checkbox" name="practice_type[]" value="rua" style="display: none;">
                        <div class="practice-type-card" data-value="rua" style="padding: var(--spacing-md); border: 2px solid var(--color-border, #e2e8f0); border-radius: var(--radius-md, 8px); text-align: center; cursor: pointer;">Rua</div>
                    </label>
                    <label class="practice-type-option" style="flex: 1; min-width: 100px;">
                        <input type="checkbox" name="practice_type[]" value="garagem" style="display: none;">
                        <div class="practice-type-card" data-value="garagem" style="padding: var(--spacing-md); border: 2px solid var(--color-border, #e2e8f0); border-radius: var(--radius-md, 8px); text-align: center; cursor: pointer;">Garagem</div>
                    </label>
                    <label class="practice-type-option" style="flex: 1; min-width: 100px;">
                        <input type="checkbox" name="practice_type[]" value="baliza" style="display: none;">
                        <div class="practice-type-card" data-value="baliza" style="padding: var(--spacing-md); border: 2px solid var(--color-border, #e2e8f0); border-radius: var(--radius-md, 8px); text-align: center; cursor: pointer;">Baliza</div>
                    </label>
                </div>
                <small class="form-hint">Será aplicado a todas as aulas do bloco</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Quilometragem Inicial <span style="color: var(--color-danger);">*</span></label>
                <input type="number" name="km_start" class="form-input" min="0" step="1" required placeholder="Ex: 12345" style="font-size: 1.25rem; font-weight: 600; text-align: center;">
                <small class="form-hint">Um único valor para todo o bloco</small>
            </div>
            
            <div style="display: flex; gap: var(--spacing-sm); justify-content: flex-end; margin-top: var(--spacing-md);">
                <a href="<?= base_path("agenda/{$lesson['id']}") ?>" class="btn btn-outline">Cancelar</a>
                <button type="submit" class="btn btn-primary" id="submitBtn">Iniciar Bloco</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.practice-type-option').forEach(function(option) {
    var checkbox = option.querySelector('input[type="checkbox"]');
    var card = option.querySelector('.practice-type-card');
    if (!checkbox || !card) return;
    option.addEventListener('click', function(e) { e.preventDefault(); checkbox.checked = !checkbox.checked; card.style.borderColor = checkbox.checked ? 'var(--color-primary)' : 'var(--color-border)'; card.style.background = checkbox.checked ? 'var(--color-primary)' : 'transparent'; card.style.color = checkbox.checked ? '#fff' : ''; });
});
document.getElementById('iniciarForm')?.addEventListener('submit', function(e) {
    if (!document.querySelectorAll('input[name="practice_type[]"]:checked').length) { e.preventDefault(); alert('Selecione pelo menos um tipo de aula.'); return false; }
    var btn = document.getElementById('submitBtn'); if (btn) { btn.disabled = true; btn.textContent = 'Iniciando...'; }
});
</script>
