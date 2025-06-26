<?php
// ========================================
// MÓDULO DE DADOS DO DASHBOARD
// Responsável por todas as queries e manipulação de dados
// Apenas contratações aprovadas conforme Lei 14133/2021
// ========================================

/**
 * Buscar contratações aprovadas com filtros e paginação
 * 
 * @param PDO $pdo Conexão com banco
 * @param int $ano Ano do PCA
 * @param array $filtros Filtros aplicados
 * @param array $paginacao Configuração de paginação
 * @return array Dados e total de registros
 */
function buscarContratacoes($pdo, $ano, $filtros, $paginacao) {
    try {
        // Filtro base: apenas contratações aprovadas
        $where_ano = "ano_pca = {$ano} AND status_contratacao = 'Aprovada' AND";
        
        // Construir filtros WHERE
        $where_conditions = [];
        $params = [];
        
        // Filtro por número de contratação/DFD
        if (!empty($filtros['numero_contratacao'])) {
            $where_conditions[] = "(p.numero_dfd LIKE ? OR p.numero_contratacao LIKE ?)";
            $params[] = '%' . $filtros['numero_contratacao'] . '%';
            $params[] = '%' . $filtros['numero_contratacao'] . '%';
        }
        
        // Filtro por situação de execução
        if (!empty($filtros['situacao_execucao'])) {
            if ($filtros['situacao_execucao'] === 'Não iniciado') {
                $where_conditions[] = "(p.situacao_execucao IS NULL OR p.situacao_execucao = '' OR p.situacao_execucao = 'Não iniciado')";
            } else {
                $where_conditions[] = "p.situacao_execucao = ?";
                $params[] = $filtros['situacao_execucao'];
            }
        }
        
        // Filtro por categoria
        if (!empty($filtros['categoria'])) {
            $where_conditions[] = "p.categoria_contratacao = ?";
            $params[] = $filtros['categoria'];
        }
        
        // Filtro por área requisitante
        if (!empty($filtros['area_requisitante'])) {
            if ($filtros['area_requisitante'] === 'GM.') {
                $where_conditions[] = "(p.area_requisitante LIKE 'GM%' OR p.area_requisitante LIKE 'GM.%')";
            } else {
                $where_conditions[] = "p.area_requisitante LIKE ?";
                $params[] = $filtros['area_requisitante'] . '%';
            }
        }
        
        // Construir WHERE clause final
        $where_clause = '';
        if ($where_conditions) {
            $where_clause = 'AND ' . implode(' AND ', $where_conditions);
        }
        
        // 1. Contar total de registros únicos
        $sql_count = "SELECT COUNT(*) as total 
                      FROM (
                          SELECT DISTINCT numero_dfd, numero_contratacao
                          FROM pca_dados p 
                          WHERE {$where_ano} numero_dfd IS NOT NULL 
                            AND numero_dfd != ''
                            AND numero_contratacao IS NOT NULL
                            AND numero_contratacao != ''
                            {$where_clause}
                      ) as contagem";
        
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute($params);
        $total_registros = $stmt_count->fetch()['total'];
        
        // 2. Buscar dados com paginação
        $sql = "SELECT 
                p.numero_contratacao,
                p.numero_dfd,
                p.status_contratacao,
                p.titulo_contratacao,
                p.categoria_contratacao,
                p.uasg_atual,
                p.valor_total_contratacao,
                p.area_requisitante,
                p.prioridade,
                p.situacao_execucao,
                p.data_inicio_processo,
                p.data_conclusao_processo,
                DATEDIFF(p.data_conclusao_processo, CURDATE()) as dias_ate_conclusao,
                COUNT(todos.id) as qtd_itens_pca,
                GROUP_CONCAT(todos.id) as ids,
                p.id,
                COUNT(DISTINCT l.id) > 0 as tem_licitacao
                
        FROM (
            -- Subconsulta para contratações aprovadas únicas
            SELECT 
                numero_dfd,
                numero_contratacao,
                MIN(id) as id_representativo
            FROM pca_dados 
            WHERE {$where_ano} numero_dfd IS NOT NULL 
              AND numero_dfd != ''
              AND numero_contratacao IS NOT NULL
              AND numero_contratacao != ''
            GROUP BY numero_dfd, numero_contratacao
        ) as unicos
        
        INNER JOIN pca_dados p ON p.id = unicos.id_representativo
        LEFT JOIN pca_dados todos ON todos.numero_dfd = p.numero_dfd 
            AND todos.numero_contratacao = p.numero_contratacao
            AND todos.status_contratacao = 'Aprovada'
        LEFT JOIN licitacoes l ON l.pca_dados_id = p.id
        
        WHERE p.status_contratacao = 'Aprovada' {$where_clause}
        
        GROUP BY p.numero_dfd, p.numero_contratacao
        ORDER BY p.numero_dfd DESC
        LIMIT ? OFFSET ?";
        
        // Adicionar parâmetros de paginação
        $params[] = $paginacao['limite'];
        $params[] = ($paginacao['pagina'] - 1) * $paginacao['limite'];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetchAll();
        
        return [
            'dados' => $dados,
            'total' => $total_registros
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao buscar contratações: " . $e->getMessage());
        return [
            'dados' => [],
            'total' => 0
        ];
    }
}

/**
 * Buscar listas para os filtros (apenas contratações aprovadas)
 * 
 * @param PDO $pdo Conexão com banco
 * @param int $ano Ano do PCA
 * @return array Listas para filtros
 */
function buscarListasFiltros($pdo, $ano) {
    try {
        $where_ano = "ano_pca = {$ano} AND status_contratacao = 'Aprovada' AND";
        
        // Situações de execução
        $situacao_sql = "SELECT DISTINCT situacao_execucao 
                         FROM pca_dados 
                         WHERE {$where_ano} situacao_execucao IS NOT NULL 
                           AND situacao_execucao != ''
                         ORDER BY situacao_execucao";
        $situacoes = $pdo->query($situacao_sql)->fetchAll(PDO::FETCH_COLUMN);
        
        // Adicionar "Não iniciado" se não estiver na lista
        if (!in_array('Não iniciado', $situacoes)) {
            array_unshift($situacoes, 'Não iniciado');
        }
        
        // Categorias de contratação
        $categoria_sql = "SELECT DISTINCT categoria_contratacao 
                          FROM pca_dados 
                          WHERE {$where_ano} categoria_contratacao IS NOT NULL
                          ORDER BY categoria_contratacao";
        $categorias = $pdo->query($categoria_sql)->fetchAll(PDO::FETCH_COLUMN);
        
        // Áreas requisitantes (agrupadas)
        $areas_sql = "SELECT DISTINCT area_requisitante 
                      FROM pca_dados 
                      WHERE {$where_ano} area_requisitante IS NOT NULL 
                        AND area_requisitante != ''
                      ORDER BY area_requisitante";
        $areas_result = $pdo->query($areas_sql);
        $areas_agrupadas = [];
        
        while ($row = $areas_result->fetch()) {
            $area_agrupada = agruparArea($row['area_requisitante']);
            if (!in_array($area_agrupada, $areas_agrupadas)) {
                $areas_agrupadas[] = $area_agrupada;
            }
        }
        sort($areas_agrupadas);
        
        return [
            'situacoes' => $situacoes,
            'categorias' => $categorias,
            'areas' => $areas_agrupadas
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao buscar listas de filtros: " . $e->getMessage());
        return [
            'situacoes' => ['Não iniciado'],
            'categorias' => [],
            'areas' => []
        ];
    }
}

/**
 * Buscar dados para gráficos do dashboard
 * 
 * @param PDO $pdo Conexão com banco
 * @param int $ano Ano do PCA
 * @return array Dados para gráficos
 */
function buscarDadosGraficos($pdo, $ano) {
    try {
        $where_ano = "ano_pca = {$ano} AND status_contratacao = 'Aprovada' AND";
        
        // Dados por categoria
        $categorias = $pdo->query("
            SELECT 
                categoria_contratacao,
                COUNT(*) as quantidade,
                SUM(valor_unico.valor_total_contratacao) as valor_total
            FROM (
                SELECT DISTINCT numero_dfd, numero_contratacao, categoria_contratacao, valor_total_contratacao
                FROM pca_dados 
                WHERE {$where_ano} numero_dfd IS NOT NULL 
                  AND numero_dfd != ''
                  AND categoria_contratacao IS NOT NULL
            ) as valor_unico
            GROUP BY categoria_contratacao
            ORDER BY valor_total DESC
        ")->fetchAll();
        
        // Dados por área
        $areas = $pdo->query("
            SELECT 
                area_agrupada,
                COUNT(*) as quantidade,
                SUM(valor_total_contratacao) as valor_total
            FROM (
                SELECT DISTINCT 
                    numero_dfd, 
                    numero_contratacao, 
                    valor_total_contratacao,
                    CASE 
                        WHEN area_requisitante LIKE 'GM%' THEN 'GM.'
                        WHEN area_requisitante LIKE 'SE%' THEN 'SE.'
                        ELSE LEFT(area_requisitante, LOCATE('.', area_requisitante))
                    END as area_agrupada
                FROM pca_dados 
                WHERE {$where_ano} numero_dfd IS NOT NULL 
                  AND numero_dfd != ''
                  AND area_requisitante IS NOT NULL
            ) as dados_agrupados
            GROUP BY area_agrupada
            ORDER BY valor_total DESC
        ")->fetchAll();
        
        // Dados por situação
        $situacoes = $pdo->query("
            SELECT 
                COALESCE(situacao_execucao, 'Não iniciado') as situacao,
                COUNT(*) as quantidade,
                SUM(valor_total_contratacao) as valor_total
            FROM (
                SELECT DISTINCT numero_dfd, numero_contratacao, situacao_execucao, valor_total_contratacao
                FROM pca_dados 
                WHERE {$where_ano} numero_dfd IS NOT NULL 
                  AND numero_dfd != ''
            ) as valor_unico
            GROUP BY situacao_execucao
            ORDER BY quantidade DESC
        ")->fetchAll();
        
        return [
            'categorias' => $categorias,
            'areas' => $areas,
            'situacoes' => $situacoes
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao buscar dados para gráficos: " . $e->getMessage());
        return [
            'categorias' => [],
            'areas' => [],
            'situacoes' => []
        ];
    }
}

/**
 * Buscar detalhes de uma contratação específica
 * 
 * @param PDO $pdo Conexão com banco
 * @param string $numero_dfd Número do DFD
 * @param int $ano Ano do PCA
 * @return array|null Detalhes da contratação
 */
function buscarDetalhesContratacao($pdo, $numero_dfd, $ano) {
    try {
        $sql = "SELECT 
                    p.*,
                    COUNT(todos.id) as total_itens,
                    l.id as licitacao_id,
                    l.nup,
                    l.modalidade,
                    l.situacao as situacao_licitacao
                FROM pca_dados p
                LEFT JOIN pca_dados todos ON todos.numero_dfd = p.numero_dfd 
                    AND todos.numero_contratacao = p.numero_contratacao
                    AND todos.status_contratacao = 'Aprovada'
                LEFT JOIN licitacoes l ON l.pca_dados_id = p.id
                WHERE p.numero_dfd = ?
                  AND p.ano_pca = ?
                  AND p.status_contratacao = 'Aprovada'
                GROUP BY p.numero_dfd, p.numero_contratacao
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$numero_dfd, $ano]);
        
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Erro ao buscar detalhes da contratação: " . $e->getMessage());
        return null;
    }
}

?>