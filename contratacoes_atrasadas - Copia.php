<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

// Buscar áreas para o filtro (agrupadas)
$areas_sql = "SELECT DISTINCT area_requisitante FROM pca_dados WHERE area_requisitante IS NOT NULL AND area_requisitante != '' ORDER BY area_requisitante";
$areas_result = $pdo->query($areas_sql);
$areas_agrupadas = [];

while ($row = $areas_result->fetch()) {
    $area_agrupada = agruparArea($row['area_requisitante']);
    if (!in_array($area_agrupada, $areas_agrupadas)) {
        $areas_agrupadas[] = $area_agrupada;
    }
}
sort($areas_agrupadas);

// Filtros
$filtro_area = $_GET['area'] ?? '';

// Construir WHERE para área
$where_area = '';
$params_area = [];
if (!empty($filtro_area)) {
    if ($filtro_area === 'GM.') {
        $where_area = " AND (area_requisitante LIKE 'GM%' OR area_requisitante LIKE 'GM.%')";
    } else {
        $where_area = " AND area_requisitante LIKE ?";
        $params_area[] = $filtro_area . '%';
    }
}

// CONTRATAÇÕES VENCIDAS (agrupadas por DFD)
$sql_vencidas = "SELECT DISTINCT 
    numero_contratacao,
    numero_dfd,
    titulo_contratacao,
    area_requisitante,
    data_inicio_processo,
    data_conclusao_processo,
    situacao_execucao,
    valor_total_contratacao,
    prioridade,
    DATEDIFF(CURDATE(), data_conclusao_processo) as dias_atraso
    FROM pca_dados 
    WHERE data_conclusao_processo < CURDATE()
    AND (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao = 'Não iniciado')
    AND numero_dfd IS NOT NULL 
    AND numero_dfd != ''
    $where_area
    GROUP BY numero_dfd
    ORDER BY dias_atraso DESC";

$stmt_vencidas = $pdo->prepare($sql_vencidas);
$stmt_vencidas->execute($params_area);
$contratacoes_vencidas = $stmt_vencidas->fetchAll();

// CONTRATAÇÕES NÃO INICIADAS (agrupadas por DFD)  
$sql_nao_iniciadas = "SELECT DISTINCT 
    numero_contratacao,
    numero_dfd,
    titulo_contratacao,
    area_requisitante,
    data_inicio_processo,
    data_conclusao_processo,
    situacao_execucao,
    valor_total_contratacao,
    prioridade,
    DATEDIFF(CURDATE(), data_inicio_processo) as dias_atraso_inicio
    FROM pca_dados 
    WHERE data_inicio_processo < CURDATE() 
    AND (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao = 'Não iniciado')
    AND data_conclusao_processo >= CURDATE()
    AND numero_dfd IS NOT NULL 
    AND numero_dfd != ''
    $where_area
    GROUP BY numero_dfd
    ORDER BY dias_atraso_inicio DESC";

$stmt_nao_iniciadas = $pdo->prepare($sql_nao_iniciadas);
$stmt_nao_iniciadas->execute($params_area);
$contratacoes_nao_iniciadas = $stmt_nao_iniciadas->fetchAll();

