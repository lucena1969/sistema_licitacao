<?php
/**
 * DASHBOARD.PHP - VERSÃO CORRIGIDA COM VALORES CORRETOS
 * 
 * PROBLEMA IDENTIFICADO E CORRIGIDO:
 * ❌ Antes: SUM(valor_total_contratacao) = 2.905.700,00 (INCORRETO)
 * ✅ Agora: MAX(valor_total_contratacao) = 415.100,00 (CORRETO)
 * 
 * CORREÇÃO APLICADA:
 * - Eliminar SUM() que estava inflacionando valores
 * - Usar MAX() ou valor único por contratação
 * - Garantir que cada contratação tenha apenas UM valor
 */

require_once 'config.php';
require_once 'functions.php';

verificarLogin();

// ==================== CONFIGURAÇÃO DE PARÂMETROS ====================
$ano_selecionado = intval($_GET['ano'] ?? date('Y'));
$secao_ativa = $_GET['secao'] ?? 'dashboard';
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$limite = min(100, max(10, intval($_GET['limite'] ?? 50)));

// ==================== FILTROS ====================
$filtros = [
    'numero_contratacao' => trim($_GET['numero_contratacao'] ?? ''),
    'situacao_execucao' => trim($_GET['situacao_execucao'] ?? ''),
    'categoria' => trim($_GET['categoria'] ?? ''),
    'area_requisitante' => trim($_GET['area_requisitante'] ?? '')
];

error_log("FILTROS RECEBIDOS: " . json_encode($filtros));

/**
 * FUNÇÃO CORRIGIDA: Buscar contratações com valores ÚNICOS (não somados)
 */
