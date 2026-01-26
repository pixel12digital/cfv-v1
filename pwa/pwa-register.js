/**
 * PWA Registration Script - Sistema CFC Bom Conselho
 * Registra Service Worker e gerencia atualizações
 */

class PWAManager {
    constructor() {
        this.registration = null;
        this.updateAvailable = false;
        this.deferredPrompt = null;
        
        // Remover qualquer banner existente imediatamente
        this.hideInstallBanner();
        
        this.init();
    }
    
    async init() {
        console.log('[PWA] Inicializando PWA Manager...');
        
        // Remover qualquer banner existente ao inicializar
        this.hideInstallBanner();
        
        // Verificar suporte a Service Worker
        if (!('serviceWorker' in navigator)) {
            console.warn('[PWA] Service Worker não suportado');
            return;
        }
        
        // Verificar se já existe um controller
        if (navigator.serviceWorker.controller) {
            console.log('[PWA] ✅ Service Worker já está controlando:', navigator.serviceWorker.controller.scriptURL);
        } else {
            console.log('[PWA] Service Worker ainda não está controlando. Registrando...');
        }
        
        // Registrar Service Worker
        await this.registerServiceWorker();
        
        // Configurar eventos de instalação (mas não mostrar banner)
        this.setupInstallEvents();
        
        // Configurar eventos de atualização
        this.setupUpdateEvents();
        
        // Configurar notificações
        this.setupNotifications();
        
        // Verificar se já está instalado
        this.checkInstallationStatus();
        
        console.log('[PWA] PWA Manager inicializado com sucesso');
        
        // Verificar controller após um delay
        setTimeout(() => {
            this.checkControllerStatus();
            // Garantir que não há banners após delay
            this.hideInstallBanner();
        }, 2000);
    }
    
    /**
     * Verificar status do controller e fornecer feedback
     */
    checkControllerStatus() {
        if (navigator.serviceWorker.controller) {
            console.log('[PWA] ✅ Service Worker está controlando a página');
            console.log('[PWA] Controller URL:', navigator.serviceWorker.controller.scriptURL);
            console.log('[PWA] Controller State:', navigator.serviceWorker.controller.state);
        } else {
            console.warn('[PWA] ⚠️ Service Worker NÃO está controlando a página');
            console.warn('[PWA] Isso é necessário para instalação PWA');
            console.warn('[PWA] Solução: Recarregue a página (F5 ou Ctrl+R)');
            
            // Verificar registros
            navigator.serviceWorker.getRegistrations().then(regs => {
                if (regs.length > 0) {
                    regs.forEach(reg => {
                        console.log('[PWA] SW registrado:', {
                            scope: reg.scope,
                            active: reg.active?.state,
                            installing: reg.installing?.state,
                            waiting: reg.waiting?.state
                        });
                    });
                } else {
                    console.error('[PWA] Nenhum Service Worker registrado!');
                }
            });
        }
    }
    
