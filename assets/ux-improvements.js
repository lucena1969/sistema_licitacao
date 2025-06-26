/**
 * Melhorias de UX - Loading States e Validação em Tempo Real
 */

// Configuração global
const UX = {
    debounceTime: 300,
    loadingClass: 'loading-state',
    errorClass: 'input-error',
    successClass: 'input-success'
};

/**
 * Sistema de Loading States
 */
class LoadingManager {
    constructor() {
        this.activeLoadings = new Set();
        this.initStyles();
    }

    initStyles() {
        if (!document.getElementById('ux-styles')) {
            const styles = `
                <style id="ux-styles">
                /* Loading States */
                .loading-state {
                    position: relative;
                    pointer-events: none;
                    opacity: 0.7;
                }

                .loading-state::after {
                    content: '';
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    width: 20px;
                    height: 20px;
                    margin: -10px 0 0 -10px;
                    border: 2px solid #f3f3f3;
                    border-top: 2px solid #3498db;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    z-index: 1000;
                }

                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

                /* Input States */
                .input-error {
                    border-color: #e74c3c !important;
                    box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.2) !important;
                }

                .input-success {
                    border-color: #27ae60 !important;
                    box-shadow: 0 0 0 2px rgba(39, 174, 96, 0.2) !important;
                }

                .validation-message {
                    font-size: 12px;
                    margin-top: 5px;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }

                .validation-message.error {
                    color: #e74c3c;
                }

                .validation-message.success {
                    color: #27ae60;
                }

                /* Button Loading */
                .btn-loading {
                    position: relative;
                    color: transparent !important;
                }

                .btn-loading::after {
                    content: '';
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    width: 16px;
                    height: 16px;
                    margin: -8px 0 0 -8px;
                    border: 2px solid transparent;
                    border-top: 2px solid currentColor;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }

                /* Skeleton Loading */
                .skeleton {
                    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                    background-size: 200% 100%;
                    animation: loading 1.5s infinite;
                }

                @keyframes loading {
                    0% { background-position: 200% 0; }
                    100% { background-position: -200% 0; }
                }

                /* Toast Notifications */
                .toast-container {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                    max-width: 400px;
                }

                .toast {
                    background: white;
                    border-radius: 8px;
                    padding: 16px;
                    margin-bottom: 10px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    border-left: 4px solid #3498db;
                    animation: slideIn 0.3s ease-out;
                }

                .toast.success { border-left-color: #27ae60; }
                .toast.error { border-left-color: #e74c3c; }
                .toast.warning { border-left-color: #f39c12; }

                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                </style>
            `;
            document.head.insertAdjacentHTML('beforeend', styles);
        }
    }

    show(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.classList.add(this.loadingClass);
            this.activeLoadings.add(element);
        }
    }

    hide(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.classList.remove(this.loadingClass);
            this.activeLoadings.delete(element);
        }
    }

    hideAll() {
        this.activeLoadings.forEach(element => {
            element.classList.remove(this.loadingClass);
        });
        this.activeLoadings.clear();
    }
}

/**
 * Sistema de Validação em Tempo Real
 */
class ValidationManager {
    constructor() {
        this.validators = new Map();
        this.debounceTimers = new Map();
        this.initValidators();
    }

    initValidators() {
        // Validadores comuns
        this.validators.set('email', {
            validate: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
            message: 'E-mail inválido'
        });

        this.validators.set('nup', {
            validate: (value) => /^\d{5}\.\d{6}\/\d{4}-\d{2}$/.test(value),
            message: 'Formato NUP inválido (xxxxx.xxxxxx/xxxx-xx)'
        });

        this.validators.set('required', {
            validate: (value) => value.trim().length > 0,
            message: 'Campo obrigatório'
        });

        this.validators.set('minLength', {
            validate: (value, min) => value.length >= min,
            message: (min) => `Mínimo ${min} caracteres`
        });

        this.validators.set('maxLength', {
            validate: (value, max) => value.length <= max,
            message: (max) => `Máximo ${max} caracteres`
        });

        this.validators.set('currency', {
            validate: (value) => /^(\d{1,3}(\.\d{3})*(\,\d{2})?|\d+(\,\d{2})?)$/.test(value),
            message: 'Formato monetário inválido'
        });
    }

    addValidator(name, validator) {
        this.validators.set(name, validator);
    }

    validateField(input, rules) {
        const value = input.value;
        let isValid = true;
        let message = '';

        for (const rule of rules) {
            const [validatorName, ...params] = rule.split(':');
            const validator = this.validators.get(validatorName);

            if (validator) {
                const result = validator.validate(value, ...params);
                if (!result) {
                    isValid = false;
                    message = typeof validator.message === 'function' 
                        ? validator.message(...params) 
                        : validator.message;
                    break;
                }
            }
        }

        this.updateFieldState(input, isValid, message);
        return isValid;
    }

