/**
 * JavaScript Mobile-First para Sistema de Agendamento CFC
 * Funcionalidades comuns e utilitários
 */

// Utilitários globais
window.CFCMobile = {
    // Configurações
    config: {
        apiBase: '/admin/api',
        toastDuration: 3000,
        loadingDelay: 500
    },

    // Estado da aplicação
    state: {
        loading: false,
        online: navigator.onLine,
        notifications: []
    },

    // Inicialização
    init() {
        this.setupEventListeners();
        this.setupOfflineHandling();
        this.setupNotifications();
        this.setupTouchHandlers();
    },

    // Configurar event listeners globais
    setupEventListeners() {
        // Detectar mudanças de conectividade
        window.addEventListener('online', () => {
            this.state.online = true;
            this.showToast('Conexão restaurada', 'success');
        });

        window.addEventListener('offline', () => {
            this.state.online = false;
            this.showToast('Sem conexão com a internet', 'warning');
        });

        // Interceptar cliques em links para loading
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href]');
            if (link && !link.href.startsWith('http') && !link.href.startsWith('mailto:')) {
                this.showLoading('Carregando...');
            }
        });

        // Interceptar submits de formulários
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.classList.contains('form-mobile')) {
                e.preventDefault();
                this.handleFormSubmit(form);
            }
        });
    },

    // Configurar tratamento offline
    setupOfflineHandling() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data.type === 'CACHE_UPDATED') {
                    this.showToast('Aplicação atualizada', 'info');
                }
            });
        }
    },

    // Configurar notificações
    setupNotifications() {
        // Solicitar permissão para notificações push
        if ('Notification' in window && Notification.permission === 'default') {
            // Aguardar algumas interações antes de solicitar
            let interactionCount = 0;
            const requestPermission = () => {
                interactionCount++;
                if (interactionCount >= 3) {
                    Notification.requestPermission();
                }
            };

            document.addEventListener('click', requestPermission, { once: true });
            document.addEventListener('touchstart', requestPermission, { once: true });
        }
    },

    // Configurar handlers de toque
    setupTouchHandlers() {
        // Melhorar feedback tátil em dispositivos móveis
        document.addEventListener('touchstart', (e) => {
            const button = e.target.closest('button, .btn, a');
            if (button) {
                button.style.transform = 'scale(0.95)';
            }
        });

        document.addEventListener('touchend', (e) => {
            const button = e.target.closest('button, .btn, a');
            if (button) {
                setTimeout(() => {
                    button.style.transform = '';
                }, 150);
            }
        });
    },

    // Utilitários de API
    async apiCall(endpoint, options = {}) {
        const url = `${this.config.apiBase}/${endpoint}`;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const mergedOptions = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(url, mergedOptions);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Erro na requisição');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    // Utilitários de UI
    showToast(message, type = 'info', duration = null) {
        const toastContainer = this.getOrCreateToastContainer();
        const toastId = 'toast-' + Date.now();
        
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${this.getToastColor(type)} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${this.getToastIcon(type)} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: duration || this.config.toastDuration
        });
        
        toast.show();
        
        // Remover elemento após esconder
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    },

    getOrCreateToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        return container;
    },

    getToastColor(type) {
        const colors = {
            success: 'success',
            error: 'danger',
            warning: 'warning',
            info: 'info'
        };
        return colors[type] || 'info';
    },

    getToastIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    },

    showLoading(message = 'Carregando...') {
        if (this.state.loading) return;
        
        this.state.loading = true;
        
        const loadingHtml = `
            <div id="loading-overlay" class="loading-overlay">
                <div class="text-center">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <div class="loading-message">${message}</div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', loadingHtml);
    },

    hideLoading() {
        if (!this.state.loading) return;
        
        this.state.loading = false;
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    },

    // Utilitários de formulário
    handleFormSubmit(form) {
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
        }
        
        // Reabilitar botão após um tempo
        setTimeout(() => {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = submitButton.dataset.originalText || 'Enviar';
            }
        }, 5000);
    },

    // Utilitários de validação
    validateForm(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    },

    // Utilitários de data/hora
    formatDate(date, format = 'dd/mm/yyyy') {
        if (!date) return '';
        
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        
        return format
            .replace('dd', day)
            .replace('mm', month)
            .replace('yyyy', year);
    },

    formatTime(time, format = 'HH:mm') {
        if (!time) return '';
        
        const [hours, minutes] = time.split(':');
        return format
            .replace('HH', hours)
            .replace('mm', minutes);
    },

    // Utilitários de navegação
    navigateTo(url, showLoading = true) {
        if (showLoading) {
            this.showLoading('Carregando...');
        }
        window.location.href = url;
    },

    // Utilitários de armazenamento local
    setLocalStorage(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {
            console.error('Erro ao salvar no localStorage:', error);
        }
    },

    getLocalStorage(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (error) {
            console.error('Erro ao ler do localStorage:', error);
            return defaultValue;
        }
    },

    // Utilitários de notificação push
    async requestNotificationPermission() {
        if ('Notification' in window) {
            const permission = await Notification.requestPermission();
            return permission === 'granted';
        }
        return false;
    },

    showNotification(title, options = {}) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(title, {
                icon: '/pwa/icons/icon-192.png',
                badge: '/pwa/icons/icon-192.png',
                ...options
            });
            
            // Auto-close após 5 segundos
            setTimeout(() => {
                notification.close();
            }, 5000);
            
            return notification;
        }
    },

    // Utilitários de acessibilidade
    announceToScreenReader(message) {
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.textContent = message;
        
        document.body.appendChild(announcement);
        
        setTimeout(() => {
            document.body.removeChild(announcement);
        }, 1000);
    },

    // Utilitários de performance
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
};

// Funções globais para compatibilidade
window.showToast = (message, type, duration) => CFCMobile.showToast(message, type, duration);
window.showLoading = (message) => CFCMobile.showLoading(message);
window.hideLoading = () => CFCMobile.hideLoading();

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    CFCMobile.init();
});

// Exportar para uso em módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CFCMobile;
}