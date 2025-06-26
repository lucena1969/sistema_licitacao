<?php
// ========================================
// MÓDULO DE ESTATÍSTICAS DO DASHBOARD
// Responsável por cálculos e estatísticas
// Apenas contratações aprovadas conforme Lei 14133/2021
// ========================================

/**
 * Calcular todas as estatísticas para contratações aprovadas
 * 
 * @param PDO $pdo Conexão com banco
 * @param int $ano Ano do PCA
 * @return array Estatísticas calculadas
 */
function calcularEstatisticasAprovadas($pdo, $ano) {
    try {
        // Filtro base: apenas contratações aprovadas
        $where_base = "ano_pca = {$ano} AND status_contratacao = 'Aprovada'";
        
        $stats = [];
        
        // 1. Total de contratações aprovadas únicas
        $stats['total_contratacoes'] = calcularTotalContratacoes($pdo, $where_base);
        
        // 2. Total de DFDs (igual ao total de contratações para aprovadas)
        $stats['total_dfds'] = $stats['total_contratacoes'];
        
        // 3. Valor total sem duplicações
        $stats['valor_total'] = calcularValorTotal($pdo, $where_base);
        
        // 4. Contratações por situação
        $situacoes = calcularPorSituacao($pdo, $where_base);
        $stats['homologadas'] = $situacoes['Concluído'] ?? 0;
        $stats['em_andamento'] = $situacoes['Em andamento'] ?? 0;
        $stats['nao_iniciadas'] = $situacoes['Não iniciado'] ?? 0;
        
        // 5. Contratações atrasadas
        $atrasos = calcularAtrasos($pdo, $where_base);
        $stats['atrasadas_inicio'] = $atrasos['inicio'];
        $stats['atrasadas_conclusao'] = $atrasos['conclusao'];
        
        // 6. Campos derivados
        $stats['pendentes'] = $stats['total_contratacoes'] - $stats['homologadas'];
        $stats['percentual_conclusao'] = $stats['total_contratacoes'] > 0 ? 
            round(($stats['homologadas'] / $stats['total_contratacoes']) * 100, 1) : 0;
        
        // 7. Estatísticas por período
        $stats['por_trimestre'] = calcularPorTrimestre($pdo, $where_base);
        
        // 8. Top categorias
        $stats['top_categorias'] = calcularTopCategorias($pdo, $where_base, 5);
        
        // 9. Top áreas
        $stats['top_areas'] = calcularTopAreas($pdo, $where_base, 5);
        
        // Log das estatísticas calculadas
        error_log("=== ESTATÍSTICAS CALCULADAS (APROVADAS) ===");
        error_log("Total contratações: {$stats['total_contratacoes']}");
        error_log("Valor total: R$ " . number_format($stats['valor_total'], 2, ',', '.'));
        error_log("Homologadas: {$stats['homologadas']}");
        error_log("Atrasadas: " . ($stats['atrasadas_inicio'] + $stats['atrasadas_conclusao']));
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Erro ao calcular estatísticas: " . $e->getMessage());
        
        // Retornar valores padrão em caso de erro
        return [
            'total_dfds' => 0,
            'total_contratacoes' => 0,
            'valor_total' => 0,
            'homologadas' => 0,
            'em_andamento' => 0,
            'nao_iniciadas' => 0,
            'atrasadas_inicio' => 0,
            'atrasadas_conclusao' => 0,
            'pendentes' => 0,
            'percentual_conclusao' => 0,
            'por_trimestre' => [],
            'top_categorias' => [],
            'top_areas' => []
        ];
    }
}

/**
 * Calcular total de contratações aprovadas únicas
 */
function calcularTotalContratacoes($pdo, $where_base) {
    $sql = "SELECT COUNT(*) FROM (
                SELECT DISTINCT numero_dfd, numero_contratacao
                FROM pca_dados 
                WHERE {$where_base}
                  AND numero_dfd IS NOT NULL 
                  AND numero_dfd != ''
                  AND numero_contratacao IS NOT NULL
                  AND numero_contratacao != ''
            ) as unicos";
    
    return $pdo->query($sql)->fetchColumn();
}

