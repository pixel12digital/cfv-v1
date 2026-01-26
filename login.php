<?php
// =====================================================
// PÁGINA DE LOGIN PRINCIPAL - SISTEMA CFC
// VERSÃO 3.0 - INTERFACE REORGANIZADA POR TIPO DE USUÁRIO
// =====================================================

require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

// Se já estiver logado, redirecionar para dashboard apropriado
// Usa função centralizada redirectAfterLogin() para garantir redirecionamento correto por tipo
if (isLoggedIn()) {
    $user = getCurrentUser();
    redirectAfterLogin($user);
}

$error = '';
$success = '';
$userType = $_GET['type'] ?? ''; // Tipo de usuário selecionado (vazio = tela de seleção)
$hasSpecificType = !empty($userType); // Se tem type definido, mostrar apenas aquele tipo

// Processar formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $remember = isset($_POST['remember']);
    $selectedType = $_POST['user_type'] ?? 'admin';
    
    if (empty($email) || empty($senha)) {
        $error = 'Por favor, preencha todos os campos';
    } else {
        try {
            // Para alunos, usar sistema específico
            if ($selectedType === 'aluno') {
                $db = db();
                
                // CORREÇÃO CRÍTICA: Limpar CPF (remover pontos e traços) antes de buscar
                // O CPF pode vir formatado (034.547.699-90) mas no banco está sem formatação
                $cpfLimpo = preg_replace('/[^0-9]/', '', $email);
                
                // Log de debug
                error_log("[LOGIN ALUNO] Tentativa de login - CPF Original: $email, CPF Limpo: $cpfLimpo, Senha: [oculta]");
                
                // CORREÇÃO: Priorizar busca na tabela usuarios (onde a senha é atualizada)
                // Primeiro, buscar na tabela usuarios (onde a senha é atualizada pelo admin)
                $aluno = null;
                
                // Tentar buscar por CPF (limpo) primeiro na tabela usuarios
                $aluno = $db->fetch("SELECT * FROM usuarios WHERE cpf = ? AND tipo = 'aluno' AND ativo = 1", [$cpfLimpo]);
                error_log("[LOGIN ALUNO] Busca por CPF limpo na tabela usuarios: " . ($aluno ? "Encontrado ID " . $aluno['id'] : "Não encontrado"));
                
                // Se não encontrar por CPF, tentar por email na tabela usuarios
                if (!$aluno) {
                    $aluno = $db->fetch("SELECT * FROM usuarios WHERE email = ? AND tipo = 'aluno' AND ativo = 1", [$email]);
                    error_log("[LOGIN ALUNO] Busca por email na tabela usuarios: " . ($aluno ? "Encontrado ID " . $aluno['id'] : "Não encontrado"));
                }
                
                // Se não encontrar na tabela usuarios, tentar na tabela alunos (compatibilidade com sistema antigo)
                if (!$aluno) {
                    $aluno = $db->fetch("SELECT * FROM alunos WHERE cpf = ? AND ativo = 1", [$cpfLimpo]);
                    error_log("[LOGIN ALUNO] Busca na tabela alunos (fallback) com CPF limpo: " . ($aluno ? "Encontrado ID " . $aluno['id'] : "Não encontrado"));
                }
                
                if ($aluno) {
                    // Verificar se a senha existe
                    $senhaHash = $aluno['senha'] ?? null;
                    if (!$senhaHash) {
                        error_log("[LOGIN ALUNO] ERRO: Campo 'senha' está vazio ou não existe para ID: " . $aluno['id']);
                        $error = 'Erro no cadastro. Entre em contato com o administrador.';
                    } else {
                        $senhaValida = password_verify($senha, $senhaHash);
                        $senhaDefault = ($senha === '123456');
                        
                        error_log("[LOGIN ALUNO] Verificação de senha:");
                        error_log("[LOGIN ALUNO]   - Hash existe: SIM");
                        error_log("[LOGIN ALUNO]   - Comprimento do hash: " . strlen($senhaHash) . " caracteres");
                        error_log("[LOGIN ALUNO]   - Primeiros 20 chars do hash: " . substr($senhaHash, 0, 20) . "...");
                        error_log("[LOGIN ALUNO]   - password_verify: " . ($senhaValida ? "SIM" : "NÃO"));
                        error_log("[LOGIN ALUNO]   - Senha padrão (123456): " . ($senhaDefault ? "SIM" : "NÃO"));
                        
                        if ($senhaValida || $senhaDefault) {
                        // Usar o sistema de autenticação unificado
                        $_SESSION['user_id'] = $aluno['id'];
                        $_SESSION['user_email'] = $aluno['email'] ?? $aluno['cpf'] . '@aluno.cfc';
                        $_SESSION['user_tipo'] = 'aluno';
                        $_SESSION['last_activity'] = time();
                        
                        error_log("[LOGIN ALUNO] Login bem-sucedido para ID: " . $aluno['id']);
                        $success = 'Login realizado com sucesso';
                        
                        // Limpar buffer antes do redirecionamento
                        if (ob_get_level()) {
                            ob_end_clean();
                        }
                        
                            header('Location: aluno/dashboard.php');
                            exit;
                        } else {
                            error_log("[LOGIN ALUNO] Senha inválida para ID: " . $aluno['id']);
                            error_log("[LOGIN ALUNO] Tente verificar: 1) Se a senha foi atualizada corretamente, 2) Se há espaços extras, 3) Se o hash está correto");
                            $error = 'CPF ou senha inválidos';
                        }
                    }
                } else {
                    error_log("[LOGIN ALUNO] Usuário não encontrado com CPF: $cpfLimpo (original: $email)");
                    error_log("[LOGIN ALUNO] Verifique: 1) Se o CPF está correto, 2) Se o usuário está ativo, 3) Se o tipo é 'aluno'");
                    $error = 'CPF ou senha inválidos';
                }
            } else {
                // Para funcionários (admin, secretaria, instrutor), usar sistema normal
                $result = $auth->login($email, $senha, $remember);
                
                if ($result['success']) {
                    $success = $result['message'];
                    
                    // Limpar buffer antes do redirecionamento
                    if (ob_get_level()) {
                        ob_end_clean();
                    }
                    
                    // Usar função centralizada para redirecionar baseado no tipo de usuário
                    // Isso garante que instrutor vá para /instrutor/dashboard.php
                    // e admin/secretaria vão para /admin/index.php
                    $user = getCurrentUser();
                    redirectAfterLogin($user);
                } else {
                    $error = $result['message'];
                }
            }
        } catch (Exception $e) {
            $error = 'Erro interno do sistema. Tente novamente.';
            if (LOG_ENABLED) {
                error_log('Erro no login: ' . $e->getMessage());
            }
        }
    }
}

