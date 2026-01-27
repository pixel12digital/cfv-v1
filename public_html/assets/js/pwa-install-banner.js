/**
 * PWA Install Banner Component
 * Banner de instalação do aplicativo PWA para o dashboard do aluno
 * 
 * Funcionalidades:
 * - Detecta se o app já está instalado
 * - Mostra banner apenas quando não instalado
 * - Esconde automaticamente após instalação
 * - Permite dispensar o banner (com cooldown de 7 dias)
 */

(function() {
    'use strict';

    // Configurações
    const CONFIG = {
        STORAGE_KEY_INSTALLED: 'pwa_app_installed',
        STORAGE_KEY_DISMISSED: 'pwa_banner_dismissed',
        STORAGE_KEY_DISMISSED_AT: 'pwa_banner_dismissed_at',
        DISMISS_COOLDOWN_DAYS: 7, // Dias até mostrar novamente após dispensar
        BANNER_CONTAINER_ID: 'pwa-install-banner'
    };

    // Estado global
    let deferredPrompt = null;
    let isInstalled = false;
    let bannerElement = null;

    /**
     * Verificar se o app já está instalado
     */
    function checkIsInstalled() {
        // 1. Verificar display-mode standalone (Android/Desktop PWA)
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
            console.log('[PWA Banner] App detectado como instalado: display-mode standalone');
            return true;
        }

        // 2. Verificar navigator.standalone (iOS Safari)
        if (window.navigator.standalone === true) {
            console.log('[PWA Banner] App detectado como instalado: navigator.standalone (iOS)');
            return true;
        }

        // 3. Verificar localStorage (marcado manualmente após instalação)
        try {
            if (localStorage.getItem(CONFIG.STORAGE_KEY_INSTALLED) === 'true') {
                console.log('[PWA Banner] App detectado como instalado: localStorage');
                return true;
            }
        } catch (e) {
            // Ignorar erro de localStorage
        }

        return false;
    }

    /**
     * Verificar se o banner foi dispensado recentemente
     */
    function wasDismissedRecently() {
        try {
            const dismissedAt = localStorage.getItem(CONFIG.STORAGE_KEY_DISMISSED_AT);
            if (!dismissedAt) return false;

            const dismissedDate = new Date(parseInt(dismissedAt));
            const now = new Date();
            const daysSinceDismissed = (now - dismissedDate) / (1000 * 60 * 60 * 24);

            if (daysSinceDismissed < CONFIG.DISMISS_COOLDOWN_DAYS) {
                console.log('[PWA Banner] Banner dispensado há ' + Math.round(daysSinceDismissed) + ' dias (cooldown: ' + CONFIG.DISMISS_COOLDOWN_DAYS + ' dias)');
                return true;
            }
        } catch (e) {
            // Ignorar erro de localStorage
        }

        return false;
    }

    /**
     * Marcar app como instalado
     */
    function markAsInstalled() {
        isInstalled = true;
        try {
            localStorage.setItem(CONFIG.STORAGE_KEY_INSTALLED, 'true');
        } catch (e) {
            // Ignorar erro de localStorage
        }
        hideBanner();
    }

    /**
     * Dispensar o banner temporariamente
     */
    function dismissBanner() {
        try {
            localStorage.setItem(CONFIG.STORAGE_KEY_DISMISSED, 'true');
            localStorage.setItem(CONFIG.STORAGE_KEY_DISMISSED_AT, Date.now().toString());
        } catch (e) {
            // Ignorar erro de localStorage
        }
        hideBanner();
    }

    /**
     * Esconder o banner
     */
    function hideBanner() {
        if (bannerElement) {
            bannerElement.classList.add('pwa-install-banner--hidden');
            setTimeout(() => {
                if (bannerElement && bannerElement.parentNode) {
                    bannerElement.parentNode.removeChild(bannerElement);
                    bannerElement = null;
                }
            }, 300);
        }
    }

    /**
     * Criar o HTML do banner
     */
    function createBannerHTML() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        
        let installButtonHTML = '';
        let instructionsHTML = '';

        if (isIOS) {
            // iOS: instruções manuais
            instructionsHTML = `
                <div class="pwa-install-banner__ios-hint">
                    <span>Toque em</span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"></path>
                        <polyline points="16 6 12 2 8 6"></polyline>
                        <line x1="12" y1="2" x2="12" y2="15"></line>
                    </svg>
                    <span>e depois em "Adicionar à Tela de Início"</span>
                </div>
            `;
        } else {
            // Android/Desktop: botão de instalação
            installButtonHTML = `
                <button class="pwa-install-banner__btn pwa-install-banner__btn--primary" id="pwa-banner-install-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>Instalar App</span>
                </button>
            `;
        }

        return `
            <div class="pwa-install-banner__content">
                <div class="pwa-install-banner__icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                        <line x1="12" y1="18" x2="12.01" y2="18"></line>
                    </svg>
                </div>
                <div class="pwa-install-banner__text">
                    <h4 class="pwa-install-banner__title">Instale o App do Aluno</h4>
                    <p class="pwa-install-banner__subtitle">Acesse mais rápido, receba notificações e use offline.</p>
                    ${instructionsHTML}
                </div>
                <div class="pwa-install-banner__actions">
                    ${installButtonHTML}
                    <button class="pwa-install-banner__btn pwa-install-banner__btn--secondary" id="pwa-banner-dismiss-btn">
                        Agora não
                    </button>
                </div>
                <button class="pwa-install-banner__close" id="pwa-banner-close-btn" title="Fechar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        `;
    }

    /**
     * Criar e mostrar o banner
     */
    function showBanner() {
        // Verificar se já existe
        if (bannerElement || document.getElementById(CONFIG.BANNER_CONTAINER_ID)) {
            return;
        }

        // Criar elemento
        bannerElement = document.createElement('div');
        bannerElement.id = CONFIG.BANNER_CONTAINER_ID;
        bannerElement.className = 'pwa-install-banner';
        bannerElement.innerHTML = createBannerHTML();

        // Inserir no início do content-area ou após content-header
        const contentHeader = document.querySelector('.content-header');
        const contentArea = document.querySelector('.content-area');
        
        if (contentHeader && contentHeader.nextSibling) {
            contentHeader.parentNode.insertBefore(bannerElement, contentHeader.nextSibling);
        } else if (contentArea) {
            contentArea.insertBefore(bannerElement, contentArea.firstChild);
        } else {
            // Fallback: inserir no body
            document.body.insertBefore(bannerElement, document.body.firstChild);
        }

        // Animar entrada
        requestAnimationFrame(() => {
            bannerElement.classList.add('pwa-install-banner--visible');
        });

        // Configurar event listeners
        setupBannerEvents();
    }

    /**
     * Configurar eventos do banner
     */
    function setupBannerEvents() {
        // Botão de instalar (Android/Desktop)
        const installBtn = document.getElementById('pwa-banner-install-btn');
        if (installBtn) {
            installBtn.addEventListener('click', handleInstallClick);
        }

        // Botão de dispensar
        const dismissBtn = document.getElementById('pwa-banner-dismiss-btn');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', dismissBanner);
        }

        // Botão de fechar (X)
        const closeBtn = document.getElementById('pwa-banner-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', dismissBanner);
        }
    }

    /**
     * Lidar com clique no botão de instalar
     */
    async function handleInstallClick() {
        const installBtn = document.getElementById('pwa-banner-install-btn');
        
        if (!deferredPrompt) {
            console.log('[PWA Banner] Prompt de instalação não disponível');
            // Mostrar instruções alternativas
            showInstallInstructions();
            return;
        }

        // Desabilitar botão durante instalação
        if (installBtn) {
            installBtn.disabled = true;
            installBtn.innerHTML = `
                <svg class="pwa-install-banner__spinner" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                </svg>
                <span>Instalando...</span>
            `;
        }

        try {
            // Mostrar prompt de instalação
            deferredPrompt.prompt();
            
            // Aguardar resposta do usuário
            const { outcome } = await deferredPrompt.userChoice;
            
            console.log('[PWA Banner] Resultado da instalação:', outcome);
            
            if (outcome === 'accepted') {
                markAsInstalled();
                showSuccessMessage();
            } else {
                // Usuário cancelou - restaurar botão
                if (installBtn) {
                    installBtn.disabled = false;
                    installBtn.innerHTML = `
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        <span>Instalar App</span>
                    `;
                }
            }
        } catch (error) {
            console.error('[PWA Banner] Erro ao instalar:', error);
            // Restaurar botão
            if (installBtn) {
                installBtn.disabled = false;
                installBtn.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>Instalar App</span>
                `;
            }
        }
        
        // Limpar prompt usado
        deferredPrompt = null;
    }

    /**
     * Mostrar instruções de instalação (quando prompt não disponível)
     */
    function showInstallInstructions() {
        const isAndroid = /Android/i.test(navigator.userAgent);
        const isChrome = /Chrome/.test(navigator.userAgent);
        
        let instructions = '';
        
        if (isAndroid) {
            instructions = `
                <p><strong>Para instalar no Android:</strong></p>
                <ol>
                    <li>Toque no menu (⋮) no canto superior direito</li>
                    <li>Selecione "Instalar app" ou "Adicionar à tela inicial"</li>
                </ol>
            `;
        } else {
            instructions = `
                <p><strong>Para instalar no computador:</strong></p>
                <ol>
                    <li>Procure o ícone de instalação na barra de endereços</li>
                    <li>Ou clique no menu (⋮) → "Instalar app"</li>
                </ol>
            `;
        }
        
        // Criar modal de instruções
        const modal = document.createElement('div');
        modal.className = 'pwa-install-modal';
        modal.innerHTML = `
            <div class="pwa-install-modal__content">
                <h4>Como instalar o aplicativo</h4>
                ${instructions}
                <button class="pwa-install-banner__btn pwa-install-banner__btn--primary" onclick="this.parentElement.parentElement.remove()">
                    Entendi
                </button>
            </div>
        `;
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });
        document.body.appendChild(modal);
    }

    /**
     * Mostrar mensagem de sucesso após instalação
     */
    function showSuccessMessage() {
        const toast = document.createElement('div');
        toast.className = 'pwa-install-toast pwa-install-toast--success';
        toast.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <span>App instalado com sucesso!</span>
        `;
        document.body.appendChild(toast);
        
        // Animar entrada
        requestAnimationFrame(() => toast.classList.add('pwa-install-toast--visible'));
        
        // Remover após 4 segundos
        setTimeout(() => {
            toast.classList.remove('pwa-install-toast--visible');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    /**
     * Verificar assincronamente via getInstalledRelatedApps (Android)
     */
    async function checkInstalledRelatedApps() {
        if (!('getInstalledRelatedApps' in navigator)) {
            return false;
        }

        try {
            const apps = await navigator.getInstalledRelatedApps();
            if (apps && apps.length > 0) {
                console.log('[PWA Banner] App detectado via getInstalledRelatedApps:', apps);
                return true;
            }
        } catch (e) {
            console.warn('[PWA Banner] Erro ao verificar getInstalledRelatedApps:', e);
        }

        return false;
    }

    /**
     * Inicializar o banner
     */
    async function init() {
        console.log('[PWA Banner] Inicializando...');

        // 1. Verificar se já está instalado (síncrono)
        if (checkIsInstalled()) {
            console.log('[PWA Banner] App já instalado, não mostrar banner');
            isInstalled = true;
            return;
        }

        // 2. Verificar se foi dispensado recentemente
        if (wasDismissedRecently()) {
            console.log('[PWA Banner] Banner dispensado recentemente, não mostrar');
            return;
        }

        // 3. Verificar assincronamente (Android)
        const installedAsync = await checkInstalledRelatedApps();
        if (installedAsync) {
            console.log('[PWA Banner] App detectado como instalado (async), não mostrar banner');
            markAsInstalled();
            return;
        }

        // 4. Capturar beforeinstallprompt (pode já ter sido capturado)
        if (window.__deferredPrompt) {
            deferredPrompt = window.__deferredPrompt;
            console.log('[PWA Banner] Usando beforeinstallprompt já capturado');
        }

        // 5. Escutar beforeinstallprompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            window.__deferredPrompt = e;
            console.log('[PWA Banner] beforeinstallprompt capturado');
            
            // Se banner ainda não foi mostrado, mostrar agora
            if (!bannerElement && !isInstalled && !wasDismissedRecently()) {
                showBanner();
            }
        });

        // 6. Escutar appinstalled
        window.addEventListener('appinstalled', () => {
            console.log('[PWA Banner] Evento appinstalled recebido');
            markAsInstalled();
            showSuccessMessage();
        });

        // 7. Escutar mudança de display-mode (quando o usuário abre o PWA instalado)
        if (window.matchMedia) {
            const standaloneQuery = window.matchMedia('(display-mode: standalone)');
            standaloneQuery.addEventListener('change', (e) => {
                if (e.matches) {
                    console.log('[PWA Banner] Mudança para display-mode: standalone detectada');
                    markAsInstalled();
                }
            });
        }

        // 8. Mostrar banner após um pequeno delay (dar tempo para beforeinstallprompt)
        setTimeout(() => {
            if (!isInstalled && !wasDismissedRecently() && !bannerElement) {
                showBanner();
            }
        }, 1000);
    }

    // Inicializar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Exportar para uso global (debug)
    window.PWAInstallBanner = {
        show: showBanner,
        hide: hideBanner,
        dismiss: dismissBanner,
        isInstalled: () => isInstalled,
        getDeferredPrompt: () => deferredPrompt
    };

})();