/**
 * Calcular valor total sem duplicações
 */
function calcularValorTotal($pdo, $where_base) {
    $sql = "SELECT COALESCE(SUM(valor_unico.valor_total_contratacao), 0) as valor_total
            FROM (
                SELECT DISTINCT 
                    numero_dfd, 
                    numero_contratacao,
                    valor_total_contratacao
                FROM pca_dados 
                WHERE {$where_base}
                  AND numero_dfd IS NOT NULL 
                  AND numero_dfd != ''
                  AND numero_contratacao IS NOT NULL
                  AND numero_contratacao != ''
                  AND valor_total_contratacao IS NOT NULL
                  AND valor_total_contratacao > 0
            ) as valor_unico";
    
    return $pdo->query($sql)->fetchColumn();
}

/**
 * Calcular distribuição por situação de execução
 */
function calcularPorSituacao($pdo, $where_base) {
    $sql = "SELECT 
                COALESCE(situacao_execucao, 'Não iniciado') as situacao,
                COUNT(*) as quantidade
            FROM (
                SELECT DISTINCT numero_dfd, numero_contratacao, situacao_execucao
                FROM pca_dados 
                WHERE {$where_base}
                  AND numero_dfd IS NOT NULL 
                  AND numero_dfd != ''
                  AND numero_contratacao IS NOT NULL
            ) as unicos
            GROUP BY situacao_execucao";
    
    $result = $pdo->query($sql)->fetchAll();
    $situacoes = [];
    
    foreach ($result as $row) {
        $situacoes[$row['situacao']] = $row['quantidade'];
    }
    
    return $situacoes;
}

/**
 * Calcular contratações atrasadas
 */
function calcularAtrasos($pdo, $where_base) {
    // Atrasadas no início
    $sql_inicio = "SELECT COUNT(*) FROM (
                       SELECT DISTINCT numero_dfd, numero_contratacao
                       FROM pca_dados 
                       WHERE {$where_base}
                         AND numero_dfd IS NOT NULL 
                         AND numero_dfd != ''
                         AND numero_contratacao IS NOT NULL
                         AND data_inicio_processo < CURDATE() 
                         AND (situacao_execucao IS NULL OR situacao_execucao = 'Não iniciado')
                   ) as unicos";
    
    $atrasadas_inicio = $pdo->query($sql_inicio)->fetchColumn();
    
    // Atrasadas na conclusão
    $sql_conclusao = "SELECT COUNT(*) FROM (
                          SELECT DISTINCT numero_dfd, numero_contratacao
                          FROM pca_dados 
                          WHERE {$where_base}
                            AND numero_dfd IS NOT NULL 
                            AND numero_dfd != ''
                            AND numero_contratacao IS NOT NULL
                            AND data_conclusao_processo < CURDATE() 
                            AND situacao_execucao != 'Concluído'
                      ) as unicos";
    
    $atrasadas_conclusao = $pdo->query($sql_conclusao)->fetchColumn();
    
    return [
        'inicio' => $atrasadas_inicio,
        'conclusao' => $atrasadas_conclusao
    ];
}

/**
 * Calcular estatísticas por trimestre
 */
function calcularPorTrimestre($pdo, $where_base) {
    $sql = "SELECT 
                CASE 
                    WHEN MONTH(data_conclusao_processo) IN (1,2,3) THEN 'Q1'
                    WHEN MONTH(data_conclusao_processo) IN (4,5,6) THEN 'Q2'
                    WHEN MONTH(data_conclusao_processo) IN (7,8,9) THEN 'Q3'
                    WHEN MONTH(data_conclusao_processo) IN (10,11,12) THEN 'Q4'
                    ELSE 'Sem data'
                END as trimestre,
                COUNT(*) as quantidade,
                SUM(valor_total_contratacao) as valor_total
            FROM (
                SELECT DISTINCT numero_dfd, numero_contratacao, data_conclusao_processo, valor_total_contratacao
                FROM pca_dados 
                WHERE {$where_base}
                  AND numero_dfd IS NOT NULL 
                  AND numero_dfd != ''
                  AND numero_contratacao IS NOT NULL
            ) as unicos
            GROUP BY trimestre
            ORDER BY trimestre";
    
    return $pdo->query($sql)->fetchAll();
}