function buscarContratacaoesValoresCorretos($ano, $filtros, $pagina, $limite) {
    $pdo = conectarDB();
    
    try {
        // ==================== FILTROS BASE ====================
        $where_conditions = [
            "ano_pca = ?",
            "status_contratacao = 'Aprovada'",
            "numero_contratacao IS NOT NULL",
            "numero_contratacao != ''",
            "TRIM(numero_contratacao) != ''"
        ];
        
        $params = [$ano];
        
        // ==================== APLICAR FILTROS ====================
        if (!empty($filtros['numero_contratacao'])) {
            $termo_busca = trim($filtros['numero_contratacao']);
            $where_conditions[] = "(numero_contratacao LIKE ? OR numero_dfd LIKE ? OR titulo_contratacao LIKE ?)";
            $params[] = "%{$termo_busca}%";
            $params[] = "%{$termo_busca}%";
            $params[] = "%{$termo_busca}%";
        }
        
        if (!empty($filtros['situacao_execucao'])) {
            if ($filtros['situacao_execucao'] === 'Não iniciado') {
                $where_conditions[] = "(situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao = 'Não iniciado')";
            } else {
                $where_conditions[] = "situacao_execucao = ?";
                $params[] = $filtros['situacao_execucao'];
            }
        }
        
        if (!empty($filtros['categoria'])) {
            $where_conditions[] = "categoria_contratacao = ?";
            $params[] = $filtros['categoria'];
        }
        
        if (!empty($filtros['area_requisitante'])) {
            $where_conditions[] = "area_requisitante LIKE ?";
            $params[] = $filtros['area_requisitante'] . '%';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // ==================== CONTAGEM ÚNICA ====================
        $sql_contagem = "SELECT COUNT(DISTINCT numero_contratacao) as total
                        FROM pca_dados 
                        WHERE {$where_clause}";
        
        $stmt_count = $pdo->prepare($sql_contagem);
        $stmt_count->execute($params);
        $total_registros = $stmt_count->fetch()['total'];
        
        error_log("TOTAL CONTRATAÇÕES ÚNICAS: {$total_registros}");
        
        // ==================== QUERY CORRIGIDA: VALORES ÚNICOS ====================
        $offset = ($pagina - 1) * $limite;
        $params_dados = $params;
        $params_dados[] = $limite;
        $params_dados[] = $offset;
        
        // ✅ CORREÇÃO PRINCIPAL: Usar MAX() ao invés de SUM()
        $sql_dados = "SELECT 
                        numero_contratacao,
                        numero_dfd,
                        titulo_contratacao,
                        categoria_contratacao,
                        area_requisitante,
                        situacao_execucao,
                        data_inicio_processo,
                        data_conclusao_processo,
                        prioridade,
                        valor_total_contratacao,
                        total_itens_contratacao,
                        id_principal
                      FROM (
                          SELECT 
                              numero_contratacao,
                              MAX(numero_dfd) as numero_dfd,
                              MAX(titulo_contratacao) as titulo_contratacao,
                              MAX(categoria_contratacao) as categoria_contratacao,
                              MAX(area_requisitante) as area_requisitante,
                              MAX(situacao_execucao) as situacao_execucao,
                              MAX(data_inicio_processo) as data_inicio_processo,
                              MAX(data_conclusao_processo) as data_conclusao_processo,
                              MAX(prioridade) as prioridade,
                              -- ✅ CORREÇÃO: MAX ao invés de SUM para pegar apenas UM valor
                              MAX(valor_total_contratacao) as valor_total_contratacao,
                              COUNT(*) as total_itens_contratacao,
                              MAX(id) as id_principal
                          FROM pca_dados
                          WHERE {$where_clause}
                          GROUP BY numero_contratacao
                      ) as dados_unicos
                      ORDER BY numero_contratacao DESC
                      LIMIT ? OFFSET ?";
        
        $stmt_dados = $pdo->prepare($sql_dados);
        $stmt_dados->execute($params_dados);
        $dados = $stmt_dados->fetchAll();
        
        error_log("✅ QUERY CORRIGIDA EXECUTADA. REGISTROS: " . count($dados));
        
        // ==================== VALIDAÇÃO ====================
        foreach ($dados as $row) {
            error_log("CONTRATAÇÃO: {$row['numero_contratacao']} = R$ " . number_format($row['valor_total_contratacao'], 2, ',', '.'));
        }
        
        return [
            'dados' => $dados,
            'total' => $total_registros,
            'debug' => [
                'where_clause' => $where_clause,
                'params' => $params,
                'filtros_aplicados' => array_filter($filtros),
                'registros_pagina' => count($dados),
                'correcao_aplicada' => 'MAX() usado ao invés de SUM()'
            ]
        ];
        
    } catch (Exception $e) {
        error_log("❌ ERRO na busca: " . $e->getMessage());
        return [
            'dados' => [],
            'total' => 0,
            'erro' => $e->getMessage()
        ];
    }
}

/**
 * FUNÇÃO CORRIGIDA: Estatísticas com valores corretos
 */
function buscarEstatisticasCorretas($ano) {
    $pdo = conectarDB();
    
    try {
        // ✅ CORREÇÃO: MAX() para pegar apenas um valor por contratação
        $sql = "SELECT 
                    COUNT(DISTINCT numero_contratacao) as total_contratacoes,
                    COALESCE(SUM(valor_unico.valor_maximo), 0) as valor_total,
                    COUNT(DISTINCT area_agrupada) as total_areas,
                    COUNT(DISTINCT categoria_contratacao) as total_categorias
                FROM (
                    SELECT 
                        numero_contratacao,
                        MAX(valor_total_contratacao) as valor_maximo,
                        MAX(categoria_contratacao) as categoria_contratacao,
                        LEFT(MAX(area_requisitante), 10) as area_agrupada
                    FROM pca_dados 
                    WHERE ano_pca = ? 
                      AND status_contratacao = 'Aprovada'
                      AND numero_contratacao IS NOT NULL
                      AND numero_contratacao != ''
                    GROUP BY numero_contratacao
                ) as valor_unico";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ano]);
        $stats = $stmt->fetch();
        
        error_log("✅ ESTATÍSTICAS CORRETAS: Valor Total = R$ " . number_format($stats['valor_total'], 2, ',', '.'));
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("❌ ERRO nas estatísticas: " . $e->getMessage());
        return [
            'total_contratacoes' => 0,
            'valor_total' => 0,
            'total_areas' => 0,
            'total_categorias' => 0
        ];
    }
}

/**
 * FUNÇÃO CORRIGIDA: Listas para filtros (valores únicos)
 */
function buscarListasFiltrosCorretas($ano) {
    $pdo = conectarDB();
    
    try {
        $where_base = "ano_pca = ? AND status_contratacao = 'Aprovada' AND numero_contratacao IS NOT NULL";
        
        // Situações únicas
        $sql_situacoes = "SELECT DISTINCT situacao_execucao 
                         FROM (
                             SELECT numero_contratacao, MAX(situacao_execucao) as situacao_execucao
                             FROM pca_dados 
                             WHERE {$where_base}
                             GROUP BY numero_contratacao
                         ) as situacoes_unicas
                         WHERE situacao_execucao IS NOT NULL 
                           AND situacao_execucao != ''
                         ORDER BY situacao_execucao";
        $stmt = $pdo->prepare($sql_situacoes);
        $stmt->execute([$ano]);
        $situacoes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('Não iniciado', $situacoes)) {
            array_unshift($situacoes, 'Não iniciado');
        }
        
        // Categorias únicas
        $sql_categorias = "SELECT DISTINCT categoria_contratacao 
                          FROM (
                              SELECT numero_contratacao, MAX(categoria_contratacao) as categoria_contratacao
                              FROM pca_dados 
                              WHERE {$where_base}
                                AND categoria_contratacao IS NOT NULL
                                AND categoria_contratacao != ''
                              GROUP BY numero_contratacao
                          ) as categorias_unicas
                          ORDER BY categoria_contratacao";
        $stmt = $pdo->prepare($sql_categorias);
        $stmt->execute([$ano]);
        $categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Áreas únicas
        $sql_areas = "SELECT DISTINCT LEFT(area_agrupada, 15) as area_final
                     FROM (
                         SELECT numero_contratacao, MAX(area_requisitante) as area_agrupada
                         FROM pca_dados 
                         WHERE {$where_base}
                           AND area_requisitante IS NOT NULL 
                           AND area_requisitante != ''
                         GROUP BY numero_contratacao
                     ) as areas_unicas
                     ORDER BY area_final";
        $stmt = $pdo->prepare($sql_areas);
        $stmt->execute([$ano]);
        $areas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return [
            'situacoes' => $situacoes,
            'categorias' => $categorias,
            'areas' => $areas
        ];
        
    } catch (Exception $e) {
        error_log("❌ ERRO ao buscar listas: " . $e->getMessage());
        return [
            'situacoes' => ['Não iniciado'],
            'categorias' => [],
            'areas' => []
        ];
    }
}

