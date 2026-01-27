<div class="page-header">
    <div>
        <h1>Editar Matrícula</h1>
        <p class="text-muted">Aluno: <?= htmlspecialchars($enrollment['student_name']) ?></p>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-sm); align-items: center;">
        <a href="<?= base_path("alunos/{$enrollment['student_id']}?tab=matricula") ?>" class="btn btn-outline">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Voltar
        </a>
        <span style="color: var(--color-text-muted); font-size: var(--font-size-sm);">|</span>
        <a href="#" class="btn btn-outline btn-sm" id="matricula-cta-wa" data-phone="<?= htmlspecialchars($studentPhoneForWa ?? '') ?>" data-message="<?= htmlspecialchars($waMessage ?? '') ?>" data-install-url="<?= htmlspecialchars($installUrl ?? '') ?>" <?= empty($hasValidPhone) ? ' style="pointer-events: none; opacity: 0.6;"' : '' ?>>Enviar app no WhatsApp</a>
        <button type="button" class="btn btn-outline btn-sm" id="matricula-cta-copy" data-install-url="<?= htmlspecialchars($installUrl ?? '') ?>">Copiar link</button>
        <?php if (empty($hasValidPhone)): ?>
        <span class="text-muted" style="font-size: var(--font-size-sm);">Aluno sem telefone.</span>
        <?php endif; ?>
        <?php if (!empty($installLinkError)): ?>
        <span class="alert alert-warning" style="margin: 0; padding: var(--spacing-xs) var(--spacing-sm); font-size: var(--font-size-sm);"><?= htmlspecialchars($installLinkError) ?></span>
        <?php endif; ?>
    </div>
