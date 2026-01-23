// =====================================================
// SISTEMA CFC - JAVASCRIPT DE LOGIN
// VERSÃO 2.0 - RESPONSIVA E ACESSÍVEL
// =====================================================

// Variáveis globais
let isFormValid = false;
let isSubmitting = false;
let currentFocusIndex = 0;
let focusableElements = [];

// Inicialização quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    console.log('Sistema CFC - JavaScript de login carregado com sucesso (Versão 2.0)');
    
    initializeAccessibility();
    initializeFormValidation();
    initializePasswordToggle();
    initializeConnectionDetection();
    initializeResponsiveFeatures();
    improveKeyboardNavigation();
    setupLiveRegions();
    
    // Auto-focus no campo de email
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.focus();
        announceToScreenReader('Campo de e-mail focado. Digite seu endereço de e-mail.');
    }
});

// =====================================================
// FUNÇÕES DE ACESSIBILIDADE AVANÇADA
// =====================================================

/**
 * Inicializa todas as funcionalidades de acessibilidade
 */
function initializeAccessibility() {
    // Adicionar classe para indicar suporte a JavaScript
    document.body.classList.add('js-enabled');
    
    // Detectar preferências de acessibilidade
    detectAccessibilityPreferences();
    
    // Configurar navegação por teclado
    setupKeyboardNavigation();
    
    // Configurar anúncios para leitores de tela
    setupScreenReaderAnnouncements();
    
    // Configurar indicadores de foco
    setupFocusIndicators();
    
    // Configurar suporte para preferências de movimento reduzido
    setupReducedMotionSupport();
}

/**
 * Detecta preferências de acessibilidade do usuário
 */
function detectAccessibilityPreferences() {
    // Preferência de movimento reduzido
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.body.classList.add('reduced-motion');
        announceToScreenReader('Movimento reduzido ativado para melhorar a acessibilidade.');
    }
    
    // Preferência de alto contraste
    if (window.matchMedia('(prefers-contrast: high)').matches) {
        document.body.classList.add('high-contrast');
        announceToScreenReader('Alto contraste ativado para melhorar a legibilidade.');
    }
    
    // Preferência de modo escuro
    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.body.classList.add('dark-mode');
        announceToScreenReader('Modo escuro ativado automaticamente.');
    }
    
    // Detectar se é um dispositivo touch
    if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
        document.body.classList.add('touch-device');
        announceToScreenReader('Dispositivo touch detectado. Alvos de toque otimizados.');
    }
    
    // Detectar se é um dispositivo de alta densidade
    if (window.devicePixelRatio > 1) {
        document.body.classList.add('high-dpi');
    }
}

/**
 * Configura navegação por teclado avançada
 */
function setupKeyboardNavigation() {
    // Atalhos de teclado globais
    document.addEventListener('keydown', function(e) {
        // Ctrl+Enter para submeter formulário
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            const form = document.getElementById('loginForm');
            if (form && !isSubmitting) {
                announceToScreenReader('Submetendo formulário com atalho de teclado.');
                form.dispatchEvent(new Event('submit'));
            }
        }
        
        // Escape para limpar formulário
        if (e.key === 'Escape') {
            e.preventDefault();
            clearForm();
            announceToScreenReader('Formulário limpo. Todos os campos foram resetados.');
        }
        
        // Tab para navegação circular
        if (e.key === 'Tab') {
            handleTabNavigation(e);
        }
        
        // Setas para navegação em elementos customizados
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
            handleArrowNavigation(e);
        }
    });
    
    // Configurar elementos focáveis
    updateFocusableElements();
}

/**
 * Configura anúncios para leitores de tela
 */
function setupScreenReaderAnnouncements() {
    // Criar região de anúncios
    const announcer = document.createElement('div');
    announcer.id = 'screen-reader-announcer';
    announcer.className = 'sr-only';
    announcer.setAttribute('aria-live', 'polite');
    announcer.setAttribute('aria-atomic', 'true');
    announcer.setAttribute('role', 'status');
    document.body.appendChild(announcer);
    
    // Anunciar mudanças de status
    announceToScreenReader('Página de login carregada. Use Tab para navegar entre os campos.');
}

