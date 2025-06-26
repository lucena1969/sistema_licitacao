<?php
// ========================================
// TEMPLATE DA SEÇÃO PRINCIPAL DO DASHBOARD
// Exibe estatísticas, gráficos e informações gerais
// ========================================
?>

<div class="dashboard-header">
    <div>
        <h1><i data-lucide="bar-chart-3"></i> Dashboard do PCA <?php echo $ano_selecionado; ?></h1>
        <p>Visão geral do planejamento de contratações aprovadas</p>
    </div>
    
    <div style="display: flex; align-items: center; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <form method="GET" style="display: flex; align-items: center; gap: 10px;">
                <input type="hidden" name="secao" value="dashboard">
                <label for="ano" style="font-weight: 600; color: #2c3e50;">Ano:</label>
                <select name="ano" id="ano" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    <?php foreach ($anos_disponiveis as $ano): ?>
                        <option value="<?php echo $ano; ?>" <?php echo $ano == $ano_selecionado ? 'selected' : ''; ?>>
                            <?php echo $ano; ?> <?php echo $ano >= 2025 ? '(Atual)' : '(Histórico)'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <?php if ($eh_historico): ?>
                <span style="background: #fef3c7; color: #a16207; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                    <i data-lucide="archive" style="width: 12px; height: 12px;"></i> Somente Leitura
                </span>
            <?php else: ?>
                <span style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                    <i data-lucide="edit" style="width: 12px; height: 12px;"></i> Editável
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Alerta sobre filtro de contratações aprovadas -->
<div style="background: #e8f5e8; border: 1px solid #28a745; border-radius: 8px; padding: 15px; margin: 20px 0;">
    <div style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="info" style="width: 20px; height: 20px; color: #28a745;"></i>
        <div>
            <h4 style="margin: 0; color: #155724;">Filtro Aplicado: Apenas Contratações Aprovadas</h4>
            <p style="margin: 5px 0 0 0; color: #155724; font-size: 14px;">
                Conforme Lei 14133/2021, apenas contratações com status "Aprovada" iniciarão processos licitatórios.
                Os valores exibidos representam o planejamento real para execução.
            </p>
        </div>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="stats-grid">
    <div class="stat-card info">
        <div class="stat-number"><?php echo number_format($stats['total_dfds'] ?? 0); ?></div>
        <div class="stat-label">Total de DFDs Aprovados</div>
        <div class="stat-change">
            <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
            Apenas aprovadas
        </div>
    </div>
    
    <div class="stat-card primary">
        <div class="stat-number"><?php echo number_format($stats['total_contratacoes'] ?? 0); ?></div>
        <div class="stat-label">Contratações para Licitação</div>
        <div class="stat-change">
            <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i>
            Status: Aprovada
        </div>
    </div>
    
    <div class="stat-card money">
        <div class="stat-number"><?php echo abreviarValor($stats['valor_total'] ?? 0); ?></div>
        <div class="stat-label">Valor Total Aprovado (R$)</div>
        <div class="stat-change">
            <i data-lucide="dollar-sign" style="width: 14px; height: 14px;"></i>
            Sem duplicações
        </div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-number"><?php echo $stats['homologadas'] ?? 0; ?></div>
        <div class="stat-label">Concluídas</div>
        <div class="stat-change">
            <?php echo $stats['percentual_conclusao'] ?? 0; ?>% do total
        </div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-number"><?php echo ($stats['atrasadas_inicio'] ?? 0) + ($stats['atrasadas_conclusao'] ?? 0); ?></div>
        <div class="stat-label">Atrasadas</div>
        <div class="stat-change">
            <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
            Requer atenção
        </div>
    </div>
</div>