</div>
<script>
(function(){
    var waEl = document.getElementById('matricula-cta-wa');
    var copyEl = document.getElementById('matricula-cta-copy');
    if (waEl && waEl.dataset.phone && waEl.dataset.message) {
        waEl.addEventListener('click', function(e){ e.preventDefault(); if (this.dataset.phone) window.open('https://wa.me/' + this.dataset.phone + '?text=' + encodeURIComponent(this.dataset.message), '_blank'); });
    }
    if (copyEl && copyEl.dataset.installUrl) {
        copyEl.addEventListener('click', function(){
            var u = this.dataset.installUrl;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(u).then(function(){ if (typeof alert === 'function') alert('Link copiado.'); });
            } else {
                var inp = document.createElement('input'); inp.value = u; inp.setAttribute('readonly',''); inp.style.position = 'absolute'; inp.style.left = '-9999px'; document.body.appendChild(inp); inp.select(); document.execCommand('copy'); document.body.removeChild(inp); alert('Link copiado.');
            }
        });
    }
})();
</script>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= base_path("matriculas/{$enrollment['id']}/atualizar") ?>" id="enrollmentForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label class="form-label">Serviço</label>
                <input 
                    type="text" 
                    class="form-input" 
                    value="<?= htmlspecialchars($enrollment['service_name']) ?>" 
                    readonly
                    style="background-color: var(--color-bg-light);"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="base_price_display">Preço Base</label>
                <input 
                    type="text" 
                    id="base_price_display" 
                    class="form-input" 
                    value="R$ <?= number_format($enrollment['base_price'], 2, ',', '.') ?>" 
                    readonly
                    style="background-color: var(--color-bg-light);"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="discount_value">Desconto (R$)</label>
                <input 
                    type="number" 
                    id="discount_value" 
                    name="discount_value" 
                    class="form-input" 
                    value="<?= number_format($enrollment['discount_value'], 2, '.', '') ?>" 
                    step="0.01"
                    min="0"
                    onchange="calculateFinal()"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="extra_value">Acréscimo (R$)</label>
                <input 
                    type="number" 
                    id="extra_value" 
                    name="extra_value" 
                    class="form-input" 
                    value="<?= number_format($enrollment['extra_value'], 2, '.', '') ?>" 
                    step="0.01"
                    min="0"
                    onchange="calculateFinal()"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="final_price_display">Valor Final</label>
                <input 
                    type="text" 
                    id="final_price_display" 
                    class="form-input" 
                    value="R$ <?= number_format($enrollment['final_price'], 2, ',', '.') ?>" 
                    readonly
                    style="background-color: var(--color-bg-light); font-weight: var(--font-weight-semibold); font-size: var(--font-size-lg);"
                >
                <input type="hidden" id="final_price" name="final_price" value="<?= $enrollment['final_price'] ?>">
            </div>

            <!-- Seção Entrada (Edição) -->
            <div style="margin-top: 1.5rem; padding: 1rem; background: var(--color-bg-light); border: 1px solid var(--color-border); border-radius: var(--border-radius);">
                <h3 style="margin-top: 0; margin-bottom: 1rem; font-size: var(--font-size-md); font-weight: var(--font-weight-semibold);">Entrada (Opcional)</h3>
                
                <div class="form-group">
                    <label class="form-label" for="entry_amount">Valor da Entrada (R$)</label>
                    <input 
                        type="number" 
                        id="entry_amount" 
                        name="entry_amount" 
                        class="form-input" 
                        step="0.01"
                        min="0"
                        value="<?= !empty($enrollment['entry_amount']) ? number_format($enrollment['entry_amount'], 2, '.', '') : '' ?>"
                        placeholder="0.00"
                        onchange="calculateOutstanding()"
                    >
                    <small class="text-muted">Deixe em branco se não houver entrada</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="entry_payment_method">Forma de Pagamento da Entrada</label>
                    <select id="entry_payment_method" name="entry_payment_method" class="form-select">
                        <option value="">Selecione (se houver entrada)</option>
                        <option value="dinheiro" <?= ($enrollment['entry_payment_method'] ?? '') === 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                        <option value="pix" <?= ($enrollment['entry_payment_method'] ?? '') === 'pix' ? 'selected' : '' ?>>PIX</option>
                        <option value="cartao" <?= ($enrollment['entry_payment_method'] ?? '') === 'cartao' ? 'selected' : '' ?>>Cartão</option>
                        <option value="boleto" <?= ($enrollment['entry_payment_method'] ?? '') === 'boleto' ? 'selected' : '' ?>>Boleto</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="entry_payment_date">Data da Entrada</label>
                    <input 
                        type="date" 
                        id="entry_payment_date" 
                        name="entry_payment_date" 
                        class="form-input"
                        value="<?= !empty($enrollment['entry_payment_date']) ? $enrollment['entry_payment_date'] : date('Y-m-d') ?>"
                    >
                    <small class="text-muted">Data em que a entrada foi/será recebida</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="outstanding_amount_display">Saldo Devedor</label>
                    <input 
                        type="text" 
                        id="outstanding_amount_display" 
                        class="form-input" 
                        value="R$ <?= number_format($enrollment['outstanding_amount'] ?? $enrollment['final_price'], 2, ',', '.') ?>" 
                        readonly
                        style="background-color: var(--color-bg); font-weight: var(--font-weight-semibold); font-size: var(--font-size-md); color: var(--color-primary);"
                    >
                    <input type="hidden" id="outstanding_amount" name="outstanding_amount" value="<?= $enrollment['outstanding_amount'] ?? $enrollment['final_price'] ?>">
                    <small class="text-muted">Valor que será cobrado no Gateway (Efí)</small>
                </div>
            </div>


            <?php
            // Verificar se pode editar condições de pagamento (não pode se já gerou cobrança)
            $billingStatus = $enrollment['billing_status'] ?? 'draft';
            $canEditPaymentPlan = ($billingStatus === 'draft' || $billingStatus === 'ready' || $billingStatus === 'error');
            ?>

            <div class="form-group">
                <label class="form-label" for="payment_method">Forma de Pagamento *</label>
                <select id="payment_method" name="payment_method" class="form-select" required onchange="togglePixAccountSelector()">
                    <option value="pix" <?= $enrollment['payment_method'] === 'pix' ? 'selected' : '' ?>>PIX</option>
                    <option value="boleto" <?= $enrollment['payment_method'] === 'boleto' ? 'selected' : '' ?>>Boleto</option>
                    <option value="cartao" <?= $enrollment['payment_method'] === 'cartao' ? 'selected' : '' ?>>Cartão</option>
                    <option value="entrada_parcelas" <?= $enrollment['payment_method'] === 'entrada_parcelas' ? 'selected' : '' ?>>Entrada + Parcelas</option>
                </select>
            </div>

            <!-- Seletor de Conta PIX (aparece quando payment_method = 'pix') -->
            <div id="pixAccountSelector" style="display: <?= $enrollment['payment_method'] === 'pix' ? 'block' : 'none' ?>; margin-top: var(--spacing-md);">
                <div class="form-group">
                    <label class="form-label" for="pix_account_id">Conta PIX</label>
                    <select id="pix_account_id" name="pix_account_id" class="form-select">
                        <option value="">Selecione uma conta PIX (opcional)</option>
                        <?php if (!empty($pixAccounts)): ?>
                            <?php foreach ($pixAccounts as $account): ?>
                                <option value="<?= $account['id'] ?>" 
                                    <?= (!empty($enrollment['pix_account_id']) && $enrollment['pix_account_id'] == $account['id']) ? 'selected' : '' ?>
                                    <?= ($account['is_default'] ?? 0) ? 'data-default="1"' : '' ?>>
                                    <?= htmlspecialchars($account['label']) ?> 
                                    <?php if (!empty($account['bank_name'])): ?>
                                        - <?= htmlspecialchars($account['bank_name']) ?>
                                    <?php endif; ?>
                                    <?php if ($account['is_default'] ?? 0): ?>
                                        (Padrão)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>Nenhuma conta PIX cadastrada</option>
                        <?php endif; ?>
                    </select>
                    <small class="form-hint">
                        Selecione qual conta PIX será usada para este pagamento. 
                        Se não selecionar, será usada a conta padrão.
                    </small>
                </div>
            </div>

            <!-- Campos de Parcelamento (Editáveis quando permitido) -->
            <?php if ($canEditPaymentPlan): ?>
            <div id="payment_plan_fields" style="margin-top: 1.5rem;">
                <!-- Campo Parcelas -->
                <div class="form-group" id="installments_field" style="display: none;">
                    <label class="form-label" for="installments">Número de Parcelas *</label>
                    <input 
                        type="number" 
                        id="installments" 
                        name="installments" 
                        class="form-input"
                        min="1"
                        max="<?= ($enrollment['payment_method'] ?? '') === 'cartao' ? 24 : 12 ?>"
                        value="<?= !empty($enrollment['installments']) ? $enrollment['installments'] : 1 ?>"
                        required
                    >
                    <small class="text-muted" id="installments_help">Número de parcelas para o saldo devedor</small>
                </div>

                <!-- Campo Data do Primeiro Vencimento (para Boleto e PIX) -->
                <div class="form-group" id="first_due_date_field" style="display: none;">
                    <label class="form-label" for="first_due_date">Data do Primeiro Vencimento *</label>
                    <input 
                        type="date" 
                        id="first_due_date" 
                        name="first_due_date" 
                        class="form-input"
                        value="<?= !empty($enrollment['first_due_date']) ? $enrollment['first_due_date'] : '' ?>"
                    >
                    <small class="text-muted">Data de vencimento da primeira parcela do saldo devedor (boleto/pix)</small>
                </div>
            </div>
            <?php endif; ?>

            <!-- Seção Condições de Pagamento (Exibição - Somente Leitura quando não pode editar) -->
            <?php if (!$canEditPaymentPlan && (!empty($enrollment['installments']) || !empty($enrollment['down_payment_amount']))): ?>
            <div style="margin-top: 1.5rem; padding: 1rem; background: var(--color-bg-light); border: 1px solid var(--color-border); border-radius: var(--border-radius);">
                <h3 style="margin-top: 0; margin-bottom: 1rem; font-size: var(--font-size-md); font-weight: var(--font-weight-semibold);">Condições de Pagamento</h3>
                
                <?php if (!empty($enrollment['installments'])): ?>
                <div class="form-group">
                    <label class="form-label">Parcelas</label>
                    <input 
                        type="text" 
                        class="form-input" 
                        value="<?= $enrollment['installments'] ?>x" 
                        readonly
                        style="background-color: var(--color-bg);"
                    >
                </div>
                <?php endif; ?>

                <?php if (!empty($enrollment['down_payment_amount'])): ?>
                <div class="form-group">
                    <label class="form-label">Valor Entrada</label>
                    <input 
                        type="text" 
                        class="form-input" 
                        value="R$ <?= number_format($enrollment['down_payment_amount'], 2, ',', '.') ?>" 
                        readonly
                        style="background-color: var(--color-bg);"
                    >
                </div>
                <div class="form-group">
                    <label class="form-label">Vencimento Entrada</label>
                    <input 
                        type="text" 
                        class="form-input" 
                        value="<?= !empty($enrollment['down_payment_due_date']) ? date('d/m/Y', strtotime($enrollment['down_payment_due_date'])) : '' ?>" 
                        readonly
                        style="background-color: var(--color-bg);"
                    >
                </div>
                <?php endif; ?>

                <?php if (!empty($enrollment['first_due_date'])): ?>
                <div class="form-group">
                    <label class="form-label">Vencimento 1ª Parcela</label>
                    <input 
                        type="text" 
                        class="form-input" 
                        value="<?= date('d/m/Y', strtotime($enrollment['first_due_date'])) ?>" 
                        readonly
                        style="background-color: var(--color-bg);"
                    >
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Status Cobrança (Gateway)</label>
                    <input 
                        type="text" 
                        class="form-input" 
                        value="<?= 
                            $enrollment['billing_status'] === 'draft' ? 'Rascunho' : 
                            ($enrollment['billing_status'] === 'ready' ? 'Pronto' : 
                            ($enrollment['billing_status'] === 'generated' ? 'Gerado' : 'Erro')) 
                        ?>" 
                        readonly
                        style="background-color: var(--color-bg);"
                    >
                </div>
                
                <?php if (!empty($enrollment['gateway_charge_id'])): ?>
                <div class="form-group">
                    <label class="form-label">ID da Cobrança</label>
                    <input 
                        type="text" 
                        class="form-input" 
                        value="<?= htmlspecialchars($enrollment['gateway_charge_id']) ?>" 
                        readonly
                        style="background-color: var(--color-bg); font-family: monospace; font-size: var(--font-size-sm);"
                    >
                </div>
                <?php endif; ?>
                
                <?php if (!empty($enrollment['gateway_last_status'])): ?>
                <div class="form-group">
                    <label class="form-label">Status no Gateway</label>
                    <?php
                    $gatewayStatusRaw = $enrollment['gateway_last_status'];
                    $statusMap = [
                        'waiting' => 'Aguardando pagamento',
                        'up_to_date' => 'Em dia (sem parcelas vencidas)',
                        'paid' => 'Pago',
                        'paid_partial' => 'Parcialmente pago',
                        'settled' => 'Liquidado',
                        'canceled' => 'Cancelado',
                        'expired' => 'Expirado',
                        'error' => 'Erro',
                        'unpaid' => 'Não pago',
                        'pending' => 'Pendente',
                        'processing' => 'Processando',
                        'new' => 'Nova cobrança'
                    ];
                    $gatewayStatusTranslated = $statusMap[strtolower($gatewayStatusRaw)] ?? $gatewayStatusRaw;
                    ?>
                    <input 
                        type="text" 
                        class="form-input" 
                        value="<?= htmlspecialchars($gatewayStatusTranslated) ?>" 
                        readonly
                        style="background-color: var(--color-bg);"
                        title="Status original: <?= htmlspecialchars($gatewayStatusRaw) ?>"
                    >
                </div>
                <?php endif; ?>
                
                <?php if (!empty($enrollment['gateway_last_event_at'])): ?>
                <div class="form-group">
                    <label class="form-label">Último Evento</label>
                    <input 
                        type="text" 
                        class="form-input" 
                        value="<?= date('d/m/Y H:i:s', strtotime($enrollment['gateway_last_event_at'])) ?>" 
                        readonly
                        style="background-color: var(--color-bg);"
                    >
                </div>
                <?php endif; ?>
                
                <?php 
                // Verificar se é Carnê (JSON) ou cobrança única (link direto)
                $paymentUrlForLink = null;
                $paymentUrlDisplay = null;
                if (!empty($enrollment['gateway_payment_url'])) {
                    $decoded = json_decode($enrollment['gateway_payment_url'], true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['type']) && $decoded['type'] === 'carne') {
                        // É um Carnê - usar cover para o link
                        $paymentUrlForLink = $decoded['cover'] ?? $decoded['download_link'] ?? null;
                        $paymentUrlDisplay = $paymentUrlForLink ?: 'Carnê (ver seção abaixo)';
                    } else {
                        // É uma cobrança única - usar o link direto
                        $paymentUrlForLink = $enrollment['gateway_payment_url'];
                        $paymentUrlDisplay = $enrollment['gateway_payment_url'];
                    }
                }
                ?>
                <?php if (!empty($paymentUrlForLink)): ?>
                <div class="form-group">
                    <label class="form-label">Link de Pagamento</label>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input 
                            type="text" 
                            class="form-input" 
                            value="<?= htmlspecialchars($paymentUrlDisplay) ?>" 
                            readonly
                            id="payment_url_input"
                            style="background-color: var(--color-bg); font-family: monospace; font-size: var(--font-size-sm); flex: 1;"
                        >
                        <a 
                            href="<?= htmlspecialchars($paymentUrlForLink) ?>" 
                            target="_blank" 
                            class="btn btn-outline"
                            style="white-space: nowrap;"
                        >
                            Abrir Link
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php 
            // Bloco de Carnê (se tipo for carne)
            $paymentData = null;
            if (!empty($enrollment['gateway_payment_url'])) {
                $paymentData = json_decode($enrollment['gateway_payment_url'], true);
            }
            $isCarnet = $paymentData && isset($paymentData['type']) && $paymentData['type'] === 'carne';
            
            if ($isCarnet && !empty($enrollment['gateway_charge_id'])): 
            ?>
            <div class="card" style="margin-top: 2rem; margin-bottom: 1rem;">
                <div class="card-header">
                    <h3 style="margin: 0;">Carnê (Boleto Parcelado)</h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;">
                        <?php if (!empty($paymentData['cover'])): ?>
                        <a 
                            href="<?= htmlspecialchars($paymentData['cover']) ?>" 
                            target="_blank" 
                            class="btn btn-outline"
                        >
                            📄 Ver Carnê (Capa)
                        </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($paymentData['download_link'])): ?>
                        <a 
                            href="<?= htmlspecialchars($paymentData['download_link']) ?>" 
                            target="_blank" 
                            class="btn btn-outline"
                            download
                        >
                            ⬇️ Baixar Carnê
                        </a>
                        <?php endif; ?>
                        
                        <button 
                            type="button" 
                            class="btn btn-outline" 
                            onclick="atualizarStatusCarne(<?= $enrollment['id'] ?>)"
                            id="btnAtualizarCarne"
                        >
                            🔄 Atualizar Status
                        </button>
                        
                        <?php if (!in_array($paymentData['status'] ?? '', ['canceled', 'expired'])): ?>
                        <button 
                            type="button" 
                            class="btn btn-outline btn-danger" 
                            onclick="cancelarCarne(<?= $enrollment['id'] ?>)"
                            id="btnCancelarCarne"
                        >
                            ❌ Cancelar Carnê
                        </button>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($paymentData['charges']) && is_array($paymentData['charges'])): ?>
                    <div style="overflow-x: auto;">
                        <table class="table" style="margin-top: 1rem;">
                            <thead>
                                <tr>
                                    <th>Parcela</th>
                                    <th>Vencimento</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="carne-parcelas-tbody">
                                <?php foreach ($paymentData['charges'] as $idx => $charge): ?>
                                <tr>
                                    <td><strong><?= ($idx + 1) ?>/<?= count($paymentData['charges']) ?></strong></td>
                                    <td>
                                        <?php if (!empty($charge['expire_at'])): ?>
                                            <?= date('d/m/Y', strtotime($charge['expire_at'])) ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusLabels = [
                                            'waiting' => ['label' => 'Aguardando pagamento', 'class' => 'badge-warning'],
                                            'up_to_date' => ['label' => 'Em dia', 'class' => 'badge-success'],
                                            'paid' => ['label' => 'Pago', 'class' => 'badge-success'],
                                            'paid_partial' => ['label' => 'Parcialmente pago', 'class' => 'badge-info'],
                                            'settled' => ['label' => 'Liquidado', 'class' => 'badge-success'],
                                            'canceled' => ['label' => 'Cancelado', 'class' => 'badge-danger'],
                                            'expired' => ['label' => 'Expirado', 'class' => 'badge-secondary'],
                                            'unpaid' => ['label' => 'Não pago', 'class' => 'badge-warning'],
                                            'pending' => ['label' => 'Pendente', 'class' => 'badge-warning'],
                                            'processing' => ['label' => 'Processando', 'class' => 'badge-info'],
                                            'error' => ['label' => 'Erro', 'class' => 'badge-danger']
                                        ];
                                        $status = $charge['status'] ?? 'waiting';
                                        $statusInfo = $statusLabels[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-secondary'];
                                        ?>
                                        <span class="badge <?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($charge['billet_link'])): ?>
                                        <a 
                                            href="<?= htmlspecialchars($charge['billet_link']) ?>" 
                                            target="_blank" 
                                            class="btn btn-sm btn-outline"
                                        >
                                            Abrir Boleto
                                        </a>
                                        <?php else: ?>
                                        <span style="color: var(--color-text-muted); font-size: var(--font-size-sm);">Link não disponível</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="color: var(--color-text-muted); margin-top: 1rem;">Carregando informações das parcelas...</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="financial_status">Status Financeiro *</label>
                <select id="financial_status" name="financial_status" class="form-select" required>
                    <option value="em_dia" <?= $enrollment['financial_status'] === 'em_dia' ? 'selected' : '' ?>>Em Dia</option>
                    <option value="pendente" <?= $enrollment['financial_status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="bloqueado" <?= $enrollment['financial_status'] === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Status da Matrícula *</label>
                <select id="status" name="status" class="form-select" required>
                    <option value="ativa" <?= $enrollment['status'] === 'ativa' ? 'selected' : '' ?>>Ativa</option>
                    <option value="concluida" <?= $enrollment['status'] === 'concluida' ? 'selected' : '' ?>>Concluída</option>
                    <option value="cancelada" <?= $enrollment['status'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                </select>
            </div>

            <!-- Seção Processo DETRAN (Colapsável) -->
            <div class="form-section-collapsible" style="margin-top: 2rem; margin-bottom: 1rem;">
                <button type="button" class="form-section-toggle" onclick="toggleDetranSection()" style="width: 100%; text-align: left; padding: 0.75rem; background: var(--color-bg-light); border: 1px solid var(--color-border); border-radius: var(--border-radius); cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-weight: var(--font-weight-semibold);">Processo DETRAN</span>
                    <svg id="detranToggleIcon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="transition: transform 0.2s;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div id="detranSection" style="display: none; padding: 1rem; background: var(--color-bg-light); border: 1px solid var(--color-border); border-top: none; border-radius: 0 0 var(--border-radius) var(--border-radius);">
                    <div class="form-group">
                        <label class="form-label" for="renach">RENACH</label>
                        <input 
                            type="text" 
                            id="renach" 
                            name="renach" 
                            class="form-input" 
                            maxlength="20"
                            value="<?= htmlspecialchars($enrollment['renach'] ?? '') ?>"
                            placeholder="Ex: ABC123456"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="numero_processo">Número do Processo</label>
                        <input 
                            type="text" 
                            id="numero_processo" 
                            name="numero_processo" 
                            class="form-input" 
                            maxlength="50"
                            value="<?= htmlspecialchars($enrollment['numero_processo'] ?? '') ?>"
                            placeholder="Ex: 12345/2024"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="detran_protocolo">Protocolo DETRAN</label>
                        <input 
                            type="text" 
                            id="detran_protocolo" 
                            name="detran_protocolo" 
                            class="form-input" 
                            maxlength="50"
                            value="<?= htmlspecialchars($enrollment['detran_protocolo'] ?? '') ?>"
                            placeholder="Ex: PROTO-123456"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="situacao_processo">Situação do Processo</label>
                        <select id="situacao_processo" name="situacao_processo" class="form-select">
                            <option value="nao_iniciado" <?= ($enrollment['situacao_processo'] ?? 'nao_iniciado') === 'nao_iniciado' ? 'selected' : '' ?>>Não Iniciado</option>
                            <option value="em_andamento" <?= ($enrollment['situacao_processo'] ?? '') === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                            <option value="pendente" <?= ($enrollment['situacao_processo'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="concluido" <?= ($enrollment['situacao_processo'] ?? '') === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                            <option value="cancelado" <?= ($enrollment['situacao_processo'] ?? '') === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Atualizar Matrícula
                </button>
                <?php 
                // Calcular saldo devedor
                $outstandingAmount = floatval($enrollment['outstanding_amount'] ?? $enrollment['final_price'] ?? 0);
                $hasOutstanding = $outstandingAmount > 0;
                
                // Mostrar botão apenas se não houver cobrança ativa
                $hasActiveCharge = !empty($enrollment['gateway_charge_id']) && 
                                  $enrollment['billing_status'] === 'generated' &&
                                  !in_array($enrollment['gateway_last_status'] ?? '', ['canceled', 'expired', 'error']);
                
                // Ocultar botões EFI quando payment_method = 'cartao' ou 'pix' (pagamentos locais/manuais)
                $isCartao = ($enrollment['payment_method'] ?? '') === 'cartao';
                $isPix = ($enrollment['payment_method'] ?? '') === 'pix';
                $isLocalPayment = $isCartao || $isPix;
                ?>
                
                <?php if (!$isLocalPayment): ?>
                <!-- Botão Gerar Cobrança: aparece se tem parcelas, saldo > 0, e não tem cobrança ativa -->
                <?php if (!empty($enrollment['installments']) && $hasOutstanding && !$hasActiveCharge && ($enrollment['billing_status'] === 'draft' || $enrollment['billing_status'] === 'ready' || $enrollment['billing_status'] === 'error')): 
                ?>
                <button type="button" class="btn btn-secondary" id="btnGerarCobranca" onclick="gerarCobrancaEfi()" style="margin-left: 0.5rem;">
                    Gerar Cobrança Efí
                </button>
                <?php elseif ($hasActiveCharge): ?>
                <span class="btn btn-outline" style="margin-left: 0.5rem; cursor: default;">
                    Cobrança já gerada
                </span>
                <?php endif; ?>
                
                <?php 
                // Botão Sincronizar: aparece se existe cobrança gerada
                if (!empty($enrollment['gateway_charge_id'])): 
                ?>
                <button type="button" class="btn btn-outline" id="btnSincronizarCobranca" onclick="sincronizarCobrancaEfi()" style="margin-left: 0.5rem;">
                    Sincronizar Cobrança
                </button>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php 
                // Botão Ver Dados do PIX: aparece apenas para PIX com saldo devedor
                if ($isPix && $hasOutstanding): 
                    // Verificar se há conta PIX configurada (da matrícula, snapshot ou padrão)
                    $hasPixAccount = !empty($pixAccount) || !empty($pixAccountSnapshot);
                ?>
                <?php if ($hasPixAccount): ?>
                <button type="button" class="btn btn-info" id="btnVerDadosPix" onclick="verDadosPix()" style="margin-left: 0.5rem;">
                    Ver Dados do PIX
                </button>
                <?php else: ?>
                <span class="btn btn-outline" style="margin-left: 0.5rem; cursor: default;" title="PIX não configurado. Configure nas Configurações do CFC.">
                    PIX não configurado
                </span>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php 
                // Botão Confirmar Pagamento: aparece para cartão ou PIX com saldo devedor
                if (($isCartao || $isPix) && $hasOutstanding && ($enrollment['billing_status'] === 'draft' || $enrollment['billing_status'] === 'ready' || $enrollment['billing_status'] === 'error' || ($enrollment['gateway_provider'] ?? '') === 'local')): 
                ?>
                <button type="button" class="btn btn-success" id="btnConfirmarPagamento" onclick="<?= $isPix ? 'confirmarPagamentoPix()' : 'confirmarPagamentoCartao()' ?>" style="margin-left: 0.5rem;">
                    Confirmar Pagamento
                </button>
                <?php endif; ?>
                
                <?php 
                // Botão Excluir Matrícula (apenas ADMIN)
                $currentRole = $_SESSION['current_role'] ?? '';
                $isAdmin = ($currentRole === \App\Config\Constants::ROLE_ADMIN);
                if ($isAdmin && $enrollment['status'] !== 'cancelada'):
                    // Verificar se pode excluir (não tem cobrança ativa na EFI)
                    $canDelete = empty($enrollment['gateway_charge_id']) || 
                                in_array(strtolower($enrollment['gateway_last_status'] ?? ''), ['canceled', 'expired', 'cancelado', 'expirado']);
                ?>
                <button 
                    type="button" 
                    class="btn btn-danger" 
                    onclick="excluirMatricula()" 
                    style="margin-left: 0.5rem;"
                    <?= !$canDelete ? 'disabled title="Não é possível excluir: há cobrança ativa na EFI. Cancele na EFI primeiro, sincronize e depois exclua."' : '' ?>
                >
                    🗑️ Excluir Matrícula
                </button>
                <?php endif; ?>
                
                <a href="<?= base_path("alunos/{$enrollment['student_id']}?tab=matricula") ?>" class="btn btn-outline">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
const basePrice = <?= $enrollment['base_price'] ?>;

function calculateFinal() {
    const discount = parseFloat(document.getElementById('discount_value').value || 0);
    const extra = parseFloat(document.getElementById('extra_value').value || 0);
    
    const final = Math.max(0, basePrice - discount + extra);
    
    document.getElementById('final_price').value = final;
    document.getElementById('final_price_display').value = 'R$ ' + final.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Recalcular saldo devedor quando valor final mudar
    calculateOutstanding();
}

function calculateOutstanding() {
    const finalPrice = parseFloat(document.getElementById('final_price').value || 0);
    const entryAmount = parseFloat(document.getElementById('entry_amount').value || 0);
    
    // Validar entrada
    if (entryAmount < 0) {
        alert('O valor da entrada não pode ser negativo.');
        document.getElementById('entry_amount').value = '';
        calculateOutstanding();
        return;
    }
    
    if (entryAmount >= finalPrice && finalPrice > 0) {
        alert('O valor da entrada deve ser menor que o valor final da matrícula.');
        document.getElementById('entry_amount').value = '';
        calculateOutstanding();
        return;
    }
    
    const outstanding = Math.max(0, finalPrice - entryAmount);
    
    document.getElementById('outstanding_amount').value = outstanding;
    document.getElementById('outstanding_amount_display').value = 'R$ ' + outstanding.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Se houver entrada, tornar obrigatórios os campos de entrada
    const entryPaymentMethod = document.getElementById('entry_payment_method');
    const entryPaymentDate = document.getElementById('entry_payment_date');
    
    if (entryAmount > 0) {
        entryPaymentMethod.setAttribute('required', 'required');
        entryPaymentDate.setAttribute('required', 'required');
    } else {
        entryPaymentMethod.removeAttribute('required');
        entryPaymentDate.removeAttribute('required');
    }
}

// Função para mostrar/esconder seletor de conta PIX
function togglePixAccountSelector() {
    const paymentMethod = document.getElementById('payment_method').value;
    const pixAccountSelector = document.getElementById('pixAccountSelector');
    
    if (paymentMethod === 'pix') {
        pixAccountSelector.style.display = 'block';
    } else {
        pixAccountSelector.style.display = 'none';
        // Limpar seleção quando não for PIX
        document.getElementById('pix_account_id').value = '';
    }
}

// Inicializar visibilidade do seletor ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    togglePixAccountSelector();
});

document.getElementById('enrollmentForm')?.addEventListener('submit', function(e) {
    calculateFinal();
    calculateOutstanding();
    
    // Validar entrada antes de submeter
    const entryAmount = parseFloat(document.getElementById('entry_amount').value || 0);
    const finalPrice = parseFloat(document.getElementById('final_price').value || 0);
    
    if (entryAmount > 0) {
        const entryPaymentMethod = document.getElementById('entry_payment_method').value;
        const entryPaymentDate = document.getElementById('entry_payment_date').value;
        
        if (!entryPaymentMethod) {
            e.preventDefault();
            alert('Se houver entrada, a forma de pagamento da entrada é obrigatória.');
            return false;
        }
        
        if (!entryPaymentDate) {
            e.preventDefault();
            alert('Se houver entrada, a data da entrada é obrigatória.');
            return false;
        }
        
        if (entryAmount >= finalPrice) {
            e.preventDefault();
            alert('O valor da entrada deve ser menor que o valor final da matrícula.');
            return false;
        }
    }
    
    // Validar campos de parcelamento se estiverem visíveis
    const installmentsField = document.getElementById('installments_field');
    const firstDueDateField = document.getElementById('first_due_date_field');
    
    if (installmentsField && installmentsField.style.display !== 'none') {
        const installments = document.getElementById('installments').value;
        if (!installments || installments < 1 || installments > 12) {
            e.preventDefault();
            alert('Número de parcelas deve ser entre 1 e 12.');
            return false;
        }
    }
    
    if (firstDueDateField && firstDueDateField.style.display !== 'none') {
        const firstDueDate = document.getElementById('first_due_date').value;
        if (!firstDueDate) {
            e.preventDefault();
            alert('Data do primeiro vencimento é obrigatória para boleto e PIX.');
            return false;
        }
    }
});

function toggleDetranSection() {
    const section = document.getElementById('detranSection');
    const icon = document.getElementById('detranToggleIcon');
    const isVisible = section.style.display !== 'none';
    
    section.style.display = isVisible ? 'none' : 'block';
    icon.style.transform = isVisible ? 'rotate(0deg)' : 'rotate(180deg)';
}

// Expandir seção DETRAN se houver dados preenchidos
document.addEventListener('DOMContentLoaded', function() {
    const renach = document.getElementById('renach')?.value || '';
    const numeroProcesso = document.getElementById('numero_processo')?.value || '';
    const detranProtocolo = document.getElementById('detran_protocolo')?.value || '';
    const situacaoProcesso = document.getElementById('situacao_processo')?.value || 'nao_iniciado';
    
    if (renach || numeroProcesso || detranProtocolo || situacaoProcesso !== 'nao_iniciado') {
        toggleDetranSection();
    }
    
    // Inicializar campos de parcelamento conforme método de pagamento selecionado
    updatePaymentPlanFields();
});

// Função para mostrar/ocultar campos de parcelamento conforme método de pagamento
function updatePaymentPlanFields() {
    const paymentMethod = document.getElementById('payment_method')?.value || '';
    const installmentsField = document.getElementById('installments_field');
    const firstDueDateField = document.getElementById('first_due_date_field');
    const installmentsInput = document.getElementById('installments');
    const installmentsHelp = document.getElementById('installments_help');
    
    if (!installmentsField || !firstDueDateField) {
        return; // Campos não existem (modo somente leitura)
    }
    
    // Métodos que requerem parcelas
    const methodsWithInstallments = ['boleto', 'pix', 'cartao', 'entrada_parcelas'];
    
    if (methodsWithInstallments.includes(paymentMethod)) {
        installmentsField.style.display = 'block';
        if (installmentsInput) {
            installmentsInput.setAttribute('required', 'required');
            
            // Ajustar max dinamicamente: 24 para cartão, 12 para outros
            const maxInstallments = (paymentMethod === 'cartao') ? 24 : 12;
            installmentsInput.setAttribute('max', maxInstallments);
            
            // Se valor atual > max novo, reduzir para o max
            const currentValue = parseInt(installmentsInput.value) || 1;
            if (currentValue > maxInstallments) {
                installmentsInput.value = maxInstallments;
            }
            
            // Atualizar help text
            if (installmentsHelp) {
                installmentsHelp.textContent = `Número de parcelas para o saldo devedor (entre 1 e ${maxInstallments})`;
            }
        }
        
        // Métodos que requerem data do primeiro vencimento
        if (['boleto', 'pix'].includes(paymentMethod)) {
            firstDueDateField.style.display = 'block';
            firstDueDateField.querySelector('#first_due_date').setAttribute('required', 'required');
        } else {
            firstDueDateField.style.display = 'none';
            firstDueDateField.querySelector('#first_due_date').removeAttribute('required');
        }
    } else {
        installmentsField.style.display = 'none';
        firstDueDateField.style.display = 'none';
        if (installmentsInput) {
            installmentsInput.removeAttribute('required');
        }
        firstDueDateField.querySelector('#first_due_date').removeAttribute('required');
    }
}

// Adicionar listener ao campo de forma de pagamento
document.getElementById('payment_method')?.addEventListener('change', function() {
    const paymentMethod = this.value || '';
    
    // Atualizar campos de parcelamento
    updatePaymentPlanFields();
    
    // Se selecionou Cartão, mostrar popup "Já está pago?"
    if (paymentMethod === 'cartao') {
        const isPaid = confirm('Pagamento na maquininha local.\n\nJá está pago?\n\n- OK = Sim, já foi pago\n- Cancelar = Não, ainda não foi pago');
        
        if (isPaid) {
            // Chamar função de confirmar pagamento
            confirmarPagamentoCartao();
        }
    }
});

function gerarCobrancaEfi() {
    const enrollmentId = <?= $enrollment['id'] ?>;
    const btn = document.getElementById('btnGerarCobranca');
    const outstandingAmount = <?= $enrollment['outstanding_amount'] ?? $enrollment['final_price'] ?? 0 ?>;
    const installments = <?= $enrollment['installments'] ?? 1 ?>;
    const entryAmount = <?= $enrollment['entry_amount'] ?? 0 ?>;
    
    // Validação adicional no frontend
    if (outstandingAmount <= 0) {
        alert('Não é possível gerar cobrança: saldo devedor deve ser maior que zero.');
        return;
    }
    
    let message = 'Deseja gerar a cobrança na Efí?\n\n';
    message += 'Valores que serão cobrados:\n';
    if (entryAmount > 0) {
        message += `- Entrada já recebida: R$ ${entryAmount.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n`;
    }
    message += `- Saldo devedor: R$ ${outstandingAmount.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n`;
    message += `- Parcelas: ${installments}x\n`;
    message += `- Valor por parcela: R$ ${(outstandingAmount / installments).toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n\n`;
    message += 'Nota: A Efí gerará cobranças apenas sobre o saldo devedor (valor final - entrada).';
    
    if (!confirm(message)) {
        return;
    }
    
    // Desabilitar botão durante processamento
    btn.disabled = true;
    btn.textContent = 'Gerando...';
    
    // Fazer chamada AJAX para gerar cobrança
    fetch('<?= base_path('api/payments/generate') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            enrollment_id: enrollmentId
        })
    })
    .then(async response => {
        // Ler resposta como texto primeiro (mais seguro)
        const raw = await response.text();
        let data;
        
        // Tentar parsear como JSON
        try {
            data = JSON.parse(raw);
        } catch (e) {
            // Se não for JSON válido, tratar como erro
            console.error('Resposta não é JSON válido:', raw);
            throw new Error('Servidor retornou resposta inválida. Status: ' + response.status + '\n\n' + raw.slice(0, 200));
        }
        
        // Se não veio JSON válido ou status não OK
        if (!response.ok) {
            const errorMsg = data.message || data.error || data.error_description || 'Erro desconhecido';
            throw new Error(`Erro ${response.status}: ${errorMsg}`);
        }
        
        return data;
    })
    .then(data => {
        if (data.ok) {
            // Sucesso
            const statusMap = {
                'waiting': 'Aguardando pagamento',
                'up_to_date': 'Em dia (sem parcelas vencidas)',
                'paid': 'Pago',
                'paid_partial': 'Parcialmente pago',
                'settled': 'Liquidado',
                'canceled': 'Cancelado',
                'expired': 'Expirado',
                'error': 'Erro',
                'unpaid': 'Não pago',
                'pending': 'Pendente',
                'processing': 'Processando',
                'new': 'Nova cobrança'
            };
            const statusTraduzido = data.status ? (statusMap[data.status.toLowerCase()] || data.status) : 'Não disponível';
            
            let successMsg = 'Cobrança gerada com sucesso!\n\n';
            // Para Carnê, usar carnet_id; para cobrança única, usar charge_id
            const chargeId = data.carnet_id || data.charge_id || 'Não disponível';
            successMsg += `- ID da Cobrança: ${chargeId}\n`;
            successMsg += `- Status: ${statusTraduzido}\n`;
            
            // Se for Carnê, mostrar informações adicionais
            if (data.type === 'carne' && data.carnet_id) {
                successMsg += `- Tipo: Carnê (${data.installments || 'N'} parcelas)\n`;
            }
            
            if (data.payment_url) {
                successMsg += `\n- Link de Pagamento: ${data.payment_url}\n`;
            }
            
            alert(successMsg);
            
            // Recarregar página para atualizar status
            window.location.reload();
        } else {
            // Erro retornado pelo backend (mas com status HTTP OK)
            const errorMsg = data.message || data.error || data.error_description || 'Erro desconhecido';
            alert('Não foi possível gerar a cobrança: ' + errorMsg);
            btn.disabled = false;
            btn.textContent = 'Gerar Cobrança Efí';
        }
    })
    .catch(error => {
        console.error('Erro completo:', error);
        console.error('Stack:', error.stack);
        const errorMsg = error.message || 'Ocorreu um erro desconhecido. Por favor, tente novamente.';
        alert('Não foi possível comunicar com o servidor: ' + errorMsg + '\n\nVerifique o console para mais detalhes.');
        btn.disabled = false;
        btn.textContent = 'Gerar Cobrança Efí';
    });
}

