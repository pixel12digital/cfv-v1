// =====================================================
// FORMULÁRIO DE LOGIN - SISTEMA CFC
// VERSÃO 2.0 - JAVASCRIPT MODERNO
// =====================================================

class LoginForm {
    constructor() {
        this.form = document.getElementById('loginForm');
        this.emailField = document.getElementById('email');
        this.senhaField = document.getElementById('senha');
        this.submitBtn = document.getElementById('btnLogin');
        this.togglePasswordBtn = document.getElementById('togglePassword');
        this.rememberCheckbox = document.getElementById('remember');
        
        this.isSubmitting = false;
        this.validationErrors = new Map();
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.setupAccessibility();
        this.loadSavedCredentials();
        // Removido validateOnInput() para evitar validação prematura
    }
    
    bindEvents() {
        // Submit do formulário
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Toggle de senha
        if (this.togglePasswordBtn) {
            this.togglePasswordBtn.addEventListener('click', () => this.togglePassword());
        }
        
        // Validação apenas quando o usuário digitar algo
        this.emailField.addEventListener('blur', () => {
            if (this.emailField.value.trim()) {
                this.validateEmail();
            }
        });
        this.senhaField.addEventListener('blur', () => {
            if (this.senhaField.value.trim()) {
                this.validateSenha();
            }
        });
        
        // Limpar erros ao digitar
        this.emailField.addEventListener('input', () => this.clearFieldError('email'));
        this.senhaField.addEventListener('input', () => this.clearFieldError('senha'));
        
        // Salvar credenciais ao marcar checkbox
        if (this.rememberCheckbox) {
            this.rememberCheckbox.addEventListener('change', () => this.handleRememberMe());
        }
        
        // Atalhos de teclado
        document.addEventListener('keydown', (e) => this.handleKeyboardShortcuts(e));
    }
    
    setupAccessibility() {
        // ARIA labels dinâmicos
        this.emailField.setAttribute('aria-describedby', 'email-help email-error');
        this.senhaField.setAttribute('aria-describedby', 'senha-help senha-error');
        
        // Focus management
        this.form.addEventListener('focusin', (e) => this.handleFocusIn(e));
        this.form.addEventListener('focusout', (e) => this.handleFocusOut(e));
        
        // Screen reader announcements
        this.setupScreenReaderSupport();
    }
    
    setupScreenReaderSupport() {
        // Criar região para anúncios
        const announcementRegion = document.createElement('div');
        announcementRegion.setAttribute('aria-live', 'polite');
        announcementRegion.setAttribute('aria-atomic', 'true');
        announcementRegion.className = 'sr-only';
        announcementRegion.id = 'announcement-region';
        
        document.body.appendChild(announcementRegion);
    }
    
    announceToScreenReader(message) {
        const region = document.getElementById('announcement-region');
        if (region) {
            region.textContent = message;
            // Limpar após o anúncio
            setTimeout(() => {
                region.textContent = '';
            }, 1000);
        }
    }
    
    handleSubmit(e) {
        e.preventDefault();
        
        if (this.isSubmitting) {
            return;
        }
        
        // Validar todos os campos
        const isValid = this.validateAllFields();
        
        if (!isValid) {
            this.announceToScreenReader('Formulário contém erros de validação');
            this.focusFirstError();
            return;
        }
        
        // Iniciar submissão
        this.startSubmission();
        
        // Simular envio (substituir por envio real)
        setTimeout(() => {
            this.completeSubmission();
        }, 2000);
    }
    
    validateAllFields() {
        // Só validar se os campos não estiverem vazios
        let emailValid = true;
        let senhaValid = true;
        
        if (this.emailField.value.trim()) {
            emailValid = this.validateEmail();
        }
        
        if (this.senhaField.value.trim()) {
            senhaValid = this.validateSenha();
        }
        
        return emailValid && senhaValid;
    }
    
    validateEmail() {
        const email = this.emailField.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!email) {
            this.showFieldError('email', 'Por favor, informe seu e-mail.');
            return false;
        }
        
        if (!emailRegex.test(email)) {
            this.showFieldError('email', 'Por favor, informe um e-mail válido.');
            return false;
        }
        
