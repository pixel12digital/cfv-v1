<?php
/**
 * Página de Troca de Senha do Instrutor
 * Permite ao instrutor trocar sua própria senha
 * Se precisa_trocar_senha = 1, esta página é obrigatória
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Verificar autenticação
$user = getCurrentUser();
if (!$user || $user['tipo'] !== 'instrutor') {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/login.php');
    exit();
}

$db = db();

// Verificar se precisa trocar senha (flag)
$precisaTrocarSenha = false;
$forcado = isset($_GET['forcado']) && $_GET['forcado'] == '1';

try {
    $checkColumn = $db->fetch("SHOW COLUMNS FROM usuarios LIKE 'precisa_trocar_senha'");
    if ($checkColumn) {
        $usuarioCompleto = $db->fetch("SELECT precisa_trocar_senha FROM usuarios WHERE id = ?", [$user['id']]);
        if ($usuarioCompleto && isset($usuarioCompleto['precisa_trocar_senha']) && $usuarioCompleto['precisa_trocar_senha'] == 1) {
            $precisaTrocarSenha = true;
        }
    }
} catch (Exception $e) {
    // Continuar normalmente
}

$success = '';
$error = '';

// Processar troca de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';
    
    // Validações
    if (empty($senhaAtual)) {
        $error = 'Senha atual é obrigatória.';
    } elseif (empty($novaSenha)) {
        $error = 'Nova senha é obrigatória.';
    } elseif (strlen($novaSenha) < 8) {
        $error = 'A nova senha deve ter no mínimo 8 caracteres.';
    } elseif ($novaSenha !== $confirmarSenha) {
        $error = 'As senhas não coincidem.';
    } else {
        // Buscar senha atual do usuário
        $usuarioCompleto = $db->fetch("SELECT senha FROM usuarios WHERE id = ?", [$user['id']]);
        
        if (!$usuarioCompleto) {
            $error = 'Usuário não encontrado.';
        } elseif (!password_verify($senhaAtual, $usuarioCompleto['senha'])) {
            $error = 'Senha atual incorreta.';
        } else {
            // Verificar se nova senha é diferente da atual
            if (password_verify($novaSenha, $usuarioCompleto['senha'])) {
                $error = 'A nova senha deve ser diferente da senha atual.';
            } else {
                // Atualizar senha
                try {
                    $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                    
                    // Preparar query de atualização
                    $updateFields = ['senha = ?', 'atualizado_em = NOW()'];
                    $updateValues = [$novaSenhaHash];
                    
                    // Se precisa_trocar_senha existe, setar para 0
                    try {
                        $checkColumn = $db->fetch("SHOW COLUMNS FROM usuarios LIKE 'precisa_trocar_senha'");
                        if ($checkColumn) {
                            $updateFields[] = 'precisa_trocar_senha = 0';
                        }
                    } catch (Exception $e) {
                        // Coluna não existe, continuar
                    }
                    
                    $updateValues[] = $user['id'];
                    $updateQuery = 'UPDATE usuarios SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
                    
                    $db->query($updateQuery, $updateValues);
                    
                    // Log de auditoria
                    if (defined('LOG_ENABLED') && LOG_ENABLED) {
                        error_log(sprintf(
                            '[PASSWORD_CHANGE] user_id=%d, user_email=%s, timestamp=%s, ip=%s',
                            $user['id'],
                            $user['email'],
                            date('Y-m-d H:i:s'),
                            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                        ));
                    }
                    
                    // Se estava forçado, redirecionar para dashboard
                    if ($precisaTrocarSenha || $forcado) {
                        // CORREÇÃO: Usar BASE_PATH para garantir caminho correto
                        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                        header('Location: ' . $basePath . '/instrutor/dashboard.php?senha_alterada=1');
                        exit();
                    } else {
                        $success = 'Senha alterada com sucesso!';
                    }
                } catch (Exception $e) {
                    $error = 'Erro ao alterar senha: ' . $e->getMessage();
                    if (defined('LOG_ENABLED') && LOG_ENABLED) {
                        error_log('Erro ao alterar senha do instrutor: ' . $e->getMessage());
                    }
                }
            }
        }
    }
}

// Verificar se veio do dashboard com mensagem de sucesso
if (isset($_GET['senha_alterada']) && $_GET['senha_alterada'] == '1') {
    $success = 'Senha alterada com sucesso! Você já pode usar o sistema normalmente.';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#10b981" id="theme-color-meta">
    <title>Trocar Senha - <?php echo htmlspecialchars($user['nome']); ?></title>
    <link rel="stylesheet" href="../assets/css/theme-tokens.css">
    <link rel="stylesheet" href="../assets/css/mobile-first.css">
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        (function(){var m=document.getElementById('theme-color-meta');if(!m)return;function u(){var d=window.matchMedia('(prefers-color-scheme: dark)').matches;m.setAttribute('content',d?'#1e293b':'#10b981');}u();window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',u);})();
    </script>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>Trocar Senha</h1>
                <div class="subtitle"><?php echo $precisaTrocarSenha || $forcado ? 'Você precisa alterar sua senha para continuar' : 'Altere sua senha de acesso'; ?></div>
            </div>
            <?php if (!$precisaTrocarSenha && !$forcado): ?>
            <?php $basePath = defined('BASE_PATH') ? BASE_PATH : ''; ?>
            <a href="<?php echo $basePath; ?>/instrutor/dashboard.php" style="color: white; text-decoration: none; padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 8px;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container" style="max-width: 600px; margin: 0 auto; padding: 20px 16px;">
        <!-- Mensagens -->
        <?php if ($success): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Aviso se forçado -->
        <?php if ($precisaTrocarSenha || $forcado): ?>
        <div class="alert alert-warning" style="background: #fff3cd; color: #856404; padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ffeaa7;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Atenção:</strong> Você precisa alterar sua senha para continuar usando o sistema. 
            Esta é uma medida de segurança obrigatória.
        </div>
        <?php endif; ?>

        <!-- Formulário -->
        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 24px;">
            <form method="POST" action="" id="changePasswordForm">
                <input type="hidden" name="action" value="change_password">
                
                <!-- Senha Atual -->
                <div style="margin-bottom: 20px;">
                    <label for="senha_atual" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Senha Atual <span style="color: #e74c3c;">*</span>
                    </label>
                    <div style="position: relative;">
                        <input 
                            type="password" 
                            id="senha_atual" 
                            name="senha_atual" 
                            required
                            autocomplete="current-password"
                            style="width: 100%; padding: 12px 40px 12px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;"
                        >
                        <i class="fas fa-eye" id="toggleSenhaAtual" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666;"></i>
                    </div>
                </div>

                <!-- Nova Senha -->
                <div style="margin-bottom: 20px;">
                    <label for="nova_senha" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Nova Senha <span style="color: #e74c3c;">*</span>
                    </label>
                    <div style="position: relative;">
                        <input 
                            type="password" 
                            id="nova_senha" 
                            name="nova_senha" 
                            required
                            minlength="8"
                            autocomplete="new-password"
                            style="width: 100%; padding: 12px 40px 12px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;"
                        >
                        <i class="fas fa-eye" id="toggleNovaSenha" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666;"></i>
                    </div>
                    <small style="color: #666; font-size: 12px; margin-top: 4px; display: block;">
                        Mínimo de 8 caracteres
                    </small>
                </div>

                <!-- Confirmar Nova Senha -->
                <div style="margin-bottom: 20px;">
                    <label for="confirmar_senha" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Confirmar Nova Senha <span style="color: #e74c3c;">*</span>
                    </label>
                    <div style="position: relative;">
                        <input 
                            type="password" 
                            id="confirmar_senha" 
                            name="confirmar_senha" 
                            required
                            minlength="8"
                            autocomplete="new-password"
                            style="width: 100%; padding: 12px 40px 12px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;"
                        >
                        <i class="fas fa-eye" id="toggleConfirmarSenha" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666;"></i>
                    </div>
                    <small id="senhaMatch" style="display: none; font-size: 12px; margin-top: 4px;"></small>
                </div>

                <!-- Botões -->
                <div style="display: flex; gap: 12px; margin-top: 32px;">
                    <button 
                        type="submit" 
                        id="submitBtn"
                        style="flex: 1; padding: 12px 24px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer;"
                    >
                        <i class="fas fa-key"></i> Alterar Senha
                    </button>
                    <?php if (!$precisaTrocarSenha && !$forcado): ?>
                    <?php $basePath = defined('BASE_PATH') ? BASE_PATH : ''; ?>
                    <a 
                        href="<?php echo $basePath; ?>/instrutor/dashboard.php" 
                        style="padding: 12px 24px; background: #f0f0f0; color: #333; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; text-decoration: none; display: flex; align-items: center; justify-content: center;"
                    >
                        Cancelar
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle visibilidade de senhas
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
        
        document.getElementById('toggleSenhaAtual').addEventListener('click', function() {
            togglePasswordVisibility('senha_atual', 'toggleSenhaAtual');
        });
        
        document.getElementById('toggleNovaSenha').addEventListener('click', function() {
            togglePasswordVisibility('nova_senha', 'toggleNovaSenha');
        });
        
        document.getElementById('toggleConfirmarSenha').addEventListener('click', function() {
            togglePasswordVisibility('confirmar_senha', 'toggleConfirmarSenha');
        });
        
        // Validar confirmação de senha em tempo real
        const novaSenha = document.getElementById('nova_senha');
        const confirmarSenha = document.getElementById('confirmar_senha');
        const senhaMatch = document.getElementById('senhaMatch');
        const submitBtn = document.getElementById('submitBtn');
        
        function validatePasswordMatch() {
            if (confirmarSenha.value.length > 0) {
                if (novaSenha.value === confirmarSenha.value) {
                    senhaMatch.textContent = '✓ Senhas coincidem';
                    senhaMatch.style.color = '#28a745';
                    senhaMatch.style.display = 'block';
                } else {
                    senhaMatch.textContent = '✗ As senhas não coincidem';
                    senhaMatch.style.color = '#dc3545';
                    senhaMatch.style.display = 'block';
                }
            } else {
                senhaMatch.style.display = 'none';
            }
        }
        
        novaSenha.addEventListener('input', validatePasswordMatch);
        confirmarSenha.addEventListener('input', validatePasswordMatch);
        
        // Validar formulário antes de enviar
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            if (novaSenha.value !== confirmarSenha.value) {
                e.preventDefault();
                alert('As senhas não coincidem. Por favor, verifique e tente novamente.');
                return false;
            }
            
            if (novaSenha.value.length < 8) {
                e.preventDefault();
                alert('A nova senha deve ter no mínimo 8 caracteres.');
                return false;
            }
        });
    </script>
</body>
</html>

