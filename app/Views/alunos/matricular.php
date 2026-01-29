<div class="page-header">
    <div>
        <h1>Nova Matrícula</h1>
        <p class="text-muted">Aluno: <?= htmlspecialchars($student['name']) ?></p>
    </div>
    <a href="<?= base_path("alunos/{$student['id']}") ?>" class="btn btn-outline">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Voltar
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= base_path("alunos/{$student['id']}/matricular") ?>" id="enrollmentForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label class="form-label" for="service_id">Serviço *</label>
                <select id="service_id" name="service_id" class="form-select" required onchange="updatePrice()">
                    <option value="">Selecione um serviço</option>
                    <?php foreach ($services as $service): ?>
                    <option 
                        value="<?= $service['id'] ?>" 
                        data-price="<?= $service['base_price'] ?>"
                    >
                        <?= htmlspecialchars($service['name']) ?> - R$ <?= number_format($service['base_price'], 2, ',', '.') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="base_price_display">Preço Base</label>
                <input 
                    type="text" 
                    id="base_price_display" 
                    class="form-input" 
                    value="R$ 0,00" 
                    readonly
                    style="background-color: var(--color-bg-light);"
                >
                <input type="hidden" id="base_price" name="base_price">
            </div>

            <div class="form-group">
                <label class="form-label" for="discount_value">Desconto (R$)</label>
                <input 
                    type="number" 
                    id="discount_value" 
                    name="discount_value" 
                    class="form-input" 
                    value="0.00" 
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
                    value="0.00" 
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
                    value="R$ 0,00" 
                    readonly
                    style="background-color: var(--color-bg-light); font-weight: var(--font-weight-semibold); font-size: var(--font-size-lg);"
                >
                <input type="hidden" id="final_price" name="final_price">
            </div>

            <!-- Seção Entrada -->
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
                        placeholder="0.00"
                        onchange="calculateOutstanding()"
                    >
                    <small class="text-muted">Deixe em branco se não houver entrada</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="entry_payment_method">Forma de Pagamento da Entrada</label>
                    <select id="entry_payment_method" name="entry_payment_method" class="form-select" onchange="toggleEntryPixAccount()">
                        <option value="">Selecione (se houver entrada)</option>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">PIX</option>
                        <option value="cartao">Cartão</option>
                        <option value="boleto">Boleto</option>
                    </select>
                </div>

                <!-- Seletor de Conta PIX para Entrada (aparece quando entry_payment_method = 'pix') -->
                <div id="entryPixAccountSelector" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="entry_pix_account_id">Conta PIX da Entrada *</label>
                        <select id="entry_pix_account_id" name="entry_pix_account_id" class="form-select">
                            <option value="">Selecione uma conta PIX</option>
                        </select>
                        <small class="form-hint">Selecione qual conta PIX recebeu a entrada</small>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="entry_payment_date">Data da Entrada</label>
                    <input 
                        type="date" 
                        id="entry_payment_date" 
                        name="entry_payment_date" 
                        class="form-input"
                        value="<?= date('Y-m-d') ?>"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="outstanding_amount_display">Saldo Devedor</label>
                    <input 
                        type="text" 
                        id="outstanding_amount_display" 
                        class="form-input" 
                        value="R$ 0,00" 
                        readonly
                        style="background-color: var(--color-bg); font-weight: var(--font-weight-semibold); font-size: var(--font-size-md); color: var(--color-primary);"
                    >
                    <input type="hidden" id="outstanding_amount" name="outstanding_amount">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="payment_method">Forma de Pagamento *</label>
                <select id="payment_method" name="payment_method" class="form-select" required onchange="togglePaymentConditions()">
                    <option value="pix">PIX</option>
                    <option value="boleto">Boleto</option>
                    <option value="cartao">Cartão</option>
                    <option value="entrada_parcelas">Entrada + Parcelas</option>
                </select>
            </div>

            <!-- Seletor de Conta PIX (aparece quando payment_method = 'pix') -->
            <div id="pixAccountSelector" style="display: none; margin-top: var(--spacing-md);">
                <div class="form-group">
                    <label class="form-label" for="pix_account_id">Conta PIX *</label>
                    <select id="pix_account_id" name="pix_account_id" class="form-select">
                        <option value="">Carregando contas...</option>
                    </select>
                    <small class="form-hint">Selecione qual conta PIX será usada para este pagamento</small>
                </div>
            </div>

            <!-- Seção Condições de Pagamento (Dinâmica) -->
            <div id="paymentConditionsSection" style="display: none; margin-top: 1.5rem; padding: 1rem; background: var(--color-bg-light); border: 1px solid var(--color-border); border-radius: var(--border-radius);">
                <h3 style="margin-top: 0; margin-bottom: 1rem; font-size: var(--font-size-md); font-weight: var(--font-weight-semibold);">Condições de Pagamento</h3>
                
                <!-- Boleto/PIX: Parcelas + Vencimento 1ª parcela -->
                <div id="boletoPixConditions" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="installments">Parcelas *</label>
                        <select id="installments" name="installments" class="form-select" required>
                            <option value="">Selecione</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?>x</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="first_due_date">Vencimento 1ª Parcela *</label>
                        <input 
                            type="date" 
                            id="first_due_date" 
                            name="first_due_date" 
                            class="form-input" 
                            required
                        >
                    </div>
                </div>

                <!-- Cartão: Apenas Parcelas -->
                <div id="cartaoConditions" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="installments_cartao">Parcelas *</label>
                        <select id="installments_cartao" name="installments" class="form-select" required>
                            <option value="">Selecione</option>
                            <?php for ($i = 1; $i <= 24; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?>x</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- Entrada + Parcelas: Entrada + Parcelas restantes -->
                <div id="entradaParcelasConditions" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="down_payment_amount">Valor Entrada (R$) *</label>
                        <input 
                            type="number" 
                            id="down_payment_amount" 
                            name="down_payment_amount" 
                            class="form-input" 
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            onchange="validateDownPayment()"
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="down_payment_due_date">Vencimento Entrada *</label>
                        <input 
                            type="date" 
                            id="down_payment_due_date" 
                            name="down_payment_due_date" 
                            class="form-input" 
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="installments_entrada">Parcelas Restantes *</label>
                        <select id="installments_entrada" name="installments" class="form-select" required>
                            <option value="">Selecione</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?>x</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="first_due_date_entrada">Vencimento 1ª Parcela Restante *</label>
                        <input 
                            type="date" 
                            id="first_due_date_entrada" 
                            name="first_due_date" 
                            class="form-input" 
                            required
                        >
                    </div>
                </div>
            </div>

            <!-- Seção Curso Teórico (Opcional) -->
            <div class="form-section-collapsible" style="margin-top: 2rem; margin-bottom: 1rem;">
                <button type="button" class="form-section-toggle" onclick="toggleTheorySection()" style="width: 100%; text-align: left; padding: 0.75rem; background: var(--color-bg-light); border: 1px solid var(--color-border); border-radius: var(--border-radius); cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-weight: var(--font-weight-semibold);">Curso Teórico (Opcional)</span>
                    <svg id="theoryToggleIcon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="transition: transform 0.2s;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div id="theorySection" style="display: none; padding: 1rem; background: var(--color-bg-light); border: 1px solid var(--color-border); border-top: none; border-radius: 0 0 var(--border-radius) var(--border-radius);">
                    <div class="form-group">
                        <label class="form-label" for="theory_course_id">Template de Curso Teórico</label>
                        <select id="theory_course_id" name="theory_course_id" class="form-select" onchange="updateTheoryClasses()">
                            <option value="">Nenhum (selecionar depois)</option>
                            <?php foreach ($theoryCourses ?? [] as $course): ?>
                                <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Selecione um template de curso teórico (opcional)</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="theory_class_id">Turma Teórica</label>
                        <select id="theory_class_id" name="theory_class_id" class="form-select">
                            <option value="">Nenhuma (criar turma depois)</option>
                            <?php foreach ($theoryClasses ?? [] as $class): ?>
                                <option value="<?= $class['id'] ?>" data-course-id="<?= $class['course_id'] ?>">
                                    <?= htmlspecialchars($class['name'] ?: $class['course_name']) ?> 
                                    (<?= date('d/m/Y', strtotime($class['start_date'] ?? 'now')) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Ou selecione uma turma existente para matricular o aluno diretamente</small>
                    </div>
                </div>
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
                            placeholder="Ex: PROTO-123456"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="situacao_processo">Situação do Processo</label>
                        <select id="situacao_processo" name="situacao_processo" class="form-select">
                            <option value="nao_iniciado">Não Iniciado</option>
                            <option value="em_andamento">Em Andamento</option>
                            <option value="pendente">Pendente</option>
                            <option value="concluido">Concluído</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Criar Matrícula
                </button>
                <a href="<?= base_path("alunos/{$student['id']}") ?>" class="btn btn-outline">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function updatePrice() {
    const select = document.getElementById('service_id');
    const option = select.options[select.selectedIndex];
    const price = parseFloat(option.getAttribute('data-price') || 0);
    
    document.getElementById('base_price').value = price;
    document.getElementById('base_price_display').value = 'R$ ' + price.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    calculateFinal();
}

function calculateFinal() {
    const basePrice = parseFloat(document.getElementById('base_price').value || 0);
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

document.getElementById('enrollmentForm')?.addEventListener('submit', function(e) {
    calculateFinal();
});

function toggleTheorySection() {
    const section = document.getElementById('theorySection');
    const icon = document.getElementById('theoryToggleIcon');
    const isVisible = section.style.display !== 'none';
    
    section.style.display = isVisible ? 'none' : 'block';
    icon.style.transform = isVisible ? 'rotate(0deg)' : 'rotate(180deg)';
}

function updateTheoryClasses() {
    const courseId = document.getElementById('theory_course_id').value;
    const classSelect = document.getElementById('theory_class_id');
    
    // Filtrar turmas por curso selecionado
    Array.from(classSelect.options).forEach(option => {
        if (option.value === '') {
            option.style.display = 'block'; // Sempre mostrar opção "Nenhuma"
        } else if (courseId && option.getAttribute('data-course-id') !== courseId) {
            option.style.display = 'none';
        } else {
            option.style.display = 'block';
        }
    });
    
    // Se nenhum curso selecionado, mostrar todas as turmas
    if (!courseId) {
        Array.from(classSelect.options).forEach(option => {
            option.style.display = 'block';
        });
    }
}

function toggleDetranSection() {
    const section = document.getElementById('detranSection');
    const icon = document.getElementById('detranToggleIcon');
    const isVisible = section.style.display !== 'none';
    
    section.style.display = isVisible ? 'none' : 'block';
    icon.style.transform = isVisible ? 'rotate(0deg)' : 'rotate(180deg)';
}

function togglePaymentConditions() {
    const paymentMethod = document.getElementById('payment_method').value;
    const conditionsSection = document.getElementById('paymentConditionsSection');
    const boletoPixDiv = document.getElementById('boletoPixConditions');
    const cartaoDiv = document.getElementById('cartaoConditions');
    const entradaParcelasDiv = document.getElementById('entradaParcelasConditions');
    const pixAccountSelector = document.getElementById('pixAccountSelector');
    
    // Esconder todas as condições
    boletoPixDiv.style.display = 'none';
    cartaoDiv.style.display = 'none';
    entradaParcelasDiv.style.display = 'none';
    pixAccountSelector.style.display = 'none';
    
    // Remover required de todos os campos
    document.querySelectorAll('#paymentConditionsSection input[required], #paymentConditionsSection select[required]').forEach(el => {
        el.removeAttribute('required');
    });
    
    // Mostrar seção apropriada
    if (paymentMethod === 'pix') {
        // PIX é pagamento à vista - não mostrar parcelas, apenas seletor de conta
        conditionsSection.style.display = 'none';
        pixAccountSelector.style.display = 'block';
        document.getElementById('pix_account_id').setAttribute('required', 'required');
        carregarContasPix();
    } else if (paymentMethod === 'boleto') {
        conditionsSection.style.display = 'block';
        boletoPixDiv.style.display = 'block';
        document.getElementById('installments').setAttribute('required', 'required');
        document.getElementById('first_due_date').setAttribute('required', 'required');
    } else if (paymentMethod === 'cartao') {
        conditionsSection.style.display = 'block';
        cartaoDiv.style.display = 'block';
        document.getElementById('installments_cartao').setAttribute('required', 'required');
        
        // Popup automático: "Já está pago?"
        const isPaid = confirm('Pagamento na maquininha local.\n\nJá está pago?\n\n- OK = Sim, já foi pago\n- Cancelar = Não, ainda não foi pago');
        if (isPaid) {
            // Adicionar campo hidden para indicar que pagamento foi confirmado
            let hiddenInput = document.getElementById('cartao_paid_confirmed');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.id = 'cartao_paid_confirmed';
                hiddenInput.name = 'cartao_paid_confirmed';
                hiddenInput.value = '1';
                document.getElementById('enrollmentForm').appendChild(hiddenInput);
            } else {
                hiddenInput.value = '1';
            }
        } else {
            // Remover campo se existir
            const hiddenInput = document.getElementById('cartao_paid_confirmed');
            if (hiddenInput) {
                hiddenInput.remove();
            }
        }
    } else if (paymentMethod === 'entrada_parcelas') {
        conditionsSection.style.display = 'block';
        entradaParcelasDiv.style.display = 'block';
        document.getElementById('down_payment_amount').setAttribute('required', 'required');
        document.getElementById('down_payment_due_date').setAttribute('required', 'required');
        document.getElementById('installments_entrada').setAttribute('required', 'required');
        document.getElementById('first_due_date_entrada').setAttribute('required', 'required');
    } else {
        conditionsSection.style.display = 'none';
    }
}

// Dados das contas PIX carregados do servidor
const pixAccountsData = <?= json_encode($pixAccounts ?? []) ?>;

function carregarContasPix() {
    const select = document.getElementById('pix_account_id');
    
    select.innerHTML = '';
    
    if (pixAccountsData.length === 0) {
        select.innerHTML = '<option value="">Nenhuma conta PIX configurada</option>';
        select.disabled = true;
        return;
    }
    
    select.disabled = false;
    
    // Encontrar conta padrão
    const defaultAccount = pixAccountsData.find(a => a.is_default == 1);
    
    pixAccountsData.forEach(account => {
        const option = document.createElement('option');
        option.value = account.id;
        option.textContent = account.label + (account.bank_name ? ' - ' + account.bank_name : '');
        if (defaultAccount && account.id == defaultAccount.id) {
            option.selected = true;
        }
        select.appendChild(option);
    });
}

function toggleEntryPixAccount() {
    const entryPaymentMethod = document.getElementById('entry_payment_method').value;
    const entryPixSelector = document.getElementById('entryPixAccountSelector');
    const entryPixSelect = document.getElementById('entry_pix_account_id');
    
    if (entryPaymentMethod === 'pix') {
        entryPixSelector.style.display = 'block';
        entryPixSelect.setAttribute('required', 'required');
        carregarContasPixEntrada();
    } else {
        entryPixSelector.style.display = 'none';
        entryPixSelect.removeAttribute('required');
        entryPixSelect.value = '';
    }
}

function carregarContasPixEntrada() {
    const select = document.getElementById('entry_pix_account_id');
    
    select.innerHTML = '';
    
    if (pixAccountsData.length === 0) {
        select.innerHTML = '<option value="">Nenhuma conta PIX configurada</option>';
        select.disabled = true;
        return;
    }
    
    select.disabled = false;
    select.innerHTML = '<option value="">Selecione uma conta PIX</option>';
    
    // Encontrar conta padrão
    const defaultAccount = pixAccountsData.find(a => a.is_default == 1);
    
    pixAccountsData.forEach(account => {
        const option = document.createElement('option');
        option.value = account.id;
        option.textContent = account.label + (account.bank_name ? ' - ' + account.bank_name : '');
        if (defaultAccount && account.id == defaultAccount.id) {
            option.selected = true;
        }
        select.appendChild(option);
    });
}

function validateDownPayment() {
    const downPayment = parseFloat(document.getElementById('down_payment_amount').value || 0);
    const finalPrice = parseFloat(document.getElementById('final_price').value || 0);
    
    if (downPayment > 0 && finalPrice > 0 && downPayment >= finalPrice) {
        alert('O valor da entrada deve ser menor que o valor final da matrícula.');
        document.getElementById('down_payment_amount').value = '';
        return false;
    }
    return true;
}

// Validar antes do submit
document.getElementById('enrollmentForm')?.addEventListener('submit', function(e) {
    calculateFinal();
    calculateOutstanding();
    
    const paymentMethod = document.getElementById('payment_method').value;
    const entryAmount = parseFloat(document.getElementById('entry_amount').value || 0);
    const finalPrice = parseFloat(document.getElementById('final_price').value || 0);
    
    // Validar entrada
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
        
        // Validar conta PIX da entrada se forma de pagamento for PIX
        if (entryPaymentMethod === 'pix') {
            const entryPixAccountId = document.getElementById('entry_pix_account_id').value;
            if (!entryPixAccountId) {
                e.preventDefault();
                alert('Selecione a conta PIX que recebeu a entrada.');
                return false;
            }
        }
        
        if (entryAmount >= finalPrice) {
            e.preventDefault();
            alert('O valor da entrada deve ser menor que o valor final da matrícula.');
            return false;
        }
    }
    
    // Desabilitar campos ocultos para não enviá-los
    document.querySelectorAll('#paymentConditionsSection input, #paymentConditionsSection select').forEach(el => {
        if (el.closest('[style*="display: none"]') || el.offsetParent === null) {
            el.disabled = true;
        }
    });
    
    if (paymentMethod === 'entrada_parcelas') {
        const downPayment = parseFloat(document.getElementById('down_payment_amount')?.value || 0);
        
        if (downPayment >= finalPrice) {
            e.preventDefault();
            alert('O valor da entrada (parcelamento) deve ser menor que o valor final da matrícula.');
            // Reabilitar campos
            document.querySelectorAll('#paymentConditionsSection input, #paymentConditionsSection select').forEach(el => {
                el.disabled = false;
            });
            return false;
        }
    }
});
</script>
