<div class="page-header">
    <div class="page-header-content">
        <div>
            <h1>Configurações do CFC</h1>
            <p class="text-muted">Configure o logo do CFC para o aplicativo PWA</p>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom: var(--spacing-md);">
    <div class="card-body">
        <h2 style="margin-bottom: var(--spacing-md); font-size: 1.25rem;">Logo do CFC</h2>
        <p class="text-muted" style="margin-bottom: var(--spacing-lg);">
            O logo será usado para gerar os ícones do aplicativo PWA (192x192 e 512x512).
            Recomendamos uma imagem quadrada ou com proporção próxima de 1:1.
        </p>

        <?php if ($hasLogo): ?>
            <div style="margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: var(--color-gray-50); border-radius: var(--radius-md);">
                <div style="display: flex; align-items: center; gap: var(--spacing-md);">
                    <div>
                        <img 
                            src="<?= base_url('login/cfc-logo') ?>" 
                            alt="Logo do CFC" 
                            style="max-width: 150px; max-height: 150px; border-radius: var(--radius-md); box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                            onerror="this.style.display='none'; this.parentElement.parentElement.querySelector('.logo-error')?.style.display='block';"
                        >
                        <div class="logo-error" style="display: none; padding: 10px; background: var(--color-warning-light); border-radius: var(--radius-md); color: var(--color-warning);">
                            Erro ao carregar logo
                        </div>
                    </div>
                    <div style="flex: 1;">
                        <p style="margin: 0 0 var(--spacing-xs) 0; font-weight: 500;">Logo atual</p>
                        <p style="margin: 0; font-size: 0.875rem; color: var(--color-gray-600);">
                            <?= htmlspecialchars(basename($cfc['logo_path'])) ?>
                        </p>
                        <?php if ($iconsExist): ?>
                            <p style="margin: var(--spacing-xs) 0 0 0; font-size: 0.875rem; color: var(--color-success);">
                                ✅ Ícones PWA gerados com sucesso
                            </p>
                        <?php else: ?>
                            <p style="margin: var(--spacing-xs) 0 0 0; font-size: 0.875rem; color: var(--color-warning);">
                                ⚠️ Ícones PWA não encontrados (serão gerados ao fazer upload)
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div style="margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: var(--color-gray-50); border-radius: var(--radius-md); border: 2px dashed var(--color-gray-300); text-align: center;">
                <p style="margin: 0; color: var(--color-gray-600);">
                    Nenhum logo cadastrado. Faça upload de um logo para personalizar os ícones do aplicativo.
                </p>
            </div>
        <?php endif; ?>

        <!-- Preview do logo selecionado (antes do upload) -->
        <div id="logo-preview-container" style="display: none; margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: var(--color-gray-50); border-radius: var(--radius-md);">
            <div style="display: flex; align-items: center; gap: var(--spacing-md);">
                <div>
                    <img 
                        id="logo-preview" 
                        src="" 
                        alt="Preview do logo" 
                        style="max-width: 150px; max-height: 150px; border-radius: var(--radius-md); box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                    >
                </div>
                <div style="flex: 1;">
                    <p style="margin: 0 0 var(--spacing-xs) 0; font-weight: 500;">Preview do logo selecionado</p>
                    <p id="logo-preview-name" style="margin: 0; font-size: 0.875rem; color: var(--color-gray-600);"></p>
                </div>
            </div>
        </div>

        <form id="logoUploadForm" method="POST" action="<?= base_url('configuracoes/cfc/logo/upload') ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label class="form-label" for="logo">
                    <?= $hasLogo ? 'Substituir Logo' : 'Fazer Upload do Logo' ?>
                    <span class="text-danger">*</span>
                </label>
                <input 
                    type="file" 
                    name="logo" 
                    id="logo" 
                    form="logoUploadForm"
                    class="form-input" 
                    accept="image/jpeg,image/jpg,image/png,image/webp"
                    <?= !$hasLogo ? 'required' : '' ?>
                >
                <small class="form-hint">
                    Formatos aceitos: JPG, PNG, WEBP. Tamanho máximo: 5MB. 
                    Recomendado: imagem quadrada (1:1) com pelo menos 512x512 pixels.
                </small>
            </div>

            <div class="form-actions">
                <button id="logoUploadBtn" type="button" class="btn btn-primary">
                    <?= $hasLogo ? 'Substituir Logo' : 'Fazer Upload' ?>
                </button>
            </div>
        </form>
        
        <script>
        // Preview e validação do upload de logo - SOLUÇÃO SIMPLIFICADA
        (function() {
            'use strict';
            
            document.addEventListener('DOMContentLoaded', function() {
                console.log('[UPLOAD] Script carregado');
                
                var logoInput = document.getElementById('logo');
                var form = document.getElementById('logoUploadForm');
                var uploadBtn = document.getElementById('logoUploadBtn');
                
                if (!logoInput || !form || !uploadBtn) {
                    console.error('[UPLOAD] Elementos não encontrados:', {
                        logoInput: !!logoInput,
                        form: !!form,
                        uploadBtn: !!uploadBtn
                    });
                    return;
                }
                
                console.log('[UPLOAD] Elementos encontrados:', {
                    logoInput: !!logoInput,
                    form: !!form,
                    uploadBtn: !!uploadBtn,
                    formAction: form.action,
                    uploadBtnType: uploadBtn.type,
                    uploadBtnId: uploadBtn.id,
                    uploadBtnParent: uploadBtn.parentElement ? uploadBtn.parentElement.tagName : 'N/A'
                });
                
                // Preview do logo
                logoInput.addEventListener('change', function(e) {
                    var file = e.target.files[0];
                    if (file) {
                        console.log('[UPLOAD] Arquivo selecionado:', file.name, file.size, 'bytes');
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            var preview = document.getElementById('logo-preview');
                            var previewName = document.getElementById('logo-preview-name');
                            var previewContainer = document.getElementById('logo-preview-container');
                            if (preview) preview.src = e.target.result;
                            if (previewName) previewName.textContent = file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)';
                            if (previewContainer) previewContainer.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    }
                });
                
                // Adicionar listener de clique com CAPTURE phase para garantir que seja executado primeiro
                console.log('[UPLOAD] Adicionando listener de clique ao botão...');
                
                // Função para fazer o upload
                function handleUpload(e) {
                    console.log('[UPLOAD] ===== BOTÃO CLICADO - EVENTO CAPTURADO! =====');
                    console.log('[UPLOAD] Detalhes do evento:', {
                        type: e.type,
                        target: e.target ? e.target.id : 'N/A',
                        currentTarget: e.currentTarget ? e.currentTarget.id : 'N/A',
                        defaultPrevented: e.defaultPrevented,
                        bubbles: e.bubbles,
                        timestamp: new Date().toISOString()
                    });
                    
                    // SEMPRE prevenir comportamento padrão
                    if (e && e.preventDefault) {
                        e.preventDefault();
                    }
                    if (e && e.stopPropagation) {
                        e.stopPropagation();
                    }
                    if (e && e.stopImmediatePropagation) {
                        e.stopImmediatePropagation();
                    }
                    
                    console.log('[UPLOAD] Verificando arquivo...');
                    // Verificar se há arquivo selecionado
                    if (!logoInput || !logoInput.files || logoInput.files.length === 0) {
                        alert('Por favor, selecione um arquivo antes de fazer upload.');
                        console.warn('[UPLOAD] Submit bloqueado: nenhum arquivo selecionado');
                        return false;
                    }
                    
                    var file = logoInput.files[0];
                    console.log('[UPLOAD] Arquivo encontrado, preparando submit:', {
                        name: file.name,
                        size: file.size,
                        type: file.type,
                        formAction: form.action,
                        formMethod: form.method,
                        formEnctype: form.enctype
                    });
                    
                    // Verificar se form existe
                    if (!form) {
                        console.error('[UPLOAD] Form não encontrado!');
                        alert('Erro: formulário não encontrado.');
                        return false;
                    }
                    
                    // Submit DIRETO do form correto - sem interceptação
                    console.log('[UPLOAD] Executando form.submit()...');
                    try {
                        form.submit();
                        console.log('[UPLOAD] form.submit() executado com sucesso');
                    } catch (error) {
                        console.error('[UPLOAD] Erro ao executar form.submit():', error);
                        alert('Erro ao enviar formulário: ' + error.message);
                        return false;
                    }
                    
                    return false;
                }
                
                // Adicionar listener no CAPTURE phase
                uploadBtn.addEventListener('click', handleUpload, true);
                
                // Também adicionar listener no bubbling phase como backup
                uploadBtn.addEventListener('click', function(e) {
                    console.log('[UPLOAD] Botão clicado (bubbling phase - backup)');
                }, false);
                
                // Adicionar onclick diretamente como último recurso
                uploadBtn.onclick = function(e) {
                    console.log('[UPLOAD] Botão clicado (onclick direto)');
                    return handleUpload(e || window.event);
                };
                
                console.log('[UPLOAD] Listeners adicionados ao botão (capture, bubble, onclick)');
                console.log('[UPLOAD] Botão pronto. ID:', uploadBtn.id, 'Type:', uploadBtn.type);
                
                // Validação antes de submit do form de upload (backup)
                form.addEventListener('submit', function(e) {
                    console.log('[UPLOAD] Submit do form logoUploadForm disparado');
                    
                    if (!logoInput.files || logoInput.files.length === 0) {
                        e.preventDefault();
                        alert('Por favor, selecione um arquivo antes de fazer upload.');
                        console.warn('[UPLOAD] Submit bloqueado: nenhum arquivo selecionado');
                        return false;
                    }
                    
                    var file = logoInput.files[0];
                    console.log('[UPLOAD] Form sendo submetido com arquivo:', {
                        name: file.name,
                        size: file.size,
                        type: file.type,
                        formAction: form.action,
                        formMethod: form.method,
                        formEnctype: form.enctype
                    });
                });
            });
        })();
        </script>

        <?php if ($hasLogo): ?>
            <hr style="margin: var(--spacing-lg) 0; border: none; border-top: 1px solid var(--color-gray-200);">
            
            <form method="POST" action="<?= base_url('configuracoes/cfc/logo/remover') ?>" onsubmit="return confirm('Tem certeza que deseja remover o logo? Os ícones PWA também serão removidos.');">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <button type="submit" class="btn btn-danger">
                    Remover Logo
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h2 style="margin-bottom: var(--spacing-md); font-size: 1.25rem;">Informações do CFC</h2>
        
        <form id="salvarForm" method="POST" action="<?= base_url('configuracoes/cfc/salvar') ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label class="form-label" for="nome">Nome do CFC <span class="text-danger">*</span></label>
                <input 
                    type="text" 
                    name="nome" 
                    id="nome"
                    class="form-input" 
                    value="<?= htmlspecialchars($cfc['nome'] ?? '') ?>" 
                    required
                    maxlength="255"
                >
                <small class="form-hint">
                    O nome do CFC é usado no manifest do aplicativo PWA.
                </small>
            </div>

            <?php if (!empty($cfc['cnpj'])): ?>
                <div class="form-group">
                    <label class="form-label" for="cnpj">CNPJ</label>
                    <input 
                        type="text" 
                        name="cnpj"
                        id="cnpj"
                        class="form-input" 
                        value="<?= htmlspecialchars($cfc['cnpj']) ?>" 
                        maxlength="18"
                    >
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Endereço</label>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                    <div>
                        <label class="form-label" for="endereco_logradouro" style="font-size: 0.875rem; font-weight: 500;">Logradouro</label>
                        <input 
                            type="text" 
                            name="endereco_logradouro"
                            id="endereco_logradouro"
                            class="form-input" 
                            value="<?= htmlspecialchars($cfc['endereco_logradouro'] ?? '') ?>" 
                            maxlength="255"
                            placeholder="Rua, Avenida, etc."
                        >
                    </div>
                    <div>
                        <label class="form-label" for="endereco_numero" style="font-size: 0.875rem; font-weight: 500;">Número</label>
                        <input 
                            type="text" 
                            name="endereco_numero"
                            id="endereco_numero"
                            class="form-input" 
                            value="<?= htmlspecialchars($cfc['endereco_numero'] ?? '') ?>" 
                            maxlength="20"
                            placeholder="123"
                        >
                    </div>
                </div>

                <div style="margin-bottom: var(--spacing-md);">
                    <label class="form-label" for="endereco_complemento" style="font-size: 0.875rem; font-weight: 500;">Complemento</label>
                    <input 
                        type="text" 
                        name="endereco_complemento"
                        id="endereco_complemento"
                        class="form-input" 
                        value="<?= htmlspecialchars($cfc['endereco_complemento'] ?? '') ?>" 
                        maxlength="150"
                        placeholder="Apto, Bloco, etc."
                    >
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                    <div>
                        <label class="form-label" for="endereco_bairro" style="font-size: 0.875rem; font-weight: 500;">Bairro</label>
                        <input 
                            type="text" 
                            name="endereco_bairro"
                            id="endereco_bairro"
                            class="form-input" 
                            value="<?= htmlspecialchars($cfc['endereco_bairro'] ?? '') ?>" 
                            maxlength="120"
                            placeholder="Nome do bairro"
                        >
                    </div>
                    <div>
                        <label class="form-label" for="endereco_cep" style="font-size: 0.875rem; font-weight: 500;">CEP</label>
                        <input 
                            type="text" 
                            name="endereco_cep"
                            id="endereco_cep"
                            class="form-input" 
                            value="<?= htmlspecialchars($cfc['endereco_cep'] ?? '') ?>" 
                            maxlength="10"
                            placeholder="00000-000"
                        >
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--spacing-md);">
                    <div>
                        <label class="form-label" for="endereco_cidade" style="font-size: 0.875rem; font-weight: 500;">Cidade</label>
                        <input 
                            type="text" 
                            name="endereco_cidade"
                            id="endereco_cidade"
                            class="form-input" 
                            value="<?= htmlspecialchars($cfc['endereco_cidade'] ?? '') ?>" 
                            maxlength="120"
                            placeholder="Nome da cidade"
                        >
                    </div>
                    <div>
                        <label class="form-label" for="endereco_uf" style="font-size: 0.875rem; font-weight: 500;">UF</label>
                        <input 
                            type="text" 
                            name="endereco_uf"
                            id="endereco_uf"
                            class="form-input" 
                            value="<?= htmlspecialchars($cfc['endereco_uf'] ?? '') ?>" 
                            maxlength="2"
                            placeholder="SP"
                            style="text-transform: uppercase;"
                        >
                    </div>
                </div>

                <small class="form-hint" style="margin-top: var(--spacing-xs); display: block;">
                    Preencha os campos de endereço do CFC.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="telefone">Telefone</label>
                <input 
                    type="text" 
                    name="telefone"
                    id="telefone"
                    class="form-input" 
                    value="<?= htmlspecialchars($cfc['telefone'] ?? '') ?>" 
                    maxlength="20"
                    placeholder="(00) 0000-0000"
                >
                <small class="form-hint">
                    Telefone de contato do CFC.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">E-mail</label>
                <input 
                    type="email" 
                    name="email"
                    id="email"
                    class="form-input" 
                    value="<?= htmlspecialchars($cfc['email'] ?? '') ?>" 
                    maxlength="255"
                    placeholder="contato@cfc.com.br"
                >
                <small class="form-hint">
                    E-mail de contato do CFC.
                </small>
            </div>

            <div class="form-actions">
                <button id="salvarBtn" type="submit" class="btn btn-primary">
                    Salvar Informações
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h2 style="margin-bottom: var(--spacing-md); font-size: 1.25rem;">Configurações PIX</h2>
        <p class="text-muted" style="margin-bottom: var(--spacing-md);">
            Configure os dados do PIX do CFC para pagamentos locais/manuais nas matrículas. Estes campos são opcionais e só serão necessários se você usar PIX como forma de pagamento.
        </p>
        
        <form id="salvarPixForm" method="POST" action="<?= base_url('configuracoes/cfc/salvar') ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label class="form-label" for="pix_banco">Banco/Instituição</label>
                <input 
                    type="text" 
                    name="pix_banco"
                    id="pix_banco"
                    class="form-input" 
                    value="<?= htmlspecialchars($cfc['pix_banco'] ?? '') ?>" 
                    maxlength="255"
                    placeholder="Ex: Banco do Brasil, Nubank, etc."
                >
                <small class="form-hint">
                    Nome do banco ou instituição financeira.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="pix_titular">Titular</label>
                <input 
                    type="text" 
                    name="pix_titular"
                    id="pix_titular"
                    class="form-input" 
                    value="<?= htmlspecialchars($cfc['pix_titular'] ?? '') ?>" 
                    maxlength="255"
                    placeholder="Nome completo do titular da conta"
                >
                <small class="form-hint">
                    Nome completo do titular da conta PIX.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="pix_chave">Chave PIX</label>
                <input 
                    type="text" 
                    name="pix_chave"
                    id="pix_chave"
                    class="form-input" 
                    value="<?= htmlspecialchars($cfc['pix_chave'] ?? '') ?>" 
                    maxlength="255"
                    placeholder="CPF, CNPJ, e-mail, telefone ou chave aleatória"
                >
                <small class="form-hint">
                    Chave PIX (CPF, CNPJ, e-mail, telefone ou chave aleatória).
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="pix_observacao">Observação</label>
                <textarea 
                    name="pix_observacao"
                    id="pix_observacao"
                    class="form-input" 
                    rows="3"
                    placeholder="Informações adicionais sobre o PIX (opcional)"
                ><?= htmlspecialchars($cfc['pix_observacao'] ?? '') ?></textarea>
                <small class="form-hint">
                    Observação opcional que será exibida junto com os dados do PIX.
                </small>
            </div>

            <div class="form-actions">
                <button id="salvarPixBtn" type="submit" class="btn btn-primary">
                    Salvar Configurações PIX
                </button>
            </div>
        </form>
    </div>
</div>
