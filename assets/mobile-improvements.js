/**
 * Melhorias de UX Mobile
 * Sistema de Licitações CGLIC
 */

class MobileEnhancements {
    constructor() {
        this.init();
    }

    init() {
        this.setupMobileMenu();
        this.setupTouchGestures();
        this.setupMobileTableCards();
        this.setupViewportHeight();
        this.setupFormImprovements();
        console.log('📱 Mobile Enhancements carregadas');
    }

    /**
     * Configurar menu mobile
     */
    setupMobileMenu() {
        // Criar botão de menu mobile
        const menuToggle = document.createElement('button');
        menuToggle.className = 'mobile-menu-toggle mobile-only';
        menuToggle.innerHTML = '<i data-lucide="menu" style="width: 20px; height: 20px;"></i>';
        menuToggle.style.display = 'none';
        document.body.appendChild(menuToggle);

        // Criar overlay
        const overlay = document.createElement('div');
        overlay.className = 'mobile-overlay';
        document.body.appendChild(overlay);

        const sidebar = document.querySelector('.sidebar');
        
        if (sidebar) {
            // Toggle menu
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
            });

            // Fechar menu ao clicar no overlay
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });

            // Fechar menu ao clicar em link (exceto se for dropdown)
            sidebar.addEventListener('click', (e) => {
                if (e.target.matches('.nav-item:not(.has-dropdown)')) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // Fechar menu com ESC
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        }

        // Recriar ícones Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    /**
     * Configurar gestos de toque
     */
    setupTouchGestures() {
        let startX = 0;
        let startY = 0;

        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        });

        document.addEventListener('touchend', (e) => {
            if (!startX || !startY) return;

            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            
            const diffX = startX - endX;
            const diffY = startY - endY;

            // Detectar swipe horizontal
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.querySelector('.mobile-overlay');
                
                if (sidebar) {
                    if (diffX > 0 && startX < 50) {
                        // Swipe da esquerda para direita - abrir menu
                        sidebar.classList.add('mobile-open');
                        overlay.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    } else if (diffX < 0 && sidebar.classList.contains('mobile-open')) {
                        // Swipe da direita para esquerda - fechar menu
                        sidebar.classList.remove('mobile-open');
                        overlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                }
            }

            startX = 0;
            startY = 0;
        });
    }

    /**
     * Converter tabelas em cards para mobile
     */
    setupMobileTableCards() {
        const tables = document.querySelectorAll('table');
        
        tables.forEach(table => {
            this.createMobileCardView(table);
        });
    }

    createMobileCardView(table) {
        const container = table.closest('.table-container') || table.parentNode;
        
        // Criar container para view mobile
        const mobileView = document.createElement('div');
        mobileView.className = 'mobile-card-view';
        
        const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const card = this.createMobileCard(row, headers);
            mobileView.appendChild(card);
        });
        
        // Adicionar classe para esconder tabela no mobile
        table.closest('.table-container').classList.add('desktop-table-view');
        
        // Inserir view mobile
        container.appendChild(mobileView);
    }

    createMobileCard(row, headers) {
        const cells = row.querySelectorAll('td');
        const card = document.createElement('div');
        card.className = 'mobile-item-card';
        
        // Header do card (primeira coluna geralmente é o título)
        const header = document.createElement('div');
        header.className = 'mobile-item-header';
        
        const title = document.createElement('div');
        title.className = 'mobile-item-title';
        title.textContent = cells[0]?.textContent.trim() || 'Item';
        
        // Status (se existir)
        const statusCell = Array.from(cells).find(cell => 
            cell.querySelector('.status-badge, .situacao-badge, .badge')
        );
        
        if (statusCell) {
            const status = document.createElement('div');
            status.className = 'mobile-item-status';
            status.innerHTML = statusCell.innerHTML;
            header.appendChild(title);
            header.appendChild(status);
        } else {
            header.appendChild(title);
        }
        
        card.appendChild(header);
        
        // Detalhes (outras colunas)
        const details = document.createElement('div');
        details.className = 'mobile-item-details';
        
        cells.forEach((cell, index) => {
            if (index === 0) return; // Pular primeira coluna (já é o título)
            
            const detail = document.createElement('div');
            detail.className = 'mobile-item-detail';
            
            const label = document.createElement('div');
            label.className = 'mobile-item-detail-label';
            label.textContent = headers[index] || `Campo ${index + 1}`;
            
            const value = document.createElement('div');
            value.innerHTML = cell.innerHTML;
            
            detail.appendChild(label);
            detail.appendChild(value);
            details.appendChild(detail);
        });
        
        card.appendChild(details);
        
        // Ações (última coluna se contiver botões)
        const actionsCell = cells[cells.length - 1];
        if (actionsCell && actionsCell.querySelector('button, a.btn')) {
            const actions = document.createElement('div');
            actions.className = 'mobile-item-actions';
            
            const buttons = actionsCell.querySelectorAll('button, a.btn');
            buttons.forEach(btn => {
                const mobileBtn = btn.cloneNode(true);
                mobileBtn.className += ' mobile-action-btn';
                actions.appendChild(mobileBtn);
            });
            
            card.appendChild(actions);
        }
        
        return card;
    }

    /**
     * Configurar altura do viewport para mobile
     */
    setupViewportHeight() {
        const setVH = () => {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        };

        setVH();
        window.addEventListener('resize', setVH);
        window.addEventListener('orientationchange', () => {
            setTimeout(setVH, 100);
        });
    }

    /**
     * Melhorias para formulários mobile
     */
    setupFormImprovements() {
        // Prevenir zoom em inputs no iOS
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type === 'email' || input.type === 'tel' || input.type === 'url') {
                input.addEventListener('focus', () => {
                    const viewport = document.querySelector('meta[name="viewport"]');
                    if (viewport) {
                        viewport.setAttribute('content', 
                            'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'
                        );
                    }
                });

                input.addEventListener('blur', () => {
                    const viewport = document.querySelector('meta[name="viewport"]');
                    if (viewport) {
                        viewport.setAttribute('content', 
                            'width=device-width, initial-scale=1.0'
                        );
                    }
                });
            }
        });

        // Scroll suave para campos com erro
        document.addEventListener('invalid', (e) => {
            e.target.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }, true);

        // Melhorar UX de upload de arquivos
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    // Mostrar nome do arquivo selecionado
                    let feedback = input.parentNode.querySelector('.file-feedback');
                    if (!feedback) {
                        feedback = document.createElement('div');
                        feedback.className = 'file-feedback';
                        feedback.style.cssText = 'margin-top: 8px; color: #28a745; font-size: 14px;';
                        input.parentNode.appendChild(feedback);
                    }
                    
                    feedback.innerHTML = `
                        <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                        Arquivo selecionado: ${file.name}
                    `;
                    
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        });
    }

    /**
     * Detectar se é dispositivo móvel
     */
    static isMobile() {
        return window.innerWidth <= 768 || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    /**
     * Detectar se é dispositivo de toque
     */
    static isTouchDevice() {
        return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    }

    /**
     * Otimizações específicas para iOS
     */
    setupiOSOptimizations() {
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            // Prevenir bounce scroll
            document.body.style.overscrollBehavior = 'none';
            
            // Configurar meta tags do iOS
            const metaTags = [
                { name: 'apple-mobile-web-app-capable', content: 'yes' },
                { name: 'apple-mobile-web-app-status-bar-style', content: 'default' },
                { name: 'apple-mobile-web-app-title', content: 'Sistema CGLIC' }
            ];
            
            metaTags.forEach(tag => {
                if (!document.querySelector(`meta[name="${tag.name}"]`)) {
                    const meta = document.createElement('meta');
                    meta.name = tag.name;
                    meta.content = tag.content;
                    document.head.appendChild(meta);
                }
            });
        }
    }
}