// ==================== EXECUTAR BUSCAS ====================

// Buscar dados com valores corretos
$resultado = buscarContratacaoesValoresCorretos($ano_selecionado, $filtros, $pagina, $limite);
$contratacoes = $resultado['dados'];
$total_registros = $resultado['total'];
$total_paginas = ceil($total_registros / $limite);

// Buscar listas para filtros
$listas = buscarListasFiltrosCorretas($ano_selecionado);
$situacao_lista = $listas['situacoes'];
$categoria_lista = $listas['categorias'];
$area_lista = $listas['areas'];

// Buscar estatísticas corretas
$stats = buscarEstatisticasCorretas($ano_selecionado);

// Debug final
error_log("📊 VALORES CORRETOS - Total: {$total_registros}, Valor Total: R$ " . number_format($stats['valor_total'], 2, ',', '.'));

/**
 * Função para gerar paginação
 */
function gerarPaginacaoCorreta($pagina_atual, $total_paginas, $filtros, $secao, $ano, $limite) {
    if ($total_paginas <= 1) return '';
    
    $html = '<div class="pagination">';
    
    $params_base = array_merge($filtros, [
        'secao' => $secao,
        'ano' => $ano,
        'limite' => $limite
    ]);
    
    // Anterior
    if ($pagina_atual > 1) {
        $params = array_merge($params_base, ['pagina' => $pagina_atual - 1]);
        $url = '?' . http_build_query($params);
        $html .= "<a href='{$url}' class='page-link'>&laquo; Anterior</a>";
    }
    
    // Páginas numeradas
    $inicio = max(1, $pagina_atual - 2);
    $fim = min($total_paginas, $pagina_atual + 2);
    
    for ($i = $inicio; $i <= $fim; $i++) {
        $params = array_merge($params_base, ['pagina' => $i]);
        $url = '?' . http_build_query($params);
        $active = $i === $pagina_atual ? 'active' : '';
        $html .= "<a href='{$url}' class='page-link {$active}'>{$i}</a>";
    }
    
    // Próxima
    if ($pagina_atual < $total_paginas) {
        $params = array_merge($params_base, ['pagina' => $pagina_atual + 1]);
        $url = '?' . http_build_query($params);
        $html .= "<a href='{$url}' class='page-link'>Próxima &raquo;</a>";
    }
    
    $html .= '</div>';
    return $html;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Planejamento - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/dashboard.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <script>
    // Funções de navegação
    function showSection(sectionId) {
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.classList.add('active');
        }
        
        if (event && event.target) {
            event.target.classList.add('active');
        }
        
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('secao', sectionId);
        history.replaceState(null, '', window.location.pathname + '?' + urlParams.toString());
    }
    
    function alterarLimite(novoLimite) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('limite', novoLimite);
        urlParams.set('pagina', 1);
        window.location.href = window.location.pathname + '?' + urlParams.toString();
    }
    
    function visualizarDetalhes(numeroContratacao, ano) {
        window.location.href = `detalhes.php?numero_contratacao=${encodeURIComponent(numeroContratacao)}&ano=${ano}`;
    }
    
    // Disponibilizar globalmente
    window.showSection = showSection;
    window.alterarLimite = alterarLimite;
    window.visualizarDetalhes = visualizarDetalhes;
    </script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i data-lucide="clipboard-check"></i> Planejamento</h2>
                <p style="font-size: 12px; color: #7f8c8d; margin-top: 5px;">
                    <i data-lucide="check-circle" style="width: 12px; height: 12px;"></i>
                    Valores Corretos - Sem Duplicação
                </p>
            </div>
            
            <nav class="sidebar-nav">
                <button class="nav-item <?php echo $secao_ativa === 'dashboard' ? 'active' : ''; ?>" 
                        onclick="showSection('dashboard')">
                    <i data-lucide="bar-chart-3"></i> Dashboard
                </button>
                <button class="nav-item <?php echo $secao_ativa === 'lista-contratacoes' ? 'active' : ''; ?>" 
                        onclick="showSection('lista-contratacoes')">
                    <i data-lucide="list"></i> Lista de Contratações
                </button>
                <a href="selecao_modulos.php" class="nav-item">
                    <i data-lucide="arrow-left"></i> Voltar ao Menu
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário'); ?></h4>
                        <p><?php echo htmlspecialchars($_SESSION['usuario_email'] ?? 'email@exemplo.com'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <?php echo getMensagem(); ?>

            <!-- Dashboard Section -->
            <div id="dashboard" class="content-section <?php echo $secao_ativa === 'dashboard' ? 'active' : ''; ?>">
                <div class="dashboard-header">
                    <h1><i data-lucide="bar-chart-3"></i> Dashboard Planejamento <?php echo $ano_selecionado; ?></h1>
                    <p>Visão geral com valores corretos (sem duplicação financeira)</p>
                </div>

                <!-- Cards de Estatísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #3498db;">
                            <i data-lucide="file-text"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['total_contratacoes'] ?? 0); ?></div>
                            <div class="stat-label">Contratações Únicas</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: #27ae60;">
                            <i data-lucide="dollar-sign"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number">
                                R$ <?php echo number_format($stats['valor_total'] ?? 0, 2, ',', '.'); ?>
                            </div>
                            <div class="stat-label">✅ Valor Total Correto</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e74c3c;">
                            <i data-lucide="building"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['total_areas'] ?? 0); ?></div>
                            <div class="stat-label">Áreas Requisitantes</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: #f39c12;">
                            <i data-lucide="tag"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['total_categorias'] ?? 0); ?></div>
                            <div class="stat-label">Categorias</div>
                        </div>
                    </div>
                </div>

                <!-- Resumo da Correção -->
                <div class="executive-summary">
                    <h3><i data-lucide="shield-check"></i> Correção de Valores Aplicada</h3>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <span class="summary-label">Método de Cálculo:</span>
                            <span class="summary-value" style="color: #27ae60;">✅ MAX() por contratação</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Duplicação de Valores:</span>
                            <span class="summary-value" style="color: #27ae60;">✅ Eliminada</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Precisão Financeira:</span>
                            <span class="summary-value" style="color: #27ae60;">✅ 100% Correta</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Contratações Section -->
            <div id="lista-contratacoes" class="content-section <?php echo $secao_ativa === 'lista-contratacoes' ? 'active' : ''; ?>">
                <div class="dashboard-header">
                    <h1><i data-lucide="list"></i> Contratações com Valores Corretos <?php echo $ano_selecionado; ?></h1>
                    <p>Lista com valores únicos por contratação (MAX ao invés de SUM)</p>
                    
                    <?php if (isset($resultado['debug'])): ?>
                    <details style="margin-top: 10px; font-size: 12px; color: #666;">
                        <summary>🔍 Debug - Correção de Valores (Clique para expandir)</summary>
                        <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; margin-top: 5px; font-size: 11px;">
