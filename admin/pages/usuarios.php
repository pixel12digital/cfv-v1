<?php
// Verificar permissões - apenas admin e secretaria podem gerenciar usuários
if (!canManageUsers()) {
    error_log('[BLOQUEIO] usuarios.php: tipo=' . ($user['tipo'] ?? '') . ', user_id=' . ($user['id'] ?? ''));
    echo '<div class="alert alert-danger">Você não tem permissão.</div>';
    return;
}
$isSecretaria = (isset($user) && ($user['tipo'] ?? '') === 'secretaria');

// Verificar se as variáveis estão definidas
$action = $_GET['action'] ?? 'list';
$db = Database::getInstance();

// Buscar usuários se for listagem
$usuarios = [];
if ($action === 'list') {
    try {
        // Buscar também último acesso se a coluna existir
        $usuarios = $db->fetchAll("
            SELECT 
                id,
                nome,
                email,
                tipo,
                ativo,
                criado_em,
                atualizado_em,
                COALESCE(ultimo_login, NULL) as ultimo_acesso
            FROM usuarios 
            ORDER BY nome
        ");
    } catch (Exception $e) {
        $usuarios = [];
        if (LOG_ENABLED) {
            error_log('Erro ao buscar usuários: ' . $e->getMessage());
        }
    }
}
?>

<!-- CSS para Layout de Cards Compacto e Organizado -->
<style>
/* =====================================================
   LAYOUT DE CARDS DE USUÁRIOS - COMPACTO E ORGANIZADO
   ===================================================== */

/* Container do Grid de Usuários */
.users-grid-container {
    padding: 0;
}

.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
    padding: 0;
}

/* Card Individual de Usuário */
.user-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.user-card:hover {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
}

/* Linha 1: Header com Nome e Badges */
.user-card-header {
    padding: 1rem 1rem 0.75rem 1rem;
    border-bottom: 1px solid #f0f0f0;
}

.user-card-title-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.user-card-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #212529;
    margin: 0;
    line-height: 1.3;
    word-break: break-word;
}

.user-card-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.user-badge-type,
.user-badge-status {
    font-size: 0.75rem;
    padding: 0.35rem 0.65rem;
    border-radius: 0.375rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.user-badge-type i {
    font-size: 0.7rem;
}

/* Linha 2: Informações do Usuário */
.user-card-body {
    padding: 0.75rem 1rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.user-info-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.875rem;
    line-height: 1.4;
}

.user-info-icon {
    color: #6c757d;
    font-size: 0.875rem;
    margin-top: 0.15rem;
    flex-shrink: 0;
    width: 16px;
    text-align: center;
}

.user-info-label {
    color: #6c757d;
    font-weight: 500;
    min-width: 85px;
    flex-shrink: 0;
}

.user-info-value {
    color: #212529;
    word-break: break-word;
    flex: 1;
}

/* Linha 3: Botões de Ação */
.user-card-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid #f0f0f0;
    background-color: #fafafa;
}

.user-card-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    align-items: center;
}

.user-card-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 500;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.user-card-actions .btn i {
    font-size: 0.8rem;
}

.user-card-actions .btn .btn-text {
    display: inline;
}

.user-card-actions .btn-sm {
    padding: 0.35rem 0.65rem;
    font-size: 0.8rem;
}

/* Cores dos Botões */
.btn-edit {
    background-color: #0d6efd;
    color: white;
    border: 1px solid #0d6efd;
}

.btn-edit:hover {
    background-color: #0b5ed7;
    border-color: #0b5ed7;
    color: white;
}

.btn-delete {
    background-color: #dc3545;
    color: white;
    border: 1px solid #dc3545;
}

.btn-delete:hover {
    background-color: #bb2d3b;
    border-color: #bb2d3b;
    color: white;
}

/* Badges de Status */
.badge-success {
    background-color: #198754;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.badge-danger {
    background-color: #dc3545;
    color: white;
}

.badge-primary {
    background-color: #0d6efd;
    color: white;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-info {
    background-color: #0dcaf0;
    color: #212529;
}

/* Header do Card Principal */
.card-header {
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    border-radius: 0.5rem 0.5rem 0 0;
}

.card-header h3 {
    margin: 0;
    padding: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #212529;
}

/* Estilos para o filtro de tipo de usuário */
.card-header .d-flex {
    flex-wrap: wrap;
    gap: 0.75rem;
}

#filtroTipoUsuario {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    background-color: white;
    cursor: pointer;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

#filtroTipoUsuario:hover {
    border-color: #86b7fe;
}

#filtroTipoUsuario:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.card-body {
    padding: 1.25rem;
}

/* =====================================================
   RESPONSIVIDADE - GRID DE CARDS
   ===================================================== */

/* Desktop grande (2+ colunas) */
@media (min-width: 1200px) {
    .users-grid {
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.25rem;
    }
}

/* Desktop médio (2 colunas) */
@media (min-width: 768px) and (max-width: 1199px) {
    .users-grid {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1rem;
    }
}

/* Tablet e Mobile (1 coluna) */
@media (max-width: 767px) {
    .users-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .user-card {
        margin: 0;
    }
    
    .user-card-name {
        font-size: 1rem;
    }
    
    .user-info-item {
        font-size: 0.8125rem;
    }
    
    .user-info-label {
        min-width: 75px;
        font-size: 0.8125rem;
    }
    
    .user-card-actions .btn {
        padding: 0.35rem 0.6rem;
        font-size: 0.75rem;
    }
    
    .user-card-actions .btn .btn-text {
        display: none; /* Ocultar texto em mobile, apenas ícones */
    }
    
    .user-card-actions .btn i {
        margin: 0;
    }
}

/* Mobile pequeno */
@media (max-width: 480px) {
    .card-body {
        padding: 1rem;
    }
    
    /* Filtro em mobile: empilhar verticalmente */
    .card-header .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .card-header .d-flex > div:last-child {
        width: 100%;
    }
    
    #filtroTipoUsuario {
        width: 100%;
        min-width: auto;
    }
    
    .user-card-header {
        padding: 0.875rem 0.875rem 0.625rem 0.875rem;
    }
    
    .user-card-body {
        padding: 0.625rem 0.875rem;
    }
    
    .user-card-footer {
        padding: 0.625rem 0.875rem;
    }
    
    .user-card-actions {
        gap: 0.375rem;
    }
    
    .user-card-actions .btn {
        padding: 0.4rem;
        min-width: 36px;
        justify-content: center;
    }
}
</style>

