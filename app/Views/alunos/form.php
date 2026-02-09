<div class="page-header">
    <div>
        <h1><?= $student ? 'Editar' : 'Novo' ?> Aluno</h1>
        <p class="text-muted"><?= $student ? 'Atualize as informações do aluno' : 'Preencha os dados do novo aluno' ?></p>
    </div>
    <a href="<?= base_path('alunos') ?>" class="btn btn-outline">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Voltar
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= base_path($student ? "alunos/{$student['id']}/atualizar" : 'alunos/criar') ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Dados Pessoais -->
            <div class="form-section">
                <h3 class="form-section-title">Dados Pessoais</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="full_name">Nome Completo *</label>
                        <input 
                            type="text" 
                            id="full_name" 
                            name="full_name" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['full_name'] ?? $student['name'] ?? '') ?>" 
                            required
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-col-2">
                        <label class="form-label" for="birth_date">Data de Nascimento *</label>
                        <input 
                            type="date" 
                            id="birth_date" 
                            name="birth_date" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['birth_date'] ?? '') ?>" 
                            required
                            max="<?= date('Y-m-d', strtotime('-16 years')) ?>"
                            min="<?= date('Y-m-d', strtotime('-120 years')) ?>"
                        >
                    </div>
                    <div class="form-group form-col-2">
                        <label class="form-label" for="marital_status">Estado Civil</label>
                        <select id="marital_status" name="marital_status" class="form-select">
                            <option value="">Selecione</option>
                            <option value="solteiro" <?= ($student['marital_status'] ?? '') === 'solteiro' ? 'selected' : '' ?>>Solteiro(a)</option>
                            <option value="casado" <?= ($student['marital_status'] ?? '') === 'casado' ? 'selected' : '' ?>>Casado(a)</option>
                            <option value="divorciado" <?= ($student['marital_status'] ?? '') === 'divorciado' ? 'selected' : '' ?>>Divorciado(a)</option>
                            <option value="viuvo" <?= ($student['marital_status'] ?? '') === 'viuvo' ? 'selected' : '' ?>>Viúvo(a)</option>
                            <option value="uniao_estavel" <?= ($student['marital_status'] ?? '') === 'uniao_estavel' ? 'selected' : '' ?>>União Estável</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-col-2">
                        <label class="form-label" for="profession">Profissão</label>
                        <input 
                            type="text" 
                            id="profession" 
                            name="profession" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['profession'] ?? '') ?>"
                        >
                    </div>
                    <div class="form-group form-col-2">
                        <label class="form-label" for="education_level">Escolaridade</label>
                        <select id="education_level" name="education_level" class="form-select">
                            <option value="">Selecione</option>
                            <option value="fundamental_incompleto" <?= ($student['education_level'] ?? '') === 'fundamental_incompleto' ? 'selected' : '' ?>>Fundamental Incompleto</option>
                            <option value="fundamental_completo" <?= ($student['education_level'] ?? '') === 'fundamental_completo' ? 'selected' : '' ?>>Fundamental Completo</option>
                            <option value="medio_incompleto" <?= ($student['education_level'] ?? '') === 'medio_incompleto' ? 'selected' : '' ?>>Médio Incompleto</option>
                            <option value="medio_completo" <?= ($student['education_level'] ?? '') === 'medio_completo' ? 'selected' : '' ?>>Médio Completo</option>
                            <option value="superior_incompleto" <?= ($student['education_level'] ?? '') === 'superior_incompleto' ? 'selected' : '' ?>>Superior Incompleto</option>
                            <option value="superior_completo" <?= ($student['education_level'] ?? '') === 'superior_completo' ? 'selected' : '' ?>>Superior Completo</option>
                            <option value="pos_graduacao" <?= ($student['education_level'] ?? '') === 'pos_graduacao' ? 'selected' : '' ?>>Pós-Graduação</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-col-2">
                        <label class="form-label" for="nationality">Nacionalidade</label>
                        <input 
                            type="text" 
                            id="nationality" 
                            name="nationality" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['nationality'] ?? 'Brasileira') ?>"
                            placeholder="Ex: Brasileira"
                        >
                    </div>
                    <div class="form-group form-col-2">
                        <label class="form-label">
                            <input 
                                type="checkbox" 
                                id="remunerated_activity" 
                                name="remunerated_activity" 
                                value="1"
                                <?= !empty($student['remunerated_activity']) ? 'checked' : '' ?>
                            >
                            Exerce atividade remunerada
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-col-2">
                        <label class="form-label" for="birth_state_uf">UF de Nascimento</label>
                        <select id="birth_state_uf" name="birth_state_uf" class="form-select">
                            <option value="">Selecione</option>
                            <?php
                            $ufs = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                            foreach ($ufs as $uf):
                            ?>
                            <option value="<?= $uf ?>" <?= ($student['birth_state_uf'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group form-col-2">
                        <label class="form-label" for="birth_city_search">Cidade de Nascimento</label>
                        <div class="city-autocomplete-wrapper" data-city-hint="Digite para buscar e clique na cidade na lista.">
                            <div class="city-input-row">
                                <input 
                                    type="text" 
                                    id="birth_city_search" 
                                    class="form-input city-search-input" 
                                    placeholder="Digite para buscar cidade..."
                                    autocomplete="nope"
                                    data-lpignore="true"
                                    data-form-type="other"
                                    disabled
                                >
                                <span class="city-selected-icon" aria-hidden="true" title="Cidade selecionada"></span>
                                <button type="button" class="city-clear-btn" aria-label="Limpar cidade" title="Limpar e buscar outra">×</button>
                                <div id="birth_city_dropdown" class="city-dropdown" style="display: none;"></div>
                            </div>
                            <input type="hidden" id="birth_city_id" name="birth_city_id" value="<?= !empty($currentBirthCity) ? $currentBirthCity['id'] : '' ?>">
                            <p class="city-field-hint" id="birth_city_hint"></p>
                            <p class="city-field-error" id="birth_city_error" role="alert"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documentos -->
            <div class="form-section">
                <h3 class="form-section-title">Documentos</h3>
                <div class="form-row">
                    <div class="form-group form-col-2">
                        <label class="form-label" for="cpf">CPF *</label>
                        <input 
                            type="text" 
                            id="cpf" 
                            name="cpf" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['cpf'] ?? '') ?>" 
                            required
                            placeholder="000.000.000-00"
                            maxlength="14"
                        >
                    </div>
                    <div class="form-group form-col-2">
                        <label class="form-label" for="rg_number">RG</label>
                        <input 
                            type="text" 
                            id="rg_number" 
                            name="rg_number" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['rg_number'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-col-2">
                        <label class="form-label" for="rg_issuer">Órgão Emissor</label>
                        <input 
                            type="text" 
                            id="rg_issuer" 
                            name="rg_issuer" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['rg_issuer'] ?? '') ?>"
                            placeholder="Ex: SSP, IFP"
                        >
                    </div>
                    <div class="form-group form-col-2">
                        <label class="form-label" for="rg_uf">UF do RG</label>
                        <select id="rg_uf" name="rg_uf" class="form-select">
                            <option value="">Selecione</option>
                            <?php foreach ($ufs as $uf): ?>
                            <option value="<?= $uf ?>" <?= ($student['rg_uf'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-col-2">
                        <label class="form-label" for="rg_issue_date">Data de Emissão do RG</label>
                        <input 
                            type="date" 
                            id="rg_issue_date" 
                            name="rg_issue_date" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['rg_issue_date'] ?? '') ?>"
                        >
                    </div>
                    <div class="form-group form-col-2">
                        <label class="form-label" for="numero_pe">PE (DETRAN-PE)</label>
                        <input 
                            type="text" 
                            id="numero_pe" 
                            name="numero_pe" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['numero_pe'] ?? '') ?>"
                            placeholder="9 dígitos"
                            maxlength="9"
                            pattern="[0-9]{9}"
                            inputmode="numeric"
                            title="9 dígitos"
                        >
                    </div>
                </div>
            </div>

            <!-- Filiação -->
            <div class="form-section">
                <h3 class="form-section-title">Filiação</h3>
                <div class="form-row">
                    <div class="form-group form-col-2">
                        <label class="form-label" for="nome_mae">Nome da mãe</label>
                        <input 
                            type="text" 
                            id="nome_mae" 
                            name="nome_mae" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['nome_mae'] ?? '') ?>"
                            placeholder="Nome da mãe"
                            maxlength="255"
                        >
                    </div>
                    <div class="form-group form-col-2">
                        <label class="form-label" for="nome_pai">Nome do pai</label>
                        <input 
                            type="text" 
                            id="nome_pai" 
                            name="nome_pai" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['nome_pai'] ?? '') ?>"
                            placeholder="Nome do pai"
                            maxlength="255"
                        >
                    </div>
                </div>
            </div>

            <!-- Contato -->
            <div class="form-section">
                <h3 class="form-section-title">Contato</h3>
                <div class="form-row">
                    <div class="form-group form-col-2">
                        <label class="form-label" for="phone_primary">Telefone Principal *</label>
                        <input 
                            type="text" 
                            id="phone_primary" 
                            name="phone_primary" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['phone_primary'] ?? $student['phone'] ?? '') ?>" 
                            required
                            placeholder="(00) 00000-0000"
                        >
                    </div>
                    <div class="form-group form-col-2">
                        <label class="form-label" for="phone_secondary">Telefone Secundário</label>
                        <input 
                            type="text" 
                            id="phone_secondary" 
                            name="phone_secondary" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['phone_secondary'] ?? '') ?>"
                            placeholder="(00) 00000-0000"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-col-2">
                        <label class="form-label" for="email">Email *</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['email'] ?? '') ?>"
                            required
                        >
                        <small class="form-text text-muted">Necessário para criar acesso ao sistema</small>
                    </div>
                </div>
            </div>

            <!-- Endereço -->
            <div class="form-section">
                <h3 class="form-section-title">Endereço</h3>
                <div class="form-row">
                    <div class="form-group form-col-2">
                        <label class="form-label" for="cep">CEP</label>
                        <div class="cep-input-wrapper">
                            <input 
                                type="text" 
                                id="cep" 
                                name="cep" 
                                class="form-input" 
                                value="<?= htmlspecialchars($student['cep'] ?? '') ?>"
                                placeholder="00000-000"
                                maxlength="9"
                            >
                            <span id="cep-loading-text" class="cep-loading-text" style="display: none;">Buscando CEP...</span>
                        </div>
                    </div>
                    <div class="form-group form-col-2">
                        <label class="form-label" for="street">Logradouro</label>
                        <input 
                            type="text" 
                            id="street" 
                            name="street" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['street'] ?? '') ?>"
                            placeholder="Rua, Avenida, etc"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-col-3">
                        <label class="form-label" for="number">Número</label>
                        <input 
                            type="text" 
                            id="number" 
                            name="number" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['number'] ?? '') ?>"
                        >
                    </div>
                    <div class="form-group form-col-3">
                        <label class="form-label" for="complement">Complemento</label>
                        <input 
                            type="text" 
                            id="complement" 
                            name="complement" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['complement'] ?? '') ?>"
                            placeholder="Apto, Bloco, etc"
                        >
                    </div>
                    <div class="form-group form-col-3">
                        <label class="form-label" for="neighborhood">Bairro</label>
                        <input 
                            type="text" 
                            id="neighborhood" 
                            name="neighborhood" 
                            class="form-input" 
                            value="<?= htmlspecialchars($student['neighborhood'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-col-2">
                        <label class="form-label" for="state_uf">UF *</label>
                        <select id="state_uf" name="state_uf" class="form-select" required>
                            <option value="">Selecione</option>
                            <?php 
                            $states = $states ?? [];
                            $currentStateUf = $student['state_uf'] ?? '';
                            foreach ($states as $state): 
                            ?>
                            <option value="<?= htmlspecialchars($state['uf']) ?>" <?= $currentStateUf === $state['uf'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($state['uf']) ?> - <?= htmlspecialchars($state['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group form-col-2">
                        <label class="form-label" for="city_search">Cidade <span id="city_required" style="display: none;">*</span></label>
                        <div class="city-autocomplete-wrapper" data-city-hint="Digite para buscar e clique na cidade na lista.">
                            <div class="city-input-row">
                                <input 
                                    type="text" 
                                    id="city_search" 
                                    class="form-input city-search-input" 
                                    placeholder="Digite para buscar cidade..."
                                    autocomplete="nope"
                                    data-lpignore="true"
                                    data-form-type="other"
                                    disabled
                                >
                                <span class="city-selected-icon" aria-hidden="true" title="Cidade selecionada"></span>
                                <button type="button" class="city-clear-btn" aria-label="Limpar cidade" title="Limpar e buscar outra">×</button>
                                <div id="city_dropdown" class="city-dropdown" style="display: none;"></div>
                            </div>
                            <input type="hidden" id="city_id" name="city_id" value="<?= !empty($currentCity) ? $currentCity['id'] : '' ?>">
                            <p class="city-field-hint" id="city_hint"></p>
                            <p class="city-field-error" id="city_error" role="alert"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outros -->
            <div class="form-section">
                <h3 class="form-section-title">Outros</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="notes">Observações</label>
                        <textarea 
                            id="notes" 
                            name="notes" 
                            class="form-textarea" 
                            rows="4"
                        ><?= htmlspecialchars($student['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $student ? 'Atualizar' : 'Criar' ?> Aluno
                </button>
                <a href="<?= base_path('alunos') ?>" class="btn btn-outline">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.form-section {
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-lg);
    border-bottom: 1px solid var(--color-border);
}

.form-section:last-of-type {
    border-bottom: none;
}

.form-section-title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    margin-bottom: var(--spacing-md);
    color: var(--color-text);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
}

@media (min-width: 768px) {
    .form-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-col-3 {
        grid-column: span 1;
    }
}

.form-col-2 {
    grid-column: span 1;
}

.form-col-3 {
    grid-column: span 1;
}

@media (min-width: 768px) {
    .form-col-3 {
        grid-column: span 1;
    }
}

.form-group input[type="checkbox"] {
    width: auto;
    margin-right: var(--spacing-xs);
}

/* City Autocomplete Styles */
.city-autocomplete-wrapper {
    position: relative;
}

.city-input-row {
    position: relative;
    display: block;
}

.city-search-input {
    width: 100%;
    padding-right: 2.5rem;
}

.city-search-input:disabled {
    background-color: var(--color-bg-light, #f5f5f5);
    cursor: not-allowed;
    opacity: 0.6;
}

.city-search-input.form-input-error {
    border-color: var(--color-error, #dc3545);
}

.city-search-input.city-selected {
    border-color: var(--color-success, #28a745);
    background-color: rgba(40, 167, 69, 0.04);
}

.city-selected-icon {
    display: none;
    position: absolute;
    right: 2rem;
    top: 50%;
    transform: translateY(-50%);
    width: 1rem;
    height: 1rem;
    background: var(--color-success, #28a745);
    mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E") center/contain no-repeat;
    -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E") center/contain no-repeat;
    pointer-events: none;
}

.city-autocomplete-wrapper.has-selected .city-selected-icon {
    display: block;
}

.city-clear-btn {
    display: none;
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    width: 1.5rem;
    height: 1.5rem;
    padding: 0;
    border: none;
    background: transparent;
    color: var(--color-text-muted, #6c757d);
    font-size: 1.25rem;
    line-height: 1;
    cursor: pointer;
    border-radius: var(--radius-sm, 2px);
    transition: color 0.15s, background 0.15s;
}

.city-clear-btn:hover {
    color: var(--color-error, #dc3545);
    background: rgba(220, 53, 69, 0.08);
}

.city-autocomplete-wrapper.has-selected .city-clear-btn {
    display: flex;
    align-items: center;
    justify-content: center;
}

.city-field-hint {
    margin: var(--spacing-xs, 4px) 0 0;
    font-size: var(--font-size-xs, 12px);
    color: var(--color-text-muted, #6c757d);
    line-height: 1.3;
}

.city-field-error {
    margin: var(--spacing-xs, 4px) 0 0;
    font-size: var(--font-size-xs, 12px);
    color: var(--color-error, #dc3545);
    line-height: 1.3;
}

.city-field-error:empty {
    display: none;
}

.city-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    background: white;
    border: 1px solid var(--color-border, #ddd);
    border-radius: var(--radius-md, 4px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    max-height: 300px;
    overflow-y: auto;
    margin-top: 2px;
}

.city-dropdown-item {
    padding: var(--spacing-sm, 8px) var(--spacing-md, 12px);
    cursor: pointer;
    transition: background-color 0.15s ease;
    border-bottom: 1px solid var(--color-border-light, #f0f0f0);
}

.city-dropdown-item:last-child {
    border-bottom: none;
}

.city-dropdown-item:hover {
    background-color: var(--color-bg-light, #f5f5f5);
}

.city-dropdown-item.city-dropdown-selected {
    background-color: var(--color-primary-light, #e3f2fd);
    font-weight: var(--font-weight-medium, 500);
}

.city-dropdown-item.city-dropdown-empty {
    color: var(--color-text-muted, #666);
    cursor: default;
    text-align: center;
    padding: var(--spacing-md, 12px);
}

.city-dropdown-item.city-dropdown-empty:hover {
    background-color: transparent;
}

/* Mobile optimizations */
@media (max-width: 767px) {
    .city-dropdown {
        max-height: 200px;
        font-size: 16px; /* Evita zoom no iOS */
    }
    
    .city-dropdown-item {
        padding: var(--spacing-md, 12px);
        min-height: 44px; /* Tamanho mínimo para touch */
        display: flex;
        align-items: center;
    }
}

/* CEP Autocomplete Styles */
.cep-input-wrapper {
    position: relative;
}

.cep-loading-text {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: var(--font-size-xs, 12px);
    color: var(--color-text-muted, #6c757d);
    pointer-events: none;
    background-color: var(--color-bg, #ffffff);
    padding: 0 var(--spacing-xs, 4px);
}

.cep-message {
    font-size: var(--font-size-sm, 12px);
    margin-top: var(--spacing-xs, 4px);
    padding: var(--spacing-xs, 4px) var(--spacing-sm, 8px);
    border-radius: var(--radius-sm, 2px);
}

.cep-message-success {
    color: var(--color-text-muted, #6c757d);
    background-color: transparent;
}

.cep-message-warning {
    color: var(--color-text-muted, #6c757d);
    background-color: transparent;
}

.cep-message-error {
    color: var(--color-text-muted, #6c757d);
    background-color: transparent;
}
</style>

<script>
// Máscara CPF
document.getElementById('cpf')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = value;
    }
});

// Máscara Telefone
function applyPhoneMask(input) {
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 11) {
            if (value.length <= 10) {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
            }
            e.target.value = value;
        }
    });
}

applyPhoneMask(document.getElementById('phone_primary'));
applyPhoneMask(document.getElementById('phone_secondary'));

// Máscara CEP e autopreenchimento via ViaCEP
(function() {
    const cepInput = document.getElementById('cep');
    if (!cepInput) {
        console.warn('[CEP] Campo CEP não encontrado');
        return;
    }
    
    // Referências aos campos de endereço
    const streetInput = document.getElementById('street');
    const neighborhoodInput = document.getElementById('neighborhood');
    const stateUfSelect = document.getElementById('state_uf');
    const citySearchInput = document.getElementById('city_search');
    const cityIdInput = document.getElementById('city_id');
    
    // Validar elementos necessários
    if (!streetInput || !neighborhoodInput || !stateUfSelect) {
        console.warn('[CEP] Alguns campos de endereço não foram encontrados', {
            street: !!streetInput,
            neighborhood: !!neighborhoodInput,
            state_uf: !!stateUfSelect
        });
    }
    
    let cepFieldsFilled = {
        street: false,
        neighborhood: false,
        state_uf: false,
        city: false
    };
    
    // Rastrear se campos foram preenchidos manualmente
    if (streetInput) {
        streetInput.addEventListener('input', function() {
            if (this.value.trim()) cepFieldsFilled.street = true;
        });
    }
    
    if (neighborhoodInput) {
        neighborhoodInput.addEventListener('input', function() {
            if (this.value.trim()) cepFieldsFilled.neighborhood = true;
        });
    }
    
    if (stateUfSelect) {
        stateUfSelect.addEventListener('change', function() {
            if (this.value) cepFieldsFilled.state_uf = true;
        });
    }
    
    // ===== FUNÇÕES PURAS =====
    
    /**
     * Extrai apenas os dígitos do CEP
     */
    function getCepDigits(cepRaw) {
        const cep = cepRaw.replace(/\D/g, '');
        console.log('[CEP] getCepDigits', { cepRaw, cepDigits: cep });
        return cep;
    }
    
    /**
     * Busca dados do CEP na API
     */
    async function fetchCep(cep) {
        const url = '<?= base_path('api/geo/cep') ?>?cep=' + encodeURIComponent(cep);
        console.log('[CEP] fetch', url);
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            console.log('[CEP] response status', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('[CEP] data', data);
            
            return data;
        } catch (error) {
            console.error('[CEP] fetch error', error);
            throw error;
        }
    }
    
    /**
     * Aplica os dados do CEP nos campos do formulário
     */
    function applyCepData(data) {
        console.log('[CEP] applyCepData', data);
        
        if (data.erro || !data.success) {
            console.warn('[CEP] CEP não encontrado ou erro na resposta');
            showCepMessage('CEP não encontrado', 'error');
            return;
        }
        
        // Preencher logradouro (apenas se não foi preenchido manualmente)
        if (data.logradouro && !cepFieldsFilled.street && streetInput) {
            streetInput.value = data.logradouro;
            console.log('[CEP] Preenchido logradouro:', data.logradouro);
        }
        
        // Preencher bairro (apenas se não foi preenchido manualmente)
        if (data.bairro && !cepFieldsFilled.neighborhood && neighborhoodInput) {
            neighborhoodInput.value = data.bairro;
            console.log('[CEP] Preenchido bairro:', data.bairro);
        }
        
        // Preencher UF e cidade
        if (data.uf && stateUfSelect) {
            // Selecionar UF
            stateUfSelect.value = data.uf;
            console.log('[CEP] Selecionada UF:', data.uf);
            
            // Disparar evento change para habilitar campo de cidade
            const changeEvent = new Event('change', { bubbles: true });
            stateUfSelect.dispatchEvent(changeEvent);
            
            // Se cidade foi encontrada na base IBGE, preencher
            if (data.city_found && data.city_id && data.city_name) {
                setTimeout(() => {
                    const citySearch = document.getElementById('city_search');
                    const cityId = document.getElementById('city_id');
                    
                    if (citySearch && cityId) {
                        citySearch.value = data.city_name;
                        cityId.value = data.city_id;
                        citySearch.disabled = false;
                        citySearch.classList.remove('form-input-error');
                        cepFieldsFilled.city = true;
                        citySearch.dispatchEvent(new CustomEvent('city-set-external', {
                            bubbles: true,
                            detail: { cityId: data.city_id, cityName: data.city_name }
                        }));
                        console.log('[CEP] Preenchida cidade:', data.city_name, 'ID:', data.city_id);
                    } else {
                        console.warn('[CEP] Campos de cidade não encontrados');
                    }
                }, 200);
            } else if (data.cidade) {
                // Cidade não encontrada na base, mas temos o nome do ViaCEP
                setTimeout(() => {
                    const citySearch = document.getElementById('city_search');
                    if (citySearch) {
                        citySearch.disabled = false;
                        citySearch.placeholder = 'Digite para buscar: ' + data.cidade;
                        console.log('[CEP] Cidade não encontrada na base, sugerindo:', data.cidade);
                    }
                }, 200);
                
                showCepMessage('Cidade "' + data.cidade + '" não encontrada na base. Selecione manualmente.', 'warning');
            }
        }
        
        // Mensagem de sucesso discreta
        if (data.city_found) {
            showCepMessage('Endereço preenchido automaticamente', 'success');
        }
    }
    
    /**
     * Controla o estado de loading (não bloqueia o preenchimento)
     */
    function setCepLoading(show) {
        const loadingText = document.getElementById('cep-loading-text');
        if (loadingText) {
            loadingText.style.display = show ? 'block' : 'none';
            cepInput.style.paddingRight = show ? '120px' : '';
        }
    }
    
    /**
     * Função principal para consultar CEP
     */
    async function consultarCep() {
        const cepRaw = cepInput.value;
        const cep = getCepDigits(cepRaw);
        
        if (cep.length !== 8) {
            console.log('[CEP] CEP inválido, não consultando', { cepRaw, cepDigits: cep });
            return;
        }
        
        console.log('[CEP] Iniciando consulta', { cepRaw, cepDigits: cep });
        
        // Mostrar loading (não bloqueia)
        setCepLoading(true);
        
        // Remover mensagens anteriores
        const existingMsg = document.getElementById('cep-message');
        if (existingMsg) {
            existingMsg.remove();
        }
        
        try {
            // Buscar dados do CEP
            const data = await fetchCep(cep);
            
            // Aplicar dados (independente do loading)
            applyCepData(data);
        } catch (error) {
            console.error('[CEP] Erro na consulta', error);
            showCepMessage('Falha ao consultar CEP', 'error');
        } finally {
            // Sempre ocultar loading ao final
            setCepLoading(false);
        }
    }
    
    // Máscara CEP
    cepInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 8) {
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
            e.target.value = value;
        }
    });
    
    // Consultar CEP ao completar 8 dígitos ou ao sair do campo
    let consultTimeout = null;
    
    // Consultar ao completar 8 dígitos
    cepInput.addEventListener('input', function() {
        const cepRaw = this.value;
        const cep = getCepDigits(cepRaw);
        
        console.log('[CEP] input disparou', { cepRaw, cepDigits: cep });
        
        clearTimeout(consultTimeout);
        
        if (cep.length === 8) {
            consultTimeout = setTimeout(() => {
                console.log('[CEP] Timeout disparado, consultando...');
                consultarCep();
            }, 500); // Debounce de 500ms
        }
    });
    
    // Consultar ao sair do campo (se tiver 8 dígitos)
    cepInput.addEventListener('blur', function() {
        const cepRaw = this.value;
        const cep = getCepDigits(cepRaw);
        
        console.log('[CEP] blur disparou', { cepRaw, cepDigits: cep });
        
        if (cep.length === 8) {
            clearTimeout(consultTimeout);
            consultarCep();
        }
    });
    
    // Função para exibir mensagens
    function showCepMessage(message, type) {
        // Remover mensagem anterior
        const existingMsg = document.getElementById('cep-message');
        if (existingMsg) {
            existingMsg.remove();
        }
        
        // Criar nova mensagem
        const msg = document.createElement('div');
        msg.id = 'cep-message';
        msg.className = 'cep-message cep-message-' + type;
        msg.textContent = message;
        
        // Inserir após o wrapper do CEP (dentro do form-group)
        const wrapper = cepInput.closest('.cep-input-wrapper');
        const formGroup = cepInput.closest('.form-group');
        
        if (formGroup) {
            // Inserir após o wrapper dentro do form-group
            if (wrapper && wrapper.parentNode === formGroup) {
                formGroup.insertBefore(msg, wrapper.nextSibling);
            } else {
                // Fallback: inserir no final do form-group
                formGroup.appendChild(msg);
            }
        } else {
            // Fallback: inserir após o wrapper
            const parent = wrapper || cepInput.parentNode;
            if (parent) {
                parent.insertBefore(msg, (wrapper || cepInput).nextSibling);
            }
        }
        
        // Remover após 5 segundos (exceto warnings)
        if (type !== 'warning') {
            setTimeout(() => {
                if (msg.parentNode) {
                    msg.remove();
                }
            }, 5000);
        }
    }
})();

// Componente reutilizável de autocomplete de cidade
function initCityAutocomplete(config) {
    const {
        stateUfSelectId,
        citySearchInputId,
        cityIdInputId,
        cityDropdownId,
        cityRequiredId,
        currentCityId,
        currentCityName,
        currentStateUf,
        fieldName
    } = config;
    
    const stateUfSelect = document.getElementById(stateUfSelectId);
    const citySearchInput = document.getElementById(citySearchInputId);
    const cityIdInput = document.getElementById(cityIdInputId);
    const cityDropdown = document.getElementById(cityDropdownId);
    const cityRequired = cityRequiredId ? document.getElementById(cityRequiredId) : null;
    
    if (!stateUfSelect || !citySearchInput || !cityIdInput || !cityDropdown) {
        return;
    }
    
    const wrapper = citySearchInput.closest('.city-autocomplete-wrapper');
    const hintEl = wrapper?.querySelector('.city-field-hint');
    const errorEl = wrapper?.querySelector('.city-field-error');
    const clearBtn = wrapper?.querySelector('.city-clear-btn');
    
    const HINT_TEXT = 'Digite para buscar e clique na cidade na lista.';
    const ERROR_MSG = 'Selecione a cidade na lista.';
    
    let searchTimeout = null;
    let selectedCityId = currentCityId || null;
    let selectedCityName = currentCityName || '';
    
    function setSelectedState(selected) {
        if (!wrapper) return;
        if (selected) {
            wrapper.classList.add('has-selected');
            citySearchInput.classList.add('city-selected');
        } else {
            wrapper.classList.remove('has-selected');
            citySearchInput.classList.remove('city-selected');
        }
    }
    
    function showError(msg) {
        if (errorEl) {
            errorEl.textContent = msg || ERROR_MSG;
        }
        citySearchInput.classList.add('form-input-error');
    }
    
    function clearError() {
        if (errorEl) errorEl.textContent = '';
        citySearchInput.classList.remove('form-input-error');
    }
    
    if (hintEl) hintEl.textContent = HINT_TEXT;
    
    if (currentCityName && currentStateUf) {
        citySearchInput.value = currentCityName;
        cityIdInput.value = currentCityId || '';
    }
    
    stateUfSelect.addEventListener('change', function() {
        const uf = this.value;
        citySearchInput.value = '';
        cityIdInput.value = '';
        citySearchInput.disabled = !uf;
        cityDropdown.style.display = 'none';
        selectedCityId = null;
        selectedCityName = '';
        setSelectedState(false);
        clearError();
        
        if (cityRequired) {
            if (uf) {
                cityRequired.style.display = 'inline';
                citySearchInput.setAttribute('required', 'required');
            } else {
                cityRequired.style.display = 'none';
                citySearchInput.removeAttribute('required');
            }
        }
    });
    
    citySearchInput.addEventListener('input', function() {
        const query = this.value.trim();
        const uf = stateUfSelect.value;
        
        if (!query) {
            cityIdInput.value = '';
            selectedCityId = null;
            selectedCityName = '';
            setSelectedState(false);
            clearError();
            cityDropdown.style.display = 'none';
            return;
        }
        
        if (selectedCityId && query !== selectedCityName) {
            cityIdInput.value = '';
            selectedCityId = null;
            selectedCityName = '';
            setSelectedState(false);
            clearError();
        }
        
        if (!uf || query.length < 2) {
            cityDropdown.style.display = 'none';
            return;
        }
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchCities(uf, query);
        }, 300);
    });
    
    citySearchInput.addEventListener('blur', function() {
        const uf = stateUfSelect.value;
        if (uf && !cityIdInput.value && citySearchInput.value.trim()) {
            showError(ERROR_MSG);
        }
    });
    
    citySearchInput.addEventListener('focus', function() {
        clearError();
    });
    
    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            citySearchInput.value = '';
            cityIdInput.value = '';
            selectedCityId = null;
            selectedCityName = '';
            setSelectedState(false);
            clearError();
            cityDropdown.style.display = 'none';
            citySearchInput.focus();
        });
    }
    
    const closeDropdown = function(e) {
        const row = citySearchInput.closest('.city-input-row');
        const inRow = row && row.contains(e.target);
        const inDropdown = cityDropdown.contains(e.target);
        if (!inRow && !inDropdown) {
            cityDropdown.style.display = 'none';
        }
    };
    document.addEventListener('click', closeDropdown);
    
    function searchCities(uf, query) {
        const url = '<?= base_path('api/geo/cidades') ?>?uf=' + encodeURIComponent(uf) + '&q=' + encodeURIComponent(query);
        fetch(url, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) throw new Error('Erro ao buscar cidades');
            return response.json();
        })
        .then(cidades => {
            displayCities(cidades);
        })
        .catch(err => {
            console.error('Erro:', err);
            cityDropdown.innerHTML = '<div class="city-dropdown-item city-dropdown-empty">Erro ao buscar cidades</div>';
            cityDropdown.style.display = 'block';
        });
    }
    
    function displayCities(cidades) {
        if (cidades.length === 0) {
            cityDropdown.innerHTML = '<div class="city-dropdown-item city-dropdown-empty">Nenhuma cidade encontrada</div>';
            cityDropdown.style.display = 'block';
            return;
        }
        cityDropdown.innerHTML = '';
        cidades.forEach(cidade => {
            const item = document.createElement('div');
            item.className = 'city-dropdown-item';
            item.textContent = cidade.name;
            item.dataset.cityId = cidade.id;
            item.dataset.cityName = cidade.name;
            if (String(cidade.id) === String(selectedCityId)) {
                item.classList.add('city-dropdown-selected');
            }
            item.addEventListener('click', function() {
                selectCity(cidade.id, cidade.name);
            });
            cityDropdown.appendChild(item);
        });
        cityDropdown.style.display = 'block';
    }
    
    function selectCity(cityId, cityName) {
        selectedCityId = cityId;
        selectedCityName = cityName;
        citySearchInput.value = cityName;
        cityIdInput.value = cityId;
        cityDropdown.style.display = 'none';
        setSelectedState(true);
        clearError();
    }
    
    citySearchInput.addEventListener('city-set-external', function(e) {
        const d = e.detail || {};
        const id = d.cityId ?? d.city_id;
        const name = d.cityName ?? d.city_name;
        if (id != null && name) {
            selectedCityId = id;
            selectedCityName = name;
            setSelectedState(true);
            clearError();
        }
    });
    
    const form = citySearchInput.closest('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const uf = stateUfSelect.value;
            if (uf && !cityIdInput.value) {
                e.preventDefault();
                showError(ERROR_MSG);
                citySearchInput.focus();
                citySearchInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        });
    }
    
    if (currentStateUf && stateUfSelect.value === currentStateUf) {
        citySearchInput.disabled = false;
        if (cityRequired) {
            cityRequired.style.display = 'inline';
            citySearchInput.setAttribute('required', 'required');
        }
        if (currentCityName && currentCityId) {
            citySearchInput.value = currentCityName;
            cityIdInput.value = currentCityId;
            selectedCityId = currentCityId;
            selectedCityName = currentCityName;
            setSelectedState(true);
        }
    }
}

// Inicializar autocomplete para cidade do endereço
initCityAutocomplete({
    stateUfSelectId: 'state_uf',
    citySearchInputId: 'city_search',
    cityIdInputId: 'city_id',
    cityDropdownId: 'city_dropdown',
    cityRequiredId: 'city_required',
    currentCityId: <?= !empty($student['city_id']) ? (int)$student['city_id'] : 'null' ?>,
    currentCityName: '<?= !empty($currentCity) ? htmlspecialchars($currentCity['name'], ENT_QUOTES) : '' ?>',
    currentStateUf: '<?= htmlspecialchars($student['state_uf'] ?? '', ENT_QUOTES) ?>',
    fieldName: 'endereço'
});

// Inicializar autocomplete para cidade de nascimento
initCityAutocomplete({
    stateUfSelectId: 'birth_state_uf',
    citySearchInputId: 'birth_city_search',
    cityIdInputId: 'birth_city_id',
    cityDropdownId: 'birth_city_dropdown',
    cityRequiredId: null,
    currentCityId: <?= !empty($student['birth_city_id']) ? (int)$student['birth_city_id'] : 'null' ?>,
    currentCityName: '<?= !empty($currentBirthCity) ? htmlspecialchars($currentBirthCity['name'], ENT_QUOTES) : '' ?>',
    currentStateUf: '<?= htmlspecialchars($student['birth_state_uf'] ?? '', ENT_QUOTES) ?>',
    fieldName: 'nascimento'
});
</script>