    async registerServiceWorker() {
        try {
            console.log('[PWA] ===== INICIANDO REGISTRO DO SERVICE WORKER =====');
            console.log('[PWA] URL atual:', window.location.href);
            console.log('[PWA] Pathname:', window.location.pathname);
            
            // Verificar se já existe um controller
            if (navigator.serviceWorker.controller) {
                console.log('[PWA] ✅ Service Worker já está controlando:', navigator.serviceWorker.controller.scriptURL);
                console.log('[PWA] Controller state:', navigator.serviceWorker.controller.state);
                return;
            }
            
            // Verificar se há registros existentes
            const existingRegs = await navigator.serviceWorker.getRegistrations();
            if (existingRegs.length > 0) {
                console.log('[PWA] ⚠️ Encontrados', existingRegs.length, 'Service Worker(s) registrado(s):');
                existingRegs.forEach((reg, idx) => {
                    console.log(`[PWA]   SW ${idx + 1}:`, {
                        scope: reg.scope,
                        active: reg.active?.state,
                        installing: reg.installing?.state,
                        waiting: reg.waiting?.state,
                        scriptURL: reg.active?.scriptURL || reg.installing?.scriptURL || reg.waiting?.scriptURL
                    });
                });
            }
            
            // Usar SW do root para garantir scope "/"
            const swPath = '/sw.js';
            console.log('[PWA] Tentando registrar Service Worker em:', swPath);
            
            this.registration = await navigator.serviceWorker.register(swPath, {
                scope: '/'
            });
            
            console.log('[PWA] ✅ Service Worker registrado com sucesso!');
            console.log('[PWA] Registration object:', this.registration);
            console.log('[PWA] SW State:', this.registration.active?.state || this.registration.installing?.state || this.registration.waiting?.state);
            console.log('[PWA] SW Scope:', this.registration.scope);
            
            // Se já existe um SW ativo, verificar se está controlando
            if (this.registration.active) {
                console.log('[PWA] SW ativo encontrado:', this.registration.active.scriptURL);
            }
            
            // Se está instalando, aguardar ativação
            if (this.registration.installing) {
                console.log('[PWA] SW instalando, aguardando ativação...');
                this.registration.installing.addEventListener('statechange', () => {
                    console.log('[PWA] SW state mudou para:', this.registration.installing.state);
                    if (this.registration.installing.state === 'activated') {
                        console.log('[PWA] SW ativado! Aguardando clients.claim()...');
                        // Aguardar um pouco e verificar se está controlando
                        setTimeout(() => {
                            if (!navigator.serviceWorker.controller) {
                                console.log('[PWA] SW ativado mas não está controlando. Recarregando página...');
                                window.location.reload();
                            }
                        }, 1000);
                    }
                });
            }
            
            // Se está waiting, pode precisar de skipWaiting
            if (this.registration.waiting) {
                console.log('[PWA] SW waiting encontrado. Pode precisar de skipWaiting.');
                // Se há um SW waiting, pode ser que precise recarregar
                if (!navigator.serviceWorker.controller) {
                    console.log('[PWA] SW waiting e sem controller. Recarregando para ativar...');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            }
            
            // Se já está ativo mas não está controlando, recarregar
            if (this.registration.active && !navigator.serviceWorker.controller) {
                console.log('[PWA] SW ativo mas não está controlando. Recarregando página...');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
            
            // Verificar atualizações
            this.registration.addEventListener('updatefound', () => {
                console.log('[PWA] Nova versão do Service Worker encontrada');
                this.handleUpdateFound();
            });
            
            // Escutar mensagens do SW
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data && event.data.type === 'SW_ACTIVATED') {
                    console.log('[PWA] ✅ Service Worker ativado! Versão:', event.data.version);
                    // Recarregar para o SW controlar (apenas se ainda não estiver controlando)
                    if (!navigator.serviceWorker.controller) {
                        console.log('[PWA] Recarregando página para SW controlar...');
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    }
                }
            });
            
        } catch (error) {
            console.error('[PWA] ❌ ERRO ao registrar Service Worker:', error);
            console.error('[PWA] Erro completo:', {
                message: error.message,
                stack: error.stack,
                name: error.name
            });
            
            // Tentar diagnosticar o problema
            if (error.message.includes('Failed to register')) {
                console.error('[PWA] Diagnóstico: Falha ao registrar - verifique se /sw.js existe e está acessível');
            } else if (error.message.includes('network')) {
                console.error('[PWA] Diagnóstico: Erro de rede - verifique conexão e servidor');
            } else {
                console.error('[PWA] Diagnóstico: Erro desconhecido - verifique console para detalhes');
            }
        }
    }
    
    setupInstallEvents() {
        // Evento beforeinstallprompt - para instalação no Android
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('[PWA] beforeinstallprompt disparado');
            e.preventDefault();
            this.deferredPrompt = e;
            
            // Desabilitado: banner removido para evitar confusão
            // O footer do login já tem o botão de instalação
            // Só mostrar banner se ainda deve mostrar baseado nas escolhas do usuário
            // if (this.shouldShowInstallPrompt()) {
            //     this.showInstallBanner();
            // } else {
            //     console.log('[PWA] beforeinstallprompt ignorado - usuário já escolheu anteriormente');
            // }
        });
        
        // Evento appinstalled - quando o app é instalado
        window.addEventListener('appinstalled', () => {
            console.log('[PWA] App instalado com sucesso');
            this.hideInstallBanner();
            this.showInstallationSuccess();
        });
    }
    
    setupUpdateEvents() {
        // Verificar periodicamente por atualizações (a cada 1 hora)
        setInterval(() => {
            if (this.registration) {
                this.registration.update().catch(err => {
                    console.warn('[PWA] Erro ao verificar atualizações:', err);
                });
            }
        }, 3600000); // 1 hora
        
        // Verificar se há atualização disponível imediatamente
        if (this.registration && this.registration.waiting) {
            this.updateAvailable = true;
            // Aguardar um pouco antes de mostrar (para não atrapalhar o carregamento)
            setTimeout(() => {
                this.showUpdateBanner();
            }, 2000);
        }
        
        // Escutar mensagens do Service Worker
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'UPDATE_AVAILABLE') {
                this.updateAvailable = true;
                setTimeout(() => {
                    this.showUpdateBanner();
                }, 2000);
            }
        });
    }
    
    handleUpdateFound() {
        const newWorker = this.registration.installing;
        
        if (!newWorker) return;
        
        newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed') {
                if (navigator.serviceWorker.controller) {
                    // Nova versão disponível
                    this.updateAvailable = true;
                    console.log('[PWA] Nova versão instalada e pronta para atualização');
                    // Aguardar um pouco antes de mostrar
                    setTimeout(() => {
                        this.showUpdateBanner();
                    }, 2000);
                } else {
                    // Primeira instalação
                    console.log('[PWA] Service Worker instalado pela primeira vez');
                }
            }
        });
    }
    
    showInstallBanner() {
        // DESABILITADO COMPLETAMENTE: Banner nunca será exibido
        // O footer do login (install-footer.js) já tem o botão de instalação
        // Não criar banner para manter apenas uma opção de instalação
        console.log('[PWA] showInstallBanner() chamado mas DESABILITADO - use o footer do login');
        
        // Garantir que nenhum banner exista
        this.hideInstallBanner();
        
        // Retornar imediatamente sem criar nada
        return;
    }
    
    showUpdateBanner() {
        // Verificar se já existe um banner
        if (document.getElementById('pwa-update-banner')) {
            console.log('[PWA] Banner de atualização já existe');
            return;
        }
        
        console.log('[PWA] Mostrando banner de atualização');
        
        // Adicionar estilos se não existirem
        this.addUpdateBannerStyles();
        
        // Criar banner
        const banner = document.createElement('div');
        banner.id = 'pwa-update-banner';
        banner.className = 'pwa-update-banner';
        banner.innerHTML = `
            <div class="pwa-update-banner-content">
                <div class="pwa-update-banner-icon">🔄</div>
                <div class="pwa-update-banner-text">
                    <h4>Nova Versão Disponível</h4>
                    <p>Atualizações e melhorias foram aplicadas. Atualize agora para ter a melhor experiência.</p>
                </div>
                <div class="pwa-update-banner-actions">
                    <button class="pwa-update-btn pwa-update-btn-primary" onclick="window.pwaManager.updateApp()">
                        Atualizar Agora
                    </button>
                    <button class="pwa-update-btn pwa-update-btn-secondary" onclick="window.pwaManager.hideUpdateBanner()">
                        Depois
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(banner);
        
        // Animação de entrada
        setTimeout(() => {
            banner.classList.add('pwa-update-banner-show');
        }, 100);
    }
    
    hideUpdateBanner() {
        const banner = document.getElementById('pwa-update-banner');
        if (banner) {
            banner.classList.remove('pwa-update-banner-show');
            setTimeout(() => {
                banner.remove();
            }, 300);
        }
    }
    
    addUpdateBannerStyles() {
        if (document.getElementById('pwa-update-banner-styles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'pwa-update-banner-styles';
        styles.textContent = `
            .pwa-update-banner {
                position: fixed;
                bottom: 20px;
                right: 20px;
                max-width: 420px;
                background: var(--theme-card-bg, #ffffff);
                color: var(--theme-text, #1e293b);
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
                z-index: 10000;
                opacity: 0;
                transform: translateY(20px);
                transition: opacity 0.3s ease, transform 0.3s ease;
                border: 1px solid var(--theme-border, #e2e8f0);
            }
            
            .pwa-update-banner-show {
                opacity: 1;
                transform: translateY(0);
            }
            
            .pwa-update-banner-content {
                display: flex;
                flex-direction: column;
                padding: 20px;
                gap: 16px;
            }
            
            .pwa-update-banner-icon {
                font-size: 32px;
                text-align: center;
                line-height: 1;
            }
            
            .pwa-update-banner-text {
                text-align: center;
            }
            
            .pwa-update-banner-text h4 {
                margin: 0 0 8px 0;
                font-size: 18px;
                font-weight: 600;
                color: var(--theme-text, #1e293b);
            }
            
            .pwa-update-banner-text p {
                margin: 0;
                font-size: 14px;
                color: var(--theme-text-muted, #64748b);
                line-height: 1.5;
            }
            
            .pwa-update-banner-actions {
                display: flex;
                gap: 12px;
                justify-content: center;
            }
            
            .pwa-update-btn {
                padding: 10px 20px;
                border: none;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                flex: 1;
            }
            
            .pwa-update-btn-primary {
                background: var(--theme-primary, #10b981);
                color: white;
            }
            
            .pwa-update-btn-primary:hover {
                background: var(--theme-primary-hover, #059669);
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            }
            
            .pwa-update-btn-secondary {
                background: var(--theme-bg-secondary, #f8fafc);
                color: var(--theme-text, #1e293b);
                border: 1px solid var(--theme-border, #e2e8f0);
            }
            
            .pwa-update-btn-secondary:hover {
                background: var(--theme-bg-tertiary, #f1f5f9);
            }
            
            @media (prefers-color-scheme: dark) {
                .pwa-update-banner {
                    background: var(--theme-card-bg, #1e293b);
                    color: var(--theme-text, #f1f5f9);
                    border-color: var(--theme-border, #334155);
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
                }
                
                .pwa-update-banner-text h4 {
                    color: var(--theme-text, #f1f5f9);
                }
                
                .pwa-update-banner-text p {
                    color: var(--theme-text-muted, #94a3b8);
                }
            }
            
            @media (max-width: 480px) {
                .pwa-update-banner {
                    bottom: 10px;
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }
                
                .pwa-update-banner-actions {
                    flex-direction: column;
                }
            }
        `;
        
        document.head.appendChild(styles);
    }
    
    addBannerStyles() {
        if (document.getElementById('pwa-banner-styles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'pwa-banner-styles';
        styles.textContent = `
            .pwa-banner {
                position: fixed;
                bottom: 20px;
                left: 20px;
                right: 20px;
                max-width: 400px;
                background: #2c3e50;
                color: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                z-index: 10000;
                animation: slideUp 0.3s ease;
            }
            
            .pwa-banner-content {
                display: flex;
                align-items: center;
                padding: 16px;
                gap: 12px;
            }
            
            .pwa-banner-icon {
                font-size: 24px;
                color: #3498db;
                flex-shrink: 0;
            }
            
            .pwa-banner-text {
                flex: 1;
            }
            
            .pwa-banner-text h4 {
                margin: 0 0 4px 0;
                font-size: 16px;
                font-weight: 600;
            }
            
            .pwa-banner-text p {
                margin: 0;
                font-size: 14px;
                opacity: 0.9;
                line-height: 1.4;
            }
            
            .pwa-banner-actions {
                display: flex;
                gap: 8px;
                flex-shrink: 0;
            }
            
            .pwa-banner-btn {
                padding: 8px 16px;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .pwa-banner-btn-primary {
                background: #3498db;
                color: white;
            }
            
            .pwa-banner-btn-primary:hover {
                background: #2980b9;
            }
            
            .pwa-banner-btn-secondary {
                background: rgba(255, 255, 255, 0.2);
                color: white;
                padding: 8px;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .pwa-banner-btn-secondary:hover {
                background: rgba(255, 255, 255, 0.3);
            }
            
            @keyframes slideUp {
                from {
                    transform: translateY(100%);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            @media (max-width: 768px) {
                .pwa-banner {
                    left: 10px;
                    right: 10px;
                    bottom: 10px;
                }
                
                .pwa-banner-content {
                    padding: 12px;
                }
                
                .pwa-banner-text h4 {
                    font-size: 14px;
                }
                
                .pwa-banner-text p {
                    font-size: 12px;
                }
            }
        `;
        
        document.head.appendChild(styles);
    }
    
    async installApp() {
        if (!this.deferredPrompt) {
            console.warn('[PWA] Deferred prompt não disponível');
            return;
        }
        
        try {
            console.log('[PWA] Iniciando instalação...');
            
            // Mostrar prompt de instalação
            this.deferredPrompt.prompt();
            
            // Aguardar resposta do usuário
            const { outcome } = await this.deferredPrompt.userChoice;
            
            console.log('[PWA] Resultado da instalação:', outcome);
            
            if (outcome === 'accepted') {
                console.log('[PWA] Usuário aceitou a instalação');
            } else {
                console.log('[PWA] Usuário rejeitou a instalação');
            }
            
            // Limpar deferred prompt
            this.deferredPrompt = null;
            
        } catch (error) {
            console.error('[PWA] Erro durante instalação:', error);
        }
    }
    
    async updateApp() {
        if (!this.registration) {
            console.warn('[PWA] Service Worker não registrado');
            return;
        }
        
        try {
            console.log('[PWA] Aplicando atualização...');
            
            // Esconder banner
            this.hideUpdateBanner();
            
            // Se há um SW waiting, enviar mensagem para skipWaiting
            if (this.registration.waiting) {
                console.log('[PWA] Enviando SKIP_WAITING para SW waiting...');
                this.registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                
                // Aguardar um pouco antes de recarregar
                setTimeout(() => {
                    console.log('[PWA] Recarregando página para aplicar atualização...');
                    window.location.reload();
                }, 500);
            } else {
                // Forçar verificação de atualização
                console.log('[PWA] Forçando verificação de atualização...');
                await this.registration.update();
                
                // Se após update ainda não há waiting, recarregar mesmo assim
                if (!this.registration.waiting) {
                    console.log('[PWA] Recarregando página para garantir atualização...');
                    window.location.reload();
                }
            }
            
        } catch (error) {
            console.error('[PWA] Erro durante atualização:', error);
            // Mesmo com erro, tentar recarregar
            window.location.reload();
        }
    }
    
    hideInstallBanner() {
        // Remover TODOS os banners PWA que possam existir
        const banners = document.querySelectorAll('.pwa-banner, .pwa-banner-install, .pwa-banner-install');
        banners.forEach(banner => {
            console.log('[PWA] Removendo banner encontrado:', banner);
            banner.remove();
        });
        
        // Também remover estilos do banner se existirem
        const bannerStyles = document.getElementById('pwa-banner-styles');
        if (bannerStyles) {
            bannerStyles.remove();
        }
    }
    
    showInstallationSuccess() {
        // Mostrar notificação de sucesso
        const notification = document.createElement('div');
        notification.className = 'pwa-success-notification';
        notification.innerHTML = `
            <div class="pwa-success-content">
                <i class="fas fa-check-circle"></i>
                <span>App instalado com sucesso!</span>
            </div>
        `;
        
        // Adicionar estilos
        this.addSuccessNotificationStyles();
        
        document.body.appendChild(notification);
        
        // Remover após 3 segundos
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    
    addSuccessNotificationStyles() {
        if (document.getElementById('pwa-success-styles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'pwa-success-styles';
        styles.textContent = `
            .pwa-success-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #27ae60;
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                z-index: 10001;
                animation: slideInRight 0.3s ease;
            }
            
            .pwa-success-content {
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 500;
            }
            
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        
        document.head.appendChild(styles);
    }
    
    checkInstallationStatus() {
        // Verificar se está rodando como PWA
        if (window.matchMedia('(display-mode: standalone)').matches) {
            console.log('[PWA] App rodando em modo standalone');
        }
        
        // Verificar se está em iOS
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            console.log('[PWA] Dispositivo iOS detectado');
        }
    }
    
    // Método público para verificar se há atualização
    hasUpdate() {
        return this.updateAvailable;
    }
    
    // Método público para forçar verificação de atualização
    async checkForUpdates() {
        if (this.registration) {
            await this.registration.update();
        }
    }
    
    /**
     * Configurar sistema de notificações
     */
    setupNotifications() {
        console.log('[PWA] Configurando notificações...');
        
        // Verificar suporte a notificações
        if (!('Notification' in window)) {
            console.warn('[PWA] Notificações não suportadas');
            return;
        }
        
        // Verificar permissão atual
        if (Notification.permission === 'granted') {
            console.log('[PWA] Notificações já autorizadas');
            this.maybeShowInstallPrompt();
        } else if (Notification.permission === 'default') {
            console.log('[PWA] Solicitando permissão para notificações...');
            this.requestNotificationPermission();
        } else {
            console.log('[PWA] Notificações negadas pelo usuário');
        }
    }
    
    /**
     * Solicitar permissão para notificações
     */
    async requestNotificationPermission() {
        try {
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                console.log('[PWA] Permissão para notificações concedida');
                this.maybeShowInstallPrompt();
                
                // Enviar notificação de boas-vindas
                this.sendWelcomeNotification();
            } else {
                console.log('[PWA] Permissão para notificações negada');
            }
        } catch (error) {
            console.error('[PWA] Erro ao solicitar permissão:', error);
        }
    }
    
    /**
     * Enviar notificação de boas-vindas
     */
    sendWelcomeNotification() {
        if (Notification.permission === 'granted') {
            const notification = new Notification('CFC Bom Conselho', {
                body: 'Bem-vindo ao sistema administrativo! Você pode instalar este app para acesso rápido.',
                icon: '/pwa/icons/icon-192.png',
                badge: '/pwa/icons/icon-72.png',
                tag: 'cfc-welcome',
                requireInteraction: false,
                silent: false
            });
            
            // Fechar automaticamente após 5 segundos
            setTimeout(() => {
                notification.close();
            }, 5000);
        }
    }
    
    /**
     * Controlar escolha do usuário sobre instalação
     */
    handleInstallChoice(choice) {
        const banner = document.querySelector('.pwa-banner');
        if (banner) {
            banner.remove();
        }
        
        const now = new Date().getTime();
        
        if (choice === 'accept') {
            // Usuário aceitou instalar - executar instalação
            this.installApp();
            // Salvar que foi aceito (não mostrar mais por 90 dias)
            localStorage.setItem('pwa-install-user-choice', 'accepted');
            localStorage.setItem('pwa-install-choice-timestamp', now + (90 * 24 * 60 * 60 * 1000)); // 90 dias
        } else if (choice === 'dismiss') {
            // Usuário dismissou - não mostrar por 30 dias
            localStorage.setItem('pwa-install-user-choice', 'dismissed');
            localStorage.setItem('pwa-install-choice-timestamp', now + (30 * 24 * 60 * 60 * 1000)); // 30 dias
            console.log('[PWA] Usuário dismissou o prompt de instalação por 30 dias');
        }
    }

    /**
     * Verificar se deve mostrar prompt de instalação
     */
    shouldShowInstallPrompt() {
        const userChoice = localStorage.getItem('pwa-install-user-choice');
        const choiceTimestamp = localStorage.getItem('pwa-install-choice-timestamp');
        const now = new Date().getTime();
        
        // Se já foi escolhido e ainda não expirou, não mostrar
        if (userChoice && choiceTimestamp) {
            if (now < parseInt(choiceTimestamp)) {
                return false; // Ainda dentro do período de repouso
            }
        }
        
        // Se foi aceito e não expirou, nunca mais mostrar
        if (userChoice === 'accepted' && now < parseInt(choiceTimestamp)) {
            return false;
        }
        
        // Limpar dados expirados
        if (now >= parseInt(choiceTimestamp)) {
            localStorage.removeItem('pwa-install-user-choice');
            localStorage.removeItem('pwa-install-choice-timestamp');
        }
        
        return true; // Pode mostrar
    }

    /**
     * Verificar se deve mostrar prompt e eventualmente mostrar
     */
    maybeShowInstallPrompt() {
        // Desabilitado: banner removido para evitar confusão
        // O footer do login já tem o botão de instalação
        // Sempre verificar se deve mostrar antes de tentar mostrar
        // if (this.shouldShowInstallPrompt()) {
        //     console.log('[PWA] Condições atendidas, mostrando prompt de instalação');
        //     this.showInstallPrompt();
        // } else {
        //     console.log('[PWA] Condições não atendidas para mostrar prompt de instalação');
        // }
    }

    /**
     * Mostrar prompt de instalação (método interno - use maybeShowInstallPrompt)
     */
    showInstallPrompt() {
        // Verificar se já foi mostrado hoje (limitação adicional)
        const lastShown = localStorage.getItem('pwa-install-prompt-last-shown');
        const today = new Date().toDateString();
        
        if (lastShown === today) {
            console.log('[PWA] Prompt já foi mostrado hoje - pule');
            return;
        }
        
        // Verificar se está instalado como PWA (verificação final)
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
            console.log('[PWA] App já está instalado como PWA - pule');
            return;
        }
        
        // Desabilitado: banner removido para evitar confusão
        // O footer do login já tem o botão de instalação
        // Mostrar banner de instalação
        // console.log('[PWA] Mostrando prompt de instalação');
        // this.showInstallBanner();
        
        // Salvar que foi mostrado hoje
        // localStorage.setItem('pwa-install-prompt-last-shown', today);
    }
}

// Função utilitária para reset das escolhas PWA (para debugging/admin)
window.resetPWAChoices = function() {
    localStorage.removeItem('pwa-install-user-choice');
    localStorage.removeItem('pwa-install-choice-timestamp');
    localStorage.removeItem('pwa-install-prompt-last-shown');
    console.log('[PWA] Escolhas de instalação resetadas');
};

// Inicializar PWA Manager quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    console.log('[PWA] ===== DOMContentLoaded - Inicializando PWA Manager =====');
    
    // Verificar se estamos na área admin, instrutor ou login
    const path = window.location.pathname;
    const isAdminArea = path.includes('/admin/');
    const isInstrutorArea = path.includes('/instrutor/');
    const isLoginPage = path.includes('/login.php') || path === '/';
    
    console.log('[PWA] Path detectado:', path);
    console.log('[PWA] isAdminArea:', isAdminArea);
    console.log('[PWA] isInstrutorArea:', isInstrutorArea);
    console.log('[PWA] isLoginPage:', isLoginPage);
    
    // Inicializar em todas as áreas (incluindo login para notificações de atualização)
    // O sistema de instalação do login é gerenciado pelo install-footer.js separadamente
    if (isAdminArea || isInstrutorArea || isLoginPage) {
        console.log('[PWA] ✅ Área válida detectada - inicializando PWAManager');
        
        // Se já existe, não criar novamente
        if (!window.pwaManager) {
            console.log('[PWA] Criando nova instância de PWAManager...');
            window.pwaManager = new PWAManager();
        } else {
            console.log('[PWA] PWAManager já existe, reutilizando instância');
        }
        
        // Debug: mostrar estado das escolhas do usuário
        if (localStorage.getItem('pwa-install-user-choice')) {
            const choice = localStorage.getItem('pwa-install-user-choice');
            const timestamp = localStorage.getItem('pwa-install-choice-timestamp');
            const expiry = new Date(parseInt(timestamp));
            console.log(`[PWA] Estado anterior: ${choice}, expira em: ${expiry.toLocaleString()}`);
        }
    } else {
        console.log('[PWA] ⚠️ Área não reconhecida - PWAManager não será inicializado');
    }
});

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.PWAManager = PWAManager;
}
