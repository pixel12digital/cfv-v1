<?php
$lessonCount = count($lessons ?? []);
$kmInicialRef = null;
foreach ($lessons ?? [] as $l) {
    if (($l['status'] ?? '') === 'em_andamento' && !empty($l['km_start'])) {
        $kmInicialRef = $l['km_start'];
        break;
    }
}
$kmInicialRef = $kmInicialRef ?? ($lesson['km_start'] ?? null);
?>
<div class="page-header">
    <div class="page-header-content">
        <div>
            <h1>Finalizar Bloco</h1>
            <p class="text-muted">Registre a quilometragem final para as <?= $lessonCount ?> aulas do bloco</p>
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
            <?php if ($kmInicialRef !== null): ?>
            <div>
                <label class="form-label">KM Inicial</label>
                <div style="font-size: 1.25rem; font-weight: 600; color: var(--color-primary);"><?= number_format($kmInicialRef, 0, ',', '.') ?> km</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Dados para Conclusão do Bloco</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= base_path("agenda/finalizar-bloco") ?>" id="concluirForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="ids" value="<?= htmlspecialchars($ids ?? '') ?>">
            
            <div class="form-group">
                <label class="form-label">Quilometragem Final <span style="color: var(--color-danger);">*</span></label>
                <input type="number" name="km_end" class="form-input" min="<?= $kmInicialRef ?? 0 ?>" step="1" required placeholder="Ex: 12390" style="font-size: 1.25rem; font-weight: 600; text-align: center;">
                <small class="form-hint">
                    Um único valor para todo o bloco
                    <?php if ($kmInicialRef !== null): ?>
                    <br><strong>KM Inicial: <?= number_format($kmInicialRef, 0, ',', '.') ?> km</strong>
                    <?php endif; ?>
                </small>
            </div>
            
            <div style="display: flex; gap: var(--spacing-sm); justify-content: flex-end; margin-top: var(--spacing-md);">
                <a href="<?= base_path("agenda/{$lesson['id']}") ?>" class="btn btn-outline">Cancelar</a>
                <button type="submit" class="btn btn-success" id="submitBtn">Finalizar Bloco</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('concluirForm')?.addEventListener('submit', function(e) {
    var btn = document.getElementById('submitBtn'); if (btn) { btn.disabled = true; btn.textContent = 'Finalizando...'; }
});
<?php if ($kmInicialRef !== null): ?>
document.getElementById('concluirForm')?.addEventListener('submit', function(e) {
    var kmEnd = parseInt(document.querySelector('input[name="km_end"]').value);
    if (kmEnd < <?= $kmInicialRef ?>) { e.preventDefault(); alert('KM final deve ser maior ou igual ao inicial.'); return false; }
});
<?php endif; ?>
</script>