<!-- Header da Página -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Gerenciar Usuários</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Cadastro e gerenciamento de usuários do sistema</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" id="btnNovoUsuario" title="Novo Usuário">
            <i class="fas fa-plus me-1"></i>
            Novo Usuário
        </button>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- Lista de Usuários -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Usuários Cadastrados</h3>
                <div class="d-flex align-items-center gap-2">
                    <label for="filtroTipoUsuario" class="mb-0 me-2 small text-muted">
                        Filtrar por tipo:
                    </label>
                    <select id="filtroTipoUsuario" class="form-select form-select-sm" style="min-width: 200px;">
                        <option value="todos">Todos</option>
                        <option value="admin">Administradores</option>
                        <option value="secretaria">Atendentes CFC</option>
                        <option value="instrutor">Instrutores</option>
                        <option value="aluno">Alunos</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($usuarios)): ?>
                <!-- Layout de Cards Responsivo - Unificado para Desktop e Mobile -->
                <div class="users-grid-container">
                    <div class="users-grid">
                        <?php foreach ($usuarios as $usuario): ?>
                            <?php 
                            $tipoDisplay = [
                                'admin' => ['text' => 'Administrador', 'class' => 'danger', 'icon' => 'user-cog'],
                                'secretaria' => ['text' => 'Atendente CFC', 'class' => 'primary', 'icon' => 'user-tie'],
                                'instrutor' => ['text' => 'Instrutor', 'class' => 'warning', 'icon' => 'chalkboard-teacher'],
                                'aluno' => ['text' => 'Aluno', 'class' => 'info', 'icon' => 'user']
                            ];
                            $tipoInfo = $tipoDisplay[$usuario['tipo']] ?? ['text' => ucfirst($usuario['tipo']), 'class' => 'secondary', 'icon' => 'user'];
                            // Marcar card com data-tipo para filtro
                            $tipoUsuario = strtolower($usuario['tipo'] ?? '');
                            ?>
                            <div class="user-card" data-tipo="<?php echo htmlspecialchars($tipoUsuario); ?>">
                                <!-- Linha 1: Nome e Badges -->
                                <div class="user-card-header">
                                    <div class="user-card-title-section">
                                        <h4 class="user-card-name"><?php echo htmlspecialchars($usuario['nome']); ?></h4>
                                        <div class="user-card-badges">
                                            <span class="badge badge-<?php echo $tipoInfo['class']; ?> user-badge-type">
                                                <i class="fas fa-<?php echo $tipoInfo['icon']; ?>"></i>
                                                <?php echo $tipoInfo['text']; ?>
                                            </span>
                                            <span class="badge badge-<?php echo $usuario['ativo'] ? 'success' : 'secondary'; ?> user-badge-status">
                                                <?php echo $usuario['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Linha 2: Informações -->
                                <div class="user-card-body">
                                    <div class="user-info-item">
                                        <i class="fas fa-envelope user-info-icon"></i>
                                        <span class="user-info-label">E-mail:</span>
                                        <span class="user-info-value"><?php echo htmlspecialchars($usuario['email']); ?></span>
                                    </div>
                                    <div class="user-info-item">
                                        <i class="fas fa-calendar-plus user-info-icon"></i>
                                        <span class="user-info-label">Criado em:</span>
                                        <span class="user-info-value"><?php echo date('d/m/Y', strtotime($usuario['criado_em'])); ?></span>
                                    </div>
                                    <?php if (isset($usuario['ultimo_acesso']) && $usuario['ultimo_acesso']): ?>
                                    <div class="user-info-item">
                                        <i class="fas fa-clock user-info-icon"></i>
                                        <span class="user-info-label">Último acesso:</span>
                                        <span class="user-info-value"><?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_acesso'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Linha 3: Botões de Ação -->
                                <div class="user-card-footer">
                                    <div class="user-card-actions">
                                        <?php if (!$isSecretaria || ($usuario['tipo'] ?? '') !== 'admin'): ?>
                                        <button class="btn btn-sm btn-edit btn-editar-usuario" 
                                                data-user-id="<?php echo $usuario['id']; ?>"
                                                title="Editar dados do usuário">
                                            <i class="fas fa-edit"></i>
                                            <span class="btn-text">Editar</span>
                                        </button>
                                        <button class="btn btn-sm btn-warning btn-redefinir-senha" 
                                                data-user-id="<?php echo $usuario['id']; ?>"
                                                data-user-name="<?php echo htmlspecialchars($usuario['nome']); ?>"
                                                data-user-email="<?php echo htmlspecialchars($usuario['email']); ?>"
                                                data-user-type="<?php echo $usuario['tipo']; ?>"
                                                title="Redefinir senha do usuário">
                                            <i class="fas fa-key"></i>
                                            <span class="btn-text">Senha</span>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (!$isSecretaria): ?>
                                        <button class="btn btn-sm btn-delete btn-excluir-usuario" 
                                                data-user-id="<?php echo $usuario['id']; ?>"
                                                title="Excluir usuário">
                                            <i class="fas fa-trash"></i>
                                            <span class="btn-text">Excluir</span>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center p-5">
                    <div class="text-light">
                        <i class="fas fa-users fa-3x mb-3"></i>
                        <p>Nenhum usuário cadastrado</p>
                        <button class="btn btn-primary" onclick="showCreateUserModal()">
                            <i class="fas fa-plus"></i>
                            Cadastrar Primeiro Usuário
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Modal de Criação/Edição de Usuário -->
<div id="userModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="userModalTitle">Novo Usuário</h3>
            <button class="modal-close" onclick="closeUserModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="userForm">
                <input type="hidden" id="userId" name="id">
                
                <div class="form-group">
                    <label for="userName" class="form-label">Nome Completo</label>
                    <input type="text" id="userName" name="nome" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="userEmail" class="form-label">E-mail</label>
                    <input type="email" id="userEmail" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="userType" class="form-label">Tipo de Usuário</label>
                    <select id="userType" name="tipo" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php if (!$isSecretaria): ?>
                        <option value="admin">Administrador</option>
                        <?php endif; ?>
                        <option value="secretaria">Atendente CFC</option>
                        <option value="instrutor">Instrutor</option>
                        <option value="aluno">Aluno</option>
                    </select>
                    <div class="form-text">
                        <strong>Administrador:</strong> Acesso total incluindo configurações<br>
                        <strong>Atendente CFC:</strong> Pode fazer tudo menos configurações<br>
                        <strong>Instrutor:</strong> Pode alterar/cancelar aulas mas não adicionar<br>
                        <strong>Aluno:</strong> Pode visualizar apenas suas informações
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Sistema de Credenciais Automáticas</strong><br>
                        • Senha temporária será gerada automaticamente<br>
                        • Credenciais serão exibidas na tela após criação<br>
                        • Usuário receberá credenciais por email<br>
                        • Senha deve ser alterada no primeiro acesso
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="userActive" name="ativo" checked>
                        Usuário Ativo
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="saveUser()">Salvar</button>
        </div>
    </div>
</div>

<!-- Modal de Redefinição de Senha -->
<!-- Modal de Redefinição de Senha - Versão Completa com Modos Auto/Manual -->
<div id="resetPasswordModal" class="modal-overlay">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Redefinir Senha do Usuário</h3>
            <button class="modal-close" onclick="closeResetPasswordModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
            <!-- Informações do Usuário -->
            <div class="user-info mb-3">
                <h5 class="mb-2">Informações do Usuário:</h5>
                <p class="mb-1"><strong>Nome:</strong> <span id="resetUserName"></span></p>
                <p class="mb-1"><strong>E-mail:</strong> <span id="resetUserEmail"></span></p>
                <p class="mb-0"><strong>Tipo:</strong> <span id="resetUserType"></span></p>
            </div>
            
            <hr class="my-3">
            
            <!-- Seleção de Modo -->
            <div class="form-group mb-3">
                <label class="form-label fw-bold">Modo de Redefinição:</label>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="resetMode" id="modeAuto" value="auto" checked onchange="toggleResetMode()">
                    <label class="form-check-label" for="modeAuto">
                        <strong>Gerar senha temporária automática</strong> <span class="badge bg-success">Recomendado</span>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="resetMode" id="modeManual" value="manual" onchange="toggleResetMode()">
                    <label class="form-check-label" for="modeManual">
                        <strong>Definir nova senha manualmente</strong>
                    </label>
                </div>
            </div>
            
            <!-- Explicação Modo Automático -->
            <div id="modeAutoInfo" class="alert alert-info mb-3">
                <i class="fas fa-info-circle"></i>
                <strong>O que acontecerá:</strong>
                <ul class="mb-0 mt-2">
                    <li>Uma senha temporária será gerada automaticamente (8-10 caracteres)</li>
                    <li>A senha será exibida apenas uma vez após a redefinição</li>
                    <li>O usuário deverá trocar a senha no primeiro acesso</li>
                    <li>A senha anterior será invalidada imediatamente</li>
                    <li>As credenciais serão enviadas por e-mail (se configurado)</li>
                </ul>
            </div>
            
            <!-- Campos Modo Manual -->
            <div id="modeManualFields" class="mb-3" style="display: none;">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Atenção:</strong> A senha definida manualmente não será exibida novamente após salvar.
                </div>
                
                <div class="form-group mb-3">
                    <label for="novaSenha" class="form-label">Nova Senha <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="novaSenha" 
                               placeholder="Mínimo 8 caracteres" 
                               minlength="8" 
                               oninput="validateManualPassword()">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('novaSenha', 'toggleNovaSenha')">
                            <i class="fas fa-eye" id="toggleNovaSenha"></i>
                        </button>
                    </div>
                    <small class="text-muted">A senha deve ter no mínimo 8 caracteres</small>
                    <div id="novaSenhaError" class="text-danger mt-1" style="display: none;"></div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="novaSenhaConfirmacao" class="form-label">Confirmar Nova Senha <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="novaSenhaConfirmacao" 
                               placeholder="Digite a senha novamente" 
                               minlength="8" 
                               oninput="validateManualPassword()">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('novaSenhaConfirmacao', 'toggleNovaSenhaConfirmacao')">
                            <i class="fas fa-eye" id="toggleNovaSenhaConfirmacao"></i>
                        </button>
                    </div>
                    <div id="novaSenhaConfirmacaoError" class="text-danger mt-1" style="display: none;"></div>
                </div>
            </div>
            
            <!-- Confirmação -->
            <div class="form-group">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmResetPassword" required onchange="toggleConfirmButton()">
                    <label class="form-check-label" for="confirmResetPassword">
                        Confirmo que desejo redefinir a senha deste usuário
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeResetPasswordModal()">Cancelar</button>
            <button type="button" class="btn btn-warning" id="confirmResetBtn" onclick="confirmResetPassword()" disabled>
                <i class="fas fa-key"></i>
                <span id="confirmResetBtnText">Redefinir Senha</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal de Credenciais -->
<div id="credentialsModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">🔐 Credenciais Criadas</h3>
            <button class="modal-close" onclick="closeCredentialsModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>Senha redefinida com sucesso!</strong>
            </div>
            
            <div class="credentials-container">
                <div class="credential-item">
                    <label class="credential-label" id="credentialLabel">
                        <i class="fas fa-envelope" id="credentialIcon"></i>
                        <span id="credentialLabelText">Email:</span>
                    </label>
                    <div class="credential-value">
                        <input type="text" id="credentialEmail" readonly value="" class="credential-input">
                        <button class="btn btn-copy" onclick="copyToClipboard('credentialEmail')" title="Copiar" id="credentialCopyBtn">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                
                <div class="credential-item">
                    <label class="credential-label">
                        <i class="fas fa-key"></i>
                        Nova Senha Temporária:
                    </label>
                    <div class="credential-value">
                        <input type="text" id="credentialPassword" readonly value="" class="credential-input">
                        <button class="btn btn-copy" onclick="copyToClipboard('credentialPassword')" title="Copiar senha">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button class="btn btn-toggle" onclick="togglePasswordVisibility()" title="Mostrar/Ocultar senha">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>IMPORTANTE:</strong><br>
                • Esta é uma nova senha temporária<br>
                • A senha anterior foi invalidada<br>
                • O usuário deve alterar no próximo acesso<br>
                • Guarde estas informações em local seguro
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeCredentialsModal()">Fechar</button>
            <button type="button" class="btn btn-success" onclick="copyAllCredentials()">
                <i class="fas fa-copy"></i>
                Copiar Tudo
            </button>
        </div>
    </div>
</div>

<!-- Scripts específicos da página -->
<script>
// Verificar se as funções estão sendo definidas
console.log('Iniciando carregamento da pagina de usuarios...');

// Verificar se o modal existe
(function() {
    const modal = document.getElementById('userModal');
    if (modal) {
        console.log('Modal de usuário encontrado e pronto para uso');
    } else {
        console.warn('Modal de usuário não encontrado');
    }
})();

// Variáveis globais
let currentUser = null;
let isEditMode = false;

// Mostrar modal de criação
function showCreateUserModal() {
    console.log('Funcao showCreateUserModal chamada!');
    isEditMode = false;
    currentUser = null;
    
    document.getElementById('userModalTitle').textContent = 'Novo Usuario';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    
    // Senha não é mais necessária - sistema gera automaticamente
    // document.getElementById('userPassword').required = true;
    // document.getElementById('userConfirmPassword').required = true;
    
    // Mostrar modal
    const modal = document.getElementById('userModal');
    modal.classList.add('show');
    
    console.log('Modal aberto com sucesso!');
}

// Garantir que a função esteja disponível globalmente
window.showCreateUserModal = showCreateUserModal;

// Mostrar modal de edição
function editUser(userId) {
    console.log('[USUARIOS] editUser chamado para ID:', userId);
    
    // DEBUG: Verificar estado da lista antes de abrir modal
    const listaContainer = document.querySelector('.users-grid');
    console.log('[USUARIOS] Container lista ANTES de abrir modal:', listaContainer);
    console.log('[USUARIOS] Quantidade de cards ANTES:', listaContainer ? listaContainer.children.length : 'container não encontrado');
    
    isEditMode = true;
    
    // CORREÇÃO DO BUG: Não substituir o conteúdo do .card-body
    // O modal é um overlay, então não precisa esconder a lista
    // A busca é rápida, então não precisa de loading destrutivo
    
    console.log('[USUARIOS] Buscando dados do usuario na API...');
    
    // Buscar dados reais da API
    // CORREÇÃO: Calcular caminho absoluto
    const currentPath = window.location.pathname;
    const apiUrl = currentPath.includes('/admin/') 
        ? currentPath.substring(0, currentPath.indexOf('/admin/') + '/admin/'.length) + 'api/usuarios.php'
        : '../api/usuarios.php';
    fetch(apiUrl + '?id=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentUser = data.data;
                
                // Preencher formulário
                document.getElementById('userModalTitle').textContent = 'Editar Usuario';
                document.getElementById('userId').value = currentUser.id;
                document.getElementById('userName').value = currentUser.nome;
                document.getElementById('userEmail').value = currentUser.email;
                document.getElementById('userType').value = currentUser.tipo;
                document.getElementById('userActive').checked = currentUser.ativo;
                
                // Mostrar modal
                const modal = document.getElementById('userModal');
                modal.classList.add('show');
                
                console.log('[USUARIOS] Modal aberto com sucesso');
                
                // DEBUG: Verificar estado da lista após abrir modal
                const listaAposAbrir = document.querySelector('.users-grid');
                console.log('[USUARIOS] Container lista APÓS abrir modal:', listaAposAbrir);
                console.log('[USUARIOS] Quantidade de cards APÓS abrir:', listaAposAbrir ? listaAposAbrir.children.length : 'container não encontrado');
            } else {
                showNotification(data.error || 'Erro ao carregar usuario', 'error');
            }
        })
        .catch(error => {
            console.error('[USUARIOS] Erro ao carregar usuario:', error);
            showNotification('Erro ao carregar usuario. Tente novamente.', 'error');
        });
}