// Configurações por tipo de usuário
$userTypes = [
    'admin' => [
        'title' => 'Administrador',
        'placeholder' => 'admin@cfc.com',
        'field_label' => 'E-mail',
        'field_type' => 'email'
    ],
    'secretaria' => [
        'title' => 'Secretaria',
        'placeholder' => 'atendente@cfc.com',
        'field_label' => 'E-mail',
        'field_type' => 'email'
    ],
    'instrutor' => [
        'title' => 'Instrutor',
        'placeholder' => 'instrutor@cfc.com',
        'field_label' => 'E-mail',
        'field_type' => 'email'
    ],
    'aluno' => [
        'title' => 'Aluno',
        'placeholder' => '000.000.000-00',
        'field_label' => 'CPF',
        'field_type' => 'text'
    ]
];

// Se não tiver type definido, usar 'admin' como padrão para exibição
$displayType = $hasSpecificType ? $userType : 'admin';
$currentConfig = $userTypes[$displayType] ?? $userTypes['admin'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentConfig['title']; ?> | Sistema CFC</title>
    
    <!-- PWA Manifest - Carregar dinamicamente baseado no tipo -->
    <?php
    // Detectar base path para funcionar em local e produção
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    if (empty($basePath)) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = dirname($scriptName);
        if ($scriptDir !== '/' && $scriptDir !== '') {
            $basePath = rtrim($scriptDir, '/');
        }
    }
    
    $manifestPath = ($basePath ? $basePath : '') . '/pwa/manifest.json'; // Default
    $appTitle = 'Sistema CFC';
    $appName = 'CFC Bom Conselho';
    
    if ($userType === 'aluno') {
        $manifestPath = ($basePath ? $basePath : '') . '/pwa/manifest-aluno.json';
        $appTitle = 'Aluno CFC';
        $appName = 'Aluno CFC';
    } elseif ($userType === 'instrutor') {
        $manifestPath = ($basePath ? $basePath : '') . '/pwa/manifest-instrutor.json';
        $appTitle = 'Instrutor CFC';
        $appName = 'Instrutor CFC';
    }
    ?>
    <link rel="manifest" href="<?php echo htmlspecialchars($manifestPath); ?>">
    
    <!-- Capturar beforeinstallprompt CEDO (antes do DOM) - CRÍTICO para elegibilidade -->
    <script>
        // Capturar beforeinstallprompt o mais cedo possível (antes do DOMContentLoaded)
        // Isso é CRÍTICO - o Chrome só dispara o evento se houver listener ativo
        (function() {
            // Variável global para armazenar o prompt
            window.__deferredPrompt = null;
            window.__bipFiredAt = null;
            
            // Listener imediato (antes de qualquer outro script)
            window.addEventListener('beforeinstallprompt', function(e) {
                console.log('[PWA Early] ✅ beforeinstallprompt capturado CEDO!', new Date().toISOString());
                e.preventDefault();
                window.__deferredPrompt = e;
                window.__bipFiredAt = Date.now();
                
                // Notificar componentes que podem estar esperando
                window.dispatchEvent(new CustomEvent('pwa:beforeinstallprompt', { detail: e }));
            }, { once: false });
            
            // CRÍTICO: Escutar mensagem do SW quando ele é ativado
            // Se o SW está ativado mas não controlando, recarregar a página
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', function(event) {
                    if (event.data && event.data.type === 'SW_ACTIVATED') {
                        console.log('[PWA Early] SW ativado recebido, versão:', event.data.version);
                        
                        // Verificar se a versão é antiga (v1.0.4 ou anterior)
                        const swVersion = event.data.version || '';
                        if (swVersion && (swVersion.includes('v1.0.4') || swVersion.includes('v1.0.3') || swVersion.includes('v1.0.2') || swVersion.includes('v1.0.1'))) {
                            console.log('[PWA Early] ⚠️ Versão antiga do SW detectada:', swVersion, '- Atualizando sem reload automático (mitigação conexões)');
                            // Forçar atualização do SW SEM reload automático
                            navigator.serviceWorker.getRegistrations().then(function(regs) {
                                if (regs.length > 0) {
                                    regs[0].update().then(function() {
                                        console.log('[PWA Early] SW atualizado. Recarregue a página manualmente se necessário.');
                                        // REMOVIDO: window.location.reload() automático para evitar loops
                                    });
                                }
                            });
                            return;
                        }
                        
                        // Aguardar um pouco para o claim() processar
                        setTimeout(function() {
                            if (!navigator.serviceWorker.controller) {
                                console.log('[PWA Early] ⚠️ SW ativado mas não controlando. Recarregue manualmente se necessário (mitigação conexões).');
                                // REMOVIDO: window.location.reload() automático para evitar loops
                            } else {
                                console.log('[PWA Early] ✅ SW agora está controlando a página');
                            }
                        }, 500);
                    }
                });
                
                // Verificação periódica: se SW está registrado mas não controlando após 2 segundos
                // REMOVIDO: reload automático para evitar loops e excesso de conexões
                setTimeout(function() {
                    if (!navigator.serviceWorker.controller) {
                        navigator.serviceWorker.getRegistrations().then(function(regs) {
                            if (regs.length > 0) {
                                const reg = regs[0];
                                if (reg.active && reg.active.state === 'activated') {
                                    // Verificar versão do SW ativo
                                    if (reg.active.scriptURL) {
                                        console.log('[PWA Early] SW ativo:', reg.active.scriptURL);
                                        // Se for versão antiga, forçar atualização SEM reload
                                        if (reg.active.scriptURL.includes('sw.js')) {
                                            console.log('[PWA Early] ⚠️ SW ativado mas não controlando após 2s. Atualizando sem reload (mitigação conexões)...');
                                            reg.update().then(function() {
                                                console.log('[PWA Early] SW atualizado. Recarregue manualmente se necessário.');
                                                // REMOVIDO: window.location.reload() automático
                                            });
                                        } else {
                                            console.log('[PWA Early] ⚠️ SW ativado mas não controlando após 2s. Recarregue manualmente se necessário (mitigação conexões).');
                                            // REMOVIDO: window.location.reload() automático
                                        }
                                    }
                                }
                            }
                        });
                    }
                }, 2000);
            }
            
            console.log('[PWA Early] Listener de beforeinstallprompt registrado (antes do DOM)');
        })();
    </script>
    
    <!-- Meta tags PWA -->
    <meta name="theme-color" content="#10b981" id="theme-color-meta">
    <meta name="color-scheme" content="light dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default" id="apple-status-bar">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($appTitle); ?>">
    <meta name="application-name" content="<?php echo htmlspecialchars($appName); ?>">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(($basePath ? $basePath : '') . '/pwa/icons/icon-192.png'); ?>">
    <link rel="apple-touch-icon" sizes="152x152" href="/pwa/icons/icon-152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/pwa/icons/icon-192.png">
    
    <!-- Theme Tokens (deve vir primeiro) -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(($basePath ? $basePath : '') . '/assets/css/theme-tokens.css'); ?>?v=<?php echo filemtime(__DIR__ . '/assets/css/theme-tokens.css'); ?>">
    
    <!-- Theme Overrides Global (dark mode fixes) -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(($basePath ? $basePath : '') . '/assets/css/theme-overrides.css'); ?>?v=<?php echo filemtime(__DIR__ . '/assets/css/theme-overrides.css'); ?>">
    
    <!-- Script para atualizar theme-color dinamicamente (iOS/Android) -->
    <script>
        (function() {
            function updateThemeColor() {
                const metaThemeColor = document.getElementById('theme-color-meta');
                const appleMeta = document.getElementById('apple-status-bar');
                if (!metaThemeColor) return;
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (prefersDark) {
                    metaThemeColor.setAttribute('content', '#1e293b');
                    if (appleMeta) { appleMeta.setAttribute('content', 'black-translucent'); }
                } else {
                    metaThemeColor.setAttribute('content', '#10b981');
                    if (appleMeta) { appleMeta.setAttribute('content', 'default'); }
                }
            }
            updateThemeColor();
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateThemeColor);
        })();
    </script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--theme-bg, #F6F8FC);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            transition: background-color 0.2s ease;
        }
        
        .login-container {
            background: var(--theme-card-bg, white);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            min-height: 600px;
            display: flex;
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .left-panel {
            background: #1A365D;
            color: white;
            padding: 40px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        
        .left-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }
        
        .logo-image {
            width: 180px;
            height: 180px;
            margin-bottom: 30px;
            border-radius: 50%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            background: #1A365D;
            padding: 8px;
            object-fit: contain;
            transition: all 0.3s ease;
            border: 4px solid rgba(255,255,255,0.3);
        }
        
        .logo-image:hover {
            transform: scale(1.08);
            box-shadow: 0 15px 40px rgba(0,0,0,0.5);
        }
        
        .system-subtitle {
            font-size: 18px;
            opacity: 0.9;
            line-height: 1.6;
            text-align: center;
            margin-top: 10px;
        }
        
        .user-types {
            position: relative;
            z-index: 1;
        }
        
        .user-type-card {
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            text-decoration: none;
            color: white;
            display: block;
        }
        
        .user-type-card:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.4);
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }
        
        .user-type-card.active {
            background: rgba(255,255,255,0.25);
            border-color: #1A365D;
            box-shadow: 0 5px 15px rgba(26, 54, 93, 0.4);
        }
        
        .user-type-title {
            font-size: 18px;
            font-weight: 600;
            text-align: center;
        }
        
        .right-panel {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-title {
            font-size: 28px;
            color: var(--theme-text, #1A365D);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .login-subtitle {
            color: var(--theme-text-muted, #7f8c8d);
            font-size: 16px;
        }
        
        /* Texto informativo do portal */
        .login-portal-info {
            margin-top: 15px;
            padding: 12px 16px;
            background: var(--theme-info-bg, #e8f4f8);
            border-left: 4px solid var(--theme-info, #1A365D);
            border-radius: 4px;
        }
        
        .portal-info-text {
            color: var(--theme-info-text, #2c3e50);
            font-size: 14px;
            margin: 0;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--theme-text, #1A365D);
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--theme-input-border, #e1e5e9);
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: var(--theme-input-bg, #f8f9fa);
            color: var(--theme-input-text, #1e293b);
        }
        
        .form-control::placeholder {
            color: var(--theme-input-placeholder, #94a3b8);
            opacity: 1;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--theme-input-border-focus, #1A365D);
            background: var(--theme-input-bg, white);
            color: var(--theme-input-text, #1e293b);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }
        
        .form-control.error {
            border-color: #e74c3c;
            background: #fdf2f2;
        }
        
        .form-help {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .form-error {
            font-size: 12px;
            color: #e74c3c;
            margin-top: 5px;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .checkbox-group label {
            font-size: 14px;
            color: var(--theme-text, #1A365D);
        }
        
        .forgot-password {
            color: var(--theme-link, #1A365D);
            text-decoration: none;
            font-size: 14px;
        }
        
        .forgot-password:hover {
            color: var(--theme-link-hover, #1d4ed8);
            text-decoration: underline;
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: #1A365D;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 54, 93, 0.4);
            background: #0f2a47;
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fdf2f2;
            color: #e74c3c;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #f0f9ff;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--theme-border, #e1e5e9);
        }
        
        .login-footer p {
            color: var(--theme-text-muted, #7f8c8d);
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .support-info {
            background: var(--theme-bg-secondary, #f8f9fa);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .support-info h4 {
            color: var(--theme-text-secondary, #2c3e50);
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .support-info p {
            color: var(--theme-text-muted, #7f8c8d);
            font-size: 12px;
            margin: 2px 0;
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 400px;
            }
            
            .left-panel {
                padding: 30px 20px;
            }
            
            .right-panel {
                padding: 30px 20px;
            }
            
            .logo-image {
                width: 140px;
                height: 140px;
                margin-bottom: 20px;
                padding: 6px;
            }
            
            .system-subtitle {
                font-size: 16px;
            }
            
            .user-type-card {
                padding: 15px;
            }
        }
        
        .hidden {
            display: none !important;
        }
        
        .link-site-institucional {
            display: inline-block;
            margin-top: 15px;
            font-size: 13px;
            color: #7f8c8d;
            text-decoration: none;
            opacity: 0.85;
            transition: all 0.2s ease;
            text-align: center;
            width: 100%;
        }
        
        .link-site-institucional:hover {
            text-decoration: underline;
            opacity: 1;
            color: #1A365D;
        }
        
        .link-site-institucional:focus {
            outline: 2px solid #1A365D;
            outline-offset: 2px;
            border-radius: 2px;
        }
        
        /* Link "Trocar tipo de acesso" */
        .change-access-type {
            margin-top: 20px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .change-access-link {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .change-access-link:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        
        .change-access-link:focus {
            outline: 2px solid rgba(255, 255, 255, 0.8);
            outline-offset: 2px;
            border-radius: 2px;
        }
    </style>
    
    <!-- CSS adicional para garantir dark mode (deve vir depois do style inline) -->
    <style id="login-dark-mode-fix">
        @media (prefers-color-scheme: dark) {
            /* Forçar placeholders legíveis */
            .form-control::placeholder,
            input.form-control::placeholder,
            input[type="email"]::placeholder,
            input[type="password"]::placeholder {
                color: #94a3b8 !important;
                opacity: 1 !important;
            }
            
            /* Forçar link "Esqueci minha senha" visível */
            .forgot-password,
            a.forgot-password,
            .link-theme {
                color: #60a5fa !important;
            }
            
            .forgot-password:hover,
            a.forgot-password:hover,
            .link-theme:hover {
                color: #93c5fd !important;
            }
            
            /* Forçar inputs com contraste */
            .form-control {
                background-color: #1e293b !important;
                color: #f1f5f9 !important;
                border-color: #475569 !important;
            }
            
            .form-control:focus {
                background-color: #1e293b !important;
                color: #f1f5f9 !important;
                border-color: #60a5fa !important;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Painel Esquerdo - Seleção de Tipo de Usuário -->
        <div class="left-panel">
            <div class="logo-section">
                <img src="assets/logo.png" alt="Logo CFC" class="logo-image">
                <p class="system-subtitle">Sistema completo para gestão de Centros de Formação de Condutores</p>
        </div>
            
            <div class="user-types">
                <?php 
                // Se tiver type específico, mostrar apenas aquele tipo
                // Se não tiver, mostrar todos (exceto aluno, que tem seu próprio portal)
                if ($hasSpecificType) {
                    // Mostrar apenas o tipo específico quando type estiver definido
                    $typeToShow = $userType;
                    if (isset($userTypes[$typeToShow])) {
                        $config = $userTypes[$typeToShow];
                        ?>
                        <a href="?type=<?php echo $typeToShow; ?>" class="user-type-card active">
                            <div class="user-type-title"><?php echo $config['title']; ?></div>
                        </a>
                        <?php
                    }
                } else {
                    // Tela de seleção: mostrar todos exceto aluno
                    foreach ($userTypes as $type => $config): 
                        // Não mostrar Aluno na tela de seleção (tem portal próprio)
                        if ($type === 'aluno') continue;
                    ?>
                        <a href="?type=<?php echo $type; ?>" class="user-type-card">
                            <div class="user-type-title"><?php echo $config['title']; ?></div>
                        </a>
                    <?php endforeach;
                }
                ?>
            </div>
            <?php if ($hasSpecificType): ?>
                <div class="change-access-type">
                    <a href="login.php" class="change-access-link">Trocar tipo de acesso</a>
                </div>
            <?php endif; ?>
                </div>
                    
        <!-- Painel Direito - Formulário de Login -->
        <div class="right-panel">
            <div class="login-header">
                <h2 class="login-title"><?php echo $currentConfig['title']; ?></h2>
                <p class="login-subtitle">Entre com suas credenciais para acessar o sistema</p>
                <?php if ($hasSpecificType): ?>
                    <div class="login-portal-info">
                        <?php 
                        switch ($userType) {
                            case 'secretaria':
                                echo '<p class="portal-info-text">Acesso para Secretaria. Administrador use Portal do Administrador.</p>';
                                break;
                            case 'instrutor':
                                echo '<p class="portal-info-text">Acesso para Instrutor (instalação do app disponível aqui).</p>';
                                break;
                            case 'admin':
                                echo '<p class="portal-info-text">Acesso para Administrador.</p>';
                                break;
                        }
                        ?>
                    </div>
                <?php endif; ?>
                    </div>
                            
                            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_GET['message']) && $_GET['message'] === 'logout_success'): ?>
                <div class="alert alert-success">
                    ✅ Logout realizado com sucesso! Você foi desconectado do sistema.
                            </div>
                            <?php endif; ?>
                            
            <form method="POST">
                <input type="hidden" name="user_type" value="<?php echo $displayType; ?>">
                
                <div class="form-group">
                    <label for="email" class="form-label"><?php echo $currentConfig['field_label']; ?></label>
                    <input type="<?php echo $currentConfig['field_type']; ?>" 
                                       id="email" 
                                       name="email" 
                           class="form-control" 
                           placeholder="<?php echo $currentConfig['placeholder']; ?>" 
                           required>
                    <div class="form-help">
                        <?php if ($userType === 'aluno'): ?>
                            Digite seu CPF cadastrado no sistema
                        <?php else: ?>
                            Digite seu endereço de e-mail cadastrado no sistema
                        <?php endif; ?>
                            </div>
                        </div>
                                
                <div class="form-group">
                    <label for="senha" class="form-label">Senha</label>
                                <input type="password" 
                                       id="senha" 
                                       name="senha" 
                           class="form-control" 
                                       placeholder="Sua senha"
                           required>
                    <div class="form-help">Digite sua senha de acesso ao sistema</div>
                        </div>
                                
                <?php if ($userType !== 'aluno'): ?>
                <div class="form-options">
                    <div class="checkbox-group">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Lembrar de mim</label>
                            </div>
                    <a href="forgot-password.php<?php echo $hasSpecificType ? '?type=' . htmlspecialchars($userType) : ''; ?>" class="forgot-password link-theme">Esqueci minha senha</a>
                            </div>
                <?php else: ?>
                <div class="form-options">
                    <div class="form-help" style="text-align: center; margin-top: 10px;">
                        <a href="forgot-password.php?type=aluno" class="link-theme" style="text-decoration: none;">Esqueci minha senha</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-login">
                    Entrar no Sistema
                            </button>
                            
                <a href="https://cfcbomconselho.com.br/" target="_blank" rel="noopener" class="link-site-institucional" id="link-site-institucional">
                    Abrir site institucional
                </a>
            </form>
            
            <div class="login-footer">
                <p>Problemas para acessar? Entre em contato com o suporte</p>
                
                <!-- PWA Install Footer Container -->
                <!-- Em mobile, este container será fixo no rodapé (barra flutuante) -->
                <div class="pwa-install-footer-container"></div>
                
                <div class="support-info">
                    <h4>📞 Suporte</h4>
                    <p>Segunda a Sexta, 8h às 18h</p>
                    <p>suporte@cfc.com</p>
                    <p>&copy; <?php echo date('Y'); ?> Sistema CFC. Versão 3.0</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Máscara para CPF quando tipo for aluno
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            const userType = '<?php echo $userType; ?>';
            
            if (userType === 'aluno') {
                emailInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                    e.target.value = value;
                });
            }
        });
        
        // Auto-focus no campo de entrada (apenas em desktop, não em mobile)
        // Em mobile, o focus automático abre o teclado e interfere na rolagem até o botão de instalação
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (!isMobile) {
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailInput.focus();
            }
        }
        
        // Mensagem de retorno guiado ao clicar no link do site institucional
        const linkSiteInstitucional = document.getElementById('link-site-institucional');
        if (linkSiteInstitucional) {
            linkSiteInstitucional.addEventListener('click', function(e) {
                // Não usar preventDefault() - deixar o link abrir normalmente
                // Mostrar mensagem após um pequeno delay para não interferir na abertura
                setTimeout(function() {
                    const userType = '<?php echo $userType; ?>';
                    let message = '';
                    
                    if (userType === 'instrutor') {
                        message = 'Você abriu o site institucional. Para voltar ao sistema, use o ícone do app "Instrutor" (ou retorne para esta aba).';
                    } else if (userType === 'aluno') {
                        message = 'Você abriu o site institucional. Para voltar ao sistema, use o ícone do app "Aluno" (ou retorne para esta aba).';
                    } else {
                        message = 'Você abriu o site institucional. Para voltar ao sistema, retorne para esta aba.';
                    }
                    
                    alert(message);
                }, 50);
            });
        }
    </script>
    
    <!-- PWA Registration Script -->
    <script src="<?php echo $basePath; ?>/pwa/pwa-register.js"></script>
    
    <!-- Script para prevenir e remover qualquer banner PWA -->
    <style>
        /* Ocultar banners PWA imediatamente com CSS */
        .pwa-banner,
        .pwa-banner-install,
        [class*="pwa-banner"] {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
            position: absolute !important;
            left: -9999px !important;
        }
    </style>
    <script>
        // Prevenir criação e remover imediatamente qualquer banner PWA
        (function() {
            function removePWABanners() {
                const banners = document.querySelectorAll('.pwa-banner, .pwa-banner-install, [class*="pwa-banner"]');
                banners.forEach(banner => {
                    console.log('[PWA Cleanup] Removendo banner encontrado:', banner);
                    banner.style.display = 'none';
                    banner.remove();
                });
                
                // Remover estilos do banner
                const bannerStyles = document.getElementById('pwa-banner-styles');
                if (bannerStyles) {
                    bannerStyles.remove();
                }
            }
            
            // Observar mutações do DOM para remover banners assim que forem criados
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            if (node.classList && (
                                node.classList.contains('pwa-banner') ||
                                node.classList.contains('pwa-banner-install') ||
                                node.className.includes('pwa-banner')
                            )) {
                                console.log('[PWA Cleanup] Banner detectado sendo adicionado, removendo imediatamente:', node);
                                node.remove();
                            }
                            // Verificar filhos também
                            const childBanners = node.querySelectorAll && node.querySelectorAll('.pwa-banner, .pwa-banner-install, [class*="pwa-banner"]');
                            if (childBanners && childBanners.length > 0) {
                                childBanners.forEach(banner => banner.remove());
                            }
                        }
                    });
                });
            });
            
            // Iniciar observação quando DOM estiver pronto
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                    removePWABanners();
                });
            } else {
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
                removePWABanners();
            }
            
            // Remover imediatamente
            removePWABanners();
            
            // Remover periodicamente como backup (mas com intervalo menor)
            setInterval(removePWABanners, 100);
        })();
    </script>
    
    <!-- PWA Install Footer Component -->
    <?php
    // Detectar base path dinamicamente
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = dirname($scriptName);
    $basePath = rtrim($scriptDir, '/');
    if ($basePath === '/' || $basePath === '') {
        $basePath = '';
    }
    ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/pwa/install-footer.css">
    <script>
        // Definir base path para o componente
        window.PWA_BASE_PATH = '<?php echo $basePath; ?>';
    </script>
    <script src="<?php echo $basePath; ?>/pwa/install-footer.js"></script>
    
    <!-- PWA Install Button Component -->
    <script>
        // Componente de instalação PWA discreto
        class PWAInstallButton {
            constructor() {
                this.deferredPrompt = null;
                this.init();
            }
            
            init() {
                // Verificar se já está instalado
                if (window.matchMedia('(display-mode: standalone)').matches) {
                    return; // Já instalado, não mostrar botão
                }
                
                // Escutar evento beforeinstallprompt (Android/Desktop)
                window.addEventListener('beforeinstallprompt', (e) => {
                    e.preventDefault();
                    this.deferredPrompt = e;
                    this.showInstallButton();
                });
                
                // Escutar evento appinstalled (quando instala)
                window.addEventListener('appinstalled', () => {
                    this.hideInstallButton();
                    this.deferredPrompt = null;
                });
                
                // Verificar se é iOS e mostrar instruções
                if (this.isIOS()) {
                    this.showIOSInstructions();
                }
            }
            
            isIOS() {
                return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            }
            
            showInstallButton() {
                // Verificar se já foi dispensado recentemente
                const dismissed = localStorage.getItem('pwa-install-dismissed');
                const dismissedTime = dismissed ? parseInt(dismissed) : 0;
                const now = Date.now();
                const sevenDays = 7 * 24 * 60 * 60 * 1000;
                
                if (dismissed && (now - dismissedTime) < sevenDays) {
                    return; // Ainda dentro do período de repouso
                }
                
                // Criar botão se não existir
                if (document.getElementById('pwa-install-btn')) {
                    return;
                }
                
                const button = document.createElement('button');
                button.id = 'pwa-install-btn';
                button.className = 'pwa-install-button';
                button.innerHTML = '<i class="fas fa-download"></i> Instalar App';
                button.onclick = () => this.install();
                
                // Adicionar estilos
                this.addStyles();
                
                // Adicionar ao formulário (antes do botão de login)
                const loginForm = document.querySelector('form');
                if (loginForm) {
                    loginForm.insertBefore(button, loginForm.querySelector('.btn-login'));
                }
            }
            
            showIOSInstructions() {
                // Verificar se já foi dispensado
                const dismissed = localStorage.getItem('pwa-install-ios-dismissed');
                const dismissedTime = dismissed ? parseInt(dismissed) : 0;
                const now = Date.now();
                const sevenDays = 7 * 24 * 60 * 60 * 1000;
                
                if (dismissed && (now - dismissedTime) < sevenDays) {
                    return;
                }
                
                // Criar instrução se não existir
                if (document.getElementById('pwa-ios-instructions')) {
                    return;
                }
                
                const instructions = document.createElement('div');
                instructions.id = 'pwa-ios-instructions';
                instructions.className = 'pwa-ios-instructions';
                instructions.innerHTML = `
                    <div class="pwa-ios-content">
                        <i class="fas fa-mobile-alt"></i>
                        <div class="pwa-ios-text">
                            <strong>Instalar App</strong>
                            <p>Toque em <strong>Compartilhar</strong> <i class="fas fa-share"></i> e depois em <strong>Adicionar à Tela de Início</strong></p>
                        </div>
                        <button class="pwa-ios-close" onclick="this.parentElement.parentElement.remove(); localStorage.setItem('pwa-install-ios-dismissed', Date.now());">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                
                // Adicionar estilos iOS
                this.addIOSStyles();
                
                // Adicionar após o formulário
                const loginForm = document.querySelector('form');
                if (loginForm) {
                    loginForm.parentElement.insertBefore(instructions, loginForm.nextSibling);
                }
            }
            
            async install() {
                if (!this.deferredPrompt) {
                    return;
                }
                
                try {
                    this.deferredPrompt.prompt();
                    const { outcome } = await this.deferredPrompt.userChoice;
                    
                    if (outcome === 'accepted') {
                        console.log('[PWA] Usuário aceitou instalação');
                    } else {
                        console.log('[PWA] Usuário rejeitou instalação');
                    }
                    
                    this.deferredPrompt = null;
                    this.hideInstallButton();
                } catch (error) {
                    console.error('[PWA] Erro ao instalar:', error);
                }
            }
            
            hideInstallButton() {
                const button = document.getElementById('pwa-install-btn');
                if (button) {
                    button.remove();
                }
            }
            
            addStyles() {
                if (document.getElementById('pwa-install-styles')) return;
                
                const style = document.createElement('style');
                style.id = 'pwa-install-styles';
                style.textContent = `
                    .pwa-install-button {
                        width: 100%;
                        padding: 12px 20px;
                        background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
                        color: white;
                        border: none;
                        border-radius: 10px;
                        font-size: 15px;
                        font-weight: 500;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        margin-bottom: 15px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 8px;
                    }
                    
                    .pwa-install-button:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
                    }
                    
                    .pwa-install-button:active {
                        transform: translateY(0);
                    }
                    
                    .pwa-install-button i {
                        font-size: 16px;
                    }
                `;
                document.head.appendChild(style);
            }
            
            addIOSStyles() {
                if (document.getElementById('pwa-ios-styles')) return;
                
                const style = document.createElement('style');
                style.id = 'pwa-ios-styles';
                style.textContent = `
                    .pwa-ios-instructions {
                        background: #f0f9ff;
                        border: 2px solid #1A365D;
                        border-radius: 10px;
                        padding: 15px;
                        margin-top: 20px;
                        margin-bottom: 20px;
                    }
                    
                    .pwa-ios-content {
                        display: flex;
                        align-items: center;
                        gap: 15px;
                    }
                    
                    .pwa-ios-content i.fa-mobile-alt {
                        font-size: 24px;
                        color: #1A365D;
                        flex-shrink: 0;
                    }
                    
                    .pwa-ios-text {
                        flex: 1;
                    }
                    
                    .pwa-ios-text strong {
                        color: #2c3e50;
                        display: block;
                        margin-bottom: 5px;
                        font-size: 15px;
                    }
                    
                    .pwa-ios-text p {
                        margin: 0;
                        color: #7f8c8d;
                        font-size: 13px;
                        line-height: 1.5;
                    }
                    
                    .pwa-ios-close {
                        background: transparent;
                        border: none;
                        color: #7f8c8d;
                        cursor: pointer;
                        font-size: 18px;
                        padding: 5px;
                        flex-shrink: 0;
                    }
                    
                    .pwa-ios-close:hover {
                        color: #2c3e50;
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        // Inicializar quando DOM estiver pronto
        document.addEventListener('DOMContentLoaded', () => {
            // Só mostrar na página de login do instrutor ou admin
            const userType = '<?php echo $userType; ?>';
            if (userType === 'instrutor' || userType === 'admin') {
                window.pwaInstallButton = new PWAInstallButton();
            }
        });
    </script>
</body>
</html>
