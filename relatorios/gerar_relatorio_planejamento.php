<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

$pdo = conectarDB();

// Parâmetros
$tipo = $_GET['tipo'] ?? '';
$data_inicial = $_GET['data_inicial'] ?? date('Y-01-01');
$data_final = $_GET['data_final'] ?? date('Y-m-d');
$categoria = $_GET['categoria'] ?? '';
$area = $_GET['area'] ?? '';
$situacao = $_GET['situacao'] ?? '';
$formato = $_GET['formato'] ?? 'html';
$incluir_graficos = isset($_GET['incluir_graficos']);

// Construir WHERE
$where = ['p.data_inicio_processo BETWEEN ? AND ?'];
$params = [$data_inicial, $data_final];

if (!empty($categoria)) {
    $where[] = 'p.categoria_contratacao = ?';
    $params[] = $categoria;
}

if (!empty($area)) {
    if ($area === 'GM.') {
        $where[] = "(p.area_requisitante LIKE 'GM%' OR p.area_requisitante LIKE 'GM.%')";
    } else {
        $where[] = 'p.area_requisitante LIKE ?';
        $params[] = $area . '%';
    }
}

if (!empty($situacao)) {
    if ($situacao === 'Não iniciado') {
        $where[] = "(p.situacao_execucao IS NULL OR p.situacao_execucao = '' OR p.situacao_execucao = 'Não iniciado')";
    } else {
        $where[] = 'p.situacao_execucao = ?';
        $params[] = $situacao;
    }
}

$whereClause = implode(' AND ', $where);

// Gerar relatório baseado no tipo
switch ($tipo) {
    case 'categoria':
        gerarRelatorioCategoria($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    case 'area':
        gerarRelatorioArea($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    case 'prazos':
        gerarRelatorioPrazos($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    case 'financeiro':
        gerarRelatorioFinanceiro($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    default:
        die('Tipo de relatório inválido');
}

// Função: Relatório por Categoria
// Função: Relatório por Categoria
function gerarRelatorioCategoria($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "WITH dfd_categorias AS (
        SELECT 
            numero_dfd,
            categoria_contratacao,
            SUM(valor_total_contratacao) as valor_categoria,
            MAX(titulo_contratacao) as titulo_contratacao,
            MAX(area_requisitante) as area_requisitante,
            MAX(situacao_execucao) as situacao_execucao,
            MAX(data_inicio_processo) as data_inicio_processo,
            MAX(data_conclusao_processo) as data_conclusao_processo,
            ROW_NUMBER() OVER (
                PARTITION BY numero_dfd 
                ORDER BY SUM(valor_total_contratacao) DESC, categoria_contratacao
            ) as rn
        FROM pca_dados p
        WHERE $where AND categoria_contratacao IS NOT NULL AND numero_dfd IS NOT NULL
        GROUP BY numero_dfd, categoria_contratacao
    ),
    dfd_principais AS (
        SELECT 
            numero_dfd,
            categoria_contratacao,
            valor_categoria as valor_total_dfd,
            titulo_contratacao,
            area_requisitante,
            situacao_execucao,
            data_inicio_processo,
            data_conclusao_processo
        FROM dfd_categorias 
        WHERE rn = 1
    )
    SELECT 
        categoria_contratacao,
        COUNT(DISTINCT numero_dfd) as total_dfds,
        COUNT(DISTINCT numero_dfd) as total_contratacoes,
        SUM(valor_total_dfd) as valor_total,
        COUNT(DISTINCT CASE WHEN situacao_execucao = 'Concluído' THEN numero_dfd END) as concluidas,
        COUNT(DISTINCT CASE WHEN (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao = 'Não iniciado') THEN numero_dfd END) as nao_iniciadas,
        COUNT(DISTINCT CASE WHEN situacao_execucao = 'Em andamento' THEN numero_dfd END) as em_andamento,
        COUNT(DISTINCT CASE WHEN data_conclusao_processo < CURDATE() AND (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao != 'Concluído') THEN numero_dfd END) as atrasadas,
        AVG(valor_total_dfd) as valor_medio,
        MAX(valor_total_dfd) as maior_valor,
        MIN(valor_total_dfd) as menor_valor,
        AVG(DATEDIFF(data_conclusao_processo, data_inicio_processo)) as prazo_medio_dias,
        COUNT(DISTINCT CASE WHEN EXISTS(
            SELECT 1 FROM licitacoes l 
            JOIN pca_dados pd ON l.pca_dados_id = pd.id 
            WHERE pd.numero_dfd = dfd_principais.numero_dfd
        ) THEN numero_dfd END) as com_licitacao
        FROM dfd_principais
        GROUP BY categoria_contratacao
        ORDER BY valor_total DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLCategoria($dados, $incluir_graficos, $params);
    } elseif ($formato === 'pdf') {
        gerarPDFCategoria($dados, $incluir_graficos);
    } else {
        gerarExcelCategoria($dados);
    }
}

// Função: Relatório por Área
// Função: Relatório por Área
function gerarRelatorioArea($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "WITH dfd_areas AS (
        SELECT 
            numero_dfd,
            area_requisitante,
            SUM(valor_total_contratacao) as valor_area,
            MAX(titulo_contratacao) as titulo_contratacao,
            MAX(categoria_contratacao) as categoria_contratacao,
            MAX(situacao_execucao) as situacao_execucao,
            MAX(data_inicio_processo) as data_inicio_processo,
            MAX(data_conclusao_processo) as data_conclusao_processo,
            ROW_NUMBER() OVER (
                PARTITION BY numero_dfd 
                ORDER BY SUM(valor_total_contratacao) DESC, area_requisitante
            ) as rn
        FROM pca_dados p
        WHERE $where AND area_requisitante IS NOT NULL AND numero_dfd IS NOT NULL
        GROUP BY numero_dfd, area_requisitante
    ),
    dfd_principais AS (
        SELECT 
            numero_dfd,
            area_requisitante,
            valor_area as valor_total_dfd,
            categoria_contratacao,
            situacao_execucao,
            data_inicio_processo,
            data_conclusao_processo
        FROM dfd_areas 
        WHERE rn = 1
    )
    SELECT 
        area_requisitante,
        COUNT(DISTINCT numero_dfd) as total_dfds,
        COUNT(DISTINCT numero_dfd) as total_contratacoes,
        SUM(valor_total_dfd) as valor_total,
        COUNT(DISTINCT CASE WHEN situacao_execucao = 'Concluído' THEN numero_dfd END) as concluidas,
        COUNT(DISTINCT CASE WHEN (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao = 'Não iniciado') THEN numero_dfd END) as nao_iniciadas,
        COUNT(DISTINCT CASE WHEN situacao_execucao = 'Em andamento' THEN numero_dfd END) as em_andamento,
        COUNT(DISTINCT CASE WHEN data_conclusao_processo < CURDATE() AND (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao != 'Concluído') THEN numero_dfd END) as atrasadas,
        AVG(DATEDIFF(data_conclusao_processo, data_inicio_processo)) as prazo_medio_dias,
        COUNT(DISTINCT CASE WHEN EXISTS(
            SELECT 1 FROM licitacoes l 
            JOIN pca_dados pd ON l.pca_dados_id = pd.id 
            WHERE pd.numero_dfd = dfd_principais.numero_dfd
        ) THEN numero_dfd END) as com_licitacao,
        ROUND(COUNT(DISTINCT CASE WHEN situacao_execucao = 'Concluído' THEN numero_dfd END) * 100.0 / COUNT(DISTINCT numero_dfd), 2) as taxa_conclusao,
        COUNT(DISTINCT categoria_contratacao) as categorias_utilizadas
        FROM dfd_principais
        GROUP BY area_requisitante
        ORDER BY valor_total DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLArea($dados, $incluir_graficos, $params);
    } elseif ($formato === 'pdf') {
        gerarPDFArea($dados, $incluir_graficos);
    } else {
        gerarExcelArea($dados);
    }
}