// Garantir que a função esteja disponível globalmente
window.editUser = editUser;

// Fechar modal
function closeUserModal() {
    console.log('[USUARIOS] closeUserModal chamado');
    
    // DEBUG: Verificar estado da lista antes de fechar modal
    const listaAntes = document.querySelector('.users-grid');
    console.log('[USUARIOS] Container lista ANTES de fechar modal:', listaAntes);
    console.log('[USUARIOS] Quantidade de cards ANTES de fechar:', listaAntes ? listaAntes.children.length : 'container não encontrado');
    console.log('[USUARIOS] Display da lista ANTES:', listaAntes ? getComputedStyle(listaAntes).display : 'n/a');
    
    const modal = document.getElementById('userModal');
    if (modal) {
        modal.classList.remove('show');
    }
    
    const form = document.getElementById('userForm');
    if (form) {
        form.reset();
    }
    
    currentUser = null;
    isEditMode = false;
    
    console.log('[USUARIOS] Modal fechado');
    
    // DEBUG: Verificar estado da lista após fechar modal
    const listaApos = document.querySelector('.users-grid');
    console.log('[USUARIOS] Container lista APÓS fechar modal:', listaApos);
    console.log('[USUARIOS] Quantidade de cards APÓS fechar:', listaApos ? listaApos.children.length : 'container não encontrado');
    console.log('[USUARIOS] Display da lista APÓS:', listaApos ? getComputedStyle(listaApos).display : 'n/a');
    
    // GARANTIA: Se a lista não existir, recarregar a página
    if (!listaApos || listaApos.children.length === 0) {
        console.error('[USUARIOS] ⚠️ LISTA PERDIDA! Recarregando página...');
        window.location.reload();
        return;
    }
    
    console.log('[USUARIOS] ✅ Lista preservada após fechar modal');
}

// Garantir que a função esteja disponível globalmente
window.closeUserModal = closeUserModal;

// Salvar usuário
function saveUser() {
    console.log('Funcao saveUser chamada!');
    const form = document.getElementById('userForm');
    const formData = new FormData(form);
    
    // Validações básicas
    if (!formData.get('nome').trim()) {
        showNotification('Nome e obrigatorio', 'error');
        return;
    }
    
    if (!formData.get('email').trim()) {
        showNotification('E-mail e obrigatorio', 'error');
        return;
    }
    
    if (!formData.get('tipo')) {
        showNotification('Tipo de usuario e obrigatorio', 'error');
        return;
    }
    
    // Validação de senha removida - sistema gera automaticamente
    // if (!isEditMode) {
    //     if (!formData.get('senha')) {
    //         showNotification('Senha e obrigatoria', 'error');
    //         return;
    //     }
    //     
    //     if (formData.get('senha').length < 6) {
    //         showNotification('Senha deve ter pelo menos 6 caracteres', 'error');
    //         return;
    //     }
    //     
    //     if (formData.get('senha') !== formData.get('confirmar_senha')) {
    //         showNotification('Senhas nao conferem', 'error');
    //         return;
    //     }
    // }
    
    console.log('Validacoes passaram, preparando dados...');
    
    // Preparar dados para envio (senha removida - sistema gera automaticamente)
    const userData = {
        nome: formData.get('nome').trim(),
        email: formData.get('email').trim(),
        tipo: formData.get('tipo'),
        ativo: formData.get('ativo') ? true : false
    };
    
    // Senha não é mais necessária - sistema gera automaticamente
    // if (!isEditMode || formData.get('senha')) {
    //     userData.senha = formData.get('senha');
    // }
    
    if (isEditMode) {
        userData.id = formData.get('id');
    }
    
    // CORREÇÃO: Não substituir conteúdo da lista durante salvamento
    // Usar notificação em vez de loading destrutivo
    showNotification('Salvando usuário...', 'info');
    
    // Fazer requisição para a API
    // CORREÇÃO: Calcular caminho absoluto
    const currentPath = window.location.pathname;
    const url = currentPath.includes('/admin/') 
        ? currentPath.substring(0, currentPath.indexOf('/admin/') + '/admin/'.length) + 'api/usuarios.php'
        : '../api/usuarios.php';
    const method = isEditMode ? 'PUT' : 'POST';
    
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include', // Incluir cookies de sessão
        body: JSON.stringify(userData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Usuário salvo com sucesso!', 'success');
            closeUserModal();
            
            // Se foram criadas credenciais, exibir na tela
            if (data.credentials) {
                console.log('🔐 Credenciais recebidas:', data.credentials);
                const credentials = data.credentials;
                
                // Exibir credenciais em modal de alerta primeiro
                const credentialsText = `
🔐 CREDENCIAIS CRIADAS COM SUCESSO!

📧 Email: ${credentials.email}
🔑 Senha Temporária: ${credentials.senha_temporaria}

⚠️ IMPORTANTE:
• Esta é uma senha temporária
• O usuário deve alterar no primeiro acesso
• Guarde estas informações em local seguro

Clique em "OK" para abrir a página completa de credenciais.
                `;
                
                if (confirm(credentialsText)) {
                    const credentialsUrl = `credenciais_criadas.php?credentials=${btoa(JSON.stringify(credentials))}`;
                    window.open(credentialsUrl, '_blank');
                }
            }
            
            // Recarregar página para mostrar dados atualizados
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(data.error || 'Erro ao salvar usuário', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao salvar usuário. Tente novamente.', 'error');
    })
    // Não precisa de finally - reload já está no then() em caso de sucesso
}

// Garantir que a função esteja disponível globalmente
window.saveUser = saveUser;