function sincronizarCobrancaEfi() {
    const enrollmentId = <?= $enrollment['id'] ?>;
    const btn = document.getElementById('btnSincronizarCobranca');
    
    if (!confirm('Deseja sincronizar o status da cobrança com a EFI?\n\nIsso irá consultar o status atual na EFI e atualizar os dados da matrícula.')) {
        return;
    }
    
    // Desabilitar botão durante processamento
    btn.disabled = true;
    btn.textContent = 'Sincronizando...';
    
    // Fazer chamada AJAX para sincronizar
    fetch('<?= base_path('api/payments/sync') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            enrollment_id: enrollmentId
        })
    })
    .then(async response => {
        // Verificar se a resposta é JSON válido
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Resposta não é JSON:', text);
            throw new Error('Resposta do servidor não é JSON válido. Status: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.ok) {
            // Sucesso
            const statusMap = {
                'waiting': 'Aguardando pagamento',
                'up_to_date': 'Em dia (sem parcelas vencidas)',
                'paid': 'Pago',
                'paid_partial': 'Parcialmente pago',
                'settled': 'Liquidado',
                'canceled': 'Cancelado',
                'expired': 'Expirado',
                'error': 'Erro',
                'unpaid': 'Não pago',
                'pending': 'Pendente',
                'processing': 'Processando',
                'new': 'Nova cobrança'
            };
            const statusTraduzido = data.status ? (statusMap[data.status.toLowerCase()] || data.status) : 'Não disponível';
            
            let successMsg = 'Cobrança sincronizada com sucesso!\n\n';
            successMsg += `- Status: ${statusTraduzido}\n`;
            successMsg += `- Status Interno: ${data.billing_status || 'Não disponível'}\n`;
            
            if (data.financial_status) {
                successMsg += `- Status Financeiro: ${data.financial_status}\n`;
            }
            
            if (data.payment_url) {
                successMsg += `\n- Link de Pagamento atualizado\n`;
            }
            
            alert(successMsg);
            
            // Recarregar página para atualizar status
            window.location.reload();
        } else {
            // Erro
            alert('Não foi possível sincronizar a cobrança: ' + (data.message || 'Ocorreu um erro desconhecido. Por favor, tente novamente.'));
            btn.disabled = false;
            btn.textContent = 'Sincronizar Cobrança';
        }
    })
    .catch(error => {
        console.error('Erro completo:', error);
        console.error('Stack:', error.stack);
        alert('Erro ao comunicar com o servidor: ' + (error.message || 'Erro desconhecido') + '\n\nVerifique o console para mais detalhes.');
        btn.disabled = false;
        btn.textContent = 'Sincronizar Cobrança';
    });
}

