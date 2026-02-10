<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#023A8D" id="theme-color-meta">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CFC Sistema">
    <base href="<?= base_path('/') ?>">
    <title><?= $pageTitle ?? 'CFC Sistema' ?></title>
    
    <!-- PWA: Captura precoce do beforeinstallprompt -->
    <script>
    // Capturar beforeinstallprompt o mais cedo possível
    window.__deferredPrompt = null;
    window.__bipFiredAt = null;
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        window.__deferredPrompt = e;
        window.__bipFiredAt = Date.now();
        console.log('[PWA Early] beforeinstallprompt capturado');
        // Disparar evento customizado para componentes que carregam depois
        window.dispatchEvent(new CustomEvent('pwa:beforeinstallprompt', { detail: e }));
    });
    </script>
    
    <!-- PWA Manifest (usando pwa-manifest.php para white-label dinâmico) -->
    <link rel="manifest" href="<?= pwa_asset_path('pwa-manifest.php') ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= base_url('icons/icon-192x192.png') ?>">
    
    <!-- Apple Touch Icon (iOS) -->
    <link rel="apple-touch-icon" href="<?= base_url('icons/icon-192x192.png') ?>">
    <link rel="apple-touch-icon" sizes="152x152" href="<?= base_url('icons/icon-192x192.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('icons/icon-192x192.png') ?>">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?= asset_url('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/layout.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/utilities.css') ?>">
    
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?= asset_url($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <!-- theme-color dinâmico para iOS/Android dark mode -->
    <script>
    (function() {
        function updateThemeColor() {
            var meta = document.getElementById('theme-color-meta');
            if (!meta) return;
            var isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            meta.setAttribute('content', isDark ? '#1e293b' : '#023A8D');
        }
        updateThemeColor();
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateThemeColor);
    })();
    </script>
