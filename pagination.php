<?php
/**
 * Sistema de Paginação Eficiente
 * Componente reutilizável para paginar resultados
 */

class PaginationHelper {
    private $totalItems;
    private $itemsPerPage;
    private $currentPage;
    private $baseUrl;
    private $queryParams;
    private $totalPages;
    
    public function __construct($totalItems, $itemsPerPage = 20, $currentPage = 1, $baseUrl = '', $queryParams = []) {
        $this->totalItems = max(0, (int)$totalItems);
        $this->itemsPerPage = max(1, (int)$itemsPerPage);
        $this->currentPage = max(1, (int)$currentPage);
        $this->baseUrl = $baseUrl ?: $_SERVER['PHP_SELF'];
        $this->queryParams = $queryParams;
        $this->totalPages = ceil($this->totalItems / $this->itemsPerPage);
        
        // Ajustar página atual se estiver fora do range
        if ($this->currentPage > $this->totalPages && $this->totalPages > 0) {
            $this->currentPage = $this->totalPages;
        }
    }
    
    /**
     * Obter OFFSET para SQL
     */
    public function getOffset() {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }
    
    /**
     * Obter LIMIT para SQL
     */
    public function getLimit() {
        return $this->itemsPerPage;
    }
    
    /**
     * Gerar URL para uma página específica
     */
    private function generateUrl($page) {
        $params = $this->queryParams;
        $params['pagina'] = $page;
        $params['limite'] = $this->itemsPerPage;
        
        return $this->baseUrl . '?' . http_build_query($params);
    }
    
    /**
     * Verificar se existe página anterior
     */
    public function hasPrevious() {
        return $this->currentPage > 1;
    }
    
    /**
     * Verificar se existe próxima página
     */
    public function hasNext() {
        return $this->currentPage < $this->totalPages;
    }
    
    /**
     * Obter informações de range (ex: "1-20 de 100")
     */
    public function getRangeInfo() {
        if ($this->totalItems == 0) {
            return "Nenhum resultado encontrado";
        }
        
        $start = $this->getOffset() + 1;
        $end = min($this->getOffset() + $this->itemsPerPage, $this->totalItems);
        
        return sprintf("%d-%d de %d", $start, $end, $this->totalItems);
    }
    
    /**
     * Obter páginas para exibir na navegação
     */
    public function getVisiblePages($maxVisible = 7) {
        $pages = [];
        
        if ($this->totalPages <= $maxVisible) {
            // Mostrar todas as páginas
            for ($i = 1; $i <= $this->totalPages; $i++) {
                $pages[] = $i;
            }
        } else {
            // Lógica para mostrar páginas com reticências
            $half = floor($maxVisible / 2);
            $start = max(1, $this->currentPage - $half);
            $end = min($this->totalPages, $start + $maxVisible - 1);
            
            // Ajustar start se end chegou no limite
            if ($end - $start < $maxVisible - 1) {
                $start = max(1, $end - $maxVisible + 1);
            }
            
            // Primeira página sempre visível
            if ($start > 1) {
                $pages[] = 1;
                if ($start > 2) {
                    $pages[] = '...';
                }
            }
            
            // Páginas do meio
            for ($i = $start; $i <= $end; $i++) {
                $pages[] = $i;
            }
            
            // Última página sempre visível
            if ($end < $this->totalPages) {
                if ($end < $this->totalPages - 1) {
                    $pages[] = '...';
                }
                $pages[] = $this->totalPages;
            }
        }
        
        return $pages;
    }
    