function atualizarStatusCarne(enrollmentId) {
    const btn = document.getElementById('btnAtualizarCarne');
    
    // Desabilitar botão durante processamento
    btn.disabled = true;
    btn.textContent = 'Atualizando...';
    
    // Fazer chamada AJAX para atualizar status (com refresh=true)
    fetch(`<?= base_path('api/payments/status') ?>?enrollment_id=${enrollmentId}&refresh=true`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(async response => {
        const raw = await response.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            throw new Error('Resposta não é JSON válido. Status: ' + response.status);
        }
        
        if (!response.ok) {
            throw new Error(data.message || 'Erro ao atualizar status');
        }
        
        return data;
    })
    .then(data => {
        if (data.ok && data.type === 'carne') {
            // Atualizar tabela de parcelas
            const tbody = document.getElementById('carne-parcelas-tbody');
            if (tbody && data.charges) {
                tbody.innerHTML = '';
                data.charges.forEach((charge, idx) => {
                        const statusLabels = {
                            'waiting': ['Aguardando pagamento', 'badge-warning'],
                            'up_to_date': ['Em dia', 'badge-success'],
                            'paid': ['Pago', 'badge-success'],
                            'paid_partial': ['Parcialmente pago', 'badge-info'],
                            'settled': ['Liquidado', 'badge-success'],
                            'canceled': ['Cancelado', 'badge-danger'],
                            'expired': ['Expirado', 'badge-secondary'],
                            'unpaid': ['Não pago', 'badge-warning'],
                            'pending': ['Pendente', 'badge-warning'],
                            'processing': ['Processando', 'badge-info'],
                            'error': ['Erro', 'badge-danger']
                        };
                    const statusInfo = statusLabels[charge.status] || [charge.status, 'badge-secondary'];
                    const expireDate = charge.expire_at ? new Date(charge.expire_at).toLocaleDateString('pt-BR') : 'N/A';
                    
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><strong>${idx + 1}/${data.charges.length}</strong></td>
                        <td>${expireDate}</td>
                        <td><span class="badge ${statusInfo[1]}">${statusInfo[0]}</span></td>
                        <td>
                            ${charge.billet_link ? 
                                `<a href="${charge.billet_link}" target="_blank" class="btn btn-sm btn-outline">Abrir Boleto</a>` :
                                '<span style="color: var(--color-text-muted); font-size: var(--font-size-sm);">Link não disponível</span>'
                            }
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
            
            alert('Status do carnê atualizado com sucesso!');
        } else {
            throw new Error('Resposta inesperada do servidor');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar status: ' + (error.message || 'Erro desconhecido'));
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = '🔄 Atualizar Status';
    });
}

function confirmarPagamentoCartao() {
    const enrollmentId = <?= $enrollment['id'] ?>;
    const outstandingAmount = <?= $enrollment['outstanding_amount'] ?? $enrollment['final_price'] ?? 0 ?>;
    
    // Buscar parcelas: primeiro tenta do campo, depois do valor salvo na matrícula
    const installmentsInput = document.getElementById('installments');
    let installments = null;
    
    if (installmentsInput && installmentsInput.value) {
        installments = parseInt(installmentsInput.value);
    } else {
        // Se campo não existe ou está vazio, usar valor salvo na matrícula
        installments = <?= !empty($enrollment['installments']) ? intval($enrollment['installments']) : 1 ?>;
    }
    
    // Garantir que installments é válido
    if (!installments || isNaN(installments) || installments < 1) {
        installments = 1; // Valor padrão
    }
    
    // Validações
    if (outstandingAmount <= 0) {
        alert('Não há saldo devedor para confirmar pagamento.');
        return;
    }
    
    if (installments < 1 || installments > 24) {
        alert('Número de parcelas deve ser entre 1 e 24 para cartão.');
        return;
    }
    
    // Confirmação final
    const confirmMsg = `Confirmar pagamento de R$ ${outstandingAmount.toLocaleString('pt-BR', {minimumFractionDigits: 2})}?\n\n` +
                      `Parcelas: ${installments}x\n` +
                      `Valor por parcela: R$ ${(outstandingAmount / installments).toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n\n` +
                      `Este pagamento foi realizado na maquininha local e será registrado imediatamente.`;
    
    if (!confirm(confirmMsg)) {
        return;
    }
    
    // Desabilitar botão durante processamento
    const btn = document.getElementById('btnConfirmarPagamento');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Confirmando...';
    }
    
    // Preparar dados
    const payload = {
        enrollment_id: enrollmentId,
        payment_method: 'cartao',
        installments: installments,
        confirm_amount: outstandingAmount
    };
    
    // Chamar endpoint de baixa manual
    fetch('<?= base_path('api/payments/mark-paid') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
    })
    .then(async response => {
        const raw = await response.text();
        let data;
        
        try {
            data = JSON.parse(raw);
        } catch (e) {
            console.error('Resposta não é JSON válido:', raw);
            console.error('Erro ao parsear:', e);
            throw new Error('Servidor retornou resposta inválida. Status: ' + response.status + '. Verifique o console para mais detalhes.');
        }
        
        if (!response.ok) {
            // Tentar extrair mensagem de erro
            const errorMsg = data?.message || data?.error || `Erro HTTP ${response.status}`;
            
            // Mensagens específicas para erros comuns
            if (response.status === 400) {
                throw new Error(errorMsg);
            } else if (response.status === 403) {
                throw new Error('Você não tem permissão para realizar esta ação.');
            } else if (response.status === 404) {
                throw new Error('Matrícula não encontrada.');
            } else if (response.status === 500) {
                const details = data?.details ? `\n\nDetalhes: ${data.details.error || ''}` : '';
                throw new Error('Erro interno do servidor. Por favor, tente novamente.' + details);
            }
            
            throw new Error(`Erro ${response.status}: ${errorMsg}`);
        }
        
        return data;
    })
    .then(data => {
        if (data.ok) {
            alert('Pagamento confirmado com sucesso!\n\nO financeiro foi atualizado imediatamente.');
            // Recarregar página para atualizar status
            window.location.reload();
        } else {
            throw new Error(data.message || 'Erro ao confirmar pagamento');
        }
    })
    .catch(error => {
        console.error('Erro completo:', error);
        console.error('Stack:', error.stack);
        alert('Erro ao confirmar pagamento: ' + (error.message || 'Erro desconhecido') + '\n\nVerifique o console para mais detalhes.');
        
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Confirmar Pagamento';
        }
    });
}

function cancelarCarne(enrollmentId) {
    if (!confirm('Deseja realmente cancelar este carnê?\n\nTodas as parcelas serão canceladas na Efí e não poderão ser pagas.\n\nEsta ação não pode ser desfeita.')) {
        return;
    }
    
    const btn = document.getElementById('btnCancelarCarne');
    btn.disabled = true;
    btn.textContent = 'Cancelando...';
    
    // Fazer chamada AJAX para cancelar carnê
    fetch('<?= base_path('api/payments/cancel') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            enrollment_id: enrollmentId
        })
    })
    .then(async response => {
        const raw = await response.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            throw new Error('Resposta não é JSON válido. Status: ' + response.status);
        }
        
        if (!response.ok) {
            throw new Error(data.message || 'Erro ao cancelar carnê');
        }
        
        return data;
    })
    .then(data => {
        if (data.ok) {
            alert('Carnê cancelado com sucesso!');
            window.location.reload();
        } else {
            throw new Error(data.message || 'Erro ao cancelar carnê');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao cancelar carnê: ' + (error.message || 'Erro desconhecido'));
        btn.disabled = false;
        btn.textContent = '❌ Cancelar Carnê';
    });
}