</head>
<body>
    <div class="app-shell">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="topbar-icon sidebar-toggle d-block-mobile d-none" id="sidebarToggle">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                
                <a href="<?= base_url('dashboard') ?>" class="topbar-logo">
                    <img 
                        src="<?= base_url('login/cfc-logo') ?>" 
                        alt="Logo do CFC" 
                        class="topbar-logo-image"
                        onerror="this.style.display='none'; if (this.nextElementSibling) { this.nextElementSibling.style.display='inline-block'; }"
                    >
                    <span class="topbar-logo-text">CFC</span>
                </a>
                
                <div class="topbar-search">
                    <input type="search" class="topbar-search-input" placeholder="Buscar...">
                </div>
            </div>
            
            <div class="topbar-right">
                <!-- Notificações -->
                <?php
                $notificationCount = 0;
                if (!empty($_SESSION['user_id'])) {
                    $notificationModel = new \App\Models\Notification();
                    $notificationCount = $notificationModel->countUnread($_SESSION['user_id']);
                }
                ?>
                <a href="<?= base_url('notificacoes') ?>" class="topbar-icon" id="notificationsBtn" style="text-decoration: none; color: inherit;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <?php if ($notificationCount > 0): ?>
                    <span class="topbar-icon-badge" id="notificationsBadge"><?= $notificationCount > 99 ? '99+' : $notificationCount ?></span>
                    <?php else: ?>
                    <span class="topbar-icon-badge" id="notificationsBadge" style="display: none;">0</span>
                    <?php endif; ?>
                </a>
                
                <!-- Seletor de Papel -->
                <?php
                // Normalizar available_roles para aceitar tanto strings quanto arrays ['role' => 'ADMIN', 'nome' => 'Administrador']
                $availableRolesRaw = $_SESSION['available_roles'] ?? [];
                $normalizedRoles = [];

                // Mapa simples de nomes amigáveis (fallback)
                $roleNameMap = [
                    'ADMIN'      => 'Admin',
                    'SECRETARIA' => 'Secretaria',
                    'INSTRUTOR'  => 'Instrutor',
                    'ALUNO'      => 'Aluno',
                ];

                if (is_array($availableRolesRaw)) {
                    foreach ($availableRolesRaw as $item) {
                        if (is_array($item)) {
                            $code = $item['role'] ?? null;
                            if ($code) {
                                $code = strtoupper($code);
                                $name = (string)($item['nome'] ?? ($roleNameMap[$code] ?? $code));
                                $normalizedRoles[] = ['role' => $code, 'nome' => $name];
                            }
                        } elseif (is_string($item) && $item !== '') {
                            $code = strtoupper($item);
                            $name = $roleNameMap[$code] ?? $code;
                            $normalizedRoles[] = ['role' => $code, 'nome' => $name];
                        }
                    }
                }

                $hasMultipleRoles = count($normalizedRoles) > 1;
                // Determinar label amigável para o papel atual
                $currentRoleCode = strtoupper($_SESSION['active_role'] ?? $_SESSION['current_role'] ?? 'ALUNO');
                $currentRoleLabel = $roleNameMap[$currentRoleCode] ?? $currentRoleCode;
                // Mobile: bloquear alternância — mostrar só INSTRUTOR (sem dropdown)
                $isMobile = function_exists('is_mobile_request') && is_mobile_request();
                $hasInstrutor = !empty(array_filter($normalizedRoles, fn($r) => ($r['role'] ?? '') === 'INSTRUTOR'));
                $showMobileInstrutorOnly = $isMobile && $hasMultipleRoles && $hasInstrutor;
                ?>
                <?php if ($showMobileInstrutorOnly): ?>
                <div class="topbar-role-selector topbar-role-selector-mobile-lock">
                    <span class="topbar-role-label-fixed">INSTRUTOR</span>
                </div>
                <?php elseif ($hasMultipleRoles && !$hideSelectorOnMobile): ?>
                <div class="topbar-role-selector">
                    <button class="topbar-role-selector-btn" id="roleSelectorBtn">
                        <span class="role-label-desktop">
                            Modo: <strong><?= htmlspecialchars($currentRoleLabel) ?></strong>
                        </span>
                        <span class="role-label-mobile">
                            <?= htmlspecialchars($currentRoleCode) ?>
                        </span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="topbar-role-dropdown" id="roleDropdown">
                        <?php foreach ($normalizedRoles as $role): ?>
                            <div class="topbar-role-dropdown-item <?= ($_SESSION['current_role'] ?? '') === $role['role'] ? 'active' : '' ?>" 
                                 data-role="<?= htmlspecialchars($role['role']) ?>">
                                <?= htmlspecialchars($role['nome']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Perfil -->
                <div class="topbar-profile" id="profileBtn">
                    <div class="topbar-profile-avatar">
                        <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <span class="d-none-mobile"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuário') ?></span>
                    <div class="topbar-profile-dropdown" id="profileDropdown" style="display: none;">
                        <!-- Botão Instalar Aplicativo (PWA) - Opcional, só aparece quando disponível -->
                        <div id="pwa-install-container" style="display: none; border-bottom: 1px solid #e0e0e0; margin-bottom: 5px;">
                            <button id="pwa-install-btn" class="topbar-profile-dropdown-item" style="width: 100%; text-align: left; background: none; border: none; cursor: pointer; padding: 12px 16px;">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 8px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                Instalar Aplicativo
                            </button>
                        </div>
                        <a href="<?= base_url('change-password') ?>" class="topbar-profile-dropdown-item">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 8px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                            Alterar Senha
                        </a>
                        <a href="<?= base_url('logout') ?>" class="topbar-profile-dropdown-item">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 8px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Sair
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main Layout -->
        <div class="app-main">
            <!-- Sidebar -->
            <aside class="sidebar" id="sidebar">
                <nav class="sidebar-menu">
                    <?php
                    $currentRole = $_SESSION['current_role'] ?? 'ALUNO';
                    $menuItems = getMenuItems($currentRole);
                    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                    
                    foreach ($menuItems as $item):
                        $isActive = strpos($currentPath, $item['path']) === 0;
                    ?>
                        <a href="<?= base_url($item['path']) ?>" class="sidebar-menu-item <?= $isActive ? 'active' : '' ?>">
                            <span class="sidebar-menu-icon">
                                <?= $item['icon'] ?? '' ?>
                            </span>
                            <span class="sidebar-menu-label"><?= htmlspecialchars($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </aside>
            
            <!-- Content Area -->
            <main class="content-area">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($_SESSION['success']) ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($_SESSION['error']) ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['warning'])): ?>
                    <div class="alert alert-warning">
                        <?= htmlspecialchars($_SESSION['warning']) ?>
                    </div>
                    <?php unset($_SESSION['warning']); ?>
                <?php endif; ?>
                
                <?php include $contentView ?? ''; ?>
            </main>
        </div>
    </div>
    
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- JavaScript -->
    <script src="<?= asset_url('js/app.js') ?>"></script>
    
    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?= asset_url($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Service Worker Registration (apenas em produção ou se arquivo existir) -->
    <script>
        (function() {
            'use strict';
            
            if ('serviceWorker' in navigator) {
                // FASE 5: Desabilitar SW em localhost durante debug
                const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
                const isProduction = <?= json_encode(($_ENV['APP_ENV'] ?? 'local') === 'production') ?>;
                
                // Corrigir path do SW usando pwa_asset_path (detecta automaticamente estrutura do servidor)
                // Tentar primeiro sw.js, se falhar, tentar sw.php como fallback
                let swPath = <?= json_encode(pwa_asset_path('sw.js')) ?>;
                let swPathFallback = <?= json_encode(pwa_asset_path('sw.php')) ?>;
                
                // Log do path para debug (sempre logar para identificar problema)
                console.log('[SW] Tentando registrar Service Worker em:', swPath);
                
                // Desabilitar SW em localhost durante debug
                if (isLocalhost) {
                    console.log('[SW] Service Worker desabilitado em localhost para debug');
                } else {
                    // Função para tentar registrar o SW
                    function tryRegisterSW(path, isFallback = false) {
                        return fetch(path, { method: 'HEAD' })
                            .then(function(response) {
                                if (response.ok) {
                                    window.addEventListener('load', function() {
                                        navigator.serviceWorker.register(path, { scope: '/' })
                                            .then(function(registration) {
                                                console.log('[SW] ✅ Service Worker registrado com sucesso:', registration.scope);
                                                
                                                // Aguardar um pouco e verificar se está controlando
                                                setTimeout(function() {
                                                    if (navigator.serviceWorker.controller) {
                                                        console.log('[SW] ✅ Service Worker está controlando a página');
                                                    } else {
                                                        console.log('[SW] ⚠️ Service Worker registrado mas não está controlando ainda');
                                                        console.log('[SW] Aguardando ativação...');
                                                    }
                                                }, 1000);
                                                
                                                if (isProduction) {
                                                    // Verificar atualizações periodicamente (apenas em produção)
                                                    setInterval(function() {
                                                        registration.update();
                                                    }, 60000); // Verificar a cada minuto
                                                }
                                            })
                                            .catch(function(error) {
                                                console.error('[SW] ❌ Erro ao registrar Service Worker:', error);
                                                if (!isFallback) {
                                                    console.log('[SW] Tentando fallback:', swPathFallback);
                                                    tryRegisterSW(swPathFallback, true);
                                                }
                                            });
                                    });
                                    return true; // Sucesso
                                } else {
                                    if (!isFallback) {
                                        console.warn('[SW] ⚠️ Service Worker não encontrado (', response.status, ') em:', path);
                                        console.log('[SW] Tentando fallback:', swPathFallback);
                                        return tryRegisterSW(swPathFallback, true);
                                    } else {
                                        console.error('[SW] ❌ Service Worker não encontrado (', response.status, ') em:', path);
                                        console.log('[SW] Verifique se o arquivo existe no servidor');
                                        return false;
                                    }
                                }
                            })
                            .catch(function(error) {
                                console.error('[SW] ❌ Erro ao verificar Service Worker:', error);
                                if (!isFallback) {
                                    console.log('[SW] Tentando fallback:', swPathFallback);
                                    return tryRegisterSW(swPathFallback, true);
                                }
                                return false;
                            });
                    }
                    
                    // Tentar registrar o SW
                    tryRegisterSW(swPath);
                }
            }
        })();
    </script>
</body>
</html>

<?php
function getMenuItems($role) {
    $menus = [
        'ADMIN' => [
            ['path' => '/dashboard', 'label' => 'Dashboard', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>'],
            ['path' => '/alunos', 'label' => 'Alunos', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>'],
            ['path' => '/agenda', 'label' => 'Agenda', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'],
            ['path' => '/instrutores', 'label' => 'Instrutores', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>'],
            ['path' => '/veiculos', 'label' => 'Veículos', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>'],
            ['path' => '/financeiro', 'label' => 'Financeiro', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
            ['path' => '/financeiro/contas-a-pagar', 'label' => 'Contas a Pagar', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>'],
            ['path' => '/servicos', 'label' => 'Serviços', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>'],
            ['path' => '/comunicados/novo', 'label' => 'Comunicados', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>'],
            ['path' => '/usuarios', 'label' => 'Usuários', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>'],
            ['path' => '/turmas-teoricas', 'label' => 'Turmas Teóricas', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>'],
            ['path' => '/configuracoes/disciplinas', 'label' => 'Disciplinas', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'],
            ['path' => '/configuracoes/cursos', 'label' => 'Cursos Teóricos', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>'],
            ['path' => '/configuracoes/cfc', 'label' => 'CFC', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'],
            ['path' => '/configuracoes/smtp', 'label' => 'Configurações', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'],
        ],
        'SECRETARIA' => [
            ['path' => '/dashboard', 'label' => 'Dashboard', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>'],
            ['path' => '/alunos', 'label' => 'Alunos', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>'],
            ['path' => '/agenda', 'label' => 'Agenda', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'],
            ['path' => '/turmas-teoricas', 'label' => 'Turmas Teóricas', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>'],
            ['path' => '/financeiro', 'label' => 'Financeiro', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
            ['path' => '/financeiro/contas-a-pagar', 'label' => 'Contas a Pagar', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>'],
            ['path' => '/usuarios', 'label' => 'Usuários', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>'],
            ['path' => '/comunicados/novo', 'label' => 'Comunicados', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>'],
        ],
        'INSTRUTOR' => [
            ['path' => '/dashboard', 'label' => 'Dashboard', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>'],
            ['path' => '/agenda', 'label' => 'Minha Agenda', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'],
        ],
        'ALUNO' => [
            ['path' => '/dashboard', 'label' => 'Meu Progresso', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>'],
            ['path' => '/agenda', 'label' => 'Minha Agenda', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'],
            ['path' => '/financeiro', 'label' => 'Financeiro', 'icon' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
        ],
    ];
    
    return $menus[$role] ?? $menus['ALUNO'];
}
?>