/**
 * Configura indicadores de foco visíveis
 */
function setupFocusIndicators() {
    // Adicionar classe para navegação por teclado
    document.addEventListener('keydown', function() {
        document.body.classList.add('keyboard-navigation');
    });
    
    document.addEventListener('mousedown', function() {
        document.body.classList.remove('keyboard-navigation');
    });
    
    // Melhorar foco visível
    document.addEventListener('focusin', function(e) {
        const target = e.target;
        if (target.matches('button, input, select, textarea, a, [tabindex]')) {
            target.classList.add('focused');
            announceToScreenReader(`Focado em: ${getAccessibleName(target)}`);
        }
    });
    
    document.addEventListener('focusout', function(e) {
        const target = e.target;
        target.classList.remove('focused');
    });
}

/**
 * Configura suporte para preferências de movimento reduzido
 */
function setupReducedMotionSupport() {
    const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    
    function handleMotionPreference(e) {
        if (e.matches) {
            document.body.classList.add('reduced-motion');
            announceToScreenReader('Movimento reduzido ativado para melhorar a acessibilidade.');
        } else {
            document.body.classList.remove('reduced-motion');
        }
    }
    
    mediaQuery.addListener(handleMotionPreference);
    handleMotionPreference(mediaQuery);
}

/**
 * Anuncia mensagens para leitores de tela
 */
function announceToScreenReader(message) {
    const announcer = document.getElementById('screen-reader-announcer');
    if (announcer) {
        // Limpar mensagem anterior
        announcer.textContent = '';
        
        // Adicionar nova mensagem com delay para garantir que seja anunciada
        setTimeout(() => {
            announcer.textContent = message;
        }, 100);
    }
}

/**
 * Obtém o nome acessível de um elemento
 */
function getAccessibleName(element) {
    // Verificar aria-label
    if (element.getAttribute('aria-label')) {
        return element.getAttribute('aria-label');
    }
    
    // Verificar aria-labelledby
    if (element.getAttribute('aria-labelledby')) {
        const labelElement = document.getElementById(element.getAttribute('aria-labelledby'));
        if (labelElement) {
            return labelElement.textContent.trim();
        }
    }
    
    // Verificar label associado
    if (element.tagName === 'INPUT' && element.id) {
        const label = document.querySelector(`label[for="${element.id}"]`);
        if (label) {
            return label.textContent.trim();
        }
    }
    
    // Verificar texto do elemento
    if (element.textContent) {
        return element.textContent.trim();
    }
    
    // Verificar placeholder
    if (element.placeholder) {
        return element.placeholder;
    }
    
    // Fallback
    return 'Elemento';
}

// =====================================================
// FUNÇÕES DE NAVEGAÇÃO POR TECLADO
// =====================================================

/**
 * Atualiza lista de elementos focáveis
 */
function updateFocusableElements() {
    focusableElements = Array.from(document.querySelectorAll(
        'button, input, select, textarea, a, [tabindex]:not([tabindex="-1"])'
    )).filter(el => {
        return !el.disabled && 
               !el.hidden && 
               el.offsetParent !== null &&
               getComputedStyle(el).visibility !== 'hidden';
    });
}

/**
 * Manipula navegação por Tab
 */
function handleTabNavigation(e) {
    if (e.shiftKey) {
        // Tab reverso
        if (document.activeElement === focusableElements[0]) {
            e.preventDefault();
            focusableElements[focusableElements.length - 1].focus();
        }
    } else {
        // Tab normal
        if (document.activeElement === focusableElements[focusableElements.length - 1]) {
            e.preventDefault();
            focusableElements[0].focus();
        }
    }
}

/**
 * Manipula navegação por setas
 */