function excluirMatricula() {
    const reason = prompt('Digite o motivo da exclusão (opcional):\n\nEsta ação não pode ser desfeita. A matrícula será marcada como cancelada e o saldo devedor será zerado.');
    
    if (reason === null) {
        return; // Usuário cancelou
    }
    
    if (!confirm('Tem certeza que deseja EXCLUIR esta matrícula?\n\nEsta ação irá:\n- Marcar a matrícula como cancelada\n- Zerar o saldo devedor\n- Limpar dados da cobrança EFI\n\nEsta ação não pode ser desfeita!')) {
        return;
    }
    
    // Criar formulário para enviar POST
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= base_path("matriculas/{$enrollment['id']}/excluir") ?>';
    
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = 'csrf_token';
    csrfToken.value = '<?= csrf_token() ?>';
    form.appendChild(csrfToken);
    
    const reasonInput = document.createElement('input');
    reasonInput.type = 'hidden';
    reasonInput.name = 'delete_reason';
    reasonInput.value = reason || 'Exclusão manual pelo usuário';
    form.appendChild(reasonInput);
    
    document.body.appendChild(form);
    form.submit();
}

// ============================================
// FUNÇÕES PIX LOCAL/MANUAL
// ============================================

function verDadosPix() {
    // Usar dados da conta PIX da matrícula (snapshot ou conta atual)
    <?php if (!empty($pixAccountSnapshot)): ?>
    // Usar snapshot (dados imutáveis do momento do pagamento)
    const pixData = {
        label: <?= json_encode($pixAccountSnapshot['label'] ?? '') ?>,
        banco: <?= json_encode($pixAccountSnapshot['bank_name'] ?? '') ?>,
        bank_code: <?= json_encode($pixAccountSnapshot['bank_code'] ?? '') ?>,
        titular: <?= json_encode($pixAccountSnapshot['holder_name'] ?? '') ?>,
        chave: <?= json_encode($pixAccountSnapshot['pix_key'] ?? '') ?>,
        observacao: null
    };
    <?php elseif (!empty($pixAccount)): ?>
    // Usar conta atual
    const pixData = {
        label: <?= json_encode($pixAccount['label'] ?? '') ?>,
        banco: <?= json_encode($pixAccount['bank_name'] ?? '') ?>,
        bank_code: <?= json_encode($pixAccount['bank_code'] ?? '') ?>,
        titular: <?= json_encode($pixAccount['holder_name'] ?? '') ?>,
        chave: <?= json_encode($pixAccount['pix_key'] ?? '') ?>,
        observacao: <?= json_encode($pixAccount['note'] ?? '') ?>
    };
    <?php else: ?>
    // Fallback para dados antigos (retrocompatibilidade)
    const pixData = {
        label: 'PIX Principal',
        banco: <?= json_encode($cfc['pix_banco'] ?? '') ?>,
        bank_code: null,
        titular: <?= json_encode($cfc['pix_titular'] ?? '') ?>,
        chave: <?= json_encode($cfc['pix_chave'] ?? '') ?>,
        observacao: <?= json_encode($cfc['pix_observacao'] ?? '') ?>
    };
    <?php endif; ?>
    
    // Verificar se dados estão configurados
    if (!pixData.chave || !pixData.titular) {
        alert('Dados do PIX não estão configurados.\n\nPor favor, configure os dados do PIX nas Configurações do CFC antes de usar esta funcionalidade.');
        return;
    }
    
    // Criar conteúdo do modal
    let modalContent = '<div style="padding: 1rem;">';
    modalContent += '<h3 style="margin-top: 0; margin-bottom: 1rem; color: var(--color-primary);">Dados do PIX</h3>';
    
    if (pixData.label) {
        modalContent += '<div style="margin-bottom: 1rem;"><strong>Conta:</strong><br>' + escapeHtml(pixData.label) + '</div>';
    }
    
    if (pixData.banco || pixData.bank_code) {
        let bancoInfo = '';
        if (pixData.bank_code) {
            bancoInfo += pixData.bank_code + ' - ';
        }
        bancoInfo += pixData.banco || 'Não informado';
        modalContent += '<div style="margin-bottom: 1rem;"><strong>Banco/Instituição:</strong><br>' + escapeHtml(bancoInfo) + '</div>';
    }
    
    modalContent += '<div style="margin-bottom: 1rem;"><strong>Titular:</strong><br>' + escapeHtml(pixData.titular) + '</div>';
    
    modalContent += '<div style="margin-bottom: 1rem;"><strong>Chave PIX:</strong><br>';
    modalContent += '<div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">';
    modalContent += '<code style="flex: 1; padding: 0.5rem; background: var(--color-bg-light); border: 1px solid var(--color-border); border-radius: var(--border-radius); font-size: 0.9rem; word-break: break-all;">' + escapeHtml(pixData.chave) + '</code>';
    modalContent += '<button onclick="copiarChavePix(\'' + escapeHtml(pixData.chave) + '\')" class="btn btn-outline" style="white-space: nowrap;">Copiar</button>';
    modalContent += '</div></div>';
    
    if (pixData.observacao) {
        modalContent += '<div style="margin-bottom: 1rem;"><strong>Observação:</strong><br><small style="color: var(--color-text-muted);">' + escapeHtml(pixData.observacao) + '</small></div>';
    }
    
    modalContent += '<div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--color-border);">';
    modalContent += '<small style="color: var(--color-text-muted);">Após receber o pagamento via PIX, clique em "Confirmar Pagamento" para dar baixa manual.</small>';
    modalContent += '</div>';
    
    modalContent += '</div>';
    
    // Criar e exibir modal simples
    const modal = document.createElement('div');
    modal.id = 'modalPix';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 1rem;';
    modal.innerHTML = `
        <div style="background: white; border-radius: var(--border-radius); max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            ${modalContent}
            <div style="padding: 1rem; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button onclick="fecharModalPix()" class="btn btn-outline">Fechar</button>
                <a href="<?= base_path('configuracoes/cfc') ?>" class="btn btn-secondary">Configurar PIX</a>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Fechar ao clicar fora
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            fecharModalPix();
        }
    });
}

function fecharModalPix() {
    const modal = document.getElementById('modalPix');
    if (modal) {
        modal.remove();
    }
}

function copiarChavePix(chave) {
    navigator.clipboard.writeText(chave).then(function() {
        alert('Chave PIX copiada para a área de transferência!');
    }).catch(function() {
        // Fallback para navegadores antigos
        const textarea = document.createElement('textarea');
        textarea.value = chave;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            alert('Chave PIX copiada para a área de transferência!');
        } catch (e) {
            alert('Erro ao copiar. Por favor, copie manualmente.');
        }
        document.body.removeChild(textarea);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function confirmarPagamentoPix() {
    const enrollmentId = <?= $enrollment['id'] ?>;
    const outstandingAmount = <?= $enrollment['outstanding_amount'] ?? $enrollment['final_price'] ?? 0 ?>;
    
    // Validações
    if (outstandingAmount <= 0) {
        alert('Não há saldo devedor para confirmar pagamento.');
        return;
    }
    
    // Confirmação final
    const confirmMsg = `Confirmar pagamento de R$ ${outstandingAmount.toLocaleString('pt-BR', {minimumFractionDigits: 2})} via PIX?\n\n` +
                      `Este pagamento foi realizado localmente e será registrado imediatamente.`;
    
    if (!confirm(confirmMsg)) {
        return;
    }
    
    // Desabilitar botão durante processamento
    const btn = document.getElementById('btnConfirmarPagamento');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Confirmando...';
    }
    
    // Preparar dados
    const payload = {
        enrollment_id: enrollmentId,
        payment_method: 'pix',
        installments: 1 // PIX sempre é à vista
        // Não enviar confirm_amount para PIX (sempre paga saldo total, evita problemas de precisão)
    };
    
    // Chamar endpoint de baixa manual
    fetch('<?= base_path('api/payments/mark-paid') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
    })
    .then(async response => {
        const raw = await response.text();
        let data;
        
        try {
            data = JSON.parse(raw);
        } catch (e) {
            console.error('Resposta não é JSON válido:', raw);
            console.error('Erro ao parsear:', e);
            throw new Error('Servidor retornou resposta inválida. Status: ' + response.status + '. Verifique o console para mais detalhes.');
        }
        
        if (!response.ok) {
            // Tentar extrair mensagem de erro
            const errorMsg = data?.message || data?.error || `Erro HTTP ${response.status}`;
            
            // Mensagens específicas para erros comuns
            if (response.status === 400) {
                throw new Error(errorMsg);
            } else if (response.status === 403) {
                throw new Error('Você não tem permissão para realizar esta ação.');
            } else if (response.status === 404) {
                throw new Error('Matrícula não encontrada.');
            } else if (response.status === 500) {
                const details = data?.details ? `\n\nDetalhes: ${data.details.error || ''}` : '';
                throw new Error('Erro interno do servidor. Por favor, tente novamente.' + details);
            }
            
            throw new Error(`Erro ${response.status}: ${errorMsg}`);
        }
        
        return data;
    })
    .then(data => {
        if (data.ok) {
            alert('Pagamento confirmado com sucesso!\n\nO financeiro foi atualizado imediatamente.');
            // Recarregar página para atualizar status
            window.location.reload();
        } else {
            throw new Error(data.message || 'Erro ao confirmar pagamento');
        }
    })
    .catch(error => {
        console.error('Erro completo:', error);
        console.error('Stack:', error.stack);
        alert('Erro ao confirmar pagamento: ' + (error.message || 'Erro desconhecido') + '\n\nVerifique o console para mais detalhes.');
        
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Confirmar Pagamento';
        }
    });
}
</script>