/**
 * Utilities para mobile
 */
const MobileUtils = {
    // Vibrar (se suportado)
    vibrate(pattern = 100) {
        if (navigator.vibrate) {
            navigator.vibrate(pattern);
        }
    },

    // Compartilhar (se suportado)
    async share(data) {
        if (navigator.share) {
            try {
                await navigator.share(data);
                return true;
            } catch (err) {
                console.log('Erro ao compartilhar:', err);
                return false;
            }
        }
        return false;
    },

    // Copiar para clipboard
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            if (window.UX && window.UX.Toast) {
                window.UX.Toast.show('Copiado para a área de transferência!', 'success', 2000);
            }
            this.vibrate(50);
            return true;
        } catch (err) {
            console.log('Erro ao copiar:', err);
            return false;
        }
    },

    // Detectar orientação
    getOrientation() {
        return screen.orientation?.type || 
               (window.orientation !== undefined ? 
                   (Math.abs(window.orientation) === 90 ? 'landscape' : 'portrait') : 
                   'unknown');
    }
};

// Inicializar melhorias mobile quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    if (MobileEnhancements.isMobile() || MobileEnhancements.isTouchDevice()) {
        window.mobileEnhancements = new MobileEnhancements();
    }
});

// Exportar para uso global
window.MobileUtils = MobileUtils;
window.MobileEnhancements = MobileEnhancements;