<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

$pdo = conectarDB();

// Parâmetros
$tipo = $_GET['tipo'] ?? '';
$data_inicial = $_GET['data_inicial'] ?? date('Y-01-01');
$data_final = $_GET['data_final'] ?? date('Y-m-d');
$modalidade = $_GET['modalidade'] ?? '';
$pregoeiro = $_GET['pregoeiro'] ?? '';
$situacao = $_GET['situacao'] ?? '';
$formato = $_GET['formato'] ?? 'html';
$incluir_graficos = isset($_GET['incluir_graficos']);

// Construir WHERE
$where = ['l.criado_em BETWEEN ? AND ?'];
$params = [$data_inicial . ' 00:00:00', $data_final . ' 23:59:59'];

if (!empty($modalidade)) {
    $where[] = 'l.modalidade = ?';
    $params[] = $modalidade;
}

if (!empty($pregoeiro)) {
    $where[] = 'l.pregoeiro = ?';
    $params[] = $pregoeiro;
}

if (!empty($situacao)) {
    $where[] = 'l.situacao = ?';
    $params[] = $situacao;
}

$whereClause = implode(' AND ', $where);

// Gerar relatório baseado no tipo
switch ($tipo) {
    case 'modalidade':
        gerarRelatorioModalidade($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    case 'pregoeiro':
        gerarRelatorioPregoeiro($pdo, $whereClause, $params, $formato, $incluir_graficos);
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

// Função: Relatório por Modalidade
function gerarRelatorioModalidade($pdo, $where, $params, $formato, $incluir_graficos) {
    // Dados por modalidade
    $sql = "SELECT 
            modalidade,
            COUNT(*) as total,
            SUM(CASE WHEN situacao = 'HOMOLOGADO' THEN 1 ELSE 0 END) as homologadas,
            SUM(CASE WHEN situacao = 'EM_ANDAMENTO' THEN 1 ELSE 0 END) as em_andamento,
            SUM(CASE WHEN situacao = 'FRACASSADO' THEN 1 ELSE 0 END) as fracassadas,
            SUM(CASE WHEN situacao = 'REVOGADO' THEN 1 ELSE 0 END) as revogadas,
            SUM(valor_estimado) as valor_total_estimado,
            SUM(CASE WHEN situacao = 'HOMOLOGADO' THEN valor_homologado ELSE 0 END) as valor_total_homologado,
            AVG(CASE WHEN situacao = 'HOMOLOGADO' AND data_homologacao IS NOT NULL AND data_entrada_dipli IS NOT NULL 
                THEN DATEDIFF(data_homologacao, data_entrada_dipli) END) as tempo_medio_dias
            FROM licitacoes l
            WHERE $where
            GROUP BY modalidade
            ORDER BY total DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    // Gerar saída
    if ($formato === 'html') {
        gerarHTMLModalidade($dados, $incluir_graficos, $params);
    } elseif ($formato === 'pdf') {
        gerarPDFModalidade($dados, $incluir_graficos);
    } else {
        gerarExcelModalidade($dados);
    }
}

// Função: Relatório por Pregoeiro
function gerarRelatorioPregoeiro($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "SELECT 
            CASE WHEN pregoeiro IS NULL OR pregoeiro = '' THEN 'Não definido' ELSE pregoeiro END as pregoeiro,
            COUNT(*) as total_processos,
            AVG(CASE WHEN data_abertura IS NOT NULL AND data_entrada_dipli IS NOT NULL 
                THEN DATEDIFF(data_abertura, data_entrada_dipli) END) as media_dias_tramitacao,
            SUM(CASE WHEN situacao = 'HOMOLOGADO' THEN 1 ELSE 0 END) as homologadas,
            ROUND(SUM(CASE WHEN situacao = 'HOMOLOGADO' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as taxa_sucesso,
            SUM(valor_estimado) as valor_total_estimado,
            SUM(CASE WHEN situacao = 'HOMOLOGADO' THEN economia ELSE 0 END) as economia_total,
            COUNT(DISTINCT modalidade) as modalidades_utilizadas
            FROM licitacoes l
            WHERE $where
            GROUP BY pregoeiro
            ORDER BY total_processos DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLPregoeiro($dados, $incluir_graficos, $params);
    } elseif ($formato === 'pdf') {
        gerarPDFPregoeiro($dados, $incluir_graficos);
    } else {
        gerarExcelPregoeiro($dados);
    }
}

// Função: Relatório de Prazos
function gerarRelatorioPrazos($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "SELECT 
            modalidade,
            COUNT(*) as total,
            AVG(CASE WHEN data_abertura IS NOT NULL AND data_entrada_dipli IS NOT NULL 
                THEN DATEDIFF(data_abertura, data_entrada_dipli) END) as media_dias_ate_abertura,
            MIN(CASE WHEN data_abertura IS NOT NULL AND data_entrada_dipli IS NOT NULL 
                THEN DATEDIFF(data_abertura, data_entrada_dipli) END) as min_dias,
            MAX(CASE WHEN data_abertura IS NOT NULL AND data_entrada_dipli IS NOT NULL 
                THEN DATEDIFF(data_abertura, data_entrada_dipli) END) as max_dias,
            AVG(CASE WHEN data_homologacao IS NOT NULL AND data_entrada_dipli IS NOT NULL 
                THEN DATEDIFF(data_homologacao, data_entrada_dipli) END) as media_dias_conclusao,
            COUNT(CASE WHEN data_abertura IS NOT NULL AND data_entrada_dipli IS NOT NULL 
                AND DATEDIFF(data_abertura, data_entrada_dipli) > 30 THEN 1 END) as processos_longos
            FROM licitacoes l
            WHERE $where 
            AND data_entrada_dipli IS NOT NULL 
            GROUP BY modalidade
            ORDER BY media_dias_ate_abertura DESC";
    
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
function gerarRelatorioFinanceiro($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "SELECT 
            DATE_FORMAT(criado_em, '%Y-%m') as mes,
            COUNT(*) as total_processos,
            SUM(valor_estimado) as valor_estimado_total,
            SUM(CASE WHEN situacao = 'HOMOLOGADO' THEN valor_homologado ELSE 0 END) as valor_homologado_total,
            SUM(CASE WHEN situacao = 'HOMOLOGADO' THEN economia ELSE 0 END) as economia_total,
            AVG(valor_estimado) as valor_medio_estimado,
            ROUND(SUM(CASE WHEN situacao = 'HOMOLOGADO' THEN economia ELSE 0 END) * 100.0 / 
                NULLIF(SUM(CASE WHEN situacao = 'HOMOLOGADO' THEN valor_estimado ELSE 0 END), 0), 2) as percentual_economia
            FROM licitacoes l
            WHERE $where
            GROUP BY DATE_FORMAT(criado_em, '%Y-%m')
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

// HTML para relatório de modalidade
function gerarHTMLModalidade($dados, $incluir_graficos, $params) {
    $total_geral = array_sum(array_column($dados, 'total'));
    $valor_total = array_sum(array_column($dados, 'valor_total_estimado'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório por Modalidade</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
            .info { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 30px; }
            .info p { margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #34495e; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f8f9fa; }
            .chart-container { width: 100%; margin: 30px 0; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Relatório de Licitações por Modalidade</h1>
            
            <div class="info">
                <p><strong>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong>Total de Licitações:</strong> <?php echo $total_geral; ?></p>
                <p><strong>Valor Total Estimado:</strong> R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></p>
                <p><strong>Data de Geração:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            
            <?php if ($incluir_graficos): ?>
            <div class="grid">
                <div class="chart-container">
                    <canvas id="chartModalidade"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="chartSituacao"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Modalidade</th>
                        <th style="text-align: center;">Total</th>
                        <th style="text-align: center;">Homologadas</th>
                        <th style="text-align: center;">Em Andamento</th>
                        <th style="text-align: center;">Fracassadas</th>
                        <th style="text-align: center;">Revogadas</th>
                        <th style="text-align: right;">Valor Estimado</th>
                        <th style="text-align: right;">Valor Homologado</th>
                        <th style="text-align: center;">Tempo Médio (dias)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $row): ?>
                    <tr>
                        <td><strong><?php echo $row['modalidade']; ?></strong></td>
                        <td style="text-align: center;"><?php echo $row['total']; ?></td>
                        <td style="text-align: center;"><?php echo $row['homologadas']; ?></td>
                        <td style="text-align: center;"><?php echo $row['em_andamento']; ?></td>
                        <td style="text-align: center;"><?php echo $row['fracassadas']; ?></td>
                        <td style="text-align: center;"><?php echo $row['revogadas']; ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['valor_total_estimado'], 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['valor_total_homologado'], 2, ',', '.'); ?></td>
                        <td style="text-align: center;"><?php echo $row['tempo_medio_dias'] ? round($row['tempo_medio_dias']) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background: #ecf0f1; font-weight: bold;">
                        <td>TOTAL</td>
                        <td style="text-align: center;"><?php echo $total_geral; ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'homologadas')); ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'em_andamento')); ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'fracassadas')); ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'revogadas')); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format(array_sum(array_column($dados, 'valor_total_homologado')), 2, ',', '.'); ?></td>
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
            // Gráfico de Pizza - Distribuição por Modalidade
            new Chart(document.getElementById('chartModalidade'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($dados, 'modalidade')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($dados, 'total')); ?>,
                        backgroundColor: ['#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Distribuição por Modalidade'
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Gráfico de Barras - Situação por Modalidade
            new Chart(document.getElementById('chartSituacao'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($dados, 'modalidade')); ?>,
                    datasets: [
                        {
                            label: 'Homologadas',
                            data: <?php echo json_encode(array_column($dados, 'homologadas')); ?>,
                            backgroundColor: '#27ae60'
                        },
                        {
                            label: 'Em Andamento',
                            data: <?php echo json_encode(array_column($dados, 'em_andamento')); ?>,
                            backgroundColor: '#f39c12'
                        },
                        {
                            label: 'Fracassadas',
                            data: <?php echo json_encode(array_column($dados, 'fracassadas')); ?>,
                            backgroundColor: '#e74c3c'
                        },
                        {
                            label: 'Revogadas',
                            data: <?php echo json_encode(array_column($dados, 'revogadas')); ?>,
                            backgroundColor: '#95a5a6'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Situação por Modalidade'
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
    
    registrarLog('GERAR_RELATORIO', 'Gerou relatório de licitações por modalidade');
}

// HTML para relatório por pregoeiro
function gerarHTMLPregoeiro($dados, $incluir_graficos, $params) {
    $total_geral = array_sum(array_column($dados, 'total_processos'));
    $valor_total = array_sum(array_column($dados, 'valor_total_estimado'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório por Pregoeiro</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
            .info { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 30px; }
            .info p { margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #34495e; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f8f9fa; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Relatório de Desempenho por Pregoeiro</h1>
            
            <div class="info">
                <p><strong>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong>Total de Processos:</strong> <?php echo $total_geral; ?></p>
                <p><strong>Valor Total Estimado:</strong> R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></p>
                <p><strong>Data de Geração:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            
            <?php if ($incluir_graficos): ?>
            <div class="chart-container">
                <canvas id="chartPregoeiro"></canvas>
            </div>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Pregoeiro</th>
                        <th style="text-align: center;">Total Processos</th>
                        <th style="text-align: center;">Homologadas</th>
                        <th style="text-align: center;">Taxa Sucesso (%)</th>
                        <th style="text-align: center;">Tempo Médio (dias)</th>
                        <th style="text-align: right;">Valor Estimado</th>
                        <th style="text-align: right;">Economia Total</th>
                        <th style="text-align: center;">Modalidades</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['pregoeiro']); ?></strong></td>
                        <td style="text-align: center;"><?php echo $row['total_processos']; ?></td>
                        <td style="text-align: center;"><?php echo $row['homologadas']; ?></td>
                        <td style="text-align: center;"><?php echo number_format($row['taxa_sucesso'], 1); ?>%</td>
                        <td style="text-align: center;"><?php echo $row['media_dias_tramitacao'] ? round($row['media_dias_tramitacao']) : '-'; ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['valor_total_estimado'], 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['economia_total'], 2, ',', '.'); ?></td>
                        <td style="text-align: center;"><?php echo $row['modalidades_utilizadas']; ?></td>
                    </tr>
                    <?php endforeach; ?>
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
            // Gráfico de Barras - Processos por Pregoeiro
            new Chart(document.getElementById('chartPregoeiro'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($dados, 'pregoeiro')); ?>,
                    datasets: [{
                        label: 'Total de Processos',
                        data: <?php echo json_encode(array_column($dados, 'total_processos')); ?>,
                        backgroundColor: '#3498db'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Distribuição de Processos por Pregoeiro'
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
    
    registrarLog('GERAR_RELATORIO', 'Gerou relatório de licitações por pregoeiro');
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
        <title>Relatório de Prazos</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
            .info { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 30px; }
            .info p { margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #34495e; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f8f9fa; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            .warning { color: #e74c3c; font-weight: bold; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Relatório de Análise de Prazos</h1>
            
            <div class="info">
                <p><strong>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong>Total de Modalidades Analisadas:</strong> <?php echo count($dados); ?></p>
                <p><strong>Data de Geração:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            
            <?php if ($incluir_graficos): ?>
            <div class="chart-container">
                <canvas id="chartPrazos"></canvas>
            </div>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Modalidade</th>
                        <th style="text-align: center;">Total Processos</th>
                        <th style="text-align: center;">Tempo Médio Abertura (dias)</th>
                        <th style="text-align: center;">Tempo Mínimo (dias)</th>
                        <th style="text-align: center;">Tempo Máximo (dias)</th>
                        <th style="text-align: center;">Tempo Médio Conclusão (dias)</th>
                        <th style="text-align: center;">Processos Longos (>30 dias)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $row): ?>
                    <tr>
                        <td><strong><?php echo $row['modalidade']; ?></strong></td>
                        <td style="text-align: center;"><?php echo $row['total']; ?></td>
                        <td style="text-align: center; <?php echo $row['media_dias_ate_abertura'] > 30 ? 'color: #e74c3c;' : ''; ?>">
                            <?php echo $row['media_dias_ate_abertura'] ? round($row['media_dias_ate_abertura'], 1) : '-'; ?>
                        </td>
                        <td style="text-align: center;"><?php echo $row['min_dias'] ?? '-'; ?></td>
                        <td style="text-align: center;"><?php echo $row['max_dias'] ?? '-'; ?></td>
                        <td style="text-align: center;"><?php echo $row['media_dias_conclusao'] ? round($row['media_dias_conclusao'], 1) : '-'; ?></td>
                        <td style="text-align: center; <?php echo $row['processos_longos'] > 0 ? 'color: #e74c3c;' : ''; ?>">
                            <?php echo $row['processos_longos']; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
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
            // Gráfico de Barras - Tempos Médios por Modalidade
            new Chart(document.getElementById('chartPrazos'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($dados, 'modalidade')); ?>,
                    datasets: [{
                        label: 'Tempo Médio até Abertura (dias)',
                        data: <?php echo json_encode(array_map(function($item) { return round($item['media_dias_ate_abertura'] ?? 0, 1); }, $dados)); ?>,
                        backgroundColor: '#3498db'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Tempo Médio de Tramitação por Modalidade'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Dias'
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
    
    registrarLog('GERAR_RELATORIO', 'Gerou relatório de prazos de licitações');
}

// HTML para relatório financeiro
function gerarHTMLFinanceiro($dados, $incluir_graficos, $params) {
    $valor_total_estimado = array_sum(array_column($dados, 'valor_estimado_total'));
    $valor_total_homologado = array_sum(array_column($dados, 'valor_homologado_total'));
    $economia_total = array_sum(array_column($dados, 'economia_total'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório Financeiro</title>
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
            .summary-card .value { font-size: 24px; font-weight: bold; color: #27ae60; }
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
            <h1>Relatório Financeiro de Licitações</h1>
            
            <div class="info">
                <p><strong>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong>Total de Meses Analisados:</strong> <?php echo count($dados); ?></p>
                <p><strong>Data de Geração:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            
            <div class="summary">
                <div class="summary-card">
                    <h3>Valor Total Estimado</h3>
                    <div class="value">R$ <?php echo number_format($valor_total_estimado, 2, ',', '.'); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Valor Total Homologado</h3>
                    <div class="value">R$ <?php echo number_format($valor_total_homologado, 2, ',', '.'); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Economia Total</h3>
                    <div class="value">R$ <?php echo number_format($economia_total, 2, ',', '.'); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Percentual de Economia</h3>
                    <div class="value"><?php echo $valor_total_estimado > 0 ? number_format(($economia_total / $valor_total_estimado) * 100, 2) : '0'; ?>%</div>
                </div>
            </div>
            
            <?php if ($incluir_graficos): ?>
            <div class="grid">
                <div class="chart-container">
                    <canvas id="chartValores"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="chartEconomia"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Mês/Ano</th>
                        <th style="text-align: center;">Total Processos</th>
                        <th style="text-align: right;">Valor Estimado</th>
                        <th style="text-align: right;">Valor Homologado</th>
                        <th style="text-align: right;">Economia</th>
                        <th style="text-align: center;">% Economia</th>
                        <th style="text-align: right;">Valor Médio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $row): 
                        $mes_ano = DateTime::createFromFormat('Y-m', $row['mes'])->format('m/Y');
                    ?>
                    <tr>
                        <td><strong><?php echo $mes_ano; ?></strong></td>
                        <td style="text-align: center;"><?php echo $row['total_processos']; ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['valor_estimado_total'], 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['valor_homologado_total'], 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['economia_total'], 2, ',', '.'); ?></td>
                        <td style="text-align: center;"><?php echo number_format($row['percentual_economia'] ?? 0, 2); ?>%</td>
                        <td style="text-align: right;">R$ <?php echo number_format($row['valor_medio_estimado'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background: #ecf0f1; font-weight: bold;">
                        <td>TOTAL</td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($dados, 'total_processos')); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($valor_total_estimado, 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($valor_total_homologado, 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($economia_total, 2, ',', '.'); ?></td>
                        <td style="text-align: center;"><?php echo $valor_total_estimado > 0 ? number_format(($economia_total / $valor_total_estimado) * 100, 2) : '0'; ?>%</td>
                        <td style="text-align: right;">-</td>
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
                            label: 'Valor Estimado',
                            data: <?php echo json_encode(array_column($dados, 'valor_estimado_total')); ?>,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Valor Homologado',
                            data: <?php echo json_encode(array_column($dados, 'valor_homologado_total')); ?>,
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
            
            // Gráfico de Barras - Economia por Mês
            new Chart(document.getElementById('chartEconomia'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($item) { 
                        return DateTime::createFromFormat('Y-m', $item['mes'])->format('m/Y'); 
                    }, $dados)); ?>,
                    datasets: [{
                        label: 'Economia Obtida',
                        data: <?php echo json_encode(array_column($dados, 'economia_total')); ?>,
                        backgroundColor: '#f39c12'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Economia Obtida por Mês'
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
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    
    registrarLog('GERAR_RELATORIO', 'Gerou relatório financeiro de licitações');
}

// Funções para gerar PDF (usando TCPDF ou similar)
function gerarPDFModalidade($dados, $incluir_graficos) {
    // Para PDF, você precisaria integrar uma biblioteca como TCPDF
    // Por simplicidade, vou redirecionar para CSV
    gerarExcelModalidade($dados);
}

function gerarPDFPregoeiro($dados, $incluir_graficos) {
    gerarExcelPregoeiro($dados);
}

function gerarPDFPrazos($dados, $incluir_graficos) {
    gerarExcelPrazos($dados);
}

function gerarPDFFinanceiro($dados, $incluir_graficos) {
    gerarExcelFinanceiro($dados);
}

// Funções para gerar Excel/CSV
function gerarExcelModalidade($dados) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_modalidade_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    
    $output = fopen('php://output', 'w');
    
    $cabecalho = [
        'Modalidade',
        'Total',
        'Homologadas',
        'Em Andamento',
        'Fracassadas',
        'Revogadas',
        'Valor Estimado',
        'Valor Homologado',
        'Tempo Médio (dias)'
    ];
    
    fputcsv($output, $cabecalho, ';');
    
    foreach ($dados as $row) {
        $linha = [
            $row['modalidade'],
            $row['total'],
            $row['homologadas'],
            $row['em_andamento'],
            $row['fracassadas'],
            $row['revogadas'],
            number_format($row['valor_total_estimado'], 2, ',', '.'),
            number_format($row['valor_total_homologado'], 2, ',', '.'),
            $row['tempo_medio_dias'] ? round($row['tempo_medio_dias']) : ''
        ];
        
        fputcsv($output, $linha, ';');
    }
    
    fclose($output);
    registrarLog('EXPORTAR_RELATORIO', 'Exportou relatório de modalidade em CSV');
    exit;
}

function gerarExcelPregoeiro($dados) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_pregoeiro_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    $cabecalho = [
        'Pregoeiro',
        'Total Processos',
        'Homologadas',
        'Taxa Sucesso (%)',
        'Tempo Médio (dias)',
        'Valor Estimado',
        'Economia Total',
        'Modalidades Utilizadas'
    ];
    
    fputcsv($output, $cabecalho, ';');
    
    foreach ($dados as $row) {
        $linha = [
            $row['pregoeiro'],
            $row['total_processos'],
            $row['homologadas'],
            number_format($row['taxa_sucesso'], 1, ',', '.'),
            $row['media_dias_tramitacao'] ? round($row['media_dias_tramitacao']) : '',
            number_format($row['valor_total_estimado'], 2, ',', '.'),
            number_format($row['economia_total'], 2, ',', '.'),
            $row['modalidades_utilizadas']
        ];
        
        fputcsv($output, $linha, ';');
    }
    
    fclose($output);
    registrarLog('EXPORTAR_RELATORIO', 'Exportou relatório de pregoeiro em CSV');
    exit;
}

function gerarExcelPrazos($dados) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_prazos_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    $cabecalho = [
        'Modalidade',
        'Total Processos',
        'Tempo Médio Abertura (dias)',
        'Tempo Mínimo (dias)',
        'Tempo Máximo (dias)',
        'Tempo Médio Conclusão (dias)',
        'Processos Longos (>30 dias)'
    ];
    
    fputcsv($output, $cabecalho, ';');
    
    foreach ($dados as $row) {
        $linha = [
            $row['modalidade'],
            $row['total'],
            $row['media_dias_ate_abertura'] ? round($row['media_dias_ate_abertura'], 1) : '',
            $row['min_dias'] ?? '',
            $row['max_dias'] ?? '',
            $row['media_dias_conclusao'] ? round($row['media_dias_conclusao'], 1) : '',
            $row['processos_longos']
        ];
        
        fputcsv($output, $linha, ';');
    }
    
    fclose($output);
    registrarLog('EXPORTAR_RELATORIO', 'Exportou relatório de prazos em CSV');
    exit;
}

function gerarExcelFinanceiro($dados) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_financeiro_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    $cabecalho = [
        'Mês/Ano',
        'Total Processos',
        'Valor Estimado',
        'Valor Homologado',
        'Economia',
        'Percentual Economia (%)',
        'Valor Médio'
    ];
    
    fputcsv($output, $cabecalho, ';');
    
    foreach ($dados as $row) {
        $mes_ano = DateTime::createFromFormat('Y-m', $row['mes'])->format('m/Y');
        
        $linha = [
            $mes_ano,
            $row['total_processos'],
            number_format($row['valor_estimado_total'], 2, ',', '.'),
            number_format($row['valor_homologado_total'], 2, ',', '.'),
            number_format($row['economia_total'], 2, ',', '.'),
            number_format($row['percentual_economia'] ?? 0, 2, ',', '.'),
            number_format($row['valor_medio_estimado'], 2, ',', '.')
        ];
        
        fputcsv($output, $linha, ';');
    }
    
    fclose($output);
    registrarLog('EXPORTAR_RELATORIO', 'Exportou relatório financeiro em CSV');
    exit;
}
?>