/**
 * Calcular top categorias por valor
 */
function calcularTopCategorias($pdo, $where_base, $limite = 5) {
    $sql = "SELECT 
                categoria_contratacao,
                COUNT(*) as quantidade,
                SUM(valor_total_contratacao) as valor_total,
                AVG(valor_total_contratacao) as valor_medio
            FROM (
                SELECT DISTINCT numero_dfd, numero_contratacao, categoria_contratacao, valor_total_contratacao
                FROM pca_dados 
                WHERE {$where_base}
                  AND numero_dfd IS NOT NULL 
                  AND numero_dfd != ''
                  AND numero_contratacao IS NOT NULL
                  AND categoria_contratacao IS NOT NULL
            ) as unicos
            GROUP BY categoria_contratacao
            ORDER BY valor_total DESC
            LIMIT {$limite}";
    
    return $pdo->query($sql)->fetchAll();
}

/**
 * Calcular top áreas por valor
 */
function calcularTopAreas($pdo, $where_base, $limite = 5) {
    $sql = "SELECT 
                area_agrupada,
                COUNT(*) as quantidade,
                SUM(valor_total_contratacao) as valor_total,
                AVG(valor_total_contratacao) as valor_medio
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
                WHERE {$where_base}
                  AND numero_dfd IS NOT NULL 
                  AND numero_dfd != ''
                  AND numero_contratacao IS NOT NULL
                  AND area_requisitante IS NOT NULL
            ) as unicos
            GROUP BY area_agrupada
            ORDER BY valor_total DESC
            LIMIT {$limite}";
    
    return $pdo->query($sql)->fetchAll();
}

/**
 * Calcular indicadores de performance
 */