        this.clearFieldError('email');
        return true;
    }
    
    validateSenha() {
        const senha = this.senhaField.value;
        
        if (!senha) {
            this.showFieldError('senha', 'Por favor, informe sua senha.');
            return false;
        }
        
        if (senha.length < 6) {
            this.showFieldError('senha', 'A senha deve ter pelo menos 6 caracteres.');
            return false;
        }
        
        this.clearFieldError('senha');
        return true;
    }
    
    showFieldError(fieldName, message) {
        const field = this.getFieldByName(fieldName);
        const errorElement = this.getErrorElement(fieldName);
        
        if (field && errorElement) {
            // Adicionar classe de erro
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');
            
            // Mostrar mensagem de erro
            errorElement.textContent = message;
            errorElement.style.display = 'flex';
            
            // Armazenar erro
            this.validationErrors.set(fieldName, message);
            
            // Anunciar para leitores de tela
            this.announceToScreenReader(`Erro no campo ${fieldName}: ${message}`);
        }
    }
    
    clearFieldError(fieldName) {
        const field = this.getFieldByName(fieldName);
        const errorElement = this.getErrorElement(fieldName);
        
        if (field && errorElement) {
            // Remover classe de erro
            field.classList.remove('is-invalid');
            field.setAttribute('aria-invalid', 'false');
            
            // Ocultar mensagem de erro
            errorElement.style.display = 'none';
            
            // Remover erro da lista
            this.validationErrors.delete(fieldName);
        }
    }
    
    getFieldByName(fieldName) {
        switch (fieldName) {
            case 'email': return this.emailField;
            case 'senha': return this.senhaField;
            default: return null;
        }
    }
    
    getErrorElement(fieldName) {
        return document.getElementById(`${fieldName}-error`);
    }
    
    focusFirstError() {
        const firstErrorField = Array.from(this.validationErrors.keys())[0];
        if (firstErrorField) {
            const field = this.getFieldByName(firstErrorField);
            if (field) {
                field.focus();
            }
        }
    }
    
    startSubmission() {
        this.isSubmitting = true;
        this.submitBtn.classList.add('loading');
        this.submitBtn.disabled = true;
        
        // Anunciar início da submissão
        this.announceToScreenReader('Enviando formulário...');
        
        // Salvar credenciais se marcado
        this.saveCredentials();
    }
    
    completeSubmission() {
        this.isSubmitting = false;
        this.submitBtn.classList.remove('loading');
        this.submitBtn.disabled = false;
        
        // Anunciar conclusão
        this.announceToScreenReader('Formulário enviado com sucesso');
        
        // Aqui você pode redirecionar ou mostrar mensagem de sucesso
        console.log('Formulário enviado com sucesso');
    }
    
    togglePassword() {
        const type = this.senhaField.type === 'password' ? 'text' : 'password';
        this.senhaField.type = type;
        
        const icon = this.togglePasswordBtn.querySelector('i');
        const isVisible = type === 'text';
        
        if (isVisible) {
            icon.className = 'fas fa-eye-slash';
            this.togglePasswordBtn.setAttribute('aria-label', 'Ocultar senha');
            this.togglePasswordBtn.setAttribute('aria-pressed', 'true');
        } else {
            icon.className = 'fas fa-eye';
            this.togglePasswordBtn.setAttribute('aria-label', 'Mostrar senha');
            this.togglePasswordBtn.setAttribute('aria-pressed', 'false');
        }
        
        // Anunciar mudança
        this.announceToScreenReader(isVisible ? 'Senha visível' : 'Senha oculta');
    }
    
    handleRememberMe() {
        if (this.rememberCheckbox.checked) {
            this.announceToScreenReader('Lembrar credenciais ativado');
        } else {
            this.announceToScreenReader('Lembrar credenciais desativado');
        }
    }
    
    saveCredentials() {
        if (this.rememberCheckbox && this.rememberCheckbox.checked) {
            const credentials = {
                email: this.emailField.value.trim(),
                remember: true,
                timestamp: Date.now()
            };
            
            try {
                localStorage.setItem('cfc_credentials', JSON.stringify(credentials));
            } catch (e) {
                console.warn('Não foi possível salvar credenciais:', e);
            }
        }
    }
    
    loadSavedCredentials() {
        try {
            const saved = localStorage.getItem('cfc_credentials');
            if (saved) {
                const credentials = JSON.parse(saved);
                
                // Verificar se as credenciais não expiraram (7 dias)
                const isExpired = Date.now() - credentials.timestamp > 7 * 24 * 60 * 60 * 1000;
                
                if (!isExpired && credentials.email) {
                    this.emailField.value = credentials.email;
                    if (this.rememberCheckbox) {
                        this.rememberCheckbox.checked = true;
                    }
                } else {
                    // Limpar credenciais expiradas
                    localStorage.removeItem('cfc_credentials');
                }
            }
        } catch (e) {
            console.warn('Erro ao carregar credenciais salvas:', e);
        }
    }
    
    handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + Enter para submeter
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            this.form.requestSubmit();
        }
        
        // Tab para navegar entre campos
        if (e.key === 'Tab') {
            this.handleTabNavigation(e);
        }
    }
    
    handleTabNavigation(e) {
        // Lógica personalizada de navegação por tab se necessário
        // Por padrão, o navegador já gerencia isso bem
    }
    
    handleFocusIn(e) {
        const field = e.target;
        if (field.classList.contains('form-field-input')) {
            field.parentElement.classList.add('focused');
        }
    }
    
    handleFocusOut(e) {
        const field = e.target;
        if (field.classList.contains('form-field-input')) {
            field.parentElement.classList.remove('focused');
        }
    }
    
    validateOnInput() {
        // Validação em tempo real com debounce
        let timeout;
        
        const validateWithDelay = (fieldName) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                if (fieldName === 'email') {
                    this.validateEmail();
                } else if (fieldName === 'senha') {
                    this.validateSenha();
                }
            }, 300);
        };
        
        this.emailField.addEventListener('input', () => validateWithDelay('email'));
        this.senhaField.addEventListener('input', () => validateWithDelay('senha'));
    }
    
    // Métodos públicos para uso externo
    reset() {
        this.form.reset();
        this.validationErrors.clear();
        this.clearAllErrors();
        this.announceToScreenReader('Formulário resetado');
    }
    
    clearAllErrors() {
        this.clearFieldError('email');
        this.clearFieldError('senha');
    }
    
    getValidationErrors() {
        return Object.fromEntries(this.validationErrors);
    }
    
    isValid() {
        return this.validationErrors.size === 0;
    }
}

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    window.loginForm = new LoginForm();
});

// Exportar para uso em outros módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LoginForm;
}