<!-- Grid de Gráficos -->
<div class="charts-grid">
    <!-- Gráfico por Categoria -->
    <div class="chart-card">
        <h3 class="chart-title">
            <i data-lucide="pie-chart"></i> 
            Contratações por Categoria
        </h3>
        <div class="chart-container">
            <canvas id="chartCategorias"></canvas>
        </div>
        <?php if (!empty($stats['top_categorias'])): ?>
            <div class="chart-legend">
                <h4>Top 3 Categorias:</h4>
                <?php foreach (array_slice($stats['top_categorias'], 0, 3) as $categoria): ?>
                    <div class="legend-item">
                        <span class="legend-label"><?php echo htmlspecialchars($categoria['categoria_contratacao']); ?></span>
                        <span class="legend-value"><?php echo formatarMoeda($categoria['valor_total']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Gráfico por Situação -->
    <div class="chart-card">
        <h3 class="chart-title">
            <i data-lucide="activity"></i> 
            Status de Execução
        </h3>
        <div class="chart-container">
            <canvas id="chartSituacoes"></canvas>
        </div>
        <div class="chart-summary">
            <div class="summary-item">
                <span class="summary-label">Taxa de Conclusão:</span>
                <span class="summary-value"><?php echo $stats['percentual_conclusao'] ?? 0; ?>%</span>
            </div>
        </div>
    </div>

    <!-- Gráfico por Área -->
    <div class="chart-card">
        <h3 class="chart-title">
            <i data-lucide="building"></i> 
            Contratações por Área
        </h3>
        <div class="chart-container">
            <canvas id="chartAreas"></canvas>
        </div>
        <?php if (!empty($stats['top_areas'])): ?>
            <div class="chart-legend">
                <h4>Top 3 Áreas:</h4>
                <?php foreach (array_slice($stats['top_areas'], 0, 3) as $area): ?>
                    <div class="legend-item">
                        <span class="legend-label"><?php echo htmlspecialchars($area['area_agrupada']); ?></span>
                        <span class="legend-value"><?php echo $area['quantidade']; ?> contratos</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Indicadores de Performance -->
    <div class="chart-card">
        <h3 class="chart-title">
            <i data-lucide="target"></i> 
            Indicadores de Performance
        </h3>
        <div style="padding: 20px;">
            <?php 
            $indicadores = calcularIndicadoresPerformance($pdo, $ano_selecionado);
            ?>
            <div class="performance-indicators">
                <div class="indicator">
                    <div class="indicator-value"><?php echo $indicadores['taxa_no_prazo']; ?>%</div>
                    <div class="indicator-label">No Prazo</div>
                </div>
                <div class="indicator">
                    <div class="indicator-value"><?php echo $indicadores['tempo_medio_execucao']; ?></div>
                    <div class="indicator-label">Dias Médios</div>
                </div>
                <div class="indicator">
                    <div class="indicator-value"><?php echo abreviarValor($indicadores['valor_medio_contratacao']); ?></div>
                    <div class="indicator-label">Valor Médio</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resumo Executivo -->
<?php 
$resumo = gerarResumoExecutivo($stats);
if (!empty($resumo['alertas']) || !empty($resumo['destaques'])): 
?>
<div class="executive-summary">
    <h3><i data-lucide="clipboard-list"></i> Resumo Executivo</h3>
    
    <div class="summary-status">
        <div class="status-badge status-<?php echo $resumo['cor_status']; ?>">
            <?php echo $resumo['status_geral']; ?>
        </div>
    </div>

    <?php if (!empty($resumo['alertas'])): ?>
    <div class="summary-section">
        <h4><i data-lucide="alert-triangle"></i> Alertas</h4>
        <?php foreach ($resumo['alertas'] as $alerta): ?>
            <div class="alert alert-<?php echo $alerta['tipo']; ?>">
                <?php echo $alerta['mensagem']; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($resumo['destaques'])): ?>
    <div class="summary-section">
        <h4><i data-lucide="star"></i> Destaques</h4>
        <?php foreach ($resumo['destaques'] as $destaque): ?>
            <div class="highlight highlight-<?php echo $destaque['tipo']; ?>">
                <?php echo $destaque['mensagem']; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Histórico de Importações (se houver) -->
<?php if (!empty($historico_importacoes)): ?>
<div class="chart-card" style="margin-top: 30px;">
    <h3 class="chart-title">
        <i data-lucide="upload"></i> 
        Últimas Importações do PCA
    </h3>
    <div class="importacao-lista">
        <?php foreach (array_slice($historico_importacoes, 0, 5) as $importacao): ?>
            <div class="importacao-item">
                <div class="importacao-info">
                    <strong><?php echo formatarData($importacao['data_importacao']); ?></strong>
                    <span><?php echo number_format($importacao['total_registros_importados']); ?> registros</span>
                </div>
                <div class="importacao-status">
                    <span class="status-badge status-success">Concluída</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<style>