    updateFieldState(input, isValid, message) {
        // Remover classes antigas
        input.classList.remove(UX.errorClass, UX.successClass);
        
        // Remover mensagem antiga
        const oldMessage = input.parentNode.querySelector('.validation-message');
        if (oldMessage) {
            oldMessage.remove();
        }

        if (input.value.trim() === '') {
            return; // Não mostrar estado para campos vazios
        }

        // Adicionar nova classe
        input.classList.add(isValid ? UX.successClass : UX.errorClass);

        // Adicionar mensagem se necessário
        if (!isValid && message) {
            const messageEl = document.createElement('div');
            messageEl.className = 'validation-message error';
            messageEl.innerHTML = `<i data-lucide="alert-circle" style="width: 14px; height: 14px;"></i> ${message}`;
            input.parentNode.appendChild(messageEl);
            
            // Recriar ícones Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }

    setupRealTimeValidation(selector) {
        const inputs = document.querySelectorAll(selector);
        
        inputs.forEach(input => {
            const rules = input.dataset.validate?.split('|') || [];
            if (rules.length === 0) return;

            // Validação em tempo real com debounce
            input.addEventListener('input', () => {
                const timerId = this.debounceTimers.get(input);
                if (timerId) clearTimeout(timerId);

                this.debounceTimers.set(input, setTimeout(() => {
                    this.validateField(input, rules);
                }, UX.debounceTime));
            });

            // Validação imediata ao sair do campo
            input.addEventListener('blur', () => {
                this.validateField(input, rules);
            });
        });
    }
}

/**
 * Sistema de Toast Notifications
 */
class ToastManager {
    constructor() {
        this.container = this.createContainer();
    }

    createContainer() {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    show(message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i data-lucide="${this.getIcon(type)}" style="width: 16px; height: 16px;"></i>
                <span>${message}</span>
            </div>
        `;

        this.container.appendChild(toast);

        // Recriar ícones Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Auto remover
        if (duration > 0) {
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        return toast;
    }

    getIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'x-circle',
            warning: 'alert-triangle',
            info: 'info'
        };
        return icons[type] || icons.info;
    }
}

// Instâncias globais
const Loading = new LoadingManager();
const Validation = new ValidationManager();
const Toast = new ToastManager();

/**
 * Melhorias para formulários
 */
function enhanceForm(formSelector) {
    const form = document.querySelector(formSelector);
    if (!form) return;

    // Setup validação em tempo real
    Validation.setupRealTimeValidation(`${formSelector} input[data-validate]`);

    // Interceptar submit
    form.addEventListener('submit', function(e) {
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Mostrar loading no botão
        if (submitBtn) {
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
        }

        // Validar todos os campos
        const inputs = form.querySelectorAll('input[data-validate]');
        let isFormValid = true;

        inputs.forEach(input => {
            const rules = input.dataset.validate.split('|');
            if (!Validation.validateField(input, rules)) {
                isFormValid = false;
            }
        });

        if (!isFormValid) {
            e.preventDefault();
            
            // Remover loading
            if (submitBtn) {
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
            }
            
            Toast.show('Por favor, corrija os erros no formulário', 'error');
            return false;
        }

        // Se chegou aqui, o formulário é válido
        Toast.show('Processando...', 'info', 2000);
    });
}

/**
 * Interceptar requisições AJAX para mostrar loading
 */
function enhanceAjaxRequests() {
    // Override do fetch
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        Loading.show('body');
        
        return originalFetch.apply(this, args)
            .then(response => {
                Loading.hide('body');
                return response;
            })
            .catch(error => {
                Loading.hide('body');
                Toast.show('Erro na requisição', 'error');
                throw error;
            });
    };

    // Interceptar formulários com AJAX
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.classList.contains('ajax-form')) {
            e.preventDefault();
            submitFormAjax(form);
        }
    });
}

/**
 * Submit de formulário via AJAX
 */
async function submitFormAjax(form) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    try {
        submitBtn?.classList.add('btn-loading');
        submitBtn && (submitBtn.disabled = true);
        
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Toast.show(result.message || 'Operação realizada com sucesso!', 'success');
            
            // Resetar formulário se especificado
            if (form.dataset.resetOnSuccess !== 'false') {
                form.reset();
            }
        } else {
            Toast.show(result.message || 'Erro na operação', 'error');
        }
        
    } catch (error) {
        Toast.show('Erro de conexão', 'error');
    } finally {
        submitBtn?.classList.remove('btn-loading');
        submitBtn && (submitBtn.disabled = false);
    }
}

/**
 * Inicialização quando DOM estiver pronto
 */
document.addEventListener('DOMContentLoaded', function() {
    // Setup das melhorias básicas
    enhanceAjaxRequests();
    
    // Melhorar formulários principais
    enhanceForm('#form-login');
    enhanceForm('#form-cadastro');
    enhanceForm('#criar-licitacao form');
    enhanceForm('#importar-pca form');
    
    // Adicionar validações específicas
    Validation.addValidator('confirmPassword', {
        validate: (value, originalFieldId) => {
            const originalField = document.getElementById(originalFieldId);
            return originalField ? value === originalField.value : false;
        },
        message: 'As senhas não coincidem'
    });
    
    console.log('🚀 UX Improvements carregadas com sucesso!');
});

// Exportar para uso global
window.UX = {
    Loading,
    Validation,
    Toast,
    enhanceForm,
    submitFormAjax
};