// Função: Relatório de Prazos
// Função: Relatório de Prazos
function gerarRelatorioPrazos($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "WITH dfd_categorias AS (
        SELECT 
            numero_dfd,
            categoria_contratacao,
            SUM(valor_total_contratacao) as valor_categoria,
            MAX(situacao_execucao) as situacao_execucao,
            MAX(data_inicio_processo) as data_inicio_processo,
            MAX(data_conclusao_processo) as data_conclusao_processo,
            ROW_NUMBER() OVER (
                PARTITION BY numero_dfd 
                ORDER BY SUM(valor_total_contratacao) DESC, categoria_contratacao
            ) as rn
        FROM pca_dados p
        WHERE $where AND categoria_contratacao IS NOT NULL AND numero_dfd IS NOT NULL
        GROUP BY numero_dfd, categoria_contratacao
    ),
    dfd_principais AS (
        SELECT 
            numero_dfd,
            categoria_contratacao,
            situacao_execucao,
            data_inicio_processo,
            data_conclusao_processo
        FROM dfd_categorias 
        WHERE rn = 1
    )
    SELECT 
        categoria_contratacao,
        COUNT(DISTINCT numero_dfd) as total_dfds,
        AVG(DATEDIFF(data_conclusao_processo, data_inicio_processo)) as prazo_medio_planejado,
        MIN(DATEDIFF(data_conclusao_processo, data_inicio_processo)) as prazo_minimo,
        MAX(DATEDIFF(data_conclusao_processo, data_inicio_processo)) as prazo_maximo,
        COUNT(DISTINCT CASE WHEN data_conclusao_processo < CURDATE() AND (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao != 'Concluído') THEN numero_dfd END) as atrasadas,
        COUNT(DISTINCT CASE WHEN data_conclusao_processo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN numero_dfd END) as vencendo_30_dias,
        COUNT(DISTINCT CASE WHEN data_inicio_processo < CURDATE() AND (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao = 'Não iniciado') THEN numero_dfd END) as atrasadas_inicio,
        AVG(CASE WHEN situacao_execucao = 'Concluído' THEN DATEDIFF(CURDATE(), data_inicio_processo) END) as tempo_medio_execucao,
        ROUND(COUNT(DISTINCT CASE WHEN data_conclusao_processo >= CURDATE() OR situacao_execucao = 'Concluído' THEN numero_dfd END) * 100.0 / COUNT(DISTINCT numero_dfd), 2) as percentual_no_prazo
        FROM dfd_principais
        GROUP BY categoria_contratacao
        ORDER BY atrasadas DESC, prazo_medio_planejado DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLPrazos($dados, $incluir_graficos, $params);
    } elseif ($formato === 'pdf') {
        gerarPDFPrazos($dados, $incluir_graficos);
    } else {
        gerarExcelPrazos($dados);
    }
}