// Calcular totais
$total_vencidas = count($contratacoes_vencidas);
$total_nao_iniciadas = count($contratacoes_nao_iniciadas);
$valor_total_vencidas = array_sum(array_column($contratacoes_vencidas, 'valor_total_contratacao'));
$valor_total_nao_iniciadas = array_sum(array_column($contratacoes_nao_iniciadas, 'valor_total_contratacao'));
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contratações Atrasadas - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .page-container {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ==================== HEADER ==================== */
        .page-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 35px;
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 50%;
            height: 200%;
            background: rgba(255,255,255,0.05);
            transform: rotate(35deg);
        }

        .header-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-left h1 {
            margin: 0 0 10px 0;
            font-size: 36px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-left p {
            margin: 0;
            font-size: 18px;
            opacity: 0.95;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-voltar {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            color: white;
            padding: 12px 24px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-voltar:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        /* ==================== FILTROS ==================== */
        .filtros-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .filtros-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filtros-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filtros-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filtro-group {
            flex: 1;
            min-width: 250px;
        }

        .filtro-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }

        .filtro-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            transition: all 0.3s ease;
        }

        .filtro-group select:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .btn-filtrar {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-filtrar:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-limpar {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-limpar:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* ==================== CARDS RESUMO ==================== */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
        }

        .stat-card.danger::before {
            background: linear-gradient(90deg, #dc3545, #c82333);
        }

        .stat-card.warning::before {
            background: linear-gradient(90deg, #ffc107, #e0a800);
        }

        .stat-card.total::before {
            background: linear-gradient(90deg, #6f42c1, #5a32a3);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .stat-card.danger .stat-icon {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .stat-card.warning .stat-icon {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .stat-card.total .stat-icon {
            background: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
        }

        .stat-value {
            font-size: 42px;
            font-weight: 800;
            color: #2c3e50;
            margin: 0 0 8px 0;
        }

        .stat-label {
            font-size: 16px;
            color: #6c757d;
            margin: 0 0 15px 0;
        }

        .stat-details {
            display: flex;
            justify-content: space-between;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        .stat-detail-item {
            text-align: center;
        }

        .stat-detail-value {
            font-size: 20px;
            font-weight: 700;
            color: #495057;
        }

        .stat-detail-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }

        /* ==================== TABELAS ==================== */
        .data-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f8f9fa;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-title h2 {
            margin: 0;
            font-size: 24px;
            color: #2c3e50;
        }

        .section-title .count-badge {
            background: #e9ecef;
            color: #495057;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .section-title.danger h2 {
            color: #dc3545;
        }

        .section-title.warning h2 {
            color: #f39c12;
        }

        .section-subtitle {
            color: #6c757d;
            font-size: 14px;
            margin: 8px 0 0 0;
        }

        .btn-exportar {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-exportar:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        /* Tabela */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .data-table th {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 16px;
            text-align: left;
            font-weight: 700;
            color: #2c3e50;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .data-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f2f6;
            vertical-align: middle;
        }

        .data-table tbody tr {
            transition: all 0.2s ease;
        }

        .data-table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .dfd-number {
            font-weight: 700;
            color: #2c3e50;
            font-size: 15px;
        }

        .titulo-cell {
            max-width: 300px;
            color: #495057;
            line-height: 1.5;
        }

        .area-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .data-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .data-label {
            font-size: 11px;
            color: #6c757d;
            text-transform: uppercase;
        }

        .dias-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .dias-badge.danger {
            background: #fee;
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        .dias-badge.warning {
            background: #fff8e1;
            color: #f39c12;
            border: 1px solid #f39c12;
        }

        .valor-cell {
            font-weight: 600;
            color: #28a745;
            font-size: 15px;
        }

        .situacao-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            background: #f8d7da;
            color: #721c24;
        }

        .prioridade-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .prioridade-badge.alta {
            background: #f8d7da;
            color: #721c24;
        }

        .prioridade-badge.media {
            background: #fff3cd;
            color: #856404;
        }

        .prioridade-badge.baixa {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Mensagem vazia */
        .empty-message {
            text-align: center;
            padding: 80px 40px;
            color: #6c757d;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-title {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 10px 0;
            color: #495057;
        }

        .empty-text {
            font-size: 16px;
            margin: 0;
        }

        /* ==================== RESPONSIVO ==================== */
        @media (max-width: 768px) {
            .page-container {
                padding: 20px;
            }

            .page-header {
                padding: 30px 25px;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-left h1 {
                font-size: 28px;
            }

            .header-left p {
                font-size: 16px;
            }

            .header-actions {
                width: 100%;
            }

            .btn-voltar {
                width: 100%;
                justify-content: center;
            }

            .filtros-form {
                flex-direction: column;
            }

            .filtro-group {
                width: 100%;
                min-width: auto;
            }

            .btn-filtrar,
            .btn-limpar {
                width: 100%;
                justify-content: center;
            }

            .stats-cards {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .stat-card {
                padding: 25px;
            }

            .stat-value {
                font-size: 36px;
            }

            .section-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }

            .btn-exportar {
                width: 100%;
                justify-content: center;
            }

            .data-table {
                font-size: 13px;
            }

            .data-table th,
            .data-table td {
                padding: 12px 8px;
            }

            .titulo-cell {
                max-width: 200px;
            }

            .hide-mobile {
                display: none;
            }
        }

        /* ==================== ANIMAÇÕES ==================== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .page-container > * {
            animation: fadeIn 0.5s ease-out;
        }

        .stat-card {
            animation: fadeIn 0.5s ease-out;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }

        /* ==================== LOADING ==================== */
        .loading {
            display: flex;
            justify-content: center;
            padding: 40px;
            color: #6c757d;
        }

        .loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* ==================== TABS ==================== */
/* ==================== TABS ==================== */
.tabs-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.tabs-header {
    display: flex;
    background: white;
    border-bottom: 1px solid #e9ecef;
    padding: 0 30px;
}

.tab-button {
    flex: 1;
    padding: 25px 20px;
    background: none;
    border: none;
    font-size: 16px;
    font-weight: 600;
    color: #6c757d;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border-bottom: 3px solid transparent;
}

.tab-button:hover {
    color: #495057;
}

.tab-button.active {
    color: #2c3e50;
    border-bottom-color: #dc3545;
}

.tab-button i {
    font-size: 20px;
}

.tab-badge {
    background: #e9ecef;
    color: #6c757d;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    min-width: 32px;
    text-align: center;
}

.tab-button.active .tab-badge {
    background: #dc3545;
    color: white;
}

/* Ícone colorido quando ativo */
.tab-button.active i {
    color: #dc3545;
}

.tab-content {
    display: none;
    animation: fadeIn 0.3s ease-out;
}

.tab-content.active {
    display: block;
}

/* Remover padding extra da data-section dentro dos tabs */
.tabs-container .data-section {
    border-radius: 0;
    box-shadow: none;
    margin: 0;
}
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Header da Página -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1><i data-lucide="alert-triangle"></i> Contratações Atrasadas</h1>
                    <p>Monitoramento de contratações com atrasos e pendências</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn-voltar">
                        <i data-lucide="arrow-left"></i> Voltar ao Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filtros-card">
            <div class="filtros-header">
                <h3><i data-lucide="filter"></i> Filtros</h3>
            </div>
            <form method="GET" class="filtros-form">
                <div class="filtro-group">
                    <label>Área Requisitante</label>
                    <select name="area" onchange="this.form.submit()">
                        <option value="">Todas as áreas</option>
                        <?php foreach ($areas_agrupadas as $area): ?>
                        <option value="<?php echo htmlspecialchars($area); ?>" 
                                <?php echo ($filtro_area === $area) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($area); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filtrar">
                    <i data-lucide="search"></i> Filtrar
                </button>
                <a href="?" class="btn-limpar">
                    <i data-lucide="x"></i> Limpar
                </a>
            </form>
        </div>

        <!-- Cards de Resumo -->
        <div class="stats-cards">
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i data-lucide="clock-alert"></i>
                </div>
                <h3 class="stat-value"><?php echo $total_vencidas; ?></h3>
                <p class="stat-label">Contratações Vencidas</p>
                <div class="stat-details">
                    <div class="stat-detail-item">
                        <div class="stat-detail-value"><?php echo abreviarValor($valor_total_vencidas); ?></div>
                        <div class="stat-detail-label">Valor Total</div>
                    </div>
                    <div class="stat-detail-item">
                        <div class="stat-detail-value"><?php echo $total_vencidas > 0 ? round($valor_total_vencidas / $total_vencidas / 1000000, 1) . 'M' : '0'; ?></div>
                        <div class="stat-detail-label">Média/DFD</div>
                    </div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i data-lucide="alert-circle"></i>
                </div>
                <h3 class="stat-value"><?php echo $total_nao_iniciadas; ?></h3>
                <p class="stat-label">Não Iniciadas</p>
                <div class="stat-details">
                    <div class="stat-detail-item">
                        <div class="stat-detail-value"><?php echo abreviarValor($valor_total_nao_iniciadas); ?></div>
                        <div class="stat-detail-label">Valor Total</div>
                    </div>
                    <div class="stat-detail-item">
                        <div class="stat-detail-value"><?php echo $total_nao_iniciadas > 0 ? round($valor_total_nao_iniciadas / $total_nao_iniciadas / 1000000, 1) . 'M' : '0'; ?></div>
                        <div class="stat-detail-label">Média/DFD</div>
                    </div>
                </div>
            </div>

            <div class="stat-card total">
                <div class="stat-icon">
                    <i data-lucide="trending-up"></i>
                </div>
                <h3 class="stat-value"><?php echo $total_vencidas + $total_nao_iniciadas; ?></h3>
                <p class="stat-label">Total de Atrasos</p>
                <div class="stat-details">
                    <div class="stat-detail-item">
                        <div class="stat-detail-value"><?php echo abreviarValor($valor_total_vencidas + $valor_total_nao_iniciadas); ?></div>
                        <div class="stat-detail-label">Valor Total</div>
                    </div>
                    <div class="stat-detail-item">
                        <div class="stat-detail-value"><?php echo $filtro_area ? '1' : count($areas_agrupadas); ?></div>
                        <div class="stat-detail-label">Áreas</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Container -->
<div class="tabs-container">
    <!-- Tabs Header -->
    <div class="tabs-header">
        <button class="tab-button active" onclick="switchTab('vencidas')" id="tab-vencidas">
            <i data-lucide="clock-alert"></i>
            <span>Vencidas</span>
            <span class="tab-badge"><?php echo $total_vencidas; ?></span>
        </button>
        <button class="tab-button" onclick="switchTab('nao-iniciadas')" id="tab-nao-iniciadas">
            <i data-lucide="alert-circle"></i>
            <span>Não Iniciadas</span>
            <span class="tab-badge"><?php echo $total_nao_iniciadas; ?></span>
        </button>
    </div>

    <!-- Tab Content: Vencidas -->
    <div class="tab-content active" id="content-vencidas">
        <div class="data-section" style="box-shadow: none; margin: 0;">
            <div class="section-header">
                <div>
                    <div class="section-title danger">
                        <h2><i data-lucide="clock-alert"></i> Contratações Vencidas</h2>
                    </div>
                    <p class="section-subtitle">Contratações que ultrapassaram a data de conclusão e ainda não foram iniciadas</p>
                </div>
                <button onclick="exportarAtrasadas('vencidas')" class="btn-exportar">
                    <i data-lucide="download"></i> Exportar
                </button>
            </div>

            <?php if (!empty($contratacoes_vencidas)): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nº DFD</th>
                            <th>Título</th>
                            <th>Área</th>
                            <th>Datas</th>
                            <th>Atraso</th>
                            <th>Valor (R$)</th>
                            <th class="hide-mobile">Situação</th>
                            <th>Prioridade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contratacoes_vencidas as $contratacao): ?>
                        <tr>
                            <td><span class="dfd-number"><?php echo htmlspecialchars($contratacao['numero_dfd']); ?></span></td>
                            <td class="titulo-cell"><?php echo htmlspecialchars($contratacao['titulo_contratacao']); ?></td>
                            <td><span class="area-badge"><?php echo htmlspecialchars(agruparArea($contratacao['area_requisitante'])); ?></span></td>
                            <td>
                                <div class="data-info">
                                    <span class="data-label">Conclusão</span>
                                    <strong><?php echo formatarData($contratacao['data_conclusao_processo']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <span class="dias-badge danger">
                                    <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                    <?php echo $contratacao['dias_atraso']; ?> dias
                                </span>
                            </td>
                            <td><span class="valor-cell"><?php echo formatarMoeda($contratacao['valor_total_contratacao']); ?></span></td>
                            <td class="hide-mobile">
                                <span class="situacao-badge">
                                    <?php echo empty($contratacao['situacao_execucao']) ? 'Não iniciado' : htmlspecialchars($contratacao['situacao_execucao']); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $prioridade_class = strtolower($contratacao['prioridade']) == 'alta' ? 'alta' : 
                                                   (strtolower($contratacao['prioridade']) == 'media' ? 'media' : 'baixa');
                                ?>
                                <span class="prioridade-badge <?php echo $prioridade_class; ?>">
                                    <?php echo htmlspecialchars($contratacao['prioridade']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-message">
                <div class="empty-icon"><i data-lucide="check-circle"></i></div>
                <h3 class="empty-title">Nenhuma contratação vencida!</h3>
                <p class="empty-text">Todas as contratações estão dentro do prazo ou já foram iniciadas.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab Content: Não Iniciadas -->
    <div class="tab-content" id="content-nao-iniciadas">
        <div class="data-section" style="box-shadow: none; margin: 0;">
            <div class="section-header">
                <div>
                    <div class="section-title warning">
                        <h2><i data-lucide="alert-circle"></i> Contratações Não Iniciadas</h2>
                    </div>
                    <p class="section-subtitle">Contratações que já deveriam ter iniciado mas ainda não começaram</p>
                </div>
                <button onclick="exportarAtrasadas('nao-iniciadas')" class="btn-exportar">
                    <i data-lucide="download"></i> Exportar
                </button>
            </div>

            <?php if (!empty($contratacoes_nao_iniciadas)): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nº DFD</th>
                            <th>Título</th>
                            <th>Área</th>
                            <th>Datas</th>
                            <th>Atraso</th>
                            <th>Valor (R$)</th>
                            <th class="hide-mobile">Situação</th>
                            <th>Prioridade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contratacoes_nao_iniciadas as $contratacao): ?>
                        <tr>
                            <td><span class="dfd-number"><?php echo htmlspecialchars($contratacao['numero_dfd']); ?></span></td>
                            <td class="titulo-cell"><?php echo htmlspecialchars($contratacao['titulo_contratacao']); ?></td>
                            <td><span class="area-badge"><?php echo htmlspecialchars(agruparArea($contratacao['area_requisitante'])); ?></span></td>
                            <td>
                                <div class="data-info">
                                    <span class="data-label">Início</span>
                                    <strong><?php echo formatarData($contratacao['data_inicio_processo']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <span class="dias-badge warning">
                                    <i data-lucide="alert-triangle" style="width: 14px; height: 14px;"></i>
                                    <?php echo $contratacao['dias_atraso_inicio']; ?> dias
                                </span>
                            </td>
                            <td><span class="valor-cell"><?php echo formatarMoeda($contratacao['valor_total_contratacao']); ?></span></td>
                            <td class="hide-mobile">
                                <span class="situacao-badge">
                                    <?php echo empty($contratacao['situacao_execucao']) ? 'Não iniciado' : htmlspecialchars($contratacao['situacao_execucao']); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $prioridade_class = strtolower($contratacao['prioridade']) == 'alta' ? 'alta' : 
                                                   (strtolower($contratacao['prioridade']) == 'media' ? 'media' : 'baixa');
                                ?>
                                <span class="prioridade-badge <?php echo $prioridade_class; ?>">
                                    <?php echo htmlspecialchars($contratacao['prioridade']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-message">
                <div class="empty-icon"><i data-lucide="check-circle"></i></div>
                <h3 class="empty-title">Todas as contratações foram iniciadas!</h3>
                <p class="empty-text">Não há contratações pendentes de início.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

    <script>
        // Função para exportar dados
        function exportarAtrasadas(tipo) {
            // Pegar filtro de área
            var area = document.querySelector('select[name="area"]').value;
            
            // Construir URL de exportação
            var url = 'exportar_atrasadas_novo.php?tipo=' + tipo;
            if (area) {
                url += '&area=' + encodeURIComponent(area);
            }
            
            // Abrir link de download
            window.open(url, '_blank');
        }

        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Adicionar animação suave ao scroll
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.data-section').forEach(section => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                section.style.transition = 'all 0.5s ease-out';
                observer.observe(section);
            });

            // Tooltip para células truncadas
            document.querySelectorAll('.titulo-cell').forEach(cell => {
                if (cell.scrollWidth > cell.clientWidth) {
                    cell.title = cell.textContent;
                }
            });

            // Contador animado para os valores
            function animateValue(obj, start, end, duration) {
                let startTimestamp = null;
                const step = (timestamp) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    obj.innerHTML = Math.floor(progress * (end - start) + start);
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                };
                window.requestAnimationFrame(step);
            }

            // Animar os números dos cards
            document.querySelectorAll('.stat-value').forEach(element => {
                const value = parseInt(element.textContent);
                animateValue(element, 0, value, 1000);
            });
        });

        // Adicionar feedback visual ao clicar nos botões
        document.querySelectorAll('button').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Função para trocar de aba
function switchTab(tabName) {
    // Remover active de todos os botões e conteúdos
    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Adicionar active ao botão e conteúdo selecionados
    document.getElementById('tab-' + tabName).classList.add('active');
    document.getElementById('content-' + tabName).classList.add('active');
    
    // Recriar ícones Lucide no novo conteúdo
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Salvar aba ativa no localStorage
    localStorage.setItem('contratacoes-aba-ativa', tabName);
}

// Restaurar última aba visualizada
document.addEventListener('DOMContentLoaded', function() {
    const ultimaAba = localStorage.getItem('contratacoes-aba-ativa');
    if (ultimaAba && ultimaAba !== 'vencidas') {
        switchTab(ultimaAba);
    }
});
    </script>

    <style>
        /* Efeito ripple para botões */
        button {
            position: relative;
            overflow: hidden;
        }

        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            transform: scale(0);
            animation: ripple-animation 0.6s ease-out;
            pointer-events: none;
        }

        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        /* Melhorias de acessibilidade */
        *:focus {
            outline: 2px solid #dc3545;
            outline-offset: 2px;
        }

        button:focus,
        a:focus {
            outline: 2px solid #dc3545;
            outline-offset: 2px;
        }

        /* Print styles */
        @media print {
            .page-header,
            .filtros-card,
            .btn-exportar,
            .btn-voltar {
                display: none !important;
            }

            .page-container {
                padding: 0;
            }

            .data-section {
                page-break-inside: avoid;
                box-shadow: none;
                border: 1px solid #dee2e6;
            }

            .data-table {
                font-size: 12px;
            }

            .stats-cards {
                display: none;
            }
        }
    </style>
</body>
</html>