<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

$pdo = conectarDB();

// Buscar resumo por área
$sql = "SELECT 
    area_requisitante,
    COUNT(DISTINCT CASE WHEN data_inicio_processo < CURDATE() AND situacao_execucao = 'Não iniciado' THEN numero_contratacao END) as atrasadas_inicio,
    COUNT(DISTINCT CASE WHEN data_conclusao_processo < CURDATE() AND situacao_execucao != 'Concluído' THEN numero_contratacao END) as atrasadas_conclusao,
    COUNT(DISTINCT CASE WHEN data_conclusao_processo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN numero_contratacao END) as vencendo_30_dias,
    COUNT(DISTINCT numero_contratacao) as total_contratacoes,
    SUM(valor_total_contratacao) as valor_total
    FROM pca_dados
    WHERE area_requisitante IS NOT NULL
    GROUP BY area_requisitante
    HAVING (COUNT(DISTINCT CASE WHEN data_inicio_processo < CURDATE() AND situacao_execucao = 'Não iniciado' THEN numero_contratacao END) > 0 
            OR COUNT(DISTINCT CASE WHEN data_conclusao_processo < CURDATE() AND situacao_execucao != 'Concluído' THEN numero_contratacao END) > 0 
            OR COUNT(DISTINCT CASE WHEN data_conclusao_processo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN numero_contratacao END) > 0)
    ORDER BY (COUNT(DISTINCT CASE WHEN data_inicio_processo < CURDATE() AND situacao_execucao = 'Não iniciado' THEN numero_contratacao END) + 
              COUNT(DISTINCT CASE WHEN data_conclusao_processo < CURDATE() AND situacao_execucao != 'Concluído' THEN numero_contratacao END)) DESC";

$areas = $pdo->query($sql)->fetchAll();

// Se for solicitado download
if (isset($_GET['download'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_atrasos_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Área Requisitante', 'Atrasadas (Início)', 'Atrasadas (Conclusão)', 'Vencendo em 30 dias', 'Total Contratações', 'Valor Total'], ';');
    
    foreach ($areas as $area) {
        fputcsv($output, [
            $area['area_requisitante'],
            $area['atrasadas_inicio'],
            $area['atrasadas_conclusao'],
            $area['vencendo_30_dias'],
            $area['total_contratacoes'],
            number_format($area['valor_total'], 2, ',', '.')
        ], ';');
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Atrasos - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .print-only { display: none; }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            .header { position: static; }
            body { background: white; }
            .container { max-width: 100%; }
        }
        .resumo-total {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        .resumo-total h2 {
            margin: 0 0 20px 0;
        }
        .resumo-numeros {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        .resumo-item {
            text-align: center;
        }
        .resumo-item .numero {
            font-size: 32px;
            font-weight: bold;
            display: block;
        }
        .resumo-item .label {
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header no-print">
        <div class="header-content">
            <h1>Relatório de Atrasos por Área</h1>
            <div class="nav-menu">
                <a href="dashboard.php">← Voltar ao Dashboard</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="print-only">
            <h1>Relatório de Atrasos por Área</h1>
            <p>Data: <?php echo date('d/m/Y H:i'); ?></p>
        </div>

        <?php
        $total_atrasadas_inicio = array_sum(array_column($areas, 'atrasadas_inicio'));
        $total_atrasadas_conclusao = array_sum(array_column($areas, 'atrasadas_conclusao'));
        $total_vencendo = array_sum(array_column($areas, 'vencendo_30_dias'));
        ?>

        <div class="resumo-total">
            <h2>Resumo Geral</h2>
            <div class="resumo-numeros">
                <div class="resumo-item">
                    <span class="numero" style="color: #ff6b6b;"><?php echo $total_atrasadas_inicio; ?></span>
                    <span class="label">Atrasadas (Início)</span>
                </div>
                <div class="resumo-item">
                    <span class="numero" style="color: #dc3545;"><?php echo $total_atrasadas_conclusao; ?></span>
                    <span class="label">Atrasadas (Conclusão)</span>
                </div>
                <div class="resumo-item">
                    <span class="numero" style="color: #f39c12;"><?php echo $total_vencendo; ?></span>
                    <span class="label">Vencendo em 30 dias</span>
                </div>
            </div>
        </div>

        <div class="tabela-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Detalhamento por Área</h3>
                <div class="no-print">
                    <a href="?download=1" class="btn btn-pequeno btn-sucesso">📥 Baixar CSV</a>
                    <button onclick="window.print()" class="btn btn-pequeno">🖨️ Imprimir</button>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Área Requisitante</th>
                        <th style="text-align: center;">Atrasadas<br>(Início)</th>
                        <th style="text-align: center;">Atrasadas<br>(Conclusão)</th>
                        <th style="text-align: center;">Vencendo<br>em 30 dias</th>
                        <th style="text-align: center;">Total<br>Contratações</th>
                        <th style="text-align: right;">Valor Total</th>
                        <th class="no-print" style="width: 100px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($areas as $area): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($area['area_requisitante']); ?></td>
                        <td style="text-align: center;">
                            <?php if ($area['atrasadas_inicio'] > 0): ?>
                                <span style="color: #ff6b6b; font-weight: bold;">
                                    <?php echo $area['atrasadas_inicio']; ?>
                                </span>
                            <?php else: ?>
                                0
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($area['atrasadas_conclusao'] > 0): ?>
                                <span style="color: #dc3545; font-weight: bold;">
                                    <?php echo $area['atrasadas_conclusao']; ?>
                                </span>
                            <?php else: ?>
                                0
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($area['vencendo_30_dias'] > 0): ?>
                                <span style="color: #f39c12; font-weight: bold;">
                                    <?php echo $area['vencendo_30_dias']; ?>
                                </span>
                            <?php else: ?>
                                0
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;"><?php echo $area['total_contratacoes']; ?></td>
                        <td style="text-align: right;"><?php echo formatarMoeda($area['valor_total']); ?></td>
                        <td class="no-print">
                            <a href="dashboard.php?area_requisitante=<?php echo urlencode($area['area_requisitante']); ?>&situacao_execucao=atrasadas_inicio" 
                               class="btn btn-pequeno" title="Ver contratações">
                                Ver
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight: bold; background-color: #f8f9fa;">
                        <td>TOTAL</td>
                        <td style="text-align: center; color: #ff6b6b;"><?php echo $total_atrasadas_inicio; ?></td>
                        <td style="text-align: center; color: #dc3545;"><?php echo $total_atrasadas_conclusao; ?></td>
                        <td style="text-align: center; color: #f39c12;"><?php echo $total_vencendo; ?></td>
                        <td style="text-align: center;"><?php echo array_sum(array_column($areas, 'total_contratacoes')); ?></td>
                        <td style="text-align: right;"><?php echo formatarMoeda(array_sum(array_column($areas, 'valor_total'))); ?></td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-20 no-print">
            <p class="text-muted">
                <strong>Legenda:</strong><br>
                <span style="color: #ff6b6b;">● Atrasadas (Início)</span> - Contratações que já passaram da data de início mas ainda estão como "Não iniciado"<br>
                <span style="color: #dc3545;">● Atrasadas (Conclusão)</span> - Contratações que já passaram da data de conclusão mas não foram concluídas<br>
                <span style="color: #f39c12;">● Vencendo em 30 dias</span> - Contratações que vencerão nos próximos 30 dias
            </p>
        </div>
    </div>
</body>
</html>