/**
 * CORREÇÃO DE VISIBILIDADE - TEXTO DE SUPORTE
 * Sistema CFC Bom Conselho
 * 
 * Problema: Texto "Problemas para acessar? Entre em contato com o suporte." 
 * fica escondido quando a página carrega, mas aparece ao clicar em qualquer lugar
 */

(function() {
    'use strict';
    
    // Função para forçar a visibilidade do texto de suporte
    function forceSupportTextVisibility() {
        const supportTexts = document.querySelectorAll('.text-center .form-help-text, .text-center .text-muted.small');
        
        supportTexts.forEach(function(element) {
            // Forçar visibilidade com JavaScript
            element.style.position = 'relative';
            element.style.zIndex = '9999';
            element.style.display = 'block';
            element.style.visibility = 'visible';
            element.style.opacity = '1';
            element.style.transform = 'none';
            element.style.clip = 'auto';
            element.style.overflow = 'visible';
            element.style.height = 'auto';
            element.style.width = 'auto';
            element.style.maxHeight = 'none';
            element.style.maxWidth = 'none';
            element.style.minHeight = 'auto';
            element.style.minWidth = 'auto';
            
            // Remover classes que possam estar causando problemas
            element.classList.remove('d-none', 'invisible', 'opacity-0');
            element.classList.add('d-block', 'visible', 'opacity-100');
            
            // Forçar reflow para garantir que as mudanças sejam aplicadas
            element.offsetHeight;
        });
    }
    
    // Função para verificar e corrigir periodicamente
    function checkAndFixVisibility() {
        const supportTexts = document.querySelectorAll('.text-center .form-help-text, .text-center .text-muted.small');
        
        supportTexts.forEach(function(element) {
            const computedStyle = window.getComputedStyle(element);
            
            // Verificar se o elemento está visível
            if (computedStyle.display === 'none' || 
                computedStyle.visibility === 'hidden' || 
                computedStyle.opacity === '0' ||
                computedStyle.height === '0px' ||
                computedStyle.width === '0px') {
                
                console.log('Texto de suporte escondido detectado, corrigindo...');
                forceSupportTextVisibility();
            }
        });
    }
    
    // Função para corrigir quando a página carrega
    function onPageLoad() {
        console.log('Página carregada, verificando visibilidade do texto de suporte...');
        
        // Aguardar um pouco para garantir que o DOM esteja completamente carregado
        setTimeout(function() {
            forceSupportTextVisibility();
            checkAndFixVisibility();
        }, 100);
        
        // Verificar novamente após um tempo maior
        setTimeout(function() {
            checkAndFixVisibility();
        }, 500);
        
        // Verificar periodicamente
        setInterval(checkAndFixVisibility, 2000);
    }
    
    // Função para corrigir quando há interação do usuário
    function onUserInteraction() {
        console.log('Interação do usuário detectada, verificando visibilidade...');
        forceSupportTextVisibility();
    }
    
    // Função para corrigir quando o DOM muda
    function onDOMChange() {
        console.log('DOM alterado, verificando visibilidade...');
        setTimeout(forceSupportTextVisibility, 50);
    }
    
    // Event listeners
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onPageLoad);
    } else {
        onPageLoad();
    }
    
    // Event listeners para interações do usuário
    document.addEventListener('click', onUserInteraction);
    document.addEventListener('touchstart', onUserInteraction);
    document.addEventListener('keydown', onUserInteraction);
    document.addEventListener('scroll', onUserInteraction);
    
    // Observer para mudanças no DOM
    if (window.MutationObserver) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' || mutation.type === 'attributes') {
                    onDOMChange();
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class']
        });
    }
    
    // Função para forçar visibilidade em elementos específicos
    window.forceSupportTextVisibility = function() {
        forceSupportTextVisibility();
        console.log('Visibilidade do texto de suporte forçada manualmente');
    };
    
    // Log de inicialização
    console.log('Sistema de correção de visibilidade do texto de suporte inicializado');
    
})();