function calcularIndicadoresPerformance($pdo, $ano) {
    try {
        $where_base = "ano_pca = {$ano} AND status_contratacao = 'Aprovada'";
        
        // Taxa de conclusão no prazo
        $sql_prazo = "SELECT 
                          COUNT(CASE WHEN data_conclusao_processo >= CURDATE() OR situacao_execucao = 'Concluído' THEN 1 END) as no_prazo,
                          COUNT(*) as total
                      FROM (
                          SELECT DISTINCT numero_dfd, numero_contratacao, data_conclusao_processo, situacao_execucao
                          FROM pca_dados 
                          WHERE {$where_base}
                            AND numero_dfd IS NOT NULL 
                            AND numero_dfd != ''
                            AND numero_contratacao IS NOT NULL
                      ) as unicos";
        
        $resultado_prazo = $pdo->query($sql_prazo)->fetch();
        $taxa_no_prazo = $resultado_prazo['total'] > 0 ? 
            round(($resultado_prazo['no_prazo'] / $resultado_prazo['total']) * 100, 1) : 0;
        
        // Tempo médio de execução (em dias)
        $sql_tempo = "SELECT 
                          AVG(DATEDIFF(
                              COALESCE(data_conclusao_processo, CURDATE()), 
                              data_inicio_processo
                          )) as tempo_medio
                      FROM (
                          SELECT DISTINCT numero_dfd, numero_contratacao, data_inicio_processo, data_conclusao_processo
                          FROM pca_dados 
                          WHERE {$where_base}
                            AND numero_dfd IS NOT NULL 
                            AND numero_dfd != ''
                            AND numero_contratacao IS NOT NULL
                            AND data_inicio_processo IS NOT NULL
                      ) as unicos";
        
        $tempo_medio = $pdo->query($sql_tempo)->fetchColumn() ?: 0;
        
        // Valor médio por contratação
        $valor_total = calcularValorTotal($pdo, $where_base);
        $total_contratacoes = calcularTotalContratacoes($pdo, $where_base);
        $valor_medio = $total_contratacoes > 0 ? $valor_total / $total_contratacoes : 0;
        
        return [
            'taxa_no_prazo' => $taxa_no_prazo,
            'tempo_medio_execucao' => round($tempo_medio, 1),
            'valor_medio_contratacao' => $valor_medio,
            'total_contratacoes' => $total_contratacoes,
            'valor_total' => $valor_total
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao calcular indicadores de performance: " . $e->getMessage());
        return [
            'taxa_no_prazo' => 0,
            'tempo_medio_execucao' => 0,
            'valor_medio_contratacao' => 0,
            'total_contratacoes' => 0,
            'valor_total' => 0
        ];
    }
}

/**
 * Comparar estatísticas entre anos
 */
function compararAnosEstatisticas($pdo, $ano_atual, $ano_anterior) {
    try {
        $stats_atual = calcularEstatisticasAprovadas($pdo, $ano_atual);
        $stats_anterior = calcularEstatisticasAprovadas($pdo, $ano_anterior);
        
        // Calcular variações percentuais
        $comparacao = [];
        
        $campos_comparar = [
            'total_contratacoes',
            'valor_total',
            'homologadas',
            'atrasadas_inicio',
            'atrasadas_conclusao'
        ];
        
        foreach ($campos_comparar as $campo) {
            $valor_atual = $stats_atual[$campo] ?? 0;
            $valor_anterior = $stats_anterior[$campo] ?? 0;
            
            if ($valor_anterior > 0) {
                $variacao = (($valor_atual - $valor_anterior) / $valor_anterior) * 100;
            } else {
                $variacao = $valor_atual > 0 ? 100 : 0;
            }
            
            $comparacao[$campo] = [
                'atual' => $valor_atual,
                'anterior' => $valor_anterior,
                'variacao_percentual' => round($variacao, 1),
                'tendencia' => $variacao > 0 ? 'up' : ($variacao < 0 ? 'down' : 'stable')
            ];
        }
        
        return $comparacao;
        
    } catch (Exception $e) {
        error_log("Erro ao comparar estatísticas entre anos: " . $e->getMessage());
        return [];
    }
}

/**
 * Gerar resumo executivo das estatísticas
 */
function gerarResumoExecutivo($stats) {
    $resumo = [];
    
    // Status geral
    if ($stats['total_contratacoes'] == 0) {
        $resumo['status_geral'] = 'Nenhuma contratação aprovada encontrada';
        $resumo['cor_status'] = 'warning';
    } elseif ($stats['percentual_conclusao'] >= 80) {
        $resumo['status_geral'] = 'Excelente performance de execução';
        $resumo['cor_status'] = 'success';
    } elseif ($stats['percentual_conclusao'] >= 60) {
        $resumo['status_geral'] = 'Boa performance de execução';
        $resumo['cor_status'] = 'info';
    } else {
        $resumo['status_geral'] = 'Performance de execução precisa melhorar';
        $resumo['cor_status'] = 'warning';
    }
    
    // Alertas
    $resumo['alertas'] = [];
    
    if ($stats['atrasadas_inicio'] > 0) {
        $resumo['alertas'][] = [
            'tipo' => 'warning',
            'mensagem' => "{$stats['atrasadas_inicio']} contratações atrasadas no início"
        ];
    }
    
    if ($stats['atrasadas_conclusao'] > 0) {
        $resumo['alertas'][] = [
            'tipo' => 'danger',
            'mensagem' => "{$stats['atrasadas_conclusao']} contratações atrasadas na conclusão"
        ];
    }
    
    // Destaques positivos
    $resumo['destaques'] = [];
    
    if ($stats['homologadas'] > 0) {
        $resumo['destaques'][] = [
            'tipo' => 'success',
            'mensagem' => "{$stats['homologadas']} contratações já concluídas"
        ];
    }
    
    if ($stats['valor_total'] > 0) {
        $valor_formatado = number_format($stats['valor_total'], 0, ',', '.');
        $resumo['destaques'][] = [
            'tipo' => 'info',
            'mensagem' => "R$ {$valor_formatado} em contratações aprovadas"
        ];
    }
    
    return $resumo;
}

?>