    /**
     * Renderizar HTML da paginação
     */
    public function render($containerClass = 'pagination-container') {
        if ($this->totalPages <= 1) {
            return ''; // Não mostrar paginação se só há uma página
        }
        
        $html = '<div class="' . htmlspecialchars($containerClass) . '">';
        
        // Informações do range
        $html .= '<div class="pagination-info">' . $this->getRangeInfo() . '</div>';
        
        $html .= '<nav class="pagination-nav">';
        
        // Botão Primeira
        if ($this->hasPrevious()) {
            $html .= '<a href="' . htmlspecialchars($this->generateUrl(1)) . '" class="pagination-btn pagination-first" title="Primeira página">';
            $html .= '<i data-lucide="chevrons-left"></i>';
            $html .= '</a>';
        }
        
        // Botão Anterior
        if ($this->hasPrevious()) {
            $html .= '<a href="' . htmlspecialchars($this->generateUrl($this->currentPage - 1)) . '" class="pagination-btn pagination-prev" title="Página anterior">';
            $html .= '<i data-lucide="chevron-left"></i>';
            $html .= '</a>';
        }
        
        // Números das páginas
        $visiblePages = $this->getVisiblePages();
        foreach ($visiblePages as $page) {
            if ($page === '...') {
                $html .= '<span class="pagination-ellipsis">...</span>';
            } else {
                $isActive = $page == $this->currentPage;
                $class = 'pagination-btn pagination-number' . ($isActive ? ' active' : '');
                
                if ($isActive) {
                    $html .= '<span class="' . $class . '">' . $page . '</span>';
                } else {
                    $html .= '<a href="' . htmlspecialchars($this->generateUrl($page)) . '" class="' . $class . '">' . $page . '</a>';
                }
            }
        }
        
        // Botão Próximo
        if ($this->hasNext()) {
            $html .= '<a href="' . htmlspecialchars($this->generateUrl($this->currentPage + 1)) . '" class="pagination-btn pagination-next" title="Próxima página">';
            $html .= '<i data-lucide="chevron-right"></i>';
            $html .= '</a>';
        }
        
        // Botão Última
        if ($this->hasNext()) {
            $html .= '<a href="' . htmlspecialchars($this->generateUrl($this->totalPages)) . '" class="pagination-btn pagination-last" title="Última página">';
            $html .= '<i data-lucide="chevrons-right"></i>';
            $html .= '</a>';
        }
        
        $html .= '</nav>';
        
        // Seletor de itens por página
        $html .= '<div class="pagination-size-selector">';
        $html .= '<select onchange="alterarItensPorPagina(this.value)" class="items-per-page-select">';
        
        $options = [10, 20, 50, 100];
        foreach ($options as $option) {
            $selected = $option == $this->itemsPerPage ? 'selected' : '';
            $html .= '<option value="' . $option . '" ' . $selected . '>' . $option . ' por página</option>';
        }
        
        $html .= '</select>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Renderizar CSS para a paginação
     */
    public static function renderCSS() {
        return '
        <style>
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 0;
            margin-top: 20px;
            border-top: 1px solid #e9ecef;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .pagination-info {
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
        }
        
        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .pagination-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            background: white;
            color: #495057;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .pagination-btn:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
            color: #495057;
            text-decoration: none;
        }
        
        .pagination-btn.active {
            background: #007bff;
            border-color: #007bff;
            color: white;
        }
        
        .pagination-ellipsis {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            color: #6c757d;
            font-weight: bold;
        }
        
        .pagination-size-selector {
            display: flex;
            align-items: center;
        }
        
        .items-per-page-select {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: white;
            font-size: 14px;
            cursor: pointer;
        }
        
        .items-per-page-select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        
        /* Responsivo */
        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            
            .pagination-nav {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .pagination-btn {
                min-width: 36px;
                height: 36px;
                font-size: 13px;
            }
        }
        </style>';
    }
    
    /**
     * Renderizar JavaScript para a paginação
     */
    public static function renderJS() {
        return '
        <script>
        function alterarItensPorPagina(novoLimite) {
            const url = new URL(window.location);
            url.searchParams.set("limite", novoLimite);
            url.searchParams.set("pagina", 1); // Voltar para primeira página
            window.location.href = url.toString();
        }
        
        // Adicionar loading ao clicar em links de paginação
        document.addEventListener("DOMContentLoaded", function() {
            const paginationLinks = document.querySelectorAll(".pagination-btn");
            paginationLinks.forEach(link => {
                link.addEventListener("click", function() {
                    if (window.UX && window.UX.Loading) {
                        window.UX.Loading.show("body");
                    }
                });
            });
        });
        </script>';
    }
    
    /**
     * Função helper para criar instância rapidamente
     */
    public static function create($totalItems, $currentPage = null, $itemsPerPage = null, $queryParams = []) {
        $currentPage = $currentPage ?: (int)($_GET['pagina'] ?? 1);
        $itemsPerPage = $itemsPerPage ?: (int)($_GET['limite'] ?? 20);
        
        return new self($totalItems, $itemsPerPage, $currentPage, '', $queryParams);
    }
}

/**
 * Função helper global para criar paginação
 */
function createPagination($totalItems, $currentPage = null, $itemsPerPage = null, $queryParams = []) {
    return PaginationHelper::create($totalItems, $currentPage, $itemsPerPage, $queryParams);
}
?>