function handleArrowNavigation(e) {
    const currentElement = document.activeElement;
    const currentIndex = focusableElements.indexOf(currentElement);
    
    if (currentIndex === -1) return;
    
    let nextIndex = currentIndex;
    
    switch (e.key) {
        case 'ArrowDown':
        case 'ArrowRight':
            nextIndex = (currentIndex + 1) % focusableElements.length;
            break;
        case 'ArrowUp':
        case 'ArrowLeft':
            nextIndex = currentIndex === 0 ? focusableElements.length - 1 : currentIndex - 1;
            break;
    }
    
    e.preventDefault();
    focusableElements[nextIndex].focus();
}

/**
 * Melhora a navegação por teclado para elementos customizados
 */
function improveKeyboardNavigation() {
    // Adicionar suporte para Enter e Space em elementos com role="button"
    document.addEventListener('keydown', function(e) {
        const target = e.target;
        
        if (target.getAttribute('role') === 'button' || 
            target.classList.contains('btn') ||
            target.tagName === 'BUTTON') {
            
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                target.click();
                
                if (e.key === 'Enter') {
                    announceToScreenReader('Botão ativado com Enter');
                } else {
                    announceToScreenReader('Botão ativado com Espaço');
                }
            }
        }
    });
    
    // Adicionar tabindex para elementos customizados
    const customButtons = document.querySelectorAll('[role="button"], [role="tab"], [role="menuitem"]');
    customButtons.forEach(button => {
        if (!button.hasAttribute('tabindex')) {
            button.setAttribute('tabindex', '0');
        }
    });
}

// =====================================================
// FUNÇÕES DE VALIDAÇÃO DE FORMULÁRIO
// =====================================================

/**
 * Inicializa validação do formulário
 */
function initializeFormValidation() {
    const form = document.getElementById('loginForm');
    if (!form) return;
    
    // Validação em tempo real
    const inputs = form.querySelectorAll('input[required]');
    inputs.forEach(input => {
        input.addEventListener('blur', validateField);
        input.addEventListener('input', clearFieldValidation);
        input.addEventListener('keydown', handleInputKeydown);
    });
    
    // Validação no submit
    form.addEventListener('submit', handleFormSubmit);
    
    // Validação inicial
    validateForm();
}

/**
 * Valida um campo específico
 */
function validateField(e) {
    const field = e.target;
    const isValid = field.checkValidity();
    
    if (isValid) {
        showFieldSuccess(field);
        announceToScreenReader(`Campo ${getAccessibleName(field)} válido.`);
    } else {
        showFieldError(field);
        announceToScreenReader(`Erro no campo ${getAccessibleName(field)}: ${field.validationMessage}`);
    }
    
    updateFormValidity();
}

/**
 * Limpa validação de um campo
 */
function clearFieldValidation(e) {
    const field = e.target;
    field.classList.remove('is-valid', 'is-invalid');
    field.setAttribute('aria-invalid', 'false');
    
    // Remover mensagens de erro/sucesso
    const feedback = field.parentNode.querySelector('.invalid-feedback, .valid-feedback');
    if (feedback) {
        feedback.style.display = 'none';
    }
}

/**
 * Mostra erro em um campo
 */
function showFieldError(field) {
    field.classList.remove('is-valid');
    field.classList.add('is-invalid');
    field.setAttribute('aria-invalid', 'true');
    
    // Mostrar mensagem de erro
    const errorElement = field.parentNode.querySelector('.invalid-feedback');
    if (errorElement) {
        errorElement.style.display = 'block';
    }
    
    // Anunciar erro para leitores de tela
    announceToScreenReader(`Erro: ${field.validationMessage}`);
}

/**
 * Mostra sucesso em um campo
 */
function showFieldSuccess(field) {
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
    field.setAttribute('aria-invalid', 'false');
    
    // Mostrar mensagem de sucesso
    const successElement = field.parentNode.querySelector('.valid-feedback');
    if (successElement) {
        successElement.style.display = 'block';
    }
}

/**
 * Atualiza validade geral do formulário
 */