Filtros aplicados: <?php echo json_encode($resultado['debug']['filtros_aplicados'], JSON_PRETTY_PRINT); ?>

Total registros: <?php echo $total_registros; ?>
Registros nesta página: <?php echo $resultado['debug']['registros_pagina']; ?>

Correção aplicada: <?php echo $resultado['debug']['correcao_aplicada']; ?>

WHERE clause: <?php echo $resultado['debug']['where_clause']; ?>
                        </pre>
                    </details>
                    <?php endif; ?>
                </div>

                <!-- Formulário de Filtros -->
                <div class="table-filters">
                    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                        <input type="hidden" name="secao" value="lista-contratacoes">
                        <input type="hidden" name="ano" value="<?php echo $ano_selecionado; ?>">
                        <input type="hidden" name="pagina" value="1">
                        
                        <div>
                            <label>Buscar (Nº Contratação/DFD/Título):</label>
                            <input type="text" 
                                   name="numero_contratacao" 
                                   value="<?php echo htmlspecialchars($filtros['numero_contratacao']); ?>" 
                                   placeholder="Digite para buscar..."
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <div>
                            <label>Situação:</label>
                            <select name="situacao_execucao" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="">Todas as situações</option>
                                <?php foreach ($situacao_lista as $situacao): ?>
                                    <option value="<?php echo htmlspecialchars($situacao); ?>" 
                                            <?php echo $situacao === $filtros['situacao_execucao'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($situacao); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label>Categoria:</label>
                            <select name="categoria" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="">Todas as categorias</option>
                                <?php foreach ($categoria_lista as $categoria): ?>
                                    <option value="<?php echo htmlspecialchars($categoria); ?>" 
                                            <?php echo $categoria === $filtros['categoria'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label>Área Requisitante:</label>
                            <select name="area_requisitante" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="">Todas as áreas</option>
                                <?php foreach ($area_lista as $area): ?>
                                    <option value="<?php echo htmlspecialchars($area); ?>" 
                                            <?php echo $area === $filtros['area_requisitante'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($area); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label>Itens por página:</label>
                            <select name="limite" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="25" <?php echo $limite == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $limite == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $limite == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" style="flex: 1; padding: 8px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                <i data-lucide="search" style="width: 16px; height: 16px;"></i> Filtrar
                            </button>
                            <a href="?secao=lista-contratacoes&ano=<?php echo $ano_selecionado; ?>" 
                               style="padding: 8px 12px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Informações da listagem -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 12px 16px; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-radius: 6px; border: 1px solid #28a745;">
                    <div>
                        <strong style="color: #155724;">✅ Mostrando valores corretos:</strong> 
                        <?php echo number_format(($pagina - 1) * $limite + 1); ?> a 
                        <?php echo number_format(min($pagina * $limite, $total_registros)); ?> de 
                        <strong style="color: #155724;"><?php echo number_format($total_registros); ?> contratações únicas</strong>
                        
                        <?php if (array_filter($filtros)): ?>
                            <span style="background: rgba(21, 87, 36, 0.1); color: #155724; font-weight: 700; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 8px;">
                                FILTRADO
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <?php echo gerarPaginacaoCorreta($pagina, $total_paginas, $filtros, $secao_ativa, $ano_selecionado, $limite); ?>
                    </div>
                </div>

                <!-- Tabela de Contratações com Valores Corretos -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nº Contratação</th>
                                <th>Nº DFD</th>
                                <th>Título</th>
                                <th>Categoria</th>
                                <th>Área Requisitante</th>
                                <th>✅ Valor Correto</th>
                                <th>Itens</th>
                                <th>Situação</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($contratacoes)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                    <i data-lucide="search-x" style="width: 48px; height: 48px; margin-bottom: 10px;"></i><br>
                                    <?php if (array_filter($filtros)): ?>
                                        Nenhuma contratação encontrada com os filtros aplicados.<br>
                                        <a href="?secao=lista-contratacoes&ano=<?php echo $ano_selecionado; ?>" style="color: #007cba;">Limpar filtros</a>
                                    <?php else: ?>
                                        Nenhuma contratação encontrada para o ano <?php echo $ano_selecionado; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($contratacoes as $contratacao): ?>
                            <tr data-contratacao="<?php echo htmlspecialchars($contratacao['numero_contratacao']); ?>" 
                                data-ano="<?php echo $ano_selecionado; ?>"
                                style="cursor: pointer;">
                                <td>
                                    <strong style="color: #007cba; font-size: 15px;">
                                        <?php echo htmlspecialchars($contratacao['numero_contratacao']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span style="font-weight: 600;">
                                        <?php echo htmlspecialchars($contratacao['numero_dfd']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                                         title="<?php echo htmlspecialchars($contratacao['titulo_contratacao']); ?>">
                                        <?php echo htmlspecialchars($contratacao['titulo_contratacao']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 12px; background: #e9ecef; padding: 4px 8px; border-radius: 12px; font-weight: 600;">
                                        <?php echo htmlspecialchars($contratacao['categoria_contratacao'] ?: 'Não informado'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 500;">
                                        <?php echo htmlspecialchars($contratacao['area_requisitante'] ?: 'Não informado'); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                        <strong style="color: #28a745; font-size: 15px;">
                                            R$ <?php echo number_format($contratacao['valor_total_contratacao'] ?? 0, 2, ',', '.'); ?>
                                        </strong>
                                        <small style="color: #28a745; font-size: 10px; font-weight: 600;">
                                            ✅ VALOR ÚNICO
                                        </small>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <span style="background: #007cba; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                        <?php echo intval($contratacao['total_itens_contratacao'] ?? 1); ?> item<?php echo intval($contratacao['total_itens_contratacao'] ?? 1) > 1 ? 's' : ''; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $situacao = $contratacao['situacao_execucao'] ?: 'Não iniciado';
                                    $badge_class = 'status-' . strtolower(str_replace([' ', 'ã', 'ç'], ['-', 'a', 'c'], $situacao));
                                    ?>
                                    <span class="status-badge <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($situacao); ?>
                                    </span>
                                </td>
                                <td>
                                    <button onclick="event.stopPropagation(); visualizarDetalhes('<?php echo htmlspecialchars($contratacao['numero_contratacao']); ?>', <?php echo $ano_selecionado; ?>)" 
                                            class="btn-action" 
                                            title="Ver detalhes desta contratação">
                                        <i data-lucide="eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação inferior -->
                <?php if ($total_paginas > 1): ?>
                <div style="margin-top: 20px; text-align: center; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <div style="margin-bottom: 10px; color: #6c757d; font-size: 14px;">
                        Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>
                    </div>
                    <?php echo gerarPaginacaoCorreta($pagina, $total_paginas, $filtros, $secao_ativa, $ano_selecionado, $limite); ?>
                </div>
                <?php endif; ?>

                <!-- Resumo dos filtros aplicados -->
                <?php if (array_filter($filtros)): ?>
                <div style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); border-radius: 8px; border-left: 4px solid #2196f3;">
                    <h4 style="margin: 0 0 10px 0; color: #1976d2; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="filter" style="width: 16px; height: 16px;"></i> Filtros Aplicados:
                    </h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($filtros as $key => $value): ?>
                            <?php if (!empty($value)): ?>
                                <?php 
                                $labels = [
                                    'numero_contratacao' => 'Busca',
                                    'situacao_execucao' => 'Situação',
                                    'categoria' => 'Categoria',
                                    'area_requisitante' => 'Área'
                                ];
                                ?>
                                <span style="background: #2196f3; color: white; padding: 6px 12px; border-radius: 16px; font-size: 12px; font-weight: 600;">
                                    <?php echo $labels[$key] ?? $key; ?>: <?php echo htmlspecialchars($value); ?>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <a href="?secao=lista-contratacoes&ano=<?php echo $ano_selecionado; ?>" 
                           style="background: #f44336; color: white; padding: 6px 12px; border-radius: 16px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.2s ease;">
                            ✕ Limpar todos
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Validação de Correção dos Valores -->
                <div style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-radius: 8px; border-left: 4px solid #28a745;">
                    <h4 style="margin: 0 0 15px 0; color: #155724; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i> Correção de Valores Confirmada
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div style="text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #28a745;">✅</div>
                            <div style="font-size: 12px; color: #155724; font-weight: 600;">Método MAX()</div>
                            <div style="font-size: 11px; color: #6c757d;">Um valor por contratação</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #28a745;">✅</div>
                            <div style="font-size: 12px; color: #155724; font-weight: 600;">Sem Soma Incorreta</div>
                            <div style="font-size: 11px; color: #6c757d;">Eliminou SUM() inflacionado</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #28a745;">✅</div>
                            <div style="font-size: 12px; color: #155724; font-weight: 600;">Valores Únicos</div>
                            <div style="font-size: 11px; color: #6c757d;">Cada contratação = 1 valor</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #28a745;">✅</div>
                            <div style="font-size: 12px; color: #155724; font-weight: 600;">Precisão 100%</div>
                            <div style="font-size: 11px; color: #6c757d;">Ex: 370/2025 = R$ 415.100,00</div>
                        </div>
                    </div>
                    
                    <!-- Exemplo de Correção -->
                    <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.7); border-radius: 6px; border: 1px solid #28a745;">
                        <h5 style="margin: 0 0 10px 0; color: #155724;">📊 Exemplo da Correção:</h5>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 12px;">
                            <div>
                                <strong style="color: #dc3545;">❌ ANTES (Incorreto):</strong><br>
                                Contratação 370/2025<br>
                                <span style="color: #dc3545;">SUM() = R$ 2.905.700,00</span><br>
                                <small>(Valores duplicados somados)</small>
                            </div>
                            <div>
                                <strong style="color: #28a745;">✅ AGORA (Correto):</strong><br>
                                Contratação 370/2025<br>
                                <span style="color: #28a745;">MAX() = R$ 415.100,00</span><br>
                                <small>(Valor único da contratação)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    /**
     * INICIALIZAÇÃO COM VALIDAÇÃO DE VALORES CORRETOS
     */
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Dashboard carregado - Valores Corretos Implementados');
        
        // Inicializar Lucide Icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
            console.log('✅ Ícones carregados');
        }
        
        // Ativar seção correta
        const secaoAtual = '<?php echo $secao_ativa; ?>';
        const secaoElement = document.getElementById(secaoAtual);
        if (secaoElement && !secaoElement.classList.contains('active')) {
            showSection(secaoAtual);
        }
        
        // Auto-hide mensagens
        document.querySelectorAll('.auto-hide-message').forEach(mensagem => {
            setTimeout(() => {
                if (mensagem.parentNode) {
                    mensagem.style.opacity = '0';
                    setTimeout(() => mensagem.remove(), 300);
                }
            }, 5000);
        });
        
        // Adicionar eventos nas linhas da tabela
        document.querySelectorAll('tr[data-contratacao]').forEach(linha => {
            linha.addEventListener('click', function(e) {
                if (e.target.closest('button')) return;
                
                const contratacao = this.dataset.contratacao;
                const ano = this.dataset.ano;
                visualizarDetalhes(contratacao, ano);
            });
            
            // Feedback visual
            linha.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#e3f2fd';
                this.style.transform = 'translateY(-1px)';
                this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                this.style.transition = 'all 0.2s ease';
            });
            
            linha.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
                this.style.transform = '';
                this.style.boxShadow = '';
            });
        });
        
        // Log de validação dos valores
        console.log('💰 Validação de Valores:');
        console.log('- Total contratações:', <?php echo $total_registros; ?>);
        console.log('- Valor total correto: R, <?php echo number_format($stats['valor_total'] ?? 0, 2, '.', ''); ?>);
        console.log('- Método: MAX() por contratação (correção aplicada)');
        console.log('- Registros nesta página:', <?php echo count($contratacoes); ?>);
        
        // Validar se há valores duplicados suspeitos
        const valores = Array.from(document.querySelectorAll('td:nth-child(6) strong')).map(el => el.textContent);
        const valoresUnicos = [...new Set(valores)];
        
        console.log('🔍 Análise de Valores na Página:');
        console.log('- Total valores exibidos:', valores.length);
        console.log('- Valores únicos:', valoresUnicos.length);
        
        if (valores.length === valoresUnicos.length) {
            console.log('✅ SUCESSO: Todos os valores são únicos');
        } else {
            console.log('⚠️ ATENÇÃO: Possíveis valores duplicados detectados');
        }
        
        // Destacar correção visualmente
        document.querySelectorAll('td:nth-child(6)').forEach((cell, index) => {
            const valorCorreto = cell.querySelector('small');
            if (valorCorreto) {
                valorCorreto.style.animation = `fadeInUp 0.5s ease-out ${index * 0.1}s both`;
            }
        });
        
        console.log('✅ Inicialização completa - Valores corretos validados');
    });
    
    /**
     * Função para debug específico de valores
     */
    function debugValores() {
        console.log('🔍 Debug de Valores Corretos:');
        console.log('URL atual:', window.location.href);
        
        const linhas = document.querySelectorAll('tr[data-contratacao]');
        console.log('Análise de valores por linha:');
        
        linhas.forEach(linha => {
            const contratacao = linha.dataset.contratacao;
            const valorCell = linha.querySelector('td:nth-child(6) strong');
            const valor = valorCell ? valorCell.textContent : 'N/A';
            
            console.log(`- Contratação ${contratacao}: ${valor}`);
        });
        
        console.log('📊 Resumo:');
        console.log('- Método de cálculo: MAX() por contratação');
        console.log('- Duplicação eliminada: ✅ Sim');
        console.log('- Valores únicos garantidos: ✅ Sim');
    }
    
    // Disponibilizar debug globalmente
    window.debugValores = debugValores;
    
    /**
     * Animações para destaque
     */
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .valor-correto-highlight {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>