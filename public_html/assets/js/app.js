// App JavaScript

(function() {
    'use strict';
    
    // Função para inicializar submenu toggles
    function initSubmenuToggles() {
        const submenuToggles = document.querySelectorAll('[data-submenu-toggle]');
        
        submenuToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const wrapper = this.closest('.sidebar-menu-item-wrapper');
                const submenu = wrapper.querySelector('.sidebar-submenu');
                
                if (submenu) {
                    submenu.classList.toggle('open');
                    this.classList.toggle('active');
                }
            });
        });
    }
    
    // Função para inicializar o sidebar toggle
    function initSidebarToggle() {
        // Sidebar elements
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (!sidebarToggle || !sidebar) {
            return; // Elementos não encontrados
        }
        
        // Sidebar Toggle (Mobile) - Definir antes de usar
        function toggleMobileSidebar() {
            if (sidebar && sidebarOverlay && window.innerWidth <= 768) {
                sidebar.classList.toggle('open');
                sidebarOverlay.classList.toggle('show');
                // Adicionar/remover classe no body para estilizar o botão
                if (sidebar.classList.contains('open')) {
                    document.body.classList.add('sidebar-open');
                } else {
                    document.body.classList.remove('sidebar-open');
                }
            }
        }
        
        // Sidebar Toggle Handler - Único listener consolidado
        function handleSidebarToggle(e) {
            // Prevenir propagação para outros listeners que possam interferir
            e.stopPropagation();
            
            // Desktop: Toggle manual collapse (for pinning feature)
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            } else {
                // Mobile: Toggle drawer
                toggleMobileSidebar();
            }
        }
        
        // Adicionar listener - usar capture: true para garantir que seja executado antes de outros listeners
        sidebarToggle.addEventListener('click', handleSidebarToggle, true);
        
        // Restaurar estado do sidebar (desktop only, não interfere com hover)
        if (window.innerWidth > 768) {
            const wasCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (wasCollapsed) {
                sidebar.classList.add('collapsed');
            }
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                if (sidebar) {
                    sidebar.classList.remove('open');
                    sidebarOverlay.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                }
            });
        }
        
        // Fechar sidebar mobile ao clicar em item
        const sidebarItems = document.querySelectorAll('.sidebar-menu-item');
        sidebarItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768 && sidebar && sidebarOverlay) {
                    sidebar.classList.remove('open');
                    sidebarOverlay.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                }
            });
        });
    }
    
    // Inicializar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initSidebarToggle();
            initSubmenuToggles();
        });
    } else {
        // DOM já está pronto
        initSidebarToggle();
        initSubmenuToggles();
    }
    
    // Sidebar elements para uso em outros listeners (acessíveis globalmente dentro do IIFE)
    let sidebar, sidebarToggle, sidebarOverlay;
    
    // Atualizar referências após inicialização
    function updateSidebarReferences() {
        sidebar = document.getElementById('sidebar');
        sidebarToggle = document.getElementById('sidebarToggle');
        sidebarOverlay = document.getElementById('sidebarOverlay');
    }
    
    // Atualizar referências
    updateSidebarReferences();
    
    // Re-atualizar após DOMContentLoaded se necessário
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateSidebarReferences);
    }
    
    // Role Selector
    const roleSelectorBtn = document.getElementById('roleSelectorBtn');
    const roleDropdown = document.getElementById('roleDropdown');
    
    if (roleSelectorBtn && roleDropdown) {
        roleSelectorBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            roleDropdown.classList.toggle('show');
        });
        
        // Fechar ao clicar fora - garantir que não bloqueie sidebarToggle
        document.addEventListener('click', function(e) {
            // Não processar se o clique foi no sidebarToggle ou seus filhos
            if (sidebarToggle && (sidebarToggle === e.target || sidebarToggle.contains(e.target))) {
                return;
            }
            
            if (!roleSelectorBtn.contains(e.target) && !roleDropdown.contains(e.target)) {
                roleDropdown.classList.remove('show');
            }
        });
        
        // Trocar papel
        const roleItems = roleDropdown.querySelectorAll('.topbar-role-dropdown-item');
        roleItems.forEach(item => {
            item.addEventListener('click', function() {
                const role = this.getAttribute('data-role');
                
                // Fazer requisição para trocar papel
                // Usar path relativo que será resolvido pela tag <base>
                const baseElement = document.querySelector('base');
                const apiUrl = baseElement ? 
                    new URL('/api/switch-role', baseElement.href).href : 
                    '/api/switch-role';
                fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ role: role })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Erro ao trocar papel: ' + (data.message || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao trocar papel');
                });
            });
        });
    }
    
    // Profile Dropdown
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    
    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.style.display = profileDropdown.style.display === 'none' ? 'block' : 'none';
        });
        
        // Fechar ao clicar fora - garantir que não bloqueie sidebarToggle
        document.addEventListener('click', function(e) {
            // Não processar se o clique foi no sidebarToggle ou seus filhos
            if (sidebarToggle && (sidebarToggle === e.target || sidebarToggle.contains(e.target))) {
                return;
            }
            
            if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.style.display = 'none';
            }
        });
    }
    
    // Responsive
    window.addEventListener('resize', function() {
        updateSidebarReferences();
        if (sidebar && sidebarOverlay && window.innerWidth > 768) {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        }
    });
    
    // ============================================
    // PWA Installation Handler (Opcional - Sem Forçar)
    // ============================================
    
    let deferredPrompt = null;
    const installButton = document.getElementById('pwa-install-btn');
    const installButtonContainer = document.getElementById('pwa-install-container');
    
    // Detectar se já está instalado (standalone mode) - verificar no início
    function isAppInstalled() {
        return window.matchMedia('(display-mode: standalone)').matches || 
               window.navigator.standalone === true ||
               document.referrer.includes('android-app://');
    }
    
    // Detectar iOS
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    
    // Garantir que o botão está oculto por padrão
    if (installButtonContainer) {
        installButtonContainer.style.display = 'none';
    }
    
    // Não mostrar botão se já estiver instalado
    if (isAppInstalled()) {
        if (installButtonContainer) {
            installButtonContainer.style.display = 'none';
        }
    }
    
    // Função para verificar se deve mostrar o botão
    function shouldShowInstallButton() {
        // Não mostrar se já estiver instalado
        if (isAppInstalled()) {
            return false;
        }
        
        // iOS: mostrar sempre (mesmo sem beforeinstallprompt)
        if (isIOS) {
            return true;
        }
        
        // Android/Desktop: mostrar apenas se tiver deferredPrompt
        return !!deferredPrompt;
    }
    
    // Função para atualizar visibilidade do botão
    function updateInstallButtonVisibility() {
        if (installButtonContainer && shouldShowInstallButton()) {
            installButtonContainer.style.display = 'block';
        } else if (installButtonContainer) {
            installButtonContainer.style.display = 'none';
        }
    }
    
    // Interceptar beforeinstallprompt (Android/Desktop)
    // CRÍTICO: Se interceptamos (preventDefault), DEVEMOS sempre chamar prompt() quando usuário interagir
    window.addEventListener('beforeinstallprompt', function(e) {
        // Interceptar para controle manual do prompt
        e.preventDefault();
        
        // Guardar o evento para usar depois
        deferredPrompt = e;
        
        // Atualizar visibilidade do botão
        updateInstallButtonVisibility();
        
        // Log discreto (apenas em desenvolvimento)
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log('[PWA] beforeinstallprompt interceptado - botão manual disponível');
        }
    });
    
    // No iOS, mostrar botão após um pequeno delay (para garantir que não está instalado)
    if (isIOS && installButtonContainer) {
        // Verificar após DOM estar pronto
        setTimeout(function() {
            updateInstallButtonVisibility();
        }, 500);
    }
    
    // Handler do botão de instalação (alternativa manual)
    if (installButton) {
        installButton.addEventListener('click', async function(e) {
            e.preventDefault();
            
            // Android/Desktop: usar deferredPrompt
            if (deferredPrompt) {
                try {
                    // CRÍTICO: Chamar prompt() - obrigatório após preventDefault()
                    await deferredPrompt.prompt();
                    
                    // Aguardar escolha do usuário
                    const { outcome } = await deferredPrompt.userChoice;
                    
                    // Log discreto (apenas em desenvolvimento)
                    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                        if (outcome === 'accepted') {
                            console.log('[PWA] Usuário aceitou instalação');
                        } else {
                            console.log('[PWA] Usuário recusou instalação');
                        }
                    }
                    
                    // Limpar referência
                    deferredPrompt = null;
                    
                    // Esconder botão
                    if (installButtonContainer) {
                        installButtonContainer.style.display = 'none';
                    }
                } catch (error) {
                    console.error('[PWA] Erro ao chamar prompt():', error);
                    // Se houver erro, esconder botão e limpar
                    deferredPrompt = null;
                    if (installButtonContainer) {
                        installButtonContainer.style.display = 'none';
                    }
                }
            } else {
                // iOS: mostrar modal com instruções (somente ao clique)
                if (isIOS) {
                    showIOSInstallModal();
                }
                // Não mostrar alert para outros navegadores (deixar silencioso)
            }
        });
    }
    
    // Escutar evento de instalação concluída
    window.addEventListener('appinstalled', function() {
        // Log discreto (apenas em desenvolvimento)
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log('[PWA] Aplicativo instalado com sucesso');
        }
        
        // Limpar referência
        deferredPrompt = null;
        
        // Esconder botão definitivamente
        if (installButtonContainer) {
            installButtonContainer.style.display = 'none';
        }
    });
    
    // Log discreto de inicialização (apenas em desenvolvimento)
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('[PWA] install handler ready');
    }
    
    // Função para mostrar modal iOS
    function showIOSInstallModal() {
        // Criar modal se não existir
        let modal = document.getElementById('ios-install-modal');
        
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'ios-install-modal';
            modal.className = 'ios-install-modal';
            modal.innerHTML = `
                <div class="ios-install-modal-content">
                    <div class="ios-install-modal-header">
                        <h3>Instalar Aplicativo</h3>
                        <button class="ios-install-modal-close" onclick="this.closest('.ios-install-modal').style.display='none'">×</button>
                    </div>
                    <div class="ios-install-modal-body">
                        <p>Para instalar este aplicativo no seu iPhone/iPad:</p>
                        <ol>
                            <li>Toque no botão <strong>Compartilhar</strong> <span style="font-size: 20px;">📤</span> na barra inferior do Safari</li>
                            <li>Role para baixo e toque em <strong>"Adicionar à Tela de Início"</strong></li>
                            <li>Toque em <strong>"Adicionar"</strong> para confirmar</li>
                        </ol>
                    </div>
                    <div class="ios-install-modal-footer">
                        <button class="ios-install-modal-btn" onclick="document.getElementById('ios-install-modal').style.display='none'">Entendi</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Adicionar CSS inline (simples, sem depender de arquivo externo)
            if (!document.getElementById('ios-install-modal-style')) {
                const style = document.createElement('style');
                style.id = 'ios-install-modal-style';
                style.textContent = `
                    .ios-install-modal {
                        display: none;
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.5);
                        z-index: 10000;
                        align-items: center;
                        justify-content: center;
                    }
                    .ios-install-modal.show {
                        display: flex;
                    }
                    .ios-install-modal-content {
                        background: white;
                        border-radius: 8px;
                        max-width: 400px;
                        width: 90%;
                        max-height: 90vh;
                        overflow-y: auto;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                    }
                    .ios-install-modal-header {
                        padding: 20px;
                        border-bottom: 1px solid #e0e0e0;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    .ios-install-modal-header h3 {
                        margin: 0;
                        color: #023A8D;
                    }
                    .ios-install-modal-close {
                        background: none;
                        border: none;
                        font-size: 24px;
                        cursor: pointer;
                        color: #666;
                    }
                    .ios-install-modal-body {
                        padding: 20px;
                    }
                    .ios-install-modal-body ol {
                        margin: 15px 0;
                        padding-left: 20px;
                    }
                    .ios-install-modal-body li {
                        margin: 10px 0;
                    }
                    .ios-install-modal-footer {
                        padding: 20px;
                        border-top: 1px solid #e0e0e0;
                        text-align: right;
                    }
                    .ios-install-modal-btn {
                        background: #023A8D;
                        color: white;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 4px;
                        cursor: pointer;
                    }
                    @media (prefers-color-scheme: dark) {
                        .ios-install-modal-content {
                            background: #1e293b;
                            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
                        }
                        .ios-install-modal-header {
                            border-bottom-color: #334155;
                        }
                        .ios-install-modal-header h3 {
                            color: #93c5fd;
                        }
                        .ios-install-modal-close {
                            color: #94a3b8;
                        }
                        .ios-install-modal-body,
                        .ios-install-modal-body li {
                            color: #e2e8f0;
                        }
                        .ios-install-modal-footer {
                            border-top-color: #334155;
                        }
                        .ios-install-modal-btn {
                            background: #3b82f6;
                            color: white;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        // Mostrar modal
        modal.style.display = 'flex';
        modal.classList.add('show');
        
        // Fechar ao clicar fora
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
    
    // Atualizar visibilidade do botão (verificação final)
    updateInstallButtonVisibility();
})();
