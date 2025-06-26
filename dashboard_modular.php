<?php
// ========================================
// DASHBOARD.PHP - CONTROLLER PRINCIPAL
// Sistema de Licitações - Lei 14133/2021
// Versão Modular para melhor manutenção
// ========================================

require_once 'config.php';
require_once 'functions.php';
require_once 'cache.php';
require_once 'pagination.php';

// Importar módulos específicos do dashboard
require_once 'modules/dashboard_data.php';      // Lógica de dados e queries
require_once 'modules/dashboard_filters.php';   // Filtros e paginação
require_once 'modules/dashboard_stats.php';     // Estatísticas e cálculos

// ========================================
// CONFIGURAÇÃO INICIAL
// ========================================

verificarLogin();
$pdo = conectarDB();

// Configurar ambiente para desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ========================================
// PROCESSAMENTO DE PARÂMETROS
// ========================================

// Ano selecionado e seção ativa
$ano_selecionado = intval($_GET['ano'] ?? 2025);
$secao_ativa = $_GET['secao'] ?? 'dashboard';

// Buscar anos disponíveis
$anos_disponiveis = buscarAnosDisponiveis($pdo);

// Validar ano selecionado
if (!in_array($ano_selecionado, $anos_disponiveis)) {
    $ano_selecionado = 2025;
}

// Determinar tipo de ano
$eh_historico = isAnoHistorico($ano_selecionado);
$eh_ano_atual = !$eh_historico;

// ========================================
// PROCESSAMENTO DE DADOS
// ========================================

try {
    // Calcular estatísticas (apenas contratações aprovadas)
    $stats = calcularEstatisticasAprovadas($pdo, $ano_selecionado);
    
    // Configurar filtros e paginação
    $filtros = configurarFiltros($_GET);
    $paginacao = configurarPaginacao($_GET);
    
    // Buscar listas para filtros
    $listas_filtros = buscarListasFiltros($pdo, $ano_selecionado);
    
    // Buscar dados da listagem principal
    $resultado_listagem = buscarContratacoes($pdo, $ano_selecionado, $filtros, $paginacao);
    $dados = $resultado_listagem['dados'];
    $total_registros = $resultado_listagem['total'];
    
    // Criar objeto de paginação
    $pagination = createPagination($total_registros, $paginacao['pagina'], $paginacao['limite'], $_GET);
    
    // Buscar dados complementares (usar funções existentes do functions.php)
    $historico_importacoes = buscarHistoricoImportacoes($ano_selecionado, 10);
    $atualizacoes_recentes = buscarAtualizacoesRecentes(15);
    
    // Log das estatísticas principais
    error_log("=== DASHBOARD CARREGADO ===");
    error_log("Ano: {$ano_selecionado}");
    error_log("Total contratações aprovadas: {$stats['total_contratacoes']}");
    error_log("Valor total: R$ " . number_format($stats['valor_total'], 2, ',', '.'));
    error_log("Registros na listagem: " . count($dados));
    
} catch (Exception $e) {
    error_log("Erro no controller do dashboard: " . $e->getMessage());
    
    // Valores padrão em caso de erro
    $stats = getStatsDefault();
    $dados = [];
    $total_registros = 0;
    $listas_filtros = getListasFiltrosDefault();
    $pagination = createPagination(0, 1, 20, $_GET);
    
    // Adicionar mensagem de erro
    adicionarMensagem('Erro ao carregar dados do dashboard. Tente novamente.', 'erro');
}

// ========================================
// INCLUIR VIEW (TEMPLATE)
// ========================================

// Incluir o template de exibição
include 'templates/dashboard_view.php';

// ========================================
// FUNÇÕES AUXILIARES DO CONTROLLER
// ========================================

/**
 * Buscar anos disponíveis no sistema
 */
function buscarAnosDisponiveis($pdo) {
    try {
        $query = "SELECT DISTINCT ano_pca FROM pca_dados WHERE ano_pca IS NOT NULL ORDER BY ano_pca DESC";
        $result = $pdo->query($query);
        $anos = [];
        
        while ($row = $result->fetch()) {
            $anos[] = $row['ano_pca'];
        }
        
        // Se não houver dados, usar anos padrão
        return !empty($anos) ? $anos : [2025, 2026];
        
    } catch (Exception $e) {
        error_log("Erro ao buscar anos disponíveis: " . $e->getMessage());
        return [2025];
    }
}

/**
 * Configurar filtros da requisição
 */
function configurarFiltros($get_params) {
    return [
        'numero_contratacao' => $get_params['numero_contratacao'] ?? '',
        'situacao_execucao' => $get_params['situacao_execucao'] ?? '',
        'categoria' => $get_params['categoria'] ?? '',
        'area_requisitante' => $get_params['area_requisitante'] ?? ''
    ];
}

/**
 * Configurar paginação da requisição
 */
function configurarPaginacao($get_params) {
    return [
        'limite' => intval($get_params['limite'] ?? 20),
        'pagina' => intval($get_params['pagina'] ?? 1)
    ];
}

/**
 * Valores padrão para estatísticas em caso de erro
 */
function getStatsDefault() {
    return [
        'total_dfds' => 0,
        'total_contratacoes' => 0,
        'valor_total' => 0,
        'homologadas' => 0,
        'atrasadas_inicio' => 0,
        'atrasadas_conclusao' => 0,
        'pendentes' => 0,
        'percentual_conclusao' => 0
    ];
}

/**
 * Valores padrão para listas de filtros
 */
function getListasFiltrosDefault() {
    return [
        'situacoes' => ['Não iniciado'],
        'categorias' => [],
        'areas' => []
    ];
}

?>