// Função: Relatório Financeiro
// Função: Relatório Financeiro
function gerarRelatorioFinanceiro($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "WITH dfd_mensal AS (
        SELECT 
            numero_dfd,
            DATE_FORMAT(data_inicio_processo, '%Y-%m') as mes,
            SUM(valor_total_contratacao) as valor_total_dfd,
            MAX(situacao_execucao) as situacao_execucao,
            MAX(categoria_contratacao) as categoria_contratacao,
            MAX(area_requisitante) as area_requisitante
        FROM pca_dados p
        WHERE $where AND numero_dfd IS NOT NULL
        GROUP BY numero_dfd, DATE_FORMAT(data_inicio_processo, '%Y-%m')
    )
    SELECT 
        mes,
        COUNT(DISTINCT numero_dfd) as total_dfds,
        SUM(valor_total_dfd) as valor_planejado_total,
        AVG(valor_total_dfd) as valor_medio_dfd,
        COUNT(DISTINCT CASE WHEN situacao_execucao = 'Concluído' THEN numero_dfd END) as dfds_concluidos,
        SUM(CASE WHEN situacao_execucao = 'Concluído' THEN valor_total_dfd ELSE 0 END) as valor_concluido,
        COUNT(DISTINCT categoria_contratacao) as categorias_ativas,
        COUNT(DISTINCT area_requisitante) as areas_ativas,
        ROUND(COUNT(DISTINCT CASE WHEN situacao_execucao = 'Concluído' THEN numero_dfd END) * 100.0 / COUNT(DISTINCT numero_dfd), 2) as percentual_execucao
        FROM dfd_mensal
        GROUP BY mes
        ORDER BY mes DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLFinanceiro($dados, $incluir_graficos, $params);
    } elseif ($formato === 'pdf') {
        gerarPDFFinanceiro($dados, $incluir_graficos);
    } else {
        gerarExcelFinanceiro($dados);
    }
}

// HTML para relatório de categoria
function gerarHTMLCategoria($dados, $incluir_graficos, $params) {
    $total_dfds = array_sum(array_column($dados, 'total_dfds'));
    $valor_total = array_sum(array_column($dados, 'valor_total'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório por Categoria - PCA</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
            .info { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 30px; }
            .info p { margin: 5px 0; }
            .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .summary-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #3498db; }
            .summary-card h3 { margin: 0 0 10px 0; color: #2c3e50; font-size: 16px; }
            .summary-card .value { font-size: 24px; font-weight: bold; color: #3498db; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #34495e; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f8f9fa; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0; }
            .status-indicator { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
            .status-alta { background: #e74c3c; color: white; }
            .status-media { background: #f39c12; color: white; }
            .status-baixa { background: #27ae60; color: white; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Relatório do PCA por Categoria</h1>
            
            <div class="info">
                <p><strong>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong>Total de DFDs:</strong> <?php echo $total_dfds; ?></p>
                <p><strong>Valor Total Planejado:</strong> R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></p>
                <p><strong>Data de Geração:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            
            <div class="summary">
                <div class="summary-card">
                    <h3>Categorias Analisadas</h3>
                    <div class="value"><?php echo count($dados); ?></div>
                </div>
                <div class="summary-card">
                    <h3>DFDs Concluídos</h3>
                    <div class="value"><?php echo array_sum(array_column($dados, 'concluidas')); ?></div>
                </div>
                <div class="summary-card">
                    <h3>DFDs Atrasados</h3>
                    <div class="value"><?php echo array_sum(array_column($dados, 'atrasadas')); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Valor Médio por DFD</h3>
                    <div class="value">R$ <?php echo $total_dfds > 0 ? number_format($valor_total / $total_dfds / 1000000, 1) . 'M' : '0'; ?></div>
                </div>
            </div>
            
            <?php if ($incluir_graficos): ?>
            <div class="grid">
                <div class="chart-container">
                    <canvas id="chartCategoria"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="chartSituacao"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th style="text-align: center;">Total DFDs</th>
                        <th style="text-align: center;">Contratações</th>
                        <th style="text-align: center;">Concluídas</th>
                        <th style="text-align: center;">Não Iniciadas</th>
                        <th style="text-align: center;">Em Andamento</th>
                        <th style="text-align: center;">Atrasadas</th>
                        <th style="text-align: right;">Valor Total</th>
                        <th style="text-align: right;">Valor Médio</th>
                        <th style="text-align: center;">Criticidade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $row): 
                        $percentual_atraso = $row['total_dfds'] > 0 ? ($row['atrasadas'] / $row['total_dfds']) * 100 : 0;
                        $criticidade = $percentual_atraso > 30 ? 'alta' : ($percentual_atraso > 10 ? 'media' : 'baixa');
                        $criticidade_texto = $percentual_atraso > 30 ? 'Alta' : ($percentual_atraso > 10 ? 'Média' : 'Baixa');
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['categoria_contratacao']); ?></strong></td>
                        <td style="text-align: center;"><?php echo $row['total_dfds']; ?></td>
                        <td style="text-align: center;"><?php echo $row['total_contratacoes']; ?></td>
                        <td style="text-align: center;"><?php echo $row['concluidas']; ?></td>
                        <td style="text-align: center;"><?php echo $row['nao_iniciadas']; ?></td>
                        <td style="text-align: center;"><?php echo $row['em_andamento']; ?></td>
                        <td style="text-align: center;"><?php echo $row['atrasadas']; ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['valor_total'], 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['valor_medio'], 2, ',', '.'); ?></td>
                        <td style="text-align: center;">
                            <span class="status-indicator status-<?php echo $criticidade; ?>">
                                <?php echo $criticidade_texto; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background: #ecf0f1; font-weight: bold;">
                        <td>TOTAL</td>
                        <td style="text-align: center;"><?php echo $total_dfds; ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'total_contratacoes')); ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'concluidas')); ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'nao_iniciadas')); ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'em_andamento')); ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'atrasadas')); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></td>
                        <td style="text-align: right;">-</td>
                        <td style="text-align: center;">-</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="no-print" style="text-align: center; margin-top: 30px;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Imprimir Relatório
                </button>
            </div>
        </div>
        
        <?php if ($incluir_graficos): ?>
        <script>
            // Gráfico de Pizza - Distribuição por Categoria
            new Chart(document.getElementById('chartCategoria'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($dados, 'categoria_contratacao')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($dados, 'total_dfds')); ?>,
                        backgroundColor: ['#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6', '#1abc9c', '#34495e', '#e67e22']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Distribuição de DFDs por Categoria'
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Gráfico de Barras - Situação por Categoria
            new Chart(document.getElementById('chartSituacao'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($dados, 'categoria_contratacao')); ?>,
                    datasets: [
                        {
                            label: 'Concluídas',
                            data: <?php echo json_encode(array_column($dados, 'concluidas')); ?>,
                            backgroundColor: '#27ae60'
                        },
                        {
                            label: 'Em Andamento',
                            data: <?php echo json_encode(array_column($dados, 'em_andamento')); ?>,
                            backgroundColor: '#f39c12'
                        },
                        {
                            label: 'Não Iniciadas',
                            data: <?php echo json_encode(array_column($dados, 'nao_iniciadas')); ?>,
                            backgroundColor: '#95a5a6'
                        },
                        {
                            label: 'Atrasadas',
                            data: <?php echo json_encode(array_column($dados, 'atrasadas')); ?>,
                            backgroundColor: '#e74c3c'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Situação dos DFDs por Categoria'
                        }
                    },
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true }
                    }
                }
            });
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    
    registrarLog('GERAR_RELATORIO', 'Gerou relatório do PCA por categoria');
}