function updateFormValidity() {
    const form = document.getElementById('loginForm');
    if (!form) return;
    
    isFormValid = form.checkValidity();
    
    // Atualizar botão de submit
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = !isFormValid;
        submitButton.setAttribute('aria-disabled', !isFormValid);
        
        if (isFormValid) {
            announceToScreenReader('Formulário válido. Pode ser enviado.');
        } else {
            announceToScreenReader('Formulário inválido. Corrija os erros antes de enviar.');
        }
    }
}

/**
 * Valida todo o formulário
 */
function validateForm() {
    const form = document.getElementById('loginForm');
    if (!form) return;
    
    const inputs = form.querySelectorAll('input[required]');
    inputs.forEach(input => {
        if (input.value) {
            validateField({ target: input });
        }
    });
    
    updateFormValidity();
}

/**
 * Manipula o submit do formulário
 */
function handleFormSubmit(e) {
    if (!isFormValid || isSubmitting) {
        e.preventDefault();
        if (!isFormValid) {
            announceToScreenReader('Formulário inválido. Corrija os erros antes de enviar.');
        }
        return false;
    }
    
    isSubmitting = true;
    announceToScreenReader('Enviando formulário. Por favor, aguarde.');
    
    // Mostrar loading
    showLoadingState();
    
    // Simular envio (remover em produção)
    setTimeout(() => {
        isSubmitting = false;
        hideLoadingState();
        announceToScreenReader('Formulário enviado com sucesso!');
    }, 2000);
}

/**
 * Manipula teclas especiais nos campos de entrada
 */
function handleInputKeydown(e) {
    const field = e.target;
    
    // Enter para próximo campo
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        const nextField = getNextField(field);
        if (nextField) {
            nextField.focus();
            announceToScreenReader(`Movido para: ${getAccessibleName(nextField)}`);
        }
    }
    
    // Escape para limpar campo
    if (e.key === 'Escape') {
        e.preventDefault();
        field.value = '';
        field.focus();
        announceToScreenReader('Campo limpo.');
    }
}

/**
 * Obtém o próximo campo focável
 */
function getNextField(currentField) {
    const form = currentField.closest('form');
    if (!form) return null;
    
    const inputs = Array.from(form.querySelectorAll('input, select, textarea, button'));
    const currentIndex = inputs.indexOf(currentField);
    
    if (currentIndex < inputs.length - 1) {
        return inputs[currentIndex + 1];
    }
    
    return null;
}

// =====================================================
// FUNÇÕES DE TOGGLE DE SENHA
// =====================================================

/**
 * Inicializa toggle de visibilidade da senha
 */
function initializePasswordToggle() {
    const toggleButton = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('senha');
    
    if (!toggleButton || !passwordInput) return;
    
    toggleButton.addEventListener('click', function() {
        const isVisible = passwordInput.type === 'text';
        
        if (isVisible) {
            passwordInput.type = 'password';
            this.setAttribute('aria-label', 'Mostrar senha');
            this.setAttribute('aria-pressed', 'false');
            this.querySelector('i').className = 'fas fa-eye';
            announceToScreenReader('Senha ocultada');
        } else {
            passwordInput.type = 'text';
            this.setAttribute('aria-label', 'Ocultar senha');
            this.setAttribute('aria-pressed', 'true');
            this.querySelector('i').className = 'fas fa-eye-slash';
            announceToScreenReader('Senha visível');
        }
        
        // Focar no campo de senha
        passwordInput.focus();
    });
    
    // Suporte para teclado
    toggleButton.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.click();
        }
    });
}

// =====================================================
// FUNÇÕES DE DETECÇÃO DE CONEXÃO
// =====================================================

/**
 * Inicializa detecção de mudanças na conexão
 */
function initializeConnectionDetection() {
    window.addEventListener('online', function() {
        announceToScreenReader('Conexão com a internet restaurada.');
        hideOfflineWarning();
    });
    
    window.addEventListener('offline', function() {
        announceToScreenReader('Conexão com a internet perdida. Verifique sua conexão.');
        showOfflineWarning();
    });
}

