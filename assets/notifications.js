/**
 * Sistema de Auto-Hide das Notificações
 * Autor: Claude AI Assistant
 * Funcionalidades:
 * - Auto-hide após 5 segundos
 * - Click para fechar manualmente
 * - Animação suave de fade-out
 * - Suporte a múltiplas notificações
 */

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar auto-hide para todas as mensagens existentes
    initAutoHideMessages();
    
    // Observar novas mensagens que possam ser adicionadas dinamicamente
    observeNewMessages();
});

/**
 * Inicializar auto-hide para mensagens existentes na página
 */
function initAutoHideMessages() {
    const messages = document.querySelectorAll('.auto-hide-message');
    
    messages.forEach(function(message) {
        setupAutoHide(message);
    });
}

/**
 * Configurar auto-hide para uma mensagem específica
 */
function setupAutoHide(messageElement) {
    // Configurar tempo de auto-hide baseado no tipo de mensagem
    const isError = messageElement.classList.contains('erro');
    const autoHideDelay = isError ? 7000 : 5000; // Erros ficam mais tempo
    
    // Auto-hide após o tempo especificado
    const autoHideTimer = setTimeout(function() {
        hideMessage(messageElement);
    }, autoHideDelay);
    
    // Click para fechar manualmente
    messageElement.addEventListener('click', function() {
        clearTimeout(autoHideTimer);
        hideMessage(messageElement);
    });
    
    // Pause auto-hide quando mouse está sobre a mensagem
    messageElement.addEventListener('mouseenter', function() {
        clearTimeout(autoHideTimer);
    });
    
    // Resume auto-hide quando mouse sai da mensagem
    messageElement.addEventListener('mouseleave', function() {
        setTimeout(function() {
            hideMessage(messageElement);
        }, 2000); // 2 segundos após sair com o mouse
    });
}

/**
 * Esconder mensagem com animação
 */
function hideMessage(messageElement) {
    if (!messageElement || messageElement.classList.contains('fade-out')) {
        return; // Evitar execução dupla
    }
    
    // Adicionar classe de fade-out
    messageElement.classList.add('fade-out');
    
    // Remover elemento do DOM após animação
    setTimeout(function() {
        if (messageElement && messageElement.parentNode) {
            messageElement.parentNode.removeChild(messageElement);
        }
    }, 300); // Tempo da animação CSS
}

/**
 * Observar novas mensagens que possam ser adicionadas dinamicamente
 */
function observeNewMessages() {
    // Usar MutationObserver para detectar novas mensagens
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) { // Element node
                    // Verificar se o nó é uma mensagem
                    if (node.classList && node.classList.contains('auto-hide-message')) {
                        setupAutoHide(node);
                    }
                    // Verificar se há mensagens dentro do nó
                    const childMessages = node.querySelectorAll('.auto-hide-message');
                    childMessages.forEach(function(childMessage) {
                        setupAutoHide(childMessage);
                    });
                }
            });
        });
    });
    
    // Observar mudanças no body
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}

/**
 * Função global para criar notificações programaticamente
 */
window.showNotification = function(message, type = 'success', autoHide = true) {
    const messageId = 'mensagem_' + Date.now();
    const className = type === 'success' ? 'sucesso' : 'erro';
    const autoHideClass = autoHide ? ' auto-hide-message' : '';
    
    const messageHTML = `
        <div id="${messageId}" class="mensagem ${className}${autoHideClass}">
            ${message}
        </div>
    `;
    
    // Inserir no início do conteúdo principal
    const mainContent = document.querySelector('.main-content') || document.body;
    mainContent.insertAdjacentHTML('afterbegin', messageHTML);
    
    // Configurar auto-hide se necessário
    if (autoHide) {
        const messageElement = document.getElementById(messageId);
        if (messageElement) {
            setupAutoHide(messageElement);
        }
    }
};

/**
 * Função para fechar todas as notificações
 */
window.hideAllNotifications = function() {
    const messages = document.querySelectorAll('.auto-hide-message');
    messages.forEach(function(message) {
        hideMessage(message);
    });
};