<!-- 
INSTRUÇÕES PARA CORRIGIR O ERRO DE NAVEGAÇÃO:

1. Salvar o script JavaScript como: assets/dashboard-navigation.js
2. Incluir no final do dashboard.php, antes do </body>
3. Verificar se o arquivo dashboard_scripts.php está sendo incluído corretamente
-->

<!-- ADICIONR NO FINAL DO dashboard.php, ANTES DE </body> -->
<script src="assets/dashboard-navigation.js"></script>

<!-- OU, se preferir inline, adicionar dentro do dashboard_scripts.php -->
<script>
// ==================== NAVEGAÇÃO PRINCIPAL (CORREÇÃO) ====================

/**
 * Função principal de navegação entre seções
 * CORREÇÃO: Esta função resolve o erro "showSection is not defined"
 */
function showSection(sectionId) {
    console.log('Navegando para seção:', sectionId);
    
    try {
        // Esconder todas as seções
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Remover active de todos os nav-items
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Mostrar a seção selecionada
        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.classList.add('active');
            console.log('Seção ativada:', sectionId);
        } else {
            console.error('Seção não encontrada:', sectionId);
            return false;
        }
        
        // Adicionar active ao botão que chamou a função
        if (event && event.target) {
            // Se foi clicado diretamente
            event.target.classList.add('active');
        } else {
            // Buscar o botão correspondente
            const activeButton = document.querySelector(`button[onclick*="showSection('${sectionId}')"]`);
            if (activeButton) {
                activeButton.classList.add('active');
            }
        }
        
        // Atualizar URL sem recarregar página
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('secao', sectionId);
        const newURL = `${window.location.pathname}?${urlParams.toString()}`;
        history.replaceState(null, '', newURL);
        
        return true;
        
    } catch (error) {
        console.error('Erro na navegação:', error);
        return false;
    }
}

/**
 * Alterar limite de itens por página
 */
function alterarLimite(novoLimite) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('limite', novoLimite);
    urlParams.set('pagina', 1); // Voltar para primeira página
    
    window.location.href = `${window.location.pathname}?${urlParams.toString()}`;
}

/**
 * Visualizar detalhes de uma contratação
 */
function visualizarDetalhes(numeroDfd, ano = null) {
    if (!ano) {
        const urlParams = new URLSearchParams(window.location.search);
        ano = urlParams.get('ano') || new Date().getFullYear();
    }
    
    const url = `detalhes.php?numero_dfd=${encodeURIComponent(numeroDfd)}&ano=${ano}`;
    window.location.href = url;
}

/**
 * Auto-hide de mensagens
 */
function autoHideMensagens() {
    const mensagens = document.querySelectorAll('.auto-hide-message');
    mensagens.forEach(mensagem => {
        setTimeout(() => {
            if (mensagem.parentNode) {
                mensagem.style.opacity = '0';
                setTimeout(() => {
                    mensagem.remove();
                }, 300);
            }
        }, 5000);
    });
}

/**
 * Inicialização quando DOM estiver carregado
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard carregado - Navegação corrigida');
    
    // Inicializar Lucide Icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Verificar seção ativa na URL
    const urlParams = new URLSearchParams(window.location.search);
    const secaoAtiva = urlParams.get('secao') || 'dashboard';
    
    // Ativar seção atual se existir
    const secaoElement = document.getElementById(secaoAtiva);
    if (secaoElement) {
        showSection(secaoAtiva);
    }
    
    // Auto-hide mensagens
    autoHideMensagens();
    
    // Estatísticas de debug
    if (window.dashboardStats) {
        console.log('Total de contratações:', window.dashboardStats.total_contratacoes || 0);
    }
});

// Disponibilizar funções globalmente
window.showSection = showSection;
window.alterarLimite = alterarLimite;
window.visualizarDetalhes = visualizarDetalhes;

console.log('Sistema de navegação do dashboard carregado!');
</script>