/**
 * Mostra aviso de offline
 */
function showOfflineWarning() {
    const existingWarning = document.querySelector('.offline-warning');
    if (existingWarning) return;
    
    const warning = document.createElement('div');
    warning.className = 'alert alert-warning offline-warning';
    warning.setAttribute('role', 'alert');
    warning.setAttribute('aria-live', 'polite');
    warning.innerHTML = `
        <i class="fas fa-wifi" aria-hidden="true"></i>
        <strong>Sem conexão:</strong> Você está offline. Verifique sua conexão com a internet.
    `;
    
    const form = document.getElementById('loginForm');
    if (form) {
        form.parentNode.insertBefore(warning, form);
    }
}

/**
 * Esconde aviso de offline
 */
function hideOfflineWarning() {
    const warning = document.querySelector('.offline-warning');
    if (warning) {
        warning.remove();
    }
}

// =====================================================
// FUNÇÕES RESPONSIVAS
// =====================================================

/**
 * Inicializa funcionalidades responsivas
 */
function initializeResponsiveFeatures() {
    // Detectar mudanças de orientação
    window.addEventListener('orientationchange', function() {
        setTimeout(adjustLayoutForOrientation, 100);
    });
    
    // Detectar mudanças de tamanho da tela
    window.addEventListener('resize', function() {
        adjustLayoutForScreenSize();
    });
    
    // Ajuste inicial
    adjustLayoutForScreenSize();
}

/**
 * Ajusta layout para orientação
 */
function adjustLayoutForOrientation() {
    const isLandscape = window.innerHeight < window.innerWidth;
    
    if (isLandscape && window.innerHeight < 500) {
        document.body.classList.add('landscape-mobile');
        announceToScreenReader('Orientação horizontal detectada. Layout ajustado.');
    } else {
        document.body.classList.remove('landscape-mobile');
    }
}

/**
 * Ajusta layout para tamanho da tela
 */
function adjustLayoutForScreenSize() {
    const width = window.innerWidth;
    
    // Remover classes anteriores
    document.body.classList.remove(
        'extra-small-screen',
        'small-screen',
        'medium-screen',
        'large-screen',
        'extra-large-screen',
        'ultrawide'
    );
    
    // Adicionar classe apropriada
    if (width <= 320) {
        document.body.classList.add('extra-small-screen');
    } else if (width <= 575) {
        document.body.classList.add('small-screen');
    } else if (width <= 767) {
        document.body.classList.add('medium-screen');
    } else if (width <= 991) {
        document.body.classList.add('large-screen');
    } else if (width <= 1399) {
        document.body.classList.add('extra-large-screen');
    } else {
        document.body.classList.add('ultrawide');
    }
    
    // Atualizar elementos focáveis
    updateFocusableElements();
}

// =====================================================
// FUNÇÕES DE ESTADO DE LOADING
// =====================================================

/**
 * Mostra estado de loading
 */
function showLoadingState() {
    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.setAttribute('aria-disabled', 'true');
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2" aria-hidden="true"></i><span>Enviando...</span>';
    }
    
    // Mostrar modal de loading se existir
    const loadingModal = document.getElementById('loadingModal');
    if (loadingModal) {
        const modal = new bootstrap.Modal(loadingModal);
        modal.show();
    }
}

/**
 * Esconde estado de loading
 */
function hideLoadingState() {
    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = false;
        submitButton.setAttribute('aria-disabled', 'false');
        submitButton.innerHTML = '<i class="fas fa-sign-in-alt me-2" aria-hidden="true"></i><span>Entrar no Sistema</span>';
    }
    
    // Esconder modal de loading se existir
    const loadingModal = document.getElementById('loadingModal');
    if (loadingModal) {
        const modal = bootstrap.Modal.getInstance(loadingModal);
        if (modal) {
            modal.hide();
        }
    }
}

// =====================================================
// FUNÇÕES DE UTILIDADE
// =====================================================