.performance-indicators {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 20px;
    text-align: center;
}

.indicator {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.indicator-value {
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
}

.indicator-label {
    font-size: 12px;
    color: #7f8c8d;
    margin-top: 5px;
}

.executive-summary {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-top: 30px;
}

.summary-status {
    text-align: center;
    margin-bottom: 20px;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    display: inline-block;
}

.status-success { background: #d4edda; color: #155724; }
.status-info { background: #d1ecf1; color: #0c5460; }
.status-warning { background: #fff3cd; color: #856404; }

.summary-section {
    margin-bottom: 20px;
}

.summary-section h4 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    color: #2c3e50;
}

.alert, .highlight {
    padding: 10px 15px;
    margin-bottom: 8px;
    border-radius: 6px;
    border-left: 4px solid;
}

.alert-warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
.alert-danger { background: #f8d7da; border-color: #dc3545; color: #721c24; }
.highlight-success { background: #d4edda; border-color: #28a745; color: #155724; }
.highlight-info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }

.importacao-lista {
    max-height: 300px;
    overflow-y: auto;
}

.importacao-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border-bottom: 1px solid #e9ecef;
}

.importacao-item:last-child {
    border-bottom: none;
}

.importacao-info strong {
    display: block;
    color: #2c3e50;
}

.importacao-info span {
    font-size: 12px;
    color: #7f8c8d;
}

.chart-legend {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.chart-legend h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #2c3e50;
}

.legend-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    font-size: 12px;
}

.legend-label {
    color: #2c3e50;
    font-weight: 500;
}

.legend-value {
    color: #7f8c8d;
    font-weight: 600;
}

.chart-summary {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
    text-align: center;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
}

.summary-label {
    color: #2c3e50;
    font-weight: 500;
}

.summary-value {
    color: #28a745;
    font-weight: 600;
    font-size: 16px;
}
</style>

<script>
// Dados para os gráficos (serão carregados via JavaScript)
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de Categorias
    <?php if (!empty($stats['top_categorias'])): ?>
    const categoriasData = {
        labels: [<?php foreach ($stats['top_categorias'] as $cat): ?>'<?php echo addslashes($cat['categoria_contratacao']); ?>',<?php endforeach; ?>],
        datasets: [{
            data: [<?php foreach ($stats['top_categorias'] as $cat): ?><?php echo $cat['valor_total']; ?>,<?php endforeach; ?>],
            backgroundColor: [
                '#FF6384',
                '#36A2EB', 
                '#FFCE56',
                '#4BC0C0',
                '#9966FF'
            ]
        }]
    };
    
    new Chart(document.getElementById('chartCategorias'), {
        type: 'doughnut',
        data: categoriasData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    <?php endif; ?>

    // Gráfico de Situações
    const situacoesData = {
        labels: ['Concluídas', 'Em Andamento', 'Não Iniciadas', 'Atrasadas'],
        datasets: [{
            data: [
                <?php echo $stats['homologadas'] ?? 0; ?>,
                <?php echo $stats['em_andamento'] ?? 0; ?>,
                <?php echo $stats['nao_iniciadas'] ?? 0; ?>,
                <?php echo ($stats['atrasadas_inicio'] ?? 0) + ($stats['atrasadas_conclusao'] ?? 0); ?>
            ],
            backgroundColor: ['#28a745', '#ffc107', '#6c757d', '#dc3545']
        }]
    };
    
    new Chart(document.getElementById('chartSituacoes'), {
        type: 'bar',
        data: situacoesData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
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

    // Gráfico de Áreas
    <?php if (!empty($stats['top_areas'])): ?>
    const areasData = {
        labels: [<?php foreach ($stats['top_areas'] as $area): ?>'<?php echo addslashes($area['area_agrupada']); ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'Quantidade',
            data: [<?php foreach ($stats['top_areas'] as $area): ?><?php echo $area['quantidade']; ?>,<?php endforeach; ?>],
            backgroundColor: '#36A2EB'
        }]
    };
    
    new Chart(document.getElementById('chartAreas'), {
        type: 'horizontalBar',
        data: areasData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
    <?php endif; ?>
});
</script>