// HTML para relatório por área
function gerarHTMLArea($dados, $incluir_graficos, $params) {
    $total_dfds = array_sum(array_column($dados, 'total_dfds'));
    $valor_total = array_sum(array_column($dados, 'valor_total'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório por Área - PCA</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
            .info { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 30px; }
            .info p { margin: 5px 0; }
            .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .summary-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #e74c3c; }
            .summary-card h3 { margin: 0 0 10px 0; color: #2c3e50; font-size: 16px; }
            .summary-card .value { font-size: 24px; font-weight: bold; color: #e74c3c; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #34495e; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f8f9fa; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            .performance-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
            .perf-excelente { background: #27ae60; color: white; }
            .perf-bom { background: #f39c12; color: white; }
            .perf-ruim { background: #e74c3c; color: white; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Relatório do PCA por Área Requisitante</h1>
            
            <div class="info">
                <p><strong>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong>Total de DFDs:</strong> <?php echo $total_dfds; ?></p>
                <p><strong>Valor Total Planejado:</strong> R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></p>
                <p><strong>Data de Geração:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            
            <div class="summary">
                <div class="summary-card">
                    <h3>Áreas Analisadas</h3>
                    <div class="value"><?php echo count($dados); ?></div>
                </div>
                <div class="summary-card">
                    <h3>DFDs com Licitação</h3>
                    <div class="value"><?php echo array_sum(array_column($dados, 'com_licitacao')); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Taxa Média de Conclusão</h3>
                    <div class="value"><?php echo count($dados) > 0 ? number_format(array_sum(array_column($dados, 'taxa_conclusao')) / count($dados), 1) : '0'; ?>%</div>
                </div>
                <div class="summary-card">
                    <h3>Prazo Médio</h3>
                    <div class="value"><?php 
                        $prazo_medio_geral = array_filter(array_column($dados, 'prazo_medio_dias'));
                        echo count($prazo_medio_geral) > 0 ? round(array_sum($prazo_medio_geral) / count($prazo_medio_geral)) : '0'; 
                    ?> dias</div>
                </div>
            </div>
            
            <?php if ($incluir_graficos): ?>
            <div class="chart-container">
                <canvas id="chartArea"></canvas>
            </div>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Área Requisitante</th>
                        <th style="text-align: center;">Total DFDs</th>
                        <th style="text-align: center;">Concluídas</th>
                        <th style="text-align: center;">Em Andamento</th>
                        <th style="text-align: center;">Atrasadas</th>
                        <th style="text-align: center;">Com Licitação</th>
                        <th style="text-align: right;">Valor Total</th>
                        <th style="text-align: center;">Taxa Conclusão</th>
                        <th style="text-align: center;">Prazo Médio</th>
                        <th style="text-align: center;">Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $row): 
                        $performance = $row['taxa_conclusao'] > 80 ? 'excelente' : ($row['taxa_conclusao'] > 50 ? 'bom' : 'ruim');
                        $performance_texto = $row['taxa_conclusao'] > 80 ? 'Excelente' : ($row['taxa_conclusao'] > 50 ? 'Bom' : 'Precisa Melhorar');
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['area_agrupada']); ?></strong></td>
                        <td style="text-align: center;"><?php echo $row['total_dfds']; ?></td>
                        <td style="text-align: center;"><?php echo $row['concluidas']; ?></td>
                        <td style="text-align: center;"><?php echo $row['em_andamento']; ?></td>
                        <td style="text-align: center;"><?php echo $row['atrasadas']; ?></td>
                        <td style="text-align: center;"><?php echo $row['com_licitacao']; ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['valor_total'], 2, ',', '.'); ?></td>
                        <td style="text-align: center;"><?php echo number_format($row['taxa_conclusao'], 1); ?>%</td>
                        <td style="text-align: center;"><?php echo $row['prazo_medio_dias'] ? round($row['prazo_medio_dias']) . ' dias' : '-'; ?></td>
                        <td style="text-align: center;">
                            <span class="performance-badge perf-<?php echo $performance; ?>">
                                <?php echo $performance_texto; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background: #ecf0f1; font-weight: bold;">
                        <td>TOTAL</td>
                        <td style="text-align: center;"><?php echo $total_dfds; ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'concluidas')); ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'em_andamento')); ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'atrasadas')); ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'com_licitacao')); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></td>
                        <td style="text-align: center;">-</td>
                        <td style="text-align: center;">-</td>
                        <td style="text-align: center;">-</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="no-print" style="text-align: center; margin-top: 30px;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Imprimir Relatório
                </button>
            </div>
        </div>
        
        <?php if ($incluir_graficos): ?>
        <script>
            // Gráfico de Barras - DFDs por Área
            new Chart(document.getElementById('chartArea'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($item) { return agruparArea($item['area_requisitante']); }, $dados)); ?>,
                    datasets: [{
                        label: 'Total de DFDs',
                        data: <?php echo json_encode(array_column($dados, 'total_dfds')); ?>,
                        backgroundColor: '#e74c3c'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Distribuição de DFDs por Área Requisitante'
                        },
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    
    registrarLog('GERAR_RELATORIO', 'Gerou relatório do PCA por área');
}

// HTML para relatório de prazos
function gerarHTMLPrazos($dados, $incluir_graficos, $params) {
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório de Prazos - PCA</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
            .info { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 30px; }
            .info p { margin: 5px 0; }
            .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .summary-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #f39c12; }
            .summary-card h3 { margin: 0 0 10px 0; color: #2c3e50; font-size: 16px; }
            .summary-card .value { font-size: 24px; font-weight: bold; color: #f39c12; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #34495e; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f8f9fa; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            .warning { color: #e74c3c; font-weight: bold; }
            .alert-high { background: #fee; color: #e74c3c; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Relatório de Análise de Prazos - PCA</h1>
            
            <div class="info">
                <p><strong>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong>Total de Categorias Analisadas:</strong> <?php echo count($dados); ?></p>
                <p><strong>Data de Geração:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            
            <div class="summary">
                <div class="summary-card">
                    <h3>Total de Atrasos</h3>
                    <div class="value"><?php echo array_sum(array_column($dados, 'atrasadas')); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Vencendo em 30 dias</h3>
                    <div class="value"><?php echo array_sum(array_column($dados, 'vencendo_30_dias')); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Não Iniciadas</h3>
                    <div class="value"><?php echo array_sum(array_column($dados, 'atrasadas_inicio')); ?></div>
                </div>
                <div class="summary-card">
                    <h3>% Médio no Prazo</h3>
                    <div class="value"><?php 
                        $percentuais = array_filter(array_column($dados, 'percentual_no_prazo'));
                        echo count($percentuais) > 0 ? number_format(array_sum($percentuais) / count($percentuais), 1) : '0'; 
                    ?>%</div>
                </div>
            </div>
            
            <?php if ($incluir_graficos): ?>
            <div class="chart-container">
                <canvas id="chartPrazos"></canvas>
            </div>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th style="text-align: center;">Total DFDs</th>
                        <th style="text-align: center;">Prazo Médio Planejado</th>
                        <th style="text-align: center;">Prazo Mínimo</th>
                        <th style="text-align: center;">Prazo Máximo</th>
                        <th style="text-align: center;">DFDs Atrasados</th>
                        <th style="text-align: center;">Vencendo (30 dias)</th>
                        <th style="text-align: center;">Não Iniciados</th>
                        <th style="text-align: center;">% No Prazo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $row): ?>
                    <tr class="<?php echo $row['atrasadas'] > ($row['total_dfds'] * 0.3) ? 'alert-high' : ''; ?>">
                        <td><strong><?php echo htmlspecialchars($row['categoria_contratacao']); ?></strong></td>
                        <td style="text-align: center;"><?php echo $row['total_dfds']; ?></td>
                        <td style="text-align: center;">
                            <?php echo $row['prazo_medio_planejado'] ? round($row['prazo_medio_planejado']) . ' dias' : '-'; ?>
                        </td>
                        <td style="text-align: center;"><?php echo $row['prazo_minimo'] ?? '-'; ?> dias</td>
                        <td style="text-align: center;"><?php echo $row['prazo_maximo'] ?? '-'; ?> dias</td>
                        <td style="text-align: center; <?php echo $row['atrasadas'] > 0 ? 'color: #e74c3c; font-weight: bold;' : ''; ?>">
                            <?php echo $row['atrasadas']; ?>
                        </td>
                        <td style="text-align: center; <?php echo $row['vencendo_30_dias'] > 0 ? 'color: #f39c12; font-weight: bold;' : ''; ?>">
                            <?php echo $row['vencendo_30_dias']; ?>
                        </td>
                        <td style="text-align: center; <?php echo $row['atrasadas_inicio'] > 0 ? 'color: #e67e22; font-weight: bold;' : ''; ?>">
                            <?php echo $row['atrasadas_inicio']; ?>
                        </td>
                        <td style="text-align: center;">
                            <span style="color: <?php echo $row['percentual_no_prazo'] > 80 ? '#27ae60' : ($row['percentual_no_prazo'] > 60 ? '#f39c12' : '#e74c3c'); ?>;">
                                <?php echo number_format($row['percentual_no_prazo'], 1); ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>Observações sobre Prazos:</h3>
                <ul>
                    <li><strong>DFDs Atrasados:</strong> Contratações que já passaram da data de conclusão planejada</li>
                    <li><strong>Vencendo em 30 dias:</strong> Contratações que têm data de conclusão nos próximos 30 dias</li>
                    <li><strong>Não Iniciados:</strong> Contratações que já passaram da data de início mas ainda não começaram</li>
                    <li><strong>% No Prazo:</strong> Percentual de DFDs que estão cumprindo o cronograma</li>
                </ul>
            </div>
            
            <div class="no-print" style="text-align: center; margin-top: 30px;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Imprimir Relatório
                </button>
            </div>
        </div>
        
        <?php if ($incluir_graficos): ?>
        <script>
            // Gráfico de Barras - Prazos por Categoria
            new Chart(document.getElementById('chartPrazos'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($dados, 'categoria_contratacao')); ?>,
                    datasets: [
                        {
                            label: 'Atrasadas',
                            data: <?php echo json_encode(array_column($dados, 'atrasadas')); ?>,
                            backgroundColor: '#e74c3c'
                        },
                        {
                            label: 'Vencendo em 30 dias',
                            data: <?php echo json_encode(array_column($dados, 'vencendo_30_dias')); ?>,
                            backgroundColor: '#f39c12'
                        },
                        {
                            label: 'No Prazo',
                            data: <?php echo json_encode(array_map(function($row) { 
                                return $row['total_dfds'] - $row['atrasadas'] - $row['vencendo_30_dias']; 
                            }, $dados)); ?>,
                            backgroundColor: '#27ae60'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Situação de Prazos por Categoria'
                        }
                    },
                    scales: {
                        x: { stacked: true },
                        y: { 
                            stacked: true,
                            beginAtZero: true
                        }
                    }
                }
            });
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    
    registrarLog('GERAR_RELATORIO', 'Gerou relatório de prazos do PCA');
}

// HTML para relatório financeiro
function gerarHTMLFinanceiro($dados, $incluir_graficos, $params) {
    $valor_total_planejado = array_sum(array_column($dados, 'valor_planejado_total'));
    $valor_total_concluido = array_sum(array_column($dados, 'valor_concluido'));
    $dfds_totais = array_sum(array_column($dados, 'total_dfds'));
    $dfds_concluidos = array_sum(array_column($dados, 'dfds_concluidos'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório Financeiro - PCA</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
            .info { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 30px; }
            .info p { margin: 5px 0; }
            .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .summary-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
            .summary-card h3 { margin: 0 0 10px 0; color: #2c3e50; font-size: 16px; }
            .summary-card .value { font-size: 24px; font-weight: bold; }
            .card-planejado { border-left: 4px solid #3498db; }
            .card-planejado .value { color: #3498db; }
            .card-concluido { border-left: 4px solid #27ae60; }
            .card-concluido .value { color: #27ae60; }
            .card-pendente { border-left: 4px solid #f39c12; }
            .card-pendente .value { color: #f39c12; }
            .card-execucao { border-left: 4px solid #9b59b6; }
            .card-execucao .value { color: #9b59b6; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #34495e; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f8f9fa; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Relatório Financeiro do PCA</h1>
            
            <div class="info">
                <p><strong>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong>Total de Meses Analisados:</strong> <?php echo count($dados); ?></p>
                <p><strong>Data de Geração:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            
            <div class="summary">
                <div class="summary-card card-planejado">
                    <h3>Valor Total Planejado</h3>
                    <div class="value">R$ <?php echo number_format($valor_total_planejado, 2, ',', '.'); ?></div>
                </div>
                <div class="summary-card card-concluido">
                    <h3>Valor Executado</h3>
                    <div class="value">R$ <?php echo number_format($valor_total_concluido, 2, ',', '.'); ?></div>
                </div>
                <div class="summary-card card-pendente">
                    <h3>Valor Pendente</h3>
                    <div class="value">R$ <?php echo number_format($valor_total_planejado - $valor_total_concluido, 2, ',', '.'); ?></div>
                </div>
                <div class="summary-card card-execucao">
                    <h3>% de Execução</h3>
                    <div class="value"><?php echo $valor_total_planejado > 0 ? number_format(($valor_total_concluido / $valor_total_planejado) * 100, 1) : '0'; ?>%</div>
                </div>
            </div>
            
            <?php if ($incluir_graficos): ?>
            <div class="grid">
                <div class="chart-container">
                    <canvas id="chartValores"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="chartExecucao"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Mês/Ano</th>
                        <th style="text-align: center;">Total DFDs</th>
                        <th style="text-align: center;">DFDs Concluídos</th>
                        <th style="text-align: right;">Valor Planejado</th>
                        <th style="text-align: right;">Valor Executado</th>
                        <th style="text-align: right;">Valor Médio/DFD</th>
                        <th style="text-align: center;">% Execução</th>
                        <th style="text-align: center;">Áreas Ativas</th>
                        <th style="text-align: center;">Categorias</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $row): 
                        $mes_ano = DateTime::createFromFormat('Y-m', $row['mes'])->format('m/Y');
                    ?>
                    <tr>
                        <td><strong><?php echo $mes_ano; ?></strong></td>
                        <td style="text-align: center;"><?php echo $row['total_dfds']; ?></td>
                        <td style="text-align: center;"><?php echo $row['dfds_concluidos']; ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['valor_planejado_total'], 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['valor_concluido'], 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['valor_medio_dfd'], 2, ',', '.'); ?></td>
                        <td style="text-align: center;">
                            <span style="color: <?php echo $row['percentual_execucao'] > 80 ? '#27ae60' : ($row['percentual_execucao'] > 50 ? '#f39c12' : '#e74c3c'); ?>;">
                                <?php echo number_format($row['percentual_execucao'], 1); ?>%
                            </span>
                        </td>
                        <td style="text-align: center;"><?php echo $row['areas_ativas']; ?></td>
                        <td style="text-align: center;"><?php echo $row['categorias_ativas']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background: #ecf0f1; font-weight: bold;">
                        <td>TOTAL</td>
                        <td style="text-align: center;"><?php echo $dfds_totais; ?></td>
                        <td style="text-align: center;"><?php echo $dfds_concluidos; ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($valor_total_planejado, 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($valor_total_concluido, 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo $dfds_totais > 0 ? number_format($valor_total_planejado / $dfds_totais, 2, ',', '.') : '0,00'; ?></td>
                        <td style="text-align: center;"><?php echo $valor_total_planejado > 0 ? number_format(($valor_total_concluido / $valor_total_planejado) * 100, 1) : '0'; ?>%</td>
                        <td style="text-align: center;">-</td>
                        <td style="text-align: center;">-</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="no-print" style="text-align: center; margin-top: 30px;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Imprimir Relatório
                </button>
            </div>
        </div>
        
        <?php if ($incluir_graficos): ?>
        <script>
            // Gráfico de Linha - Evolução dos Valores
            new Chart(document.getElementById('chartValores'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function($item) { 
                        return DateTime::createFromFormat('Y-m', $item['mes'])->format('m/Y'); 
                    }, $dados)); ?>,
                    datasets: [
                        {
                            label: 'Valor Planejado',
                            data: <?php echo json_encode(array_column($dados, 'valor_planejado_total')); ?>,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Valor Executado',
                            data: <?php echo json_encode(array_column($dados, 'valor_concluido')); ?>,
                            borderColor: '#27ae60',
                            backgroundColor: 'rgba(39, 174, 96, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Evolução dos Valores por Mês'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    }
                }
            });
            
            // Gráfico de Barras - Percentual de Execução
            new Chart(document.getElementById('chartExecucao'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($item) { 
                        return DateTime::createFromFormat('Y-m', $item['mes'])->format('m/Y'); 
                    }, $dados)); ?>,
                    datasets: [{
                        label: '% de Execução',
                        data: <?php echo json_encode(array_column($dados, 'percentual_execucao')); ?>,
                        backgroundColor: '#9b59b6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Percentual de Execução por Mês'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    
    registrarLog('GERAR_RELATORIO', 'Gerou relatório financeiro do PCA');
}

// Funções para gerar PDF (usando TCPDF ou similar)
function gerarPDFCategoria($dados, $incluir_graficos) {
    // Para PDF, você precisaria integrar uma biblioteca como TCPDF
    // Por simplicidade, vou redirecionar para CSV
    gerarExcelCategoria($dados);
}

function gerarPDFArea($dados, $incluir_graficos) {
    gerarExcelArea($dados);
}

function gerarPDFPrazos($dados, $incluir_graficos) {
    gerarExcelPrazos($dados);
}

function gerarPDFFinanceiro($dados, $incluir_graficos) {
    gerarExcelFinanceiro($dados);
}

// Funções para gerar Excel/CSV
function gerarExcelCategoria($dados) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_pca_categoria_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    $cabecalho = [
        'Categoria',
        'Total DFDs',
        'Total Contratações',
        'Concluídas',
        'Não Iniciadas',
        'Em Andamento',
        'Atrasadas',
        'Valor Total',
        'Valor Médio',
        'Maior Valor',
        'Menor Valor',
        'Prazo Médio (dias)',
        'Com Licitação'
    ];
    
    fputcsv($output, $cabecalho, ';');
    
    foreach ($dados as $row) {
        $linha = [
            $row['categoria_contratacao'],
            $row['total_dfds'],
            $row['total_contratacoes'],
            $row['concluidas'],
            $row['nao_iniciadas'],
            $row['em_andamento'],
            $row['atrasadas'],
            number_format($row['valor_total'], 2, ',', '.'),
            number_format($row['valor_medio'], 2, ',', '.'),
            number_format($row['maior_valor'], 2, ',', '.'),
            number_format($row['menor_valor'], 2, ',', '.'),
            $row['prazo_medio_dias'] ? round($row['prazo_medio_dias']) : '',
            $row['com_licitacao']
        ];
        
        fputcsv($output, $linha, ';');
    }
    
    fclose($output);
    registrarLog('EXPORTAR_RELATORIO', 'Exportou relatório PCA por categoria em CSV');
    exit;
}

function gerarExcelArea($dados) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_pca_area_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    $cabecalho = [
        'Área Requisitante',
        'Total DFDs',
        'Total Contratações',
        'Concluídas',
        'Não Iniciadas',
        'Em Andamento',
        'Atrasadas',
        'Com Licitação',
        'Valor Total',
        'Taxa Conclusão (%)',
        'Prazo Médio (dias)',
        'Categorias Utilizadas'
    ];
    
    fputcsv($output, $cabecalho, ';');
    
    foreach ($dados as $row) {
        $linha = [
            $row['area_agrupada'],
            $row['total_dfds'],
            $row['total_contratacoes'],
            $row['concluidas'],
            $row['nao_iniciadas'],
            $row['em_andamento'],
            $row['atrasadas'],
            $row['com_licitacao'],
            number_format($row['valor_total'], 2, ',', '.'),
            number_format($row['taxa_conclusao'], 2, ',', '.'),
            $row['prazo_medio_dias'] ? round($row['prazo_medio_dias']) : '',
            $row['categorias_utilizadas']
        ];
        
        fputcsv($output, $linha, ';');
    }
    
    fclose($output);
    registrarLog('EXPORTAR_RELATORIO', 'Exportou relatório PCA por área em CSV');
    exit;
}

function gerarExcelPrazos($dados) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_pca_prazos_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    $cabecalho = [
        'Categoria',
        'Total DFDs',
        'Prazo Médio Planejado (dias)',
        'Prazo Mínimo (dias)',
        'Prazo Máximo (dias)',
        'DFDs Atrasados',
        'Vencendo em 30 dias',
        'Não Iniciados',
        'Tempo Médio Execução (dias)',
        'Percentual No Prazo (%)'
    ];
    
    fputcsv($output, $cabecalho, ';');
    
    foreach ($dados as $row) {
        $linha = [
            $row['categoria_contratacao'],
            $row['total_dfds'],
            $row['prazo_medio_planejado'] ? round($row['prazo_medio_planejado'], 1) : '',
            $row['prazo_minimo'] ?? '',
            $row['prazo_maximo'] ?? '',
            $row['atrasadas'],
            $row['vencendo_30_dias'],
            $row['atrasadas_inicio'],
            $row['tempo_medio_execucao'] ? round($row['tempo_medio_execucao'], 1) : '',
            number_format($row['percentual_no_prazo'], 2, ',', '.')
        ];
        
        fputcsv($output, $linha, ';');
    }
    
    fclose($output);
    registrarLog('EXPORTAR_RELATORIO', 'Exportou relatório PCA de prazos em CSV');
    exit;
}

function gerarExcelFinanceiro($dados) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_pca_financeiro_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    $cabecalho = [
        'Mês/Ano',
        'Total DFDs',
        'DFDs Concluídos',
        'Valor Planejado',
        'Valor Executado',
        'Valor Médio por DFD',
        'Percentual Execução (%)',
        'Áreas Ativas',
        'Categorias Ativas'
    ];
    
    fputcsv($output, $cabecalho, ';');
    
    foreach ($dados as $row) {
        $mes_ano = DateTime::createFromFormat('Y-m', $row['mes'])->format('m/Y');
        
        $linha = [
            $mes_ano,
            $row['total_dfds'],
            $row['dfds_concluidos'],
            number_format($row['valor_planejado_total'], 2, ',', '.'),
            number_format($row['valor_concluido'], 2, ',', '.'),
            number_format($row['valor_medio_dfd'], 2, ',', '.'),
            number_format($row['percentual_execucao'], 2, ',', '.'),
            $row['areas_ativas'],
            $row['categorias_ativas']
        ];
        
        fputcsv($output, $linha, ';');
    }
    
    fclose($output);
    registrarLog('EXPORTAR_RELATORIO', 'Exportou relatório PCA financeiro em CSV');
    exit;
}
?>