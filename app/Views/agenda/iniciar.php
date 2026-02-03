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
                • Última: <strong><?= date('d/m', strtotime($lastDate)) ?></strong><?php if ($lastType): ?> (<?php 
                    $lastTypes = array_map(function($t) use ($typeLabels) { return $typeLabels[trim($t)] ?? trim($t); }, explode(',', $lastType));
                    echo htmlspecialchars(implode(', ', $lastTypes));
                ?>)<?php endif; ?>
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
                        <input type="checkbox" name="practice_type[]" value="rua" style="display: none;">
                        <div class="practice-type-card" data-value="rua" style="padding: var(--spacing-md); border: 2px solid var(--color-border, #e2e8f0); border-radius: var(--radius-md, 8px); text-align: center; cursor: pointer; transition: all 0.2s;">
                            <div style="font-weight: 600; font-size: 1rem;">Rua</div>
                        </div>
                    </label>
                    <label class="practice-type-option" style="flex: 1; min-width: 100px;">
                        <input type="checkbox" name="practice_type[]" value="garagem" style="display: none;">
                        <div class="practice-type-card" data-value="garagem" style="padding: var(--spacing-md); border: 2px solid var(--color-border, #e2e8f0); border-radius: var(--radius-md, 8px); text-align: center; cursor: pointer; transition: all 0.2s;">
                            <div style="font-weight: 600; font-size: 1rem;">Garagem</div>
                        </div>
                    </label>
                    <label class="practice-type-option" style="flex: 1; min-width: 100px;">
                        <input type="checkbox" name="practice_type[]" value="baliza" style="display: none;">
                        <div class="practice-type-card" data-value="baliza" style="padding: var(--spacing-md); border: 2px solid var(--color-border, #e2e8f0); border-radius: var(--radius-md, 8px); text-align: center; cursor: pointer; transition: all 0.2s;">
                            <div style="font-weight: 600; font-size: 1rem;">Baliza</div>
                        </div>
                    </label>
                </div>
                <small class="form-hint">Selecione um ou mais tipos de aula prática (pode incluir Rua, Garagem e Baliza no mesmo bloco)</small>
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
// Seleção múltipla de tipo de aula (checkbox)
document.querySelectorAll('.practice-type-option').forEach(function(option) {
    var checkbox = option.querySelector('input[type="checkbox"]');
    var card = option.querySelector('.practice-type-card');
    if (!checkbox || !card) return;
    
    function updateCardStyle() {
        if (checkbox.checked) {
            card.style.borderColor = 'var(--color-primary, #3b82f6)';
            card.style.background = 'var(--color-primary-light, #eff6ff)';
        } else {
            card.style.borderColor = 'var(--color-border, #e2e8f0)';
            card.style.background = 'transparent';
        }
    }
    
    option.addEventListener('click', function(e) {
        e.preventDefault();
        checkbox.checked = !checkbox.checked;
        updateCardStyle();
    });
    
    checkbox.addEventListener('change', updateCardStyle);
});

// Prevenir duplo submit
document.getElementById('iniciarForm')?.addEventListener('submit', function(e) {
    // Verificar se pelo menos um tipo foi selecionado
    var tiposSelecionados = document.querySelectorAll('input[name="practice_type[]"]:checked');
    if (!tiposSelecionados || tiposSelecionados.length === 0) {
        e.preventDefault();
        alert('Selecione pelo menos um tipo de aula (Rua, Garagem ou Baliza).');
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