// Excluir usuário
function deleteUser(userId) {
    console.log('Funcao deleteUser chamada para usuario ID: ' + userId);
    
    if (!userId || userId === '' || userId === 0) {
        console.error('ID de usuario invalido:', userId);
        showNotification('ID de usuário inválido', 'error');
        return;
    }
    
    if (confirm('⚠️ ATENÇÃO!\n\nTem certeza que deseja excluir este usuário?\n\nEsta ação NÃO pode ser desfeita!')) {
        console.log('Confirmacao recebida, excluindo usuario ID:', userId);
        
        // CORREÇÃO: Não substituir conteúdo da lista durante exclusão
        // Usar notificação em vez de loading destrutivo
        showNotification('Excluindo usuário...', 'info');
        
        // URL da API
        // CORREÇÃO: Calcular caminho absoluto
        const currentPath = window.location.pathname;
        const apiUrl = (currentPath.includes('/admin/') 
            ? currentPath.substring(0, currentPath.indexOf('/admin/') + '/admin/'.length) + 'api/usuarios.php'
            : '../api/usuarios.php') + '?id=' + encodeURIComponent(userId);
        console.log('Fazendo requisicao DELETE para:', apiUrl);
        
        // Fazer requisição para a API
        fetch(apiUrl, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('Resposta recebida. Status:', response.status);
            
            // Verificar se a resposta é válida
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status} - ${response.statusText}`);
            }
            
            // Verificar se o content-type é JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Resposta não é JSON válido');
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Dados recebidos da API:', data);
            
            if (data.success) {
                console.log('Usuario excluido com sucesso');
                showNotification(data.message || 'Usuário excluído com sucesso!', 'success');
                
                // Recarregar página após sucesso
                setTimeout(function() {
                    console.log('Recarregando pagina...');
                    window.location.reload();
                }, 1500);
            } else {
                console.error('Erro retornado pela API:', data);
                let errorMessage = data.error || 'Erro desconhecido ao excluir usuário';
                
                // Melhorar mensagens de erro baseadas no código
                switch (data.code) {
                    case 'NOT_LOGGED_IN':
                        errorMessage = 'Sessão expirada. Faça login novamente.';
                        setTimeout(() => window.location.href = 'index.php', 2000);
                        break;
                    case 'NOT_ADMIN':
                    case 'NOT_AUTHORIZED':
                        errorMessage = 'Você não tem permissão.';
                        break;
                    case 'USER_NOT_FOUND':
                        errorMessage = 'Usuário não encontrado.';
                        break;
                    case 'SELF_DELETE':
                        errorMessage = 'Você não pode excluir o próprio usuário.';
                        break;
                    case 'HAS_CFCS':
                        errorMessage = 'Este usuário possui CFCs vinculados. Remova os vínculos antes de excluir.';
                        break;
                }
                
                showNotification(errorMessage, 'error');
            }
        })
        .catch(error => {
            console.error('Erro na requisicao:', error);
            
            let errorMessage = 'Erro de conexão ao excluir usuário.';
            
            if (error.message.includes('HTTP Error: 401')) {
                errorMessage = 'Sessão expirada. Faça login novamente.';
                setTimeout(() => window.location.href = 'index.php', 2000);
            } else if (error.message.includes('HTTP Error: 403')) {
                errorMessage = 'Você não tem permissão.';
            } else if (error.message.includes('HTTP Error: 404')) {
                errorMessage = 'Usuário não encontrado.';
            } else if (error.message.includes('HTTP Error: 500')) {
                errorMessage = 'Erro interno do servidor. Tente novamente.';
            } else if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                errorMessage = 'Erro de conexão. Verifique sua internet e tente novamente.';
            }
            
            showNotification(errorMessage, 'error');
        })
        .finally(() => {
            console.log('Finalizando operacao de exclusao');
            
            // Restaurar conteúdo da página se ainda estiver em loading
            if (loadingEl && loadingEl.innerHTML.includes('Excluindo usuario')) {
                setTimeout(() => {
                    console.log('Recarregando pagina no finally...');
                    window.location.reload();
                }, 2000);
            }
        });
    } else {
        console.log('Exclusao cancelada pelo usuario');
    }
}

// Garantir que a função esteja disponível globalmente
window.deleteUser = deleteUser;

// Exportar usuários
function exportUsers() {
    console.log('[USUARIOS] exportUsers chamado');
    
    // CORREÇÃO: Não substituir conteúdo da lista durante exportação
    // Usar notificação em vez de loading destrutivo
    showNotification('Preparando exportação...', 'info');
    
    console.log('[USUARIOS] Buscando dados dos usuarios na API...');
    
    // Buscar dados reais da API
    // CORREÇÃO: Calcular caminho absoluto
    const currentPath = window.location.pathname;
    const apiUrl = currentPath.includes('/admin/') 
        ? currentPath.substring(0, currentPath.indexOf('/admin/') + '/admin/'.length) + 'api/usuarios.php'
        : '../api/usuarios.php';
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Criar CSV
                let csv = 'Nome,E-mail,Tipo,Status,Criado em\n';
                data.data.forEach(usuario => {
                    csv += '"' + usuario.nome + '","' + usuario.email + '","' + usuario.tipo + '","' + (usuario.ativo ? 'Ativo' : 'Inativo') + '","' + usuario.criado_em + '"\n';
                });
                
                // Download do arquivo
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'usuarios.csv';
                link.click();
                
                showNotification('Exportação concluída!', 'success');
            } else {
                showNotification(data.error || 'Erro ao exportar usuários', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showNotification('Erro ao exportar usuários. Tente novamente.', 'error');
        })
        // Não precisa de finally - exportação não destrói a lista
}

// Garantir que a função esteja disponível globalmente
window.exportUsers = exportUsers;

// Função para mostrar notificações
function showNotification(message, type = 'info') {
    console.log('Mostrando notificacao: ' + message + ' (tipo: ' + type + ')');
    
    // Criar elemento de notificação
    const notification = document.createElement('div');
    notification.className = 'alert alert-' + type + ' alert-dismissible fade show';
    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
    
    notification.innerHTML = message + '<button type="button" class="btn-close" onclick="this.parentElement.remove()">x</button>';
    
    // Adicionar ao body
    document.body.appendChild(notification);
    
    // Remover automaticamente após 5 segundos
    setTimeout(function() {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
    
    console.log('Notificacao criada e exibida!');
}

// Garantir que a função esteja disponível globalmente
window.showNotification = showNotification;

// Variáveis globais para redefinição de senha
let resetPasswordUser = null;

// Mostrar modal de redefinição de senha
function showResetPasswordModal(userId, userName, userEmail, userType) {
    console.log('Função showResetPasswordModal chamada para usuário ID: ' + userId);
    
    resetPasswordUser = {
        id: userId,
        name: userName,
        email: userEmail,
        type: userType
    };
    
    // Mapear tipo para exibição
    const tipoDisplay = {
        'admin': 'Administrador',
        'secretaria': 'Atendente CFC',
        'instrutor': 'Instrutor',
        'aluno': 'Aluno'
    };
    
    // Preencher dados do usuário no modal
    document.getElementById('resetUserName').textContent = userName;
    document.getElementById('resetUserEmail').textContent = userEmail;
    document.getElementById('resetUserType').textContent = tipoDisplay[userType] || userType;
    
    // Resetar formulário
    document.getElementById('confirmResetPassword').checked = false;
    document.getElementById('confirmResetBtn').disabled = true;
    document.getElementById('modeAuto').checked = true;
    document.getElementById('modeManual').checked = false;
    
    // Limpar campos modo manual
    document.getElementById('novaSenha').value = '';
    document.getElementById('novaSenhaConfirmacao').value = '';
    document.getElementById('novaSenhaError').style.display = 'none';
    document.getElementById('novaSenhaConfirmacaoError').style.display = 'none';
    
    // Mostrar/ocultar campos conforme modo
    toggleResetMode();
    
    // Mostrar modal
    const modal = document.getElementById('resetPasswordModal');
    modal.classList.add('show');
    
    console.log('Modal de redefinição de senha aberto com sucesso!');
}

// Garantir que a função esteja disponível globalmente
window.showResetPasswordModal = showResetPasswordModal;

// Fechar modal de redefinição de senha
function closeResetPasswordModal() {
    console.log('Fechando modal de redefinição de senha...');
    const modal = document.getElementById('resetPasswordModal');
    modal.classList.remove('show');
    
    // Resetar dados
    resetPasswordUser = null;
    document.getElementById('confirmResetPassword').checked = false;
    document.getElementById('confirmResetBtn').disabled = true;
    
    // Limpar campos modo manual
    document.getElementById('novaSenha').value = '';
    document.getElementById('novaSenhaConfirmacao').value = '';
    document.getElementById('novaSenhaError').style.display = 'none';
    document.getElementById('novaSenhaConfirmacaoError').style.display = 'none';
    
    console.log('Modal de redefinição de senha fechado com sucesso!');
}

// Alternar entre modos de redefinição
function toggleResetMode() {
    const modeAuto = document.getElementById('modeAuto').checked;
    const modeAutoInfo = document.getElementById('modeAutoInfo');
    const modeManualFields = document.getElementById('modeManualFields');
    
    if (modeAuto) {
        modeAutoInfo.style.display = 'block';
        modeManualFields.style.display = 'none';
    } else {
        modeAutoInfo.style.display = 'none';
        modeManualFields.style.display = 'block';
    }
    
    // Validar e atualizar botão
    validateManualPassword();
    toggleConfirmButton();
}

// Validar senha manual
function validateManualPassword() {
    const modeManual = document.getElementById('modeManual').checked;
    if (!modeManual) {
        return true; // Modo automático não precisa validação de senha
    }
    
    const novaSenha = document.getElementById('novaSenha').value;
    const novaSenhaConfirmacao = document.getElementById('novaSenhaConfirmacao').value;
    const novaSenhaError = document.getElementById('novaSenhaError');
    const novaSenhaConfirmacaoError = document.getElementById('novaSenhaConfirmacaoError');
    
    let isValid = true;
    
    // Validar tamanho mínimo
    if (novaSenha.length > 0 && novaSenha.length < 8) {
        novaSenhaError.textContent = 'A senha deve ter no mínimo 8 caracteres';
        novaSenhaError.style.display = 'block';
        isValid = false;
    } else {
        novaSenhaError.style.display = 'none';
    }
    
    // Validar confirmação
    if (novaSenhaConfirmacao.length > 0) {
        if (novaSenha !== novaSenhaConfirmacao) {
            novaSenhaConfirmacaoError.textContent = 'As senhas não coincidem';
            novaSenhaConfirmacaoError.style.display = 'block';
            isValid = false;
        } else {
            novaSenhaConfirmacaoError.style.display = 'none';
        }
    } else {
        novaSenhaConfirmacaoError.style.display = 'none';
    }
    
    // Se modo manual, verificar se ambos os campos estão preenchidos
    if (modeManual && (novaSenha.length === 0 || novaSenhaConfirmacao.length === 0)) {
        isValid = false;
    }
    
    return isValid;
}

// Alternar visibilidade de senha
function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Habilitar/desabilitar botão de confirmação
function toggleConfirmButton() {
    const confirmCheckbox = document.getElementById('confirmResetPassword').checked;
    const confirmBtn = document.getElementById('confirmResetBtn');
    
    if (!confirmCheckbox) {
        confirmBtn.disabled = true;
        return;
    }
    
    // Se modo manual, validar senha também
    const modeManual = document.getElementById('modeManual').checked;
    if (modeManual) {
        const isValid = validateManualPassword();
        confirmBtn.disabled = !isValid;
    } else {
        confirmBtn.disabled = false;
    }
}

// Garantir que a função esteja disponível globalmente
window.closeResetPasswordModal = closeResetPasswordModal;

// Confirmar redefinição de senha
function confirmResetPassword() {
    console.log('Função confirmResetPassword chamada');
    
    if (!resetPasswordUser) {
        showNotification('Erro: Dados do usuário não encontrados', 'error');
        return;
    }
    
    if (!document.getElementById('confirmResetPassword').checked) {
        showNotification('Você deve confirmar a redefinição de senha', 'error');
        return;
    }
    
    // Determinar modo
    const modeAuto = document.getElementById('modeAuto').checked;
    const mode = modeAuto ? 'auto' : 'manual';
    
    // Validar senha manual se necessário
    if (mode === 'manual') {
        const isValid = validateManualPassword();
        if (!isValid) {
            showNotification('Por favor, corrija os erros nos campos de senha', 'error');
            return;
        }
    }
    
    console.log('Confirmando redefinição de senha para usuário ID: ' + resetPasswordUser.id + ' (Modo: ' + mode + ')');
    
    // Desabilitar botão para evitar cliques múltiplos
    const confirmBtn = document.getElementById('confirmResetBtn');
    const confirmBtnText = document.getElementById('confirmResetBtnText');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtnText.textContent = 'Redefinindo...';
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redefinindo...';
    }
    
    // Preparar dados da requisição
    const requestData = {
        action: 'reset_password',
        user_id: resetPasswordUser.id,
        mode: mode
    };
    
    // Adicionar senha se modo manual
    if (mode === 'manual') {
        requestData.nova_senha = document.getElementById('novaSenha').value;
        requestData.nova_senha_confirmacao = document.getElementById('novaSenhaConfirmacao').value;
    }
    
    // Fazer requisição para a API
    console.log('[USUARIOS] Enviando requisição de redefinição de senha:', requestData);
    
    // CORREÇÃO: Calcular caminho absoluto baseado na estrutura do projeto
    // A página está sempre em /admin/index.php?page=usuarios, então a API está em /admin/api/usuarios.php
    const currentPath = window.location.pathname;
    let apiUrl;
    
    // Extrair o diretório base (até /admin/)
    if (currentPath.includes('/admin/')) {
        // Se estamos em /admin/, a API está em /admin/api/
        // Exemplo: /cfc-bom-conselho/admin/index.php -> /cfc-bom-conselho/admin/api/usuarios.php
        const basePath = currentPath.substring(0, currentPath.indexOf('/admin/') + '/admin/'.length);
        apiUrl = basePath + 'api/usuarios.php';
    } else {
        // Fallback: caminho relativo
        apiUrl = '../api/usuarios.php';
    }
    
    console.log('[USUARIOS] URL da API calculada:', apiUrl);
    console.log('[USUARIOS] Caminho atual:', currentPath);
    
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include', // Incluir cookies de sessão
        body: JSON.stringify(requestData)
    })
    .then(response => {
        console.log('[USUARIOS] Resposta recebida - Status:', response.status, response.statusText);
        console.log('[USUARIOS] Headers:', response.headers);
        
        // Verificar se a resposta é ok antes de fazer parse
        if (!response.ok) {
            // Tentar ler o corpo da resposta mesmo em caso de erro
            return response.text().then(text => {
                console.error('[USUARIOS] Erro HTTP:', response.status, text);
                let errorData;
                try {
                    errorData = JSON.parse(text);
                } catch (e) {
                    errorData = { 
                        success: false,
                        error: 'Erro ao processar resposta do servidor', 
                        details: text,
                        code: 'PARSE_ERROR'
                    };
                }
                // Retornar objeto de erro em vez de lançar exceção
                return errorData;
            });
        }
        
        // Tentar fazer parse do JSON
        return response.text().then(text => {
            console.log('[USUARIOS] Corpo da resposta (texto):', text);
            try {
                const jsonData = JSON.parse(text);
                console.log('[USUARIOS] Dados parseados:', jsonData);
                return jsonData;
            } catch (e) {
                console.error('[USUARIOS] Erro ao fazer parse do JSON:', e);
                return {
                    success: false,
                    error: 'Resposta do servidor não é um JSON válido',
                    details: text,
                    code: 'INVALID_JSON'
                };
            }
        });
    })
    .then(data => {
        console.log('[USUARIOS] Dados recebidos da API:', data);
        
        // Verificar se data é válido
        if (!data) {
            console.error('[USUARIOS] ❌ Resposta vazia ou inválida');
            showNotification('Erro: Resposta inválida do servidor', 'error');
            return;
        }
        
        if (data.success === true || data.success === 'true') {
            showNotification(data.message || 'Senha redefinida com sucesso!', 'success');
            closeResetPasswordModal();
            
            // Se modo automático e senha temporária retornada, exibir modal de credenciais
            if (mode === 'auto' && data.temp_password) {
                console.log('[USUARIOS] 🔐 Senha temporária recebida:', data.temp_password);
                
                // Preparar credenciais para exibição
                const credentials = {
                    email: resetPasswordUser.email,
                    senha_temporaria: data.temp_password,
                    tipo: resetPasswordUser.type,
                    message: 'Nova senha temporária gerada'
                };
                
                // Exibir credenciais em modal customizado para facilitar cópia
                showCredentialsModal(credentials);
            } else if (mode === 'manual') {
                // Modo manual: apenas notificação de sucesso
                console.log('[USUARIOS] ✅ Senha redefinida manualmente com sucesso');
            }
            
            // Não recarregar automaticamente - a página já está correta
            console.log('[USUARIOS] ✅ Senha redefinida com sucesso - página permanece carregada');
        } else {
            console.error('[USUARIOS] ❌ Erro na resposta:', data);
            const errorMsg = data.error || data.message || 'Erro ao redefinir senha';
            const errorCode = data.code || 'UNKNOWN_ERROR';
            console.error('[USUARIOS] Código do erro:', errorCode);
            showNotification(errorMsg + (data.details ? ' (' + data.details + ')' : ''), 'error');
        }
    })
    .catch(error => {
        console.error('[USUARIOS] ❌ Erro na requisição:', error);
        console.error('[USUARIOS] Stack:', error.stack);
        showNotification(error.message || 'Erro ao redefinir senha. Tente novamente.', 'error');
    })
    .finally(() => {
        // Restaurar botão
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-key"></i> <span id="confirmResetBtnText">Redefinir Senha</span>';
        }
        console.log('Operação de redefinição de senha finalizada');
    });
}

// Garantir que a função esteja disponível globalmente
window.confirmResetPassword = confirmResetPassword;

// Mostrar modal de credenciais
function showCredentialsModal(credentials) {
    console.log('Exibindo modal de credenciais');
    
    // Determinar o tipo de campo baseado no tipo de usuário
    const userType = credentials.tipo || (resetPasswordUser ? resetPasswordUser.type || 'admin' : 'admin');
    const isStudent = userType === 'aluno';
    
    // Ajustar interface baseada no tipo de usuário
    const credentialLabel = document.getElementById('credentialLabelText');
    const credentialIcon = document.getElementById('credentialIcon');
    const credentialInput = document.getElementById('credentialEmail');
    const credentialCopyBtn = document.getElementById('credentialCopyBtn');
    
    if (isStudent) {
        // Para alunos, mostrar CPF
        credentialLabel.textContent = 'CPF:';
        credentialIcon.className = 'fas fa-id-card';
        credentialInput.placeholder = '000.000.000-00';
        credentialCopyBtn.title = 'Copiar CPF';
        
        // Usar CPF das credenciais ou do usuário
        const userCpf = credentials.cpf || (resetPasswordUser ? resetPasswordUser.cpf : '') || 'CPF não encontrado';
        credentialInput.value = userCpf;
    } else {
        // Para outros usuários, mostrar email
        credentialLabel.textContent = 'Email:';
        credentialIcon.className = 'fas fa-envelope';
        credentialInput.placeholder = 'usuario@email.com';
        credentialCopyBtn.title = 'Copiar email';
        credentialInput.value = credentials.email;
    }
    
    // Preencher senha
    document.getElementById('credentialPassword').value = credentials.senha_temporaria;
    
    // Mostrar modal
    const modal = document.getElementById('credentialsModal');
    modal.classList.add('show');
    
    console.log('Modal de credenciais aberto para tipo:', userType, isStudent ? 'CPF' : 'Email');
}

// Garantir que a função esteja disponível globalmente
window.showCredentialsModal = showCredentialsModal;

// Fechar modal de credenciais
function closeCredentialsModal() {
    console.log('Fechando modal de credenciais');
    const modal = document.getElementById('credentialsModal');
    modal.classList.remove('show');
}

// Garantir que a função esteja disponível globalmente
window.closeCredentialsModal = closeCredentialsModal;

// Copiar para área de transferência
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const value = element.value;
    
    // Selecionar o texto
    element.select();
    element.setSelectionRange(0, 99999); // Para mobile
    
    // Copiar para área de transferência
    navigator.clipboard.writeText(value).then(() => {
        // Feedback visual
        const button = element.parentElement.querySelector('.btn-copy');
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i>';
        button.style.background = '#28a745';
        
        // Mostrar notificação
        const credentialLabel = document.getElementById('credentialLabelText').textContent;
        const fieldName = elementId === 'credentialEmail' ? credentialLabel.replace(':', '') : 'Senha';
        showNotification(`${fieldName} copiado!`, 'success');
        
        // Restaurar botão após 2 segundos
        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.style.background = '#17a2b8';
        }, 2000);
        
        console.log('Copiado para área de transferência:', value);
    }).catch(err => {
        console.error('Erro ao copiar:', err);
        
        // Fallback para navegadores mais antigos
        try {
            document.execCommand('copy');
            const credentialLabel = document.getElementById('credentialLabelText').textContent;
            const fieldName = elementId === 'credentialEmail' ? credentialLabel.replace(':', '') : 'Senha';
            showNotification(`${fieldName} copiado!`, 'success');
        } catch (fallbackErr) {
            console.error('Fallback copy failed:', fallbackErr);
            showNotification('Erro ao copiar. Tente selecionar e copiar manualmente.', 'error');
        }
    });
}

// Garantir que a função esteja disponível globalmente
window.copyToClipboard = copyToClipboard;

// Alternar visibilidade da senha
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('credentialPassword');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'fas fa-eye';
    }
}

// Garantir que as funções estejam disponíveis globalmente
window.toggleResetMode = toggleResetMode;
window.validateManualPassword = validateManualPassword;
window.togglePasswordVisibility = togglePasswordVisibility;
window.toggleConfirmButton = toggleConfirmButton;

// Copiar todas as credenciais
function copyAllCredentials() {
    const credentialValue = document.getElementById('credentialEmail').value;
    const password = document.getElementById('credentialPassword').value;
    const credentialLabel = document.getElementById('credentialLabelText').textContent;
    
    const allCredentials = `${credentialLabel} ${credentialValue}\nSenha: ${password}`;
    
    navigator.clipboard.writeText(allCredentials).then(() => {
        showNotification('Todas as credenciais copiadas!', 'success');
        console.log('Todas as credenciais copiadas');
    }).catch(err => {
        console.error('Erro ao copiar credenciais:', err);
        showNotification('Erro ao copiar credenciais.', 'error');
    });
}

// Garantir que a função esteja disponível globalmente
window.copyAllCredentials = copyAllCredentials;

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    console.log('[USUARIOS] DOM carregado - Iniciando verificação...');
    
    // DEBUG: Verificar estado inicial da lista
    const listaContainer = document.querySelector('.users-grid');
    const cardBody = document.querySelector('.card-body');
    console.log('[USUARIOS] Container lista inicial:', listaContainer);
    console.log('[USUARIOS] Card body inicial:', cardBody);
    console.log('[USUARIOS] Quantidade de cards inicial:', listaContainer ? listaContainer.children.length : 'container não encontrado');
    console.log('[USUARIOS] Display da lista inicial:', listaContainer ? getComputedStyle(listaContainer).display : 'n/a');
    
    // Verificar se o modal está disponível
    const modal = document.getElementById('userModal');
    if (modal) {
        console.log('[USUARIOS] Modal de usuário disponível e pronto para uso');
    } else {
        console.warn('[USUARIOS] Modal de usuário não encontrado');
    }
    
    // Verificar se as funções estão definidas
    if (typeof showCreateUserModal === 'function') {
        console.log('Funcao showCreateUserModal esta disponivel');
    } else {
        console.error('Funcao showCreateUserModal NAO esta disponivel');
    }
    
    if (typeof editUser === 'function') {
        console.log('Funcao editUser esta disponivel');
    } else {
        console.error('Funcao editUser NAO esta disponivel');
    }
    
    if (typeof deleteUser === 'function') {
        console.log('Funcao deleteUser esta disponivel');
    } else {
        console.error('Funcao deleteUser NAO esta disponivel');
    }
    
    // Configurar event listeners para botões de exclusão
    const deleteButtons = document.querySelectorAll('.btn-excluir-usuario');
    console.log('Encontrados ' + deleteButtons.length + ' botoes de exclusao');
    
    deleteButtons.forEach(function(button, index) {
        const userId = button.getAttribute('data-user-id');
        console.log('Configurando botao de exclusao ' + (index + 1) + ' para usuario ID: ' + userId);
        
        // Adicionar event listener
        button.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            const userIdFromButton = this.getAttribute('data-user-id');
            console.log('Botao de exclusao clicado para usuario ID: ' + userIdFromButton);
            
            if (typeof deleteUser === 'function') {
                deleteUser(userIdFromButton);
            } else {
                console.error('Funcao deleteUser nao esta disponivel!');
                showNotification('Erro: Função de exclusão não está disponível. Recarregue a página.', 'error');
            }
        });
    });
    
    // Configurar event listeners para botões de edição
    const editButtons = document.querySelectorAll('.btn-editar-usuario');
    console.log('Encontrados ' + editButtons.length + ' botoes de edicao');
    
    editButtons.forEach(function(button, index) {
        const userId = button.getAttribute('data-user-id');
        console.log('Configurando botao de edicao ' + (index + 1) + ' para usuario ID: ' + userId);
        
        // Adicionar event listener
        button.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            const userIdFromButton = this.getAttribute('data-user-id');
            console.log('Botao de edicao clicado para usuario ID: ' + userIdFromButton);
            
            if (typeof editUser === 'function') {
                editUser(userIdFromButton);
            } else {
                console.error('Funcao editUser nao esta disponivel!');
                showNotification('Erro: Função de edição não está disponível. Recarregue a página.', 'error');
            }
        });
    });
    
    // Configurar event listeners para botões de redefinição de senha
    const resetPasswordButtons = document.querySelectorAll('.btn-redefinir-senha');
    console.log('Encontrados ' + resetPasswordButtons.length + ' botoes de redefinir senha');
    
    resetPasswordButtons.forEach(function(button, index) {
        const userId = button.getAttribute('data-user-id');
        const userName = button.getAttribute('data-user-name');
        const userEmail = button.getAttribute('data-user-email');
        console.log('Configurando botao de redefinir senha ' + (index + 1) + ' para usuario ID: ' + userId);
        
        // Adicionar event listener
        button.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            const userIdFromButton = this.getAttribute('data-user-id');
            const userNameFromButton = this.getAttribute('data-user-name');
            const userEmailFromButton = this.getAttribute('data-user-email');
            const userTypeFromButton = this.getAttribute('data-user-type') || 'admin';
            console.log('Botao de redefinir senha clicado para usuario ID: ' + userIdFromButton);
            
            if (typeof showResetPasswordModal === 'function') {
                showResetPasswordModal(userIdFromButton, userNameFromButton, userEmailFromButton, userTypeFromButton);
            } else {
                console.error('Funcao showResetPasswordModal nao esta disponivel!');
                showNotification('Erro: Função de redefinição de senha não está disponível. Recarregue a página.', 'error');
            }
        });
    });
    
    // Adicionar event listeners para os botões
    const novoUsuarioBtn = document.getElementById('btnNovoUsuario');
    if (novoUsuarioBtn) {
        console.log('Adicionando event listener para botao Novo Usuario');
        console.log('Botao encontrado:', novoUsuarioBtn);
        console.log('Botao ID:', novoUsuarioBtn.id);
        console.log('Botao HTML:', novoUsuarioBtn.outerHTML);
        
        novoUsuarioBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Botao Novo Usuario clicado via event listener');
            console.log('Evento:', e);
            console.log('Target:', e.target);
            
            if (typeof showCreateUserModal === 'function') {
                console.log('Chamando showCreateUserModal...');
                showCreateUserModal();
            } else {
                console.error('Funcao showCreateUserModal ainda nao esta disponivel');
                alert('Erro: Funcao nao disponivel. Tente recarregar a pagina.');
            }
        });
        
        console.log('Event listener adicionado com sucesso ao botao Novo Usuario');
    } else {
        console.error('Botao Novo Usuario NAO encontrado!');
        console.log('Procurando por botao com ID btnNovoUsuario...');
        const todosBotoes = document.querySelectorAll('button');
        console.log('Total de botoes encontrados:', todosBotoes.length);
        todosBotoes.forEach((btn, index) => {
            console.log('Botao ' + index + ':', btn.id, btn.textContent.trim());
        });
    }

    const btnExportar = document.getElementById('btnExportar');
    if (btnExportar) {
        console.log('Adicionando event listener para botao Exportar');
        btnExportar.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Botao Exportar clicado via event listener');
            if (typeof exportUsers === 'function') {
                exportUsers();
            } else {
                console.error('Funcao exportUsers ainda nao esta disponivel');
                alert('Erro: Funcao nao disponivel. Tente recarregar a pagina.');
            }
        });
    }

    const btnTeste = document.getElementById('btnTeste');
    if (btnTeste) {
        console.log('Adicionando event listener para botao Teste Modal');
        btnTeste.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Botao Teste Modal clicado via event listener');
            
            // Testar especificamente o modal
            console.log('Testando abertura do modal...');
            if (typeof showCreateUserModal === 'function') {
                showCreateUserModal();
                console.log('showCreateUserModal executado com sucesso');
                
                // Verificar se o modal está visível
                setTimeout(function() {
                    const modal = document.getElementById('userModal');
                    if (modal) {
                        console.log('Modal encontrado:', modal);
                        console.log('Modal display:', modal.style.display);
                        console.log('Modal visibility:', modal.style.visibility);
                        console.log('Modal opacity:', modal.style.opacity);
                        console.log('Modal offsetHeight:', modal.offsetHeight);
                        console.log('Modal offsetWidth:', modal.offsetWidth);
                        
                        if (modal.style.display === 'flex' || modal.style.display === 'block') {
                            console.log('Modal deve estar visível!');
                        } else {
                            console.log('Modal NAO esta visivel!');
                        }
                    } else {
                        console.error('Modal NAO encontrado!');
                    }
                }, 100);
            } else {
                console.error('showCreateUserModal NAO disponivel');
            }
        });
    }

    const btnTesteEventos = document.getElementById('btnTesteEventos');
    if (btnTesteEventos) {
        console.log('Adicionando event listener para botao Teste Eventos');
        btnTesteEventos.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Botao Teste Eventos clicado via event listener');
            alert('Teste de eventos funcionando!');
        });
    }
    
    const btnDebugModal = document.getElementById('btnDebugModal');
    if (btnDebugModal) {
        console.log('Adicionando event listener para botao Debug Modal');
        btnDebugModal.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Botao Debug Modal clicado via event listener');
            
            const modal = document.getElementById('userModal');
            if (modal) {
                console.log('=== DEBUG COMPLETO DO MODAL ===');
                console.log('Modal elemento:', modal);
                console.log('Modal classes:', modal.className);
                console.log('Modal tem classe show:', modal.classList.contains('show'));
                
                const styles = window.getComputedStyle(modal);
                console.log('Modal CSS computado:');
                console.log('- display:', styles.display);
                console.log('- visibility:', styles.visibility);
                console.log('- opacity:', styles.opacity);
                console.log('- z-index:', styles.zIndex);
                console.log('- pointer-events:', styles.pointerEvents);
                
                // Forçar abertura do modal para teste
                console.log('Forçando abertura do modal para teste...');
                modal.classList.add('show');
                
                setTimeout(function() {
                    console.log('Modal após forçar abertura:');
                    console.log('Classes:', modal.className);
                    console.log('Tem show:', modal.classList.contains('show'));
                    
                    const newStyles = window.getComputedStyle(modal);
                    console.log('Novos estilos:');
                    console.log('- display:', newStyles.display);
                    console.log('- visibility:', newStyles.visibility);
                    console.log('- opacity:', newStyles.opacity);
                    
                    // Verificar se está realmente visível
                    if (newStyles.display === 'flex' && newStyles.visibility === 'visible') {
                        console.log('✅ Modal está visível!');
                        alert('Modal aberto! Agora teste se ele fecha automaticamente.');
                    } else {
                        console.log('❌ Modal ainda não está visível!');
                    }
                }, 100);
            } else {
                console.error('Modal não encontrado!');
            }
        });
    }
    
    // Adicionar event listeners para botões de ação na tabela
    const btnEditarUsuarios = document.querySelectorAll('.btn-editar-usuario');
    btnEditarUsuarios.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const userId = this.getAttribute('data-user-id');
            console.log('Botao Editar clicado para usuario ID: ' + userId);
            if (typeof editUser === 'function') {
                editUser(userId);
            } else {
                console.error('Funcao editUser ainda nao esta disponivel');
                alert('Erro: Funcao nao disponivel. Tente recarregar a pagina.');
            }
        });
    });
    
    const btnExcluirUsuarios = document.querySelectorAll('.btn-excluir-usuario');
    btnExcluirUsuarios.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const userId = this.getAttribute('data-user-id');
            console.log('Botao Excluir clicado para usuario ID: ' + userId);
            if (typeof deleteUser === 'function') {
                deleteUser(userId);
            } else {
                console.error('Funcao deleteUser ainda nao esta disponivel');
                alert('Erro: Funcao nao disponivel. Tente recarregar a pagina.');
            }
        });
    });
    
    // Adicionar estilos para avatar do usuário
    const style = document.createElement('style');
    style.textContent = `
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        
        .font-weight-semibold {
            font-weight: var(--font-weight-semibold);
        }
        
        /* Estilos específicos para o modal */
        #userModal {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            background-color: rgba(0, 0, 0, 0.8) !important;
            z-index: 999999 !important;
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            visibility: hidden !important;
            opacity: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            pointer-events: none !important;
            transition: all 0.3s ease !important;
        }
        
        #userModal.show {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }
        
        #userModal .modal {
            background: white !important;
            border-radius: 8px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
            max-width: 500px !important;
            width: 90% !important;
            max-height: 90vh !important;
            overflow-y: auto !important;
            position: relative !important;
            margin: 20px !important;
            z-index: 1000000 !important;
            pointer-events: auto !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        #userModal .modal-header {
            padding: 20px !important;
            border-bottom: 1px solid #e5e7eb !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            background: white !important;
            color: #000 !important;
        }
        
        #userModal .modal-body {
            padding: 20px !important;
            background: white !important;
            color: #000 !important;
        }
        
        #userModal .modal-footer {
            padding: 20px !important;
            border-top: 1px solid #e5e7eb !important;
            display: flex !important;
            gap: 10px !important;
            justify-content: flex-end !important;
            background: white !important;
            color: #000 !important;
        }
        
        #userModal .form-group {
            margin-bottom: 15px !important;
        }
        
        #userModal .form-label {
            display: block !important;
            margin-bottom: 5px !important;
            font-weight: 500 !important;
            color: #000 !important;
        }
        
        #userModal .form-control {
            width: 100% !important;
            padding: 8px 12px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 4px !important;
            font-size: 14px !important;
            background: white !important;
            color: #000 !important;
        }
        
        #userModal .btn {
            padding: 8px 16px !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            font-size: 14px !important;
        }
        
        #userModal .btn-primary {
            background: #3b82f6 !important;
            color: white !important;
            border: none !important;
        }
        
        #userModal .btn-secondary {
            background: #f9fafb !important;
            color: #374151 !important;
            border: 1px solid #d1d5db !important;
        }
        
        #userModal .modal-close {
            background: none !important;
            border: none !important;
            font-size: 20px !important;
            cursor: pointer !important;
            color: #6b7280 !important;
            padding: 5px !important;
            border-radius: 4px !important;
        }
        
        /* Garantir que o título seja visível */
        #userModal .modal-title {
            color: #000 !important;
            font-weight: bold !important;
            font-size: 18px !important;
        }
        
        /* Garantir que o texto de ajuda seja visível */
        #userModal .form-text {
            color: #6b7280 !important;
            font-size: 12px !important;
        }
        
        /* Forçar visibilidade de todos os elementos filhos */
        #userModal.show * {
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        /* Estilos para o modal de redefinição de senha */
        #resetPasswordModal {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            background-color: rgba(0, 0, 0, 0.8) !important;
            z-index: 999999 !important;
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            visibility: hidden !important;
            opacity: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            pointer-events: none !important;
            transition: all 0.3s ease !important;
        }
        
        #resetPasswordModal.show {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }
        
        #resetPasswordModal .modal {
            background: white !important;
            border-radius: 8px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
            max-width: 500px !important;
            width: 90% !important;
            max-height: 90vh !important;
            overflow-y: auto !important;
            position: relative !important;
            margin: 20px !important;
            z-index: 1000000 !important;
            pointer-events: auto !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        #resetPasswordModal .user-info {
            background: var(--gray-50) !important;
            padding: 15px !important;
            border-radius: 6px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary-color) !important;
        }
        
        #resetPasswordModal .user-info h4 {
            margin: 0 0 10px 0 !important;
            color: var(--gray-800) !important;
            font-size: 16px !important;
        }
        
        #resetPasswordModal .user-info p {
            margin: 5px 0 !important;
            color: var(--gray-700) !important;
            font-size: 14px !important;
        }
        
        #resetPasswordModal .btn-warning {
            background: #f59e0b !important;
            color: white !important;
            border: none !important;
        }
        
        #resetPasswordModal .btn-warning:hover {
            background: #d97706 !important;
        }
        
        #resetPasswordModal .btn-warning:disabled {
            background: #d1d5db !important;
            color: #9ca3af !important;
            cursor: not-allowed !important;
        }
        
        /* Estilos para o modal de credenciais */
        #credentialsModal {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            background-color: rgba(0, 0, 0, 0.8) !important;
            z-index: 999999 !important;
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            visibility: hidden !important;
            opacity: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            pointer-events: none !important;
            transition: all 0.3s ease !important;
        }
        
        #credentialsModal.show {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }
        
        #credentialsModal .modal {
            background: white !important;
            border-radius: 8px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
            max-width: 600px !important;
            width: 90% !important;
            max-height: 90vh !important;
            overflow-y: auto !important;
            position: relative !important;
            margin: 20px !important;
            z-index: 1000000 !important;
            pointer-events: auto !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        .credentials-container {
            margin: 20px 0 !important;
        }
        
        .credential-item {
            margin-bottom: 20px !important;
            padding: 15px !important;
            background: #f8f9fa !important;
            border-radius: 8px !important;
            border-left: 4px solid #28a745 !important;
        }
        
        .credential-label {
            display: block !important;
            font-weight: 600 !important;
            color: #495057 !important;
            margin-bottom: 8px !important;
            font-size: 14px !important;
        }
        
        .credential-label i {
            margin-right: 8px !important;
            color: #28a745 !important;
        }
        
        .credential-value {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        
        .credential-input {
            flex: 1 !important;
            padding: 10px 12px !important;
            border: 1px solid #ced4da !important;
            border-radius: 4px !important;
            font-size: 14px !important;
            background: white !important;
            color: #495057 !important;
            font-family: 'Courier New', monospace !important;
            font-weight: 600 !important;
        }
        
        .btn-copy, .btn-toggle {
            padding: 8px 12px !important;
            border: none !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            font-size: 14px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-width: 40px !important;
            height: 40px !important;
        }
        
        .btn-copy {
            background: #17a2b8 !important;
            color: white !important;
        }
        
        .btn-copy:hover {
            background: #138496 !important;
        }
        
        .btn-toggle {
            background: #6c757d !important;
            color: white !important;
        }
        
        .btn-toggle:hover {
            background: #5a6268 !important;
        }
        
        .btn-success {
            background: #28a745 !important;
            color: white !important;
            border: none !important;
        }
        
        .btn-success:hover {
            background: #218838 !important;
        }
    `;
    document.head.appendChild(style);
    
    console.log('Pagina de usuarios inicializada com sucesso!');
    
    // Função de debug para verificar elementos da página
    function debugPageElements() {
        console.log('=== DEBUG ELEMENTOS DA PÁGINA ===');
        
        const tableContainer = document.querySelector('.table-container');
        const mobileCards = document.querySelector('.mobile-user-cards');
        const cardBody = document.querySelector('.card-body');
        
        console.log('Table Container:', tableContainer);
        console.log('Mobile Cards:', mobileCards);
        console.log('Card Body:', cardBody);
        
        if (tableContainer) {
            console.log('Table Container Display:', window.getComputedStyle(tableContainer).display);
            console.log('Table Container Visibility:', window.getComputedStyle(tableContainer).visibility);
        }
        
        if (mobileCards) {
            console.log('Mobile Cards Display:', window.getComputedStyle(mobileCards).display);
            console.log('Mobile Cards Visibility:', window.getComputedStyle(mobileCards).visibility);
        }
        
        console.log('Viewport Width:', window.innerWidth);
        console.log('================================');
    }
    
    // Executar debug após carregamento
    setTimeout(debugPageElements, 1000);
    
    // Função para alternar entre tabela e cards mobile
    function toggleMobileLayout() {
        const viewportWidth = window.innerWidth;
        const isMobile = viewportWidth <= 600; // Aumentar threshold
        const tableContainer = document.querySelector('.table-container');
        const mobileCards = document.querySelector('.mobile-user-cards');
        
        
        if (isMobile && mobileCards) {
            // Mobile pequeno - mostrar cards
            if (tableContainer) {
                tableContainer.style.display = 'none';
            }
            mobileCards.style.display = 'block';
        } else {
            // Desktop/tablet - mostrar tabela
            if (tableContainer) {
                tableContainer.style.display = 'block';
            }
            if (mobileCards) {
                mobileCards.style.display = 'none';
            }
        }
    }
    
    // Executar na inicialização
    toggleMobileLayout();
    
    // Executar no resize
    window.addEventListener('resize', toggleMobileLayout);
    
    // Configurar event listener para checkbox de confirmação
    const confirmCheckbox = document.getElementById('confirmResetPassword');
    const confirmBtn = document.getElementById('confirmResetBtn');
    
    if (confirmCheckbox && confirmBtn) {
        confirmCheckbox.addEventListener('change', function() {
            confirmBtn.disabled = !this.checked;
        });
    }
    
    // =====================================================
    // FILTRO POR TIPO DE USUÁRIO
    // =====================================================
    // Implementação: Filtro visual que mostra/oculta cards baseado no tipo selecionado
    // Compatibilidade: Funciona com modais (não recarrega página, apenas show/hide)
    
    const selectFiltro = document.getElementById('filtroTipoUsuario');
    const cards = document.querySelectorAll('.user-card');
    
    if (!selectFiltro) {
        console.warn('[USUARIOS] Filtro de tipo não inicializado — select não encontrado.');
    } else if (!cards.length) {
        console.warn('[USUARIOS] Filtro de tipo não inicializado — nenhum card encontrado.');
    } else {
        console.log('[USUARIOS] Filtro de tipo inicializado -', cards.length, 'cards encontrados');
        
        /**
         * Aplica filtro baseado no tipo selecionado
         * Mostra cards que correspondem ao tipo ou todos se "todos" estiver selecionado
         */
        function aplicarFiltroTipo() {
            const tipoSelecionado = selectFiltro.value; // 'todos', 'admin', 'secretaria', 'instrutor', 'aluno'
            let cardsVisiveis = 0;
            let cardsOcultos = 0;
            
            cards.forEach(card => {
                const tipoCard = (card.getAttribute('data-tipo') || '').toLowerCase();
                
                if (tipoSelecionado === 'todos' || tipoSelecionado === tipoCard) {
                    card.classList.remove('d-none');
                    cardsVisiveis++;
                } else {
                    card.classList.add('d-none');
                    cardsOcultos++;
                }
            });
            
            console.log('[USUARIOS] Filtro aplicado:', {
                tipo: tipoSelecionado,
                visiveis: cardsVisiveis,
                ocultos: cardsOcultos
            });
        }
        
        // Adicionar listener para mudanças no select
        selectFiltro.addEventListener('change', aplicarFiltroTipo);
        
        // Aplicar filtro inicial (garantir estado consistente)
        aplicarFiltroTipo();
        
        console.log('[USUARIOS] ✅ Filtro de tipo configurado com sucesso');
    }
});

// Verificação adicional após carregamento completo
window.addEventListener('load', function() {
    console.log('Página completamente carregada');
    console.log('Verificação final das funções:');
    console.log('- showCreateUserModal:', typeof showCreateUserModal);
    console.log('- editUser:', typeof editUser);
    console.log('- deleteUser:', typeof deleteUser);
    console.log('- closeUserModal:', typeof closeUserModal);
    console.log('- saveUser:', typeof saveUser);
    
    // Verificar se todas as funções estão disponíveis
    const funcoes = ['showCreateUserModal', 'editUser', 'deleteUser', 'closeUserModal', 'saveUser', 'exportUsers', 'showNotification', 'showResetPasswordModal', 'closeResetPasswordModal', 'confirmResetPassword', 'toggleResetMode', 'validateManualPassword', 'togglePasswordVisibility', 'toggleConfirmButton', 'showCredentialsModal', 'closeCredentialsModal', 'copyToClipboard', 'copyAllCredentials'];
    const funcoesFaltando = funcoes.filter(f => typeof window[f] !== 'function');
    
    if (funcoesFaltando.length > 0) {
        console.error('Funções faltando:', funcoesFaltando);
        alert('Atenção: As seguintes funções não estão funcionando: ' + funcoesFaltando.join(', ') + '. Tente recarregar a página.');
    } else {
             console.log('Todas as funções estão disponíveis!');
 }
});

// Timeout adicional para garantir que as funções sejam definidas
setTimeout(function() {
    console.log('Verificação de timeout das funções:');
    const funcoes = ['showCreateUserModal', 'editUser', 'deleteUser', 'closeUserModal', 'saveUser', 'exportUsers', 'showNotification', 'showResetPasswordModal', 'closeResetPasswordModal', 'confirmResetPassword', 'toggleResetMode', 'validateManualPassword', 'togglePasswordVisibility', 'toggleConfirmButton', 'showCredentialsModal', 'closeCredentialsModal', 'copyToClipboard', 'copyAllCredentials'];
    funcoes.forEach(f => {
        if (typeof window[f] === 'function') {
            console.log(f + ': Disponível');
        } else {
            console.error(f + ': NAO disponivel');
        }
    });
}, 2000);
</script>
