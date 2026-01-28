<?php
$isEdit = isset($user) && !empty($user['id']);
$pageTitle = $isEdit ? 'Editar Usuário' : 'Criar Acesso';
?>

<div class="page-header">
    <div class="page-header-content">
        <div>
            <h1><?= $pageTitle ?></h1>
            <p class="text-muted"><?= $isEdit ? 'Editar informações do usuário' : 'Criar novo acesso ao sistema' ?></p>
        </div>
        <a href="<?= base_path('usuarios') ?>" class="btn btn-outline">Voltar</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form id="usuarioForm" method="POST" action="<?= base_path($isEdit ? "usuarios/{$user['id']}/atualizar" : 'usuarios/criar') ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <?php if (!$isEdit): ?>
            <!-- Tipo de vínculo (apenas na criação) -->
            <div class="form-group">
                <label class="form-label" for="link_type">Tipo de Acesso</label>
                <select name="link_type" id="link_type" class="form-input" required>
                    <option value="none">Usuário Administrativo</option>
                    <option value="student">Vincular a Aluno Existente</option>
                    <option value="instructor">Vincular a Instrutor Existente</option>
                </select>
            </div>

            <!-- Seleção de Aluno -->
            <div class="form-group" id="student-select" style="display: none;">
                <label class="form-label" for="student_id">Aluno</label>
                <select name="link_id" id="student_id" class="form-input">
                    <option value="">Selecione um aluno...</option>
                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $student): ?>
                        <option value="<?= $student['id'] ?>">
                            <?= htmlspecialchars($student['full_name'] ?: $student['name']) ?> 
                            (<?= htmlspecialchars($student['cpf']) ?>)
                        </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>Nenhum aluno disponível</option>
                    <?php endif; ?>
                </select>
                <small class="form-text">
                    <?php if (empty($students)): ?>
                        <span style="color: #dc3545;">⚠️ Nenhum aluno sem acesso encontrado. Todos os alunos já possuem acesso vinculado ou não possuem email cadastrado.</span>
                    <?php else: ?>
                        Apenas alunos sem acesso vinculado aparecem aqui. (<?= count($students) ?> disponível(is))
                    <?php endif; ?>
                </small>
            </div>

            <!-- Seleção de Instrutor -->
            <div class="form-group" id="instructor-select" style="display: none;">
                <label class="form-label" for="instructor_id">Instrutor</label>
                <select name="link_id" id="instructor_id" class="form-input">
                    <option value="">Selecione um instrutor...</option>
                    <?php if (!empty($instructors)): ?>
                        <?php foreach ($instructors as $instructor): ?>
                        <option value="<?= $instructor['id'] ?>">
                            <?= htmlspecialchars($instructor['name']) ?> 
                            (<?= htmlspecialchars($instructor['cpf']) ?>)
                        </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>Nenhum instrutor disponível</option>
                    <?php endif; ?>
                </select>
                <small class="form-text">
                    <?php if (empty($instructors)): ?>
                        <span style="color: #dc3545;">⚠️ Nenhum instrutor sem acesso encontrado. Todos os instrutores já possuem acesso vinculado ou não possuem email cadastrado.</span>
                    <?php else: ?>
                        Apenas instrutores sem acesso vinculado aparecem aqui. (<?= count($instructors) ?> disponível(is))
                    <?php endif; ?>
                </small>
            </div>

            <!-- Nome (apenas para administrativo) -->
            <div class="form-group" id="nome-input">
                <label class="form-label" for="nome">Nome</label>
                <input type="text" name="nome" id="nome" class="form-input" placeholder="Nome completo">
                <small class="form-text">Obrigatório apenas para usuários administrativos.</small>
            </div>
            <?php endif; ?>

            <!-- E-mail -->
            <div class="form-group">
                <label class="form-label" for="email">E-mail <span class="text-danger">*</span></label>
                <input 
                    type="email" 
                    name="email" 
                    id="email" 
                    class="form-input" 
                    value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                    required
                    placeholder="usuario@exemplo.com"
                >
            </div>

            <!-- Perfil -->
            <div class="form-group">
                <label class="form-label" for="role">Perfil <span class="text-danger">*</span></label>
                <select name="role" id="role" class="form-input" required>
                    <option value="">Selecione...</option>
                    <?php 
                    $currentRole = '';
                    if (!empty($user['roles']) && is_array($user['roles']) && isset($user['roles'][0]['role'])) {
                        $currentRole = $user['roles'][0]['role'];
                    }
                    ?>
                    <option value="ADMIN" <?= $currentRole === 'ADMIN' ? 'selected' : '' ?>>Administrador</option>
                    <option value="SECRETARIA" <?= $currentRole === 'SECRETARIA' ? 'selected' : '' ?>>Secretaria</option>
                    <option value="INSTRUTOR" <?= $currentRole === 'INSTRUTOR' ? 'selected' : '' ?>>Instrutor</option>
                    <option value="ALUNO" <?= $currentRole === 'ALUNO' ? 'selected' : '' ?>>Aluno</option>
                </select>
            </div>

            <?php if ($isEdit): ?>
            <!-- Status (apenas na edição) -->
            <div class="form-group">
                <label class="form-label" for="status">Status</label>
                <select name="status" id="status" class="form-input">
                    <option value="ativo" <?= ($user['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inativo" <?= ($user['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
            <?php else: ?>
            <!-- Enviar e-mail (apenas na criação) -->
            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="send_email" value="1" checked>
                    <span>Enviar e-mail com credenciais de acesso</span>
                </label>
                <small class="form-text">Se marcado, um e-mail será enviado com a senha temporária.</small>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" id="btnSalvar" class="btn btn-primary">
                    <?= $isEdit ? 'Salvar Alterações' : 'Criar Acesso' ?>
                </button>
                <a href="<?= base_path('usuarios') ?>" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php if ($isEdit): ?>
<!-- Bloco: Acesso e Segurança (UI refino: 3 ações principais, status em Mais opções) -->
<div class="card" style="margin-top: var(--spacing-lg); border-left: 2px solid var(--color-border, #dee2e6);">
    <div class="card-header" style="background-color: #f8f9fa;">
        <h3 style="margin: 0; font-size: var(--font-size-lg); color: var(--color-text, #333); display: flex; align-items: center; gap: 8px;">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink: 0; opacity: 0.8;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            Acesso e Segurança
        </h3>
    </div>
    <div class="card-body">

        <!-- Senha Temporária Gerada (exibir apenas uma vez) -->
        <?php if (!empty($tempPasswordGenerated) && (int)$tempPasswordGenerated['user_id'] === (int)$user['id']): ?>
        <div class="alert alert-success" style="margin-bottom: var(--spacing-md);">
            <h4 style="margin: 0 0 var(--spacing-sm) 0; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Senha Temporária Gerada
            </h4>
            <p style="margin: 0 0 var(--spacing-sm) 0;">
                <strong>E-mail:</strong> <?= htmlspecialchars($tempPasswordGenerated['user_email']) ?><br>
                <strong>Senha temporária:</strong> 
                <code style="background: #fff; padding: 4px 8px; border-radius: 4px; font-size: 14px;" id="temp-password">
                    <?= htmlspecialchars($tempPasswordGenerated['temp_password']) ?>
                </code>
                <button type="button" onclick="copyToClipboard('temp-password')" class="btn btn-sm btn-outline" style="margin-left: 8px; display: inline-flex; align-items: center; gap: 4px;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Copiar
                </button>
            </p>
            <small style="color: #666; display: flex; align-items: center; gap: 4px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Esta senha será exibida apenas uma vez. Salve-a em local seguro.
            </small>
        </div>
        <?php endif; ?>

        <!-- Ações de acesso: WhatsApp, Copiar, E-mail -->
        <div id="acesso-ctas-wrapper" data-access-link-url="<?= htmlspecialchars(base_path("usuarios/{$user['id']}/access-link")) ?>" data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
            <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-sm); align-items: center; margin-bottom: var(--spacing-md);">
                <button type="button" class="btn btn-outline btn-sm" id="acesso-cta-wa" style="display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink: 0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    Enviar no WhatsApp
                </button>
                <button type="button" class="btn btn-outline btn-sm" id="acesso-cta-copy" style="display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink: 0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Copiar link
                </button>
                <form method="POST" action="<?= base_path("usuarios/{$user['id']}/enviar-link-email") ?>" style="margin: 0; display: inline-block;" onsubmit="return confirm('Enviar link por e-mail?');">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button type="submit" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink: 0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Enviar por e-mail
                    </button>
                </form>
                <span id="acesso-feedback" style="display: none; font-size: var(--font-size-sm); color: #666; margin-left: 4px;"></span>
            </div>

            <!-- Link de acesso (único bloco, aparece quando há link ativo) -->
            <div id="acesso-link-block" style="display: <?= !empty($activationLinkGenerated) && (int)($activationLinkGenerated['user_id'] ?? 0) === (int)$user['id'] ? 'block' : 'none' ?>; margin-bottom: var(--spacing-md); padding: var(--spacing-sm) var(--spacing-md); background: #e8f4fc; border-radius: 6px; border: 1px solid #b8daef;">
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
                    <svg width="16" height="16" fill="none" stroke="#0d6efd" viewBox="0 0 24 24" style="flex-shrink: 0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    <input type="text" id="acesso-link-url" readonly class="form-input" style="flex: 1; min-width: 200px; font-size: 12px; font-family: monospace; background: #fff; border: 1px solid #cce5ff;" value="<?= !empty($activationLinkGenerated) && (int)($activationLinkGenerated['user_id'] ?? 0) === (int)$user['id'] ? htmlspecialchars($activationLinkGenerated['activation_url'] ?? '') : '' ?>">
                    <button type="button" class="btn btn-primary btn-sm" id="acesso-link-copy-btn" title="Copiar link">Copiar</button>
                </div>
                <small id="acesso-link-expires" style="color: #0d6efd; font-size: 0.75rem; display: block; margin-top: 6px;"><?php if (!empty($activationLinkGenerated) && (int)($activationLinkGenerated['user_id'] ?? 0) === (int)$user['id'] && !empty($activationLinkGenerated['expires_at'])): ?>Expira em: <?= date('d/m/Y, H:i', strtotime($activationLinkGenerated['expires_at'])) ?><?php endif; ?></small>
            </div>
        </div>

        <!-- Mais opções (colapsável): status discreto + Avançado -->
        <div class="form-section-collapsible" style="margin-top: var(--spacing-md);">
            <button type="button" class="form-section-toggle" id="acesso-mais-opcoes-toggle" style="width: 100%; text-align: left; padding: 0.5rem 0.75rem; background: #f5f5f5; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; font-size: 0.875rem; color: #495057;">
                <span>Mais opções</span>
                <svg id="acesso-mais-opcoes-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="transition: transform 0.2s;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div id="acesso-mais-opcoes-body" style="display: none; padding: var(--spacing-md); background: #f8f9fa; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 4px 4px;">
                <!-- Status (linha discreta, sem chips) -->
                <p style="margin: 0 0 var(--spacing-md) 0; font-size: 0.8rem; color: #6c757d; line-height: 1.5;">
                    Senha definida: <?= $hasPassword ? 'sim' : 'não' ?>. Troca obrigatória: <?= !empty($user['must_change_password']) ? 'sim' : 'não' ?>. Link ativo: <?= $hasActiveToken ? 'sim' : 'não' ?>.
                </p>
                <p style="margin: 0 0 var(--spacing-sm) 0; font-size: 0.75rem; color: #868e96; font-weight: 600;">Avançado</p>
                <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-sm); align-items: flex-start;">
                    <form method="POST" action="<?= base_path("usuarios/{$user['id']}/gerar-link-ativacao") ?>" style="margin: 0;" onsubmit="return confirm('Regenerar link? O link atual será invalidado.');">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Regenerar link
                        </button>
                    </form>
                    <div style="flex: 1; min-width: 200px;">
                        <form method="POST" action="<?= base_path("usuarios/{$user['id']}/gerar-senha-temporaria") ?>" style="margin: 0;" onsubmit="return confirm('Gerar senha temporária?');">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                Gerar senha temporária
                            </button>
                        </form>
                        <small style="display: block; margin-top: 4px; font-size: 0.75rem; color: #6c757d;">Use apenas se não houver WhatsApp/e-mail. O usuário precisará trocar no login.</small>
                    </div>
                </div>
            </div>
        </div>
        <script>
        (function(){
            var wrapper = document.getElementById('acesso-ctas-wrapper');
            if (!wrapper) return;
            var urlEndpoint = wrapper.getAttribute('data-access-link-url');
            var csrf = wrapper.getAttribute('data-csrf');
            var btnWa = document.getElementById('acesso-cta-wa');
            var btnCopy = document.getElementById('acesso-cta-copy');
            var feedback = document.getElementById('acesso-feedback');
            var linkBlock = document.getElementById('acesso-link-block');
            var linkUrl = document.getElementById('acesso-link-url');
            var linkExpires = document.getElementById('acesso-link-expires');
            var linkCopyBtn = document.getElementById('acesso-link-copy-btn');
            var linkWaBtn = document.getElementById('acesso-link-wa-btn');
            var lastData = null;

            function showFeedback(msg, isError) {
                if (!feedback) return;
                feedback.textContent = msg;
                feedback.style.display = 'inline';
                feedback.style.color = isError ? '#c00' : '#495057';
                setTimeout(function(){ feedback.style.display = 'none'; }, 4000);
            }

            function showLinkBlock(data) {
                lastData = data;
                if (linkBlock && linkUrl) {
                    linkUrl.value = data.url || '';
                    linkBlock.style.display = 'block';
                }
                if (linkExpires && data.expires_at) {
                    try {
                        var d = new Date(data.expires_at.replace(/-/g, '/'));
                        linkExpires.textContent = 'Expira em: ' + d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                    } catch(e) { linkExpires.textContent = 'Expira em: ' + data.expires_at; }
                }
                if (linkWaBtn && data.phone_wa) {
                    linkWaBtn.href = 'https://wa.me/' + data.phone_wa + '?text=' + encodeURIComponent(data.message || data.url);
                    linkWaBtn.style.display = 'inline-flex';
                } else if (linkWaBtn) { linkWaBtn.style.display = 'none'; }
            }

            function fetchAccessLink() {
                return fetch(urlEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ csrf_token: csrf })
                }).then(function(r){ return r.json(); });
            }

            function copyToClipboard(text) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    return navigator.clipboard.writeText(text);
                }
                var inp = document.createElement('input');
                inp.value = text;
                inp.setAttribute('readonly','');
                inp.style.position = 'absolute';
                inp.style.left = '-9999px';
                document.body.appendChild(inp);
                inp.select();
                document.execCommand('copy');
                document.body.removeChild(inp);
                return Promise.resolve();
            }

            if (btnWa) {
                btnWa.addEventListener('click', function(){
                    btnWa.disabled = true;
                    fetchAccessLink().then(function(data){
                        if (!data || !data.ok) { showFeedback(data && data.message ? data.message : 'Erro ao obter link.', true); btnWa.disabled = false; return; }
                        showLinkBlock(data);
                        if (data.phone_wa && data.message) {
                            window.open('https://wa.me/' + data.phone_wa + '?text=' + encodeURIComponent(data.message), '_blank');
                            showFeedback('Link gerado. WhatsApp aberto.');
                        } else {
                            showFeedback('É necessário ter um telefone válido cadastrado para o aluno.', true);
                        }
                        btnWa.disabled = false;
                    }).catch(function(err){
                        showFeedback('Erro ao obter link. Tente novamente.', true);
                        btnWa.disabled = false;
                    });
                });
            }

            if (btnCopy) {
                btnCopy.addEventListener('click', function(){
                    btnCopy.disabled = true;
                    fetchAccessLink().then(function(data){
                        if (!data || !data.ok) { showFeedback(data && data.message ? data.message : 'Erro ao obter link.', true); btnCopy.disabled = false; return; }
                        showLinkBlock(data);
                        copyToClipboard(data.url).then(function(){ showFeedback('Link copiado.'); });
                        btnCopy.disabled = false;
                    }).catch(function(err){
                        showFeedback('Erro ao obter link. Tente novamente.', true);
                        btnCopy.disabled = false;
                    });
                });
            }

            if (linkCopyBtn && linkUrl) {
                linkCopyBtn.addEventListener('click', function(){
                    copyToClipboard(linkUrl.value).then(function(){ showFeedback('Link copiado.'); });
                });
            }

            var toggleMais = document.getElementById('acesso-mais-opcoes-toggle');
            var bodyMais = document.getElementById('acesso-mais-opcoes-body');
            var iconMais = document.getElementById('acesso-mais-opcoes-icon');
            if (toggleMais && bodyMais) {
                toggleMais.addEventListener('click', function(){
                    var show = bodyMais.style.display !== 'block';
                    bodyMais.style.display = show ? 'block' : 'none';
                    if (iconMais) iconMais.style.transform = show ? 'rotate(180deg)' : 'rotate(0deg)';
                });
            }
        })();
        </script>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar listener para debug do submit (não bloquear, apenas logar)
    const form = document.getElementById('usuarioForm');
    const btnSalvar = document.getElementById('btnSalvar');
    
    console.log('[USUARIOS_FORM] Inicializando...');
    console.log('[USUARIOS_FORM] Form encontrado:', !!form);
    console.log('[USUARIOS_FORM] Botão encontrado:', !!btnSalvar);
    
    if (form) {
        console.log('[USUARIOS_FORM] Action do formulário:', form.action);
        console.log('[USUARIOS_FORM] Method do formulário:', form.method);
        
        // Verificar se o botão está dentro do formulário
        if (btnSalvar) {
            console.log('[USUARIOS_FORM] Botão está dentro do form:', form.contains(btnSalvar));
            console.log('[USUARIOS_FORM] Botão form property:', btnSalvar.form);
            console.log('[USUARIOS_FORM] Botão type:', btnSalvar.type);
            
            // Adicionar listener direto no botão para forçar submit se necessário
            btnSalvar.addEventListener('click', function(e) {
                console.log('[USUARIOS_FORM] ===== BOTÃO CLICADO =====');
                console.log('[USUARIOS_FORM] Event type:', e.type);
                console.log('[USUARIOS_FORM] Default prevented:', e.defaultPrevented);
                
                // Verificar validação
                const email = document.getElementById('email');
                const role = document.getElementById('role');
                
                if (email && !email.value) {
                    console.error('[USUARIOS_FORM] Email vazio!');
                    email.focus();
                    email.reportValidity();
                    e.preventDefault();
                    return false;
                }
                
                if (role && !role.value) {
                    console.error('[USUARIOS_FORM] Role vazio!');
                    role.focus();
                    role.reportValidity();
                    e.preventDefault();
                    return false;
                }
                
                if (!form.checkValidity()) {
                    console.warn('[USUARIOS_FORM] Formulário inválido!');
                    form.reportValidity();
                    e.preventDefault();
                    return false;
                }
                
                console.log('[USUARIOS_FORM] Formulário válido, permitindo submit...');
                
                // Se o submit não foi disparado automaticamente após um tempo, forçar
                setTimeout(function() {
                    if (!form.submitted) {
                        console.log('[USUARIOS_FORM] Submit não foi disparado automaticamente, forçando...');
                        form.submit();
                    }
                }, 50);
            });
        }
        
        // Listener no submit do formulário - este é o evento correto
        // IMPORTANTE: Verificar se o evento é do formulário principal
        form.addEventListener('submit', function(e) {
            // Verificar se o evento realmente veio deste formulário
            // Se o target não for o form principal, é um formulário filho (ação) - ignorar
            if (e.target !== form) {
                console.log('[USUARIOS_FORM] Submit ignorado - formulário filho detectado:', e.target.action);
                return; // Deixar o formulário filho processar normalmente
            }
            
            console.log('[USUARIOS_FORM] ===== FORMULÁRIO SENDO SUBMETIDO =====');
            console.log('[USUARIOS_FORM] Action:', form.action);
            console.log('[USUARIOS_FORM] Method:', form.method);
            console.log('[USUARIOS_FORM] Event type:', e.type);
            console.log('[USUARIOS_FORM] Default prevented:', e.defaultPrevented);
            
            // Verificar campos apenas para log
            const email = document.getElementById('email');
            const role = document.getElementById('role');
            
            if (email) {
                console.log('[USUARIOS_FORM] Email:', email.value, 'Válido:', email.validity.valid);
            }
            if (role) {
                console.log('[USUARIOS_FORM] Role:', role.value, 'Válido:', role.validity.valid);
            }
            
            // NÃO prevenir o submit - deixar a validação HTML5 fazer o trabalho
            console.log('[USUARIOS_FORM] Enviando formulário...');
        }, false); // Bubbling phase
        
        // Também adicionar listener na fase de captura - mas apenas para o formulário principal
        form.addEventListener('submit', function(e) {
            // Verificar se o evento realmente veio deste formulário
            // Se o target não for o form principal, é um formulário filho (ação) - ignorar
            if (e.target !== form) {
                return; // Deixar o formulário filho processar normalmente
            }
            console.log('[USUARIOS_FORM] Submit capturado na fase de captura');
        }, true); // Capture phase
    } else {
        console.error('[USUARIOS_FORM] Formulário principal não encontrado!');
    }
    
    // Adicionar listeners específicos para os formulários de ação
    // Isso garante que eles sejam processados corretamente e não interfiram com o formulário principal
    const formGerarSenhaTemp = document.getElementById('formGerarSenhaTemp');
    const formGerarLinkAtivacao = document.getElementById('formGerarLinkAtivacao');
    const formEnviarLinkEmail = document.getElementById('formEnviarLinkEmail');
    
    if (formGerarSenhaTemp) {
        formGerarSenhaTemp.addEventListener('submit', function(e) {
            console.log('[FORM_ACAO] Gerar Senha Temporária - Submit capturado');
            console.log('[FORM_ACAO] Action:', this.action);
            // Garantir que o evento não borbulhe até o formulário principal
            e.stopPropagation();
        }, true); // Capture phase para interceptar antes do formulário principal
        
        formGerarSenhaTemp.addEventListener('submit', function(e) {
            console.log('[FORM_ACAO] Gerar Senha Temporária - Processando submit');
        }, false); // Bubbling phase
    }
    
    if (formGerarLinkAtivacao) {
        formGerarLinkAtivacao.addEventListener('submit', function(e) {
            console.log('[FORM_ACAO] Gerar Link Ativação - Submit capturado');
            e.stopPropagation();
        }, true);
    }
    
    if (formEnviarLinkEmail) {
        formEnviarLinkEmail.addEventListener('submit', function(e) {
            console.log('[FORM_ACAO] Enviar Link Email - Submit capturado');
            e.stopPropagation();
        }, true);
    }
    
    // Código para gerenciar visibilidade dos campos (apenas na criação)
    const linkType = document.getElementById('link_type');
    const studentSelect = document.getElementById('student-select');
    const instructorSelect = document.getElementById('instructor-select');
    const nomeInput = document.getElementById('nome-input');
    const studentIdSelect = document.getElementById('student_id');
    const instructorIdSelect = document.getElementById('instructor_id');
    
    // Se não estiver em modo de criação, não executar o código de link_type
    if (!linkType) {
        return; // Sair se não estiver em modo de criação
    }
    
    // Função para atualizar visibilidade dos campos
    function updateFieldsVisibility(value) {
        if (!value && linkType) {
            value = linkType.value;
        }
        if (!value) {
            value = 'none';
        }
        
        console.log('[USUARIOS_FORM] Atualizando visibilidade para:', value);
        
        if (studentSelect) {
            const shouldShow = value === 'student';
            studentSelect.style.display = shouldShow ? 'block' : 'none';
            console.log('[USUARIOS_FORM] Student select:', {
                display: studentSelect.style.display,
                shouldShow: shouldShow,
                optionsCount: studentIdSelect ? studentIdSelect.options.length : 0
            });
        } else {
            console.warn('[USUARIOS_FORM] student-select não encontrado!');
        }
        
        if (instructorSelect) {
            const shouldShow = value === 'instructor';
            instructorSelect.style.display = shouldShow ? 'block' : 'none';
            console.log('[USUARIOS_FORM] Instructor select:', {
                display: instructorSelect.style.display,
                shouldShow: shouldShow,
                optionsCount: instructorIdSelect ? instructorIdSelect.options.length : 0
            });
        } else {
            console.warn('[USUARIOS_FORM] instructor-select não encontrado!');
        }
        
        if (nomeInput) {
            nomeInput.style.display = value === 'none' ? 'block' : 'none';
        }
        
        // Limpar seleções quando mudar
        if (studentIdSelect && value !== 'student') {
            studentIdSelect.value = '';
        }
        if (instructorIdSelect && value !== 'instructor') {
            instructorIdSelect.value = '';
        }
    }
    
    // Inicializar estado ao carregar
    console.log('[USUARIOS_FORM] Link type inicial:', linkType.value);
    console.log('[USUARIOS_FORM] Alunos disponíveis:', studentIdSelect ? studentIdSelect.options.length : 0);
    console.log('[USUARIOS_FORM] Instrutores disponíveis:', instructorIdSelect ? instructorIdSelect.options.length : 0);
    
    // Atualizar visibilidade inicial
    updateFieldsVisibility(linkType.value);
    
    // Adicionar listener para mudanças
    linkType.addEventListener('change', function() {
        updateFieldsVisibility(this.value);
    });
});

// Função para copiar ao clipboard
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.textContent.trim();
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Copiado para a área de transferência!');
        }).catch(function(err) {
            console.error('Erro ao copiar:', err);
            fallbackCopyTextToClipboard(text);
        });
    } else {
        fallbackCopyTextToClipboard(text);
    }
}

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            alert('Copiado para a área de transferência!');
        } else {
            alert('Erro ao copiar. Selecione o texto manualmente.');
        }
    } catch (err) {
        alert('Erro ao copiar. Selecione o texto manualmente.');
    }
    document.body.removeChild(textArea);
}
</script>