/**
 * Limpa o formulário
 */
function clearForm() {
    const form = document.getElementById('loginForm');
    if (!form) return;
    
    form.reset();
    
    // Limpar validações
    const inputs = form.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.classList.remove('is-valid', 'is-invalid');
        input.setAttribute('aria-invalid', 'false');
    });
    
    // Esconder mensagens de feedback
    const feedbacks = form.querySelectorAll('.invalid-feedback, .valid-feedback');
    feedbacks.forEach(feedback => {
        feedback.style.display = 'none';
    });
    
    // Focar no primeiro campo
    const firstInput = form.querySelector('input');
    if (firstInput) {
        firstInput.focus();
    }
    
    // Atualizar validade
    updateFormValidity();
}

/**
 * Configura regiões ao vivo para leitores de tela
 */
function setupLiveRegions() {
    // Configurar alertas como regiões ao vivo
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (!alert.hasAttribute('aria-live')) {
            alert.setAttribute('aria-live', 'polite');
            alert.setAttribute('aria-atomic', 'true');
        }
    });
    
    // Configurar mensagens de validação como regiões ao vivo
    const feedbacks = document.querySelectorAll('.invalid-feedback, .valid-feedback');
    feedbacks.forEach(feedback => {
        if (!feedback.hasAttribute('aria-live')) {
            feedback.setAttribute('aria-live', 'polite');
            feedback.setAttribute('aria-atomic', 'true');
        }
    });
}

/**
 * Bloqueia formulário após muitas tentativas
 */
function lockoutForm(attempts, maxAttempts) {
    const submitButton = document.querySelector('button[type="submit"]');
    if (!submitButton) return;
    
    if (attempts >= maxAttempts) {
        submitButton.disabled = true;
        submitButton.setAttribute('aria-disabled', 'true');
        submitButton.innerHTML = '<i class="fas fa-lock me-2" aria-hidden="true"></i><span>Bloqueado temporariamente</span>';
        
        announceToScreenReader('Formulário bloqueado temporariamente devido a muitas tentativas. Tente novamente em alguns minutos.');
        
        // Desbloquear após 5 minutos
        setTimeout(() => {
            submitButton.disabled = false;
            submitButton.setAttribute('aria-disabled', 'false');
            submitButton.innerHTML = '<i class="fas fa-sign-in-alt me-2" aria-hidden="true"></i><span>Entrar no Sistema</span>';
            announceToScreenReader('Formulário desbloqueado. Pode tentar novamente.');
        }, 300000);
    }
}

// =====================================================
// FUNÇÕES DE CAPTCHA
// =====================================================

/**
 * Manipula sucesso do captcha
 */
function onRecaptchaSuccess() {
    announceToScreenReader('Verificação de segurança concluída com sucesso.');
    
    const submitButton = document.getElementById('btnLogin');
    if (submitButton) {
        submitButton.disabled = false;
        submitButton.setAttribute('aria-disabled', 'false');
        submitButton.classList.remove('btn-secondary');
        submitButton.classList.add('btn-primary');
    }
}

/**
 * Manipula expiração do captcha
 */
function onRecaptchaExpired() {
    announceToScreenReader('Verificação de segurança expirou. Complete novamente.');
    
    const submitButton = document.getElementById('btnLogin');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.setAttribute('aria-disabled', 'true');
        submitButton.classList.remove('btn-primary');
        submitButton.classList.add('btn-secondary');
    }
}

// =====================================================
// FUNÇÕES DE LOG E MONITORAMENTO
// =====================================================

/**
 * Registra eventos de acessibilidade
 */
function logAccessibilityEvent(event, details) {
    console.log(`[Acessibilidade] ${event}:`, details);
    
    // Em produção, enviar para sistema de analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', 'accessibility_interaction', {
            event_category: 'accessibility',
            event_label: event,
            value: 1
        });
    }
}

// Log de inicialização completa
console.log('Sistema CFC - JavaScript de login carregado com sucesso (Versão 2.0)');
