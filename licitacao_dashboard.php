<?php
require_once 'config.php';
require_once 'functions.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

verificarLogin();

$pdo = conectarDB();

// Buscar estatísticas para os cards e gráficos
$stats_sql = "SELECT
    COUNT(*) as total_licitacoes,
    COUNT(CASE WHEN situacao = 'EM_ANDAMENTO' THEN 1 END) as em_andamento,
    COUNT(CASE WHEN situacao = 'HOMOLOGADO' THEN 1 END) as homologadas,
    COUNT(CASE WHEN situacao = 'FRACASSADO' THEN 1 END) as fracassadas,
    COUNT(CASE WHEN situacao = 'REVOGADO' THEN 1 END) as revogadas,
    SUM(CASE WHEN situacao = 'HOMOLOGADO' THEN valor_estimado ELSE 0 END) as valor_homologado
    FROM licitacoes";

$stats = $pdo->query($stats_sql)->fetch();

// Dados para gráficos
$dados_modalidade = $pdo->query("
    SELECT modalidade, COUNT(*) as quantidade
    FROM licitacoes
    GROUP BY modalidade
")->fetchAll();

$dados_pregoeiro = $pdo->query("
    SELECT
        CASE
            WHEN l.pregoeiro IS NULL OR l.pregoeiro = '' THEN 'Não Definido'
            ELSE l.pregoeiro
        END AS pregoeiro,
        COUNT(*) AS quantidade
    FROM licitacoes l
    GROUP BY l.pregoeiro
    ORDER BY quantidade DESC
    LIMIT 5
")->fetchAll();

$dados_mensal = $pdo->query("
    SELECT
        DATE_FORMAT(
            COALESCE(data_abertura, criado_em),
            '%Y-%m'
        ) as mes,
        COUNT(*) as quantidade,
        SUM(CASE WHEN data_abertura IS NOT NULL THEN 1 ELSE 0 END) as com_data_abertura,
        SUM(CASE WHEN data_abertura IS NULL THEN 1 ELSE 0 END) as sem_data_abertura
    FROM licitacoes
    WHERE (data_abertura IS NOT NULL AND data_abertura >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH))
    OR (data_abertura IS NULL AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH))
    GROUP BY DATE_FORMAT(
        COALESCE(data_abertura, criado_em),
        '%Y-%m'
    )
    ORDER BY mes
")->fetchAll();

// Configuração da paginação
$licitacoes_por_pagina = isset($_GET['por_pagina']) ? max(10, min(100, intval($_GET['por_pagina']))) : 10;
$pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_atual - 1) * $licitacoes_por_pagina;

// Filtros opcionais
$filtro_situacao = $_GET['situacao_filtro'] ?? '';
$filtro_busca = $_GET['busca'] ?? '';

// Construir WHERE clause para filtros
$where_conditions = ['1=1'];
$params = [];

if (!empty($filtro_situacao)) {
    $where_conditions[] = "l.situacao = ?";
    $params[] = $filtro_situacao;
}

if (!empty($filtro_busca)) {
    $where_conditions[] = "(l.nup LIKE ? OR l.objeto LIKE ? OR l.pregoeiro LIKE ? OR COALESCE(l.numero_contratacao, p.numero_contratacao) LIKE ?)";
    $busca_param = "%$filtro_busca%";
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Contar total de licitações (para paginação)
$sql_count = "SELECT COUNT(*) as total 
              FROM licitacoes l 
              LEFT JOIN usuarios u ON l.usuario_id = u.id
              LEFT JOIN pca_dados p ON l.pca_dados_id = p.id
              WHERE $where_clause";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_licitacoes = $stmt_count->fetch()['total'];
$total_paginas = ceil($total_licitacoes / $licitacoes_por_pagina);

// Buscar licitações da página atual
$sql = "SELECT 
            l.*, 
            u.nome as usuario_criador_nome,
            COALESCE(l.numero_contratacao, p.numero_contratacao) as numero_contratacao_final
        FROM licitacoes l 
        LEFT JOIN usuarios u ON l.usuario_id = u.id
        LEFT JOIN pca_dados p ON l.pca_dados_id = p.id
        WHERE $where_clause
        ORDER BY l.criado_em DESC
        LIMIT $licitacoes_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$licitacoes_recentes = $stmt->fetchAll();


// Calcular tempo de tramitação por unidade para cada licitação
foreach ($licitacoes_recentes as &$lic) {
    $tempos = [];
    if (isset($lic['processo_id'])) {
        $tempos = calcularTempoUnidade($lic['processo_id']);
    }
    $lic['tempo_unidade_detalhes'] = $tempos;
    $lic['tempo_unidade_total'] = array_sum($tempos);
}
unset($lic);


// Buscar contratações disponíveis do PCA para o dropdown - dos anos atuais (2025-2026)
$contratacoes_pca = $pdo->query("
    SELECT DISTINCT
        p.numero_contratacao,
        p.numero_dfd,
        p.titulo_contratacao,
        p.area_requisitante,
        p.valor_total_contratacao,
        pi.ano_pca
    FROM pca_dados p
    INNER JOIN pca_importacoes pi ON p.importacao_id = pi.id
    WHERE p.numero_contratacao IS NOT NULL
    AND p.numero_contratacao != ''
    AND TRIM(p.numero_contratacao) != ''
    AND pi.ano_pca IN (2025, 2026)
    ORDER BY p.numero_contratacao DESC
    LIMIT 500
")->fetchAll(PDO::FETCH_ASSOC);

// Debug básico
echo "<script>console.log('Sistema carregado - Contratações disponíveis:', " . count($contratacoes_pca) . ");</script>";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Licitações - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/licitacao-dashboard.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/licitacao-dashboard.js"></script>

    <style>
    .search-input {
        width: 100% !important;
        padding: 12px 16px !important;
        border: 2px solid #e5e7eb !important;
        border-radius: 8px !important;
        font-size: 14px !important;
        font-family: inherit !important;
        transition: all 0.2s ease !important;
        background: white !important;
        color: #374151 !important;
        outline: none !important;
    }

    .search-input:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        transform: translateY(-1px) !important;
    }

    .search-input:hover {
        border-color: #d1d5db !important;
    }

    .search-suggestions {
        position: absolute !important;
        top: 100% !important;
        left: 0 !important;
        right: 0 !important;
        background: white !important;
        border: 2px solid #e5e7eb !important;
        border-top: none !important;
        border-radius: 0 0 8px 8px !important;
        max-height: 280px !important;
        overflow-y: auto !important;
        z-index: 1000 !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
        margin-top: -1px !important;
    }

    .suggestion-item {
        padding: 12px 16px !important;
        border-bottom: 1px solid #f3f4f6 !important;
        cursor: pointer !important;
        transition: background 0.15s ease !important;
        font-size: 14px !important;
    }

    .suggestion-item:hover {
        background: #f8fafc !important;
    }

    .suggestion-item:last-child {
        border-bottom: none !important;
    }

    .suggestion-numero {
        font-weight: 600 !important;
        color: #1f2937 !important;
        margin-bottom: 4px !important;
    }

    .suggestion-titulo {
        font-size: 12px !important;
        color: #6b7280 !important;
        line-height: 1.4 !important;
    }

    .no-results {
        padding: 16px !important;
        text-align: center !important;
        color: #9ca3af !important;
        font-style: italic !important;
        font-size: 14px !important;
    }
        /* Estilos para detalhes */
        .detalhes-licitacao {
            font-family: inherit;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .tab-link {
            padding: 6px 12px;
            border: 1px solid #dee2e6;
            background: #f8f9fa;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .tab-link.active {
            background: #007cba;
            color: #fff;
            border-color: #007cba;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
        .detail-section {
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .detail-section h4 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 8px;
        }
        
        .detail-section p {
            margin: 8px 0;
            color: #6c757d;
            line-height: 1.5;
        }
        
        .detail-section strong {
            color: #495057;
            font-weight: 600;
        }
        
        /* Estilos para paginação */
        .pagination {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #495057;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .page-link:hover {
            background: #e9ecef;
            border-color: #adb5bd;
            color: #495057;
            text-decoration: none;
        }
        
        .page-link.active {
            background: #007cba;
            border-color: #007cba;
            color: white;
        }
        
        .page-link.active:hover {
            background: #006ba6;
            border-color: #006ba6;
        }

        /* Tabela de tempo por unidade no modal */
        .tempo-unidade-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .tempo-unidade-table th,
        .tempo-unidade-table td {
            border: 1px solid #dee2e6;
            padding: 6px 8px;
            text-align: left;
        }
        .tempo-unidade-table th {
            background: #f1f3f5;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i data-lucide="gavel"></i> Licitações</h2>
            </div>

            <nav class="sidebar-nav">
    <div class="nav-section">
        <div class="nav-section-title">Visão Geral</div>
        <button class="nav-item active" onclick="showSection('dashboard')">
            <i data-lucide="bar-chart-3"></i> Dashboard
        </button>
    </div>

    <div class="nav-section">
        <div class="nav-section-title">Gerenciar</div>
        <button class="nav-item" onclick="showSection('lista-licitacoes')">
            <i data-lucide="list"></i> Lista de Licitações
        </button>
        <?php if (isVisitante()): ?>
        <div style="margin: 10px 15px; padding: 8px; background: #fff3cd; border-radius: 6px; border-left: 3px solid #f39c12;">
            <small style="color: #856404; font-size: 11px; font-weight: 600;">
                <i data-lucide="eye" style="width: 12px; height: 12px;"></i> MODO VISITANTE<br>
                Somente visualização e exportação
            </small>
        </div>
        <?php endif; ?>
    </div>

    <?php if (temPermissao('licitacao_relatorios')): ?>
    <div class="nav-section">
        <div class="nav-section-title">Relatórios</div>
        <button class="nav-item" onclick="showSection('relatorios')">
            <i data-lucide="file-text"></i> Relatórios
        </button>
    </div>
    <?php endif; ?>

    <div class="nav-section">
        <div class="nav-section-title">Navegação</div>
        <a href="dashboard.php" class="nav-item">
            <i data-lucide="calendar"></i> Dashboard Planejamento
        </a>
    </div>

    <div class="nav-section">
        <div class="nav-section-title">Sistema</div>
        <a href="selecao_modulos.php" class="nav-item">
            <i data-lucide="arrow-left"></i> Voltar ao Menu
        </a>
    </div>
</nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['usuario_nome'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></h4>
                        <p><?php echo htmlspecialchars($_SESSION['usuario_email']); ?></p>
                        <small style="color: #3498db; font-weight: 600;">
                            <?php echo getNomeNivel($_SESSION['usuario_nivel'] ?? 3); ?> - <?php echo htmlspecialchars($_SESSION['usuario_departamento'] ?? ''); ?>
                        </small>
                        <?php if (isVisitante()): ?>
                        <small style="color: #f39c12; font-weight: 600; display: block; margin-top: 4px;">
                            <i data-lucide="eye" style="width: 12px; height: 12px;"></i> Modo Somente Leitura
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i data-lucide="log-out"></i> Sair
                </button>
            </div>
        </div>

        <main class="main-content">
            <?php echo getMensagem(); ?>

            <div id="dashboard" class="content-section active">
                <div class="dashboard-header">
                    <h1><i data-lucide="bar-chart-3"></i> Dashboard de Licitações</h1>
                    <p>Visão geral do processo licitatório e indicadores de desempenho</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-number"><?php echo number_format($stats['total_licitacoes'] ?? 0); ?></div>
                        <div class="stat-label">Total de Licitações</div>
                    </div>
                    <div class="stat-card andamento">
                        <div class="stat-number"><?php echo $stats['em_andamento'] ?? 0; ?></div>
                        <div class="stat-label">Em Andamento</div>
                    </div>
                    <div class="stat-card homologadas">
                        <div class="stat-number"><?php echo $stats['homologadas'] ?? 0; ?></div>
                        <div class="stat-label">Homologadas</div>
                    </div>
                    <div class="stat-card fracassadas">
                        <div class="stat-number"><?php echo $stats['fracassadas'] ?? 0; ?></div>
                        <div class="stat-label">Fracassadas</div>
                    </div>
                    <div class="stat-card valor">
                        <div class="stat-number"><?php echo abreviarValor($stats['valor_homologado'] ?? 0); ?></div>
                        <div class="stat-label">Valor Homologado</div>
                    </div>
                </div>

                <div class="charts-grid">
    <div class="chart-card">
        <h3 class="chart-title"><i data-lucide="pie-chart"></i> Licitações por Modalidade</h3>
        <div class="chart-container">
            <canvas id="chartModalidade"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3 class="chart-title"><i data-lucide="users"></i> Licitações por Pregoeiro</h3>
        <div class="chart-container">
            <canvas id="chartPregoeiro"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3 class="chart-title"><i data-lucide="trending-up"></i> Evolução Mensal</h3>
        <div class="chart-container">
            <canvas id="chartMensal"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3 class="chart-title"><i data-lucide="activity"></i> Status das Licitações</h3>
        <div class="chart-container">
            <canvas id="chartStatus"></canvas>
        </div>
    </div>
</div>
            </div>

            <div id="lista-licitacoes" class="content-section">
    <div class="dashboard-header">
        <h1><i data-lucide="list"></i> Lista de Licitações</h1>
        <p>Visualize e gerencie todas as licitações cadastradas</p>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h3 class="table-title">Todas as Licitações</h3>
            <div class="table-filters">
                <button onclick="abrirModalImportarJson()" class="btn-primary" style="margin-left: 10px;">
                    <i data-lucide="upload"></i> Importar JSON
            </button>
                <?php if (temPermissao('licitacao_criar')): ?>
                <button onclick="abrirModalCriarLicitacao()" class="btn-primary" style="margin-right: 10px;">
                    <i data-lucide="plus-circle"></i> Nova Licitação
                </button>
                <?php endif; ?>
                <?php if (temPermissao('licitacao_exportar')): ?>
                <button onclick="exportarLicitacoes()" class="btn-primary">
                    <i data-lucide="download"></i> Exportar
                </button>
                <?php endif; ?>
                
            </div>
            
        </div>

        <!-- Filtros e Busca -->
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Buscar</label>
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($filtro_busca); ?>" 
                           placeholder="NUP, objeto, pregoeiro ou nº contratação..." 
                           style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Situação</label>
                    <select name="situacao_filtro" style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px;">
                        <option value="">Todas as Situações</option>
                        <option value="EM_ANDAMENTO" <?php echo $filtro_situacao === 'EM_ANDAMENTO' ? 'selected' : ''; ?>>Em Andamento</option>
                        <option value="HOMOLOGADO" <?php echo $filtro_situacao === 'HOMOLOGADO' ? 'selected' : ''; ?>>Homologadas</option>
                        <option value="FRACASSADO" <?php echo $filtro_situacao === 'FRACASSADO' ? 'selected' : ''; ?>>Fracassadas</option>
                        <option value="REVOGADO" <?php echo $filtro_situacao === 'REVOGADO' ? 'selected' : ''; ?>>Revogadas</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Por página</label>
                    <select name="por_pagina" onchange="this.form.submit()" style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px;">
                        <option value="10" <?php echo $licitacoes_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $licitacoes_por_pagina == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $licitacoes_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $licitacoes_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary" style="padding: 8px 16px;">
                        <i data-lucide="search"></i> Filtrar
                    </button>
                    <a href="licitacao_dashboard.php" class="btn-secondary" style="padding: 8px 16px; text-decoration: none;">
                        <i data-lucide="x"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <?php if (empty($licitacoes_recentes)): ?>
            <div style="text-align: center; padding: 60px; color: #7f8c8d;">
                <i data-lucide="inbox" style="width: 64px; height: 64px; margin-bottom: 20px;"></i>
                <h3 style="margin: 0 0 10px 0;">Nenhuma licitação encontrada</h3>
                <p style="margin: 0;">Comece criando sua primeira licitação.</p>
                <?php if (temPermissao('licitacao_criar')): ?>
                <button onclick="abrirModalCriarLicitacao()" class="btn-primary" style="margin-top: 20px;">
                    <i data-lucide="plus-circle"></i> Criar Primeira Licitação
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table>
<thead>
<tr>
<th>NUP</th>
<th>Número da contratação</th>
<th>Modalidade</th>
<th>Objeto</th>
<th>Valor Estimado</th>
<th>Situação</th>
<th>Pregoeiro</th>
<th>Data Abertura</th>
<th>Tempo na Unidade (dias)</th>
<th>Ações</th>
</tr>
</thead>
<tbody>
<?php foreach ($licitacoes_recentes as $licitacao): ?>
<tr>
<tr data-tempos='<?php echo json_encode($licitacao['tempo_unidade_detalhes']); ?>'>
<td>
<a href="#" onclick="verDetalhes(<?php echo $licitacao['id']; ?>); return false;" title="Clique para ver os detalhes">
<a href="#" onclick="verDetalhes(<?php echo $licitacao['id']; ?>, this); return false;" title="Clique para ver os detalhes">
<strong><?php echo htmlspecialchars($licitacao['nup']); ?></strong>
</a>
</td>
<td><?php echo htmlspecialchars($licitacao['numero_contratacao_final'] ?? $licitacao['numero_contratacao'] ?? 'N/A'); ?></td>
 
                        <td><span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($licitacao['modalidade']); ?></span></td>
<td title="<?php echo htmlspecialchars($licitacao['objeto'] ?? ''); ?>">
<?php 
                            $objeto = $licitacao['objeto'] ?? '';
                            echo htmlspecialchars(strlen($objeto) > 80 ? substr($objeto, 0, 80) . '...' : $objeto); 
                            ?>
</td>
<td style="font-weight: 600; color: #27ae60;"><?php echo formatarMoeda($licitacao['valor_estimado'] ?? 0); ?></td>
<td>
<span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $licitacao['situacao'])); ?>">
<?php echo str_replace('_', ' ', $licitacao['situacao']); ?>
</span>
</td>
<td><?php echo htmlspecialchars($licitacao['pregoeiro'] ?: 'Não Definido'); ?></td>
<td><?php echo $licitacao['data_abertura'] ? formatarData($licitacao['data_abertura']) : '-'; ?></td>
<td><?php echo $licitacao['tempo_unidade_total'] ? intval($licitacao['tempo_unidade_total']) : '-'; ?></td>
<td>
<div style="display: flex; gap: 5px;">
<?php if (temPermissao('licitacao_editar')): ?>
<button onclick="editarLicitacao(<?php echo $licitacao['id']; ?>)" title="Editar" style="background: #f39c12; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="edit" style="width: 14px; height: 14px;"></i>
</button>
<?php else: ?>
<span style="color: #7f8c8d; font-size: 12px; font-style: italic;">Somente leitura</span>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

            <!-- Informações de Paginação -->
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div style="color: #7f8c8d; font-size: 14px;">
                    <?php 
                    $inicio = ($pagina_atual - 1) * $licitacoes_por_pagina + 1;
                    $fim = min($pagina_atual * $licitacoes_por_pagina, $total_licitacoes);
                    ?>
                    Mostrando <?php echo $inicio; ?> a <?php echo $fim; ?> de <?php echo $total_licitacoes; ?> licitações<br>
                    Valor total estimado (página atual): <?php echo formatarMoeda(array_sum(array_column($licitacoes_recentes, 'valor_estimado'))); ?>
                </div>
                
                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php
                    // Construir URL base preservando filtros
                    $url_params = [];
                    if (!empty($filtro_busca)) $url_params['busca'] = $filtro_busca;
                    if (!empty($filtro_situacao)) $url_params['situacao_filtro'] = $filtro_situacao;
                    if ($licitacoes_por_pagina != 10) $url_params['por_pagina'] = $licitacoes_por_pagina;
                    $url_base = 'licitacao_dashboard.php?' . http_build_query($url_params);
                    $url_base .= empty($url_params) ? '?' : '&';
                    ?>
                    
                    <!-- Primeira página -->
                    <?php if ($pagina_atual > 1): ?>
                        <a href="<?php echo $url_base; ?>pagina=1" class="page-link">
                            <i data-lucide="chevrons-left"></i>
                        </a>
                        <a href="<?php echo $url_base; ?>pagina=<?php echo $pagina_atual - 1; ?>" class="page-link">
                            <i data-lucide="chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Páginas numeradas -->
                    <?php
                    $inicio_pag = max(1, $pagina_atual - 2);
                    $fim_pag = min($total_paginas, $pagina_atual + 2);
                    
                    for ($i = $inicio_pag; $i <= $fim_pag; $i++):
                    ?>
                        <a href="<?php echo $url_base; ?>pagina=<?php echo $i; ?>" 
                           class="page-link <?php echo $i == $pagina_atual ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <!-- Última página -->
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="<?php echo $url_base; ?>pagina=<?php echo $pagina_atual + 1; ?>" class="page-link">
                            <i data-lucide="chevron-right"></i>
                        </a>
                        <a href="<?php echo $url_base; ?>pagina=<?php echo $total_paginas; ?>" class="page-link">
                            <i data-lucide="chevrons-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="modalCriarLicitacao" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="plus-circle"></i> Criar Nova Licitação
            </h3>
            <span class="close" onclick="fecharModal('modalCriarLicitacao')">&times;</span>
        </div>
        <div class="modal-body">
            <form action="process.php" method="POST">
                <input type="hidden" name="acao" value="criar_licitacao">
                <?php echo getCSRFInput(); ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label>NUP *</label>
                        <input type="text" name="nup" id="nup_criar" required placeholder="xxxxx.xxxxxx/xxxx-xx" maxlength="20">
                    </div>

                    <div class="form-group">
                        <label>Data Entrada DIPLI</label>
                        <input type="date" name="data_entrada_dipli">
                    </div>

                    <div class="form-group">
                        <label>Responsável Instrução</label>
                        <input type="text" name="resp_instrucao">
                    </div>

                    <div class="form-group">
                        <label>Área Demandante</label>
                        <input type="text" name="area_demandante" id="area_demandante_criar">
                    </div>

                    <div class="form-group">
                        <label>Pregoeiro</label>
                        <input type="text" name="pregoeiro">
                    </div>

                    <div class="form-group">
                        <label>Modalidade *</label>
                        <select name="modalidade" required>
                            <option value="DISPENSA">DISPENSA</option>
                            <option value="PREGAO">PREGÃO</option>
                            <option value="RDC">RDC</option>
                            <option value="INEXIBILIDADE">INEXIBILIDADE</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tipo *</label>
                        <select name="tipo" required>
                            <option value="TRADICIONAL">TRADICIONAL</option>
                            <option value="COTACAO">COTAÇÃO</option>
                            <option value="SRP">SRP</option>
                        </select>
                    </div>

                    <div class="form-group">
    <label>Número da Contratação *</label>
    <div class="search-container" style="position: relative;">
        <input
            type="text"
            name="numero_contratacao"
            id="input_contratacao"
            required
            placeholder="Digite o número da contratação..."
            autocomplete="off"
            class="search-input"
            oninput="pesquisarContratacaoInline(this.value)"
            onfocus="mostrarSugestoesInline()"
            onblur="ocultarSugestoesInline()"
        >
        <div id="sugestoes_contratacao" class="search-suggestions" style="display: none;">
            </div>
    </div>

<input type="hidden" id="numero_dfd_selecionado" name="numero_dfd">
<input type="hidden" id="titulo_contratacao_selecionado" name="titulo_contratacao">

    <small style="color: #6b7280; font-size: 12px;">
        Digite o número da contratação ou parte do título para pesquisar
    </small>
</div>

                    <div class="form-group">
                        <label>Ano</label>
                        <input type="number" name="ano" value="<?php echo date('Y'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Valor Estimado (R$)</label>
                        <input type="text" name="valor_estimado" id="valor_estimado_criar" placeholder="0,00">
                    </div>

                    <div class="form-group">
                        <label>Data Abertura</label>
                        <input type="date" name="data_abertura">
                    </div>

                    <div class="form-group">
                        <label>Data Homologação</label>
                        <input type="date" name="data_homologacao" id="data_homologacao_criar">
                    </div>

                    <div class="form-group">
                        <label>Valor Homologado (R$)</label>
                        <input type="text" name="valor_homologado" id="valor_homologado_criar" placeholder="0,00">
                    </div>

                    <div class="form-group">
                        <label>Economia (R$)</label>
                        <input type="text" name="economia" id="economia_criar" placeholder="0,00" readonly style="background: #f8f9fa;">
                    </div>

                    <div class="form-group">
                        <label>Link</label>
                        <input type="url" name="link" placeholder="https://...">
                    </div>

                    <div class="form-group">
                        <label>Situação *</label>
                        <select name="situacao" required>
                            <option value="EM_ANDAMENTO">EM ANDAMENTO</option>
                            <option value="REVOGADO">REVOGADO</option>
                            <option value="FRACASSADO">FRACASSADO</option>
                            <option value="HOMOLOGADO">HOMOLOGADO</option>
                        </select>
                    </div>

                    <div class="form-group form-full">
                        <label>Objeto *</label>
                        <textarea name="objeto" id="objeto_textarea" required rows="3" placeholder="Descreva o objeto da licitação..."></textarea>
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalCriarLicitacao')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i data-lucide="refresh-cw"></i> Limpar Formulário
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="check"></i> Criar Licitação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

            <div id="relatorios" class="content-section">
    <div class="dashboard-header">
        <h1><i data-lucide="file-text"></i> Relatórios</h1>
        <p>Relatórios detalhados sobre o processo licitatório</p>
    </div>

    <div class="stats-grid">
        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorio('modalidade')">
            <h3 class="chart-title"><i data-lucide="pie-chart"></i> Relatório por Modalidade</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Análise detalhada das licitações por modalidade</p>
            <div style="text-align: center;">
                <i data-lucide="bar-chart-3" style="width: 64px; height: 64px; color: #e74c3c; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>

        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorio('pregoeiro')">
            <h3 class="chart-title"><i data-lucide="users"></i> Relatório por Pregoeiro</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Performance e distribuição por pregoeiro</p>
            <div style="text-align: center;">
                <i data-lucide="user-check" style="width: 64px; height: 64px; color: #3498db; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>

        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorio('prazos')">
            <h3 class="chart-title"><i data-lucide="clock"></i> Relatório de Prazos</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Análise de cumprimento de prazos</p>
            <div style="text-align: center;">
                <i data-lucide="calendar-check" style="width: 64px; height: 64px; color: #f39c12; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>

        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorio('financeiro')">
            <h3 class="chart-title"><i data-lucide="trending-up"></i> Relatório Financeiro</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Valores estimados vs homologados</p>
            <div style="text-align: center;">
                <i data-lucide="dollar-sign" style="width: 64px; height: 64px; color: #27ae60; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>
    </div>
</div>

<div id="modalRelatorio" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="file-text"></i> <span id="tituloRelatorio">Configurar Relatório</span>
            </h3>
            <span class="close" onclick="fecharModal('modalRelatorio')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formRelatorio">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="tipo_relatorio" name="tipo">

                <div class="form-group">
                    <label>Período</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Inicial</label>
                            <input type="date" name="data_inicial" id="rel_data_inicial">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Final</label>
                            <input type="date" name="data_final" id="rel_data_final" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group" id="filtroModalidade" style="display: none;">
                    <label>Modalidade</label>
                    <select name="modalidade" id="rel_modalidade">
                        <option value="">Todas</option>
                        <option value="DISPENSA">Dispensa</option>
                        <option value="PREGAO">Pregão</option>
                        <option value="RDC">RDC</option>
                        <option value="INEXIBILIDADE">Inexibilidade</option>
                    </select>
                </div>

                <div class="form-group" id="filtroPregoeiro" style="display: none;">
                    <label>Pregoeiro</label>
                    <select name="pregoeiro" id="rel_pregoeiro">
                        <option value="">Todos</option>
                        <?php
                        // Buscar pregoeiros únicos
                        $pregoeiros = $pdo->query("SELECT DISTINCT pregoeiro FROM licitacoes WHERE pregoeiro IS NOT NULL AND pregoeiro != '' ORDER BY pregoeiro")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($pregoeiros as $preg): ?>
                            <option value="<?php echo htmlspecialchars($preg); ?>"><?php echo htmlspecialchars($preg); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="filtroSituacao">
                    <label>Situação</label>
                    <select name="situacao" id="rel_situacao">
                        <option value="">Todas</option>
                        <option value="EM_ANDAMENTO">Em Andamento</option>
                        <option value="HOMOLOGADO">Homologado</option>
                        <option value="FRACASSADO">Fracassado</option>
                        <option value="REVOGADO">Revogado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Formato de Saída</label>
                    <select name="formato" id="rel_formato" required>
                        <option value="html">Visualizar (HTML)</option>
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="incluir_graficos" id="rel_graficos" checked>
                        Incluir gráficos no relatório
                    </label>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalRelatorio')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="file-text"></i> Gerar Relatório
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

        </div>
    </div>

    <div id="modalDetalhes" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="file-text"></i> Detalhes da Licitação
            </h3>
            <span class="close" onclick="fecharModal('modalDetalhes')">&times;</span>
        </div>
        <div class="modal-body" id="detalhesContent">
            </div>
    </div>
</div>

<div id="modalEdicao" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="edit"></i> Editar Licitação
            </h3>
            <span class="close" onclick="fecharModal('modalEdicao')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formEditarLicitacao">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="edit_id" name="id">
                <input type="hidden" name="acao" value="editar_licitacao">

                <div class="form-grid">
                    <div class="form-group">
                        <label>NUP *</label>
                        <input type="text" name="nup" id="edit_nup" required placeholder="xxxxx.xxxxxx/xxxx-xx" maxlength="20">
                    </div>

                    <div class="form-group">
                        <label>Data Entrada DIPLI</label>
                        <input type="date" name="data_entrada_dipli" id="edit_data_entrada_dipli">
                    </div>

                    <div class="form-group">
                        <label>Responsável Instrução</label>
                        <input type="text" name="resp_instrucao" id="edit_resp_instrucao">
                    </div>

                    <div class="form-group">
                        <label>Área Demandante</label>
                        <input type="text" name="area_demandante" id="edit_area_demandante">
                    </div>

                    <div class="form-group">
                        <label>Pregoeiro</label>
                        <input type="text" name="pregoeiro" id="edit_pregoeiro">
                    </div>

                    <div class="form-group">
                        <label>Modalidade *</label>
                        <select name="modalidade" id="edit_modalidade" required>
                            <option value="">Selecione</option>
                            <option value="DISPENSA">DISPENSA</option>
                            <option value="PREGAO">PREGÃO</option>
                            <option value="RDC">RDC</option>
                            <option value="INEXIBILIDADE">INEXIBILIDADE</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tipo *</label>
                        <select name="tipo" id="edit_tipo" required>
                            <option value="">Selecione</option>
                            <option value="TRADICIONAL">TRADICIONAL</option>
                            <option value="COTACAO">COTAÇÃO</option>
                            <option value="SRP">SRP</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Número da Contratação *</label>
                        <div class="search-container" style="position: relative;">
                            <input
                                type="text"
                                name="numero_contratacao"
                                id="edit_input_contratacao"
                                required
                                placeholder="Digite o número da contratação..."
                                autocomplete="off"
                                class="search-input"
                                oninput="pesquisarContratacaoInlineEdit(this.value)"
                                onfocus="mostrarSugestoesInlineEdit()"
                                onblur="ocultarSugestoesInlineEdit()"
                            >
                            <div id="edit_sugestoes_contratacao" class="search-suggestions" style="display: none;">
                                </div>
                        </div>

                        <input type="hidden" id="edit_numero_dfd_selecionado" name="numero_dfd">
                        <input type="hidden" id="edit_titulo_contratacao_selecionado" name="titulo_contratacao">

                        <small style="color: #6b7280; font-size: 12px;">
                            Digite o número da contratação ou parte do título para pesquisar
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Ano</label>
                        <input type="number" name="ano" id="edit_ano" value="<?php echo date('Y'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Valor Estimado (R$)</label>
                        <input type="text" name="valor_estimado" id="edit_valor_estimado" placeholder="0,00">
                    </div>

                    <div class="form-group">
                        <label>Data Abertura</label>
                        <input type="date" name="data_abertura" id="edit_data_abertura">
                    </div>

                    <div class="form-group">
                        <label>Data Homologação</label>
                        <input type="date" name="data_homologacao" id="edit_data_homologacao">
                    </div>

                    <div class="form-group">
                        <label>Valor Homologado (R$)</label>
                        <input type="text" name="valor_homologado" id="edit_valor_homologado" placeholder="0,00">
                    </div>

                    <div class="form-group">
                        <label>Economia (R$)</label>
                        <input type="text" name="economia" id="edit_economia" placeholder="0,00" readonly style="background: #f8f9fa;">
                    </div>

                    <div class="form-group">
                        <label>Link</label>
                        <input type="url" name="link" id="edit_link" placeholder="https://...">
                    </div>

                    <div class="form-group">
                        <label>Situação *</label>
                        <select name="situacao" id="edit_situacao" required>
                            <option value="EM_ANDAMENTO">EM ANDAMENTO</option>
                            <option value="REVOGADO">REVOGADO</option>
                            <option value="FRACASSADO">FRACASSADO</option>
                            <option value="HOMOLOGADO">HOMOLOGADO</option>
                        </select>
                    </div>

                    <div class="form-group form-full">
                        <label>Objeto *</label>
                        <textarea name="objeto" id="edit_objeto" required rows="3" placeholder="Descreva o objeto da licitação..."></textarea>
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalEdicao')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i data-lucide="refresh-cw"></i> Restaurar Valores
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modalExportar" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="download"></i> Exportar Dados
            </h3>
            <span class="close" onclick="fecharModal('modalExportar')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formExportar">
                <?php echo getCSRFInput(); ?>
                <div class="form-group">
                    <label>Formato de Exportação</label>
                    <select id="formato_export" name="formato" required>
                        <option value="csv">CSV (Excel)</option>
                        <option value="excel">Excel (XLS)</option>
                        <option value="json">JSON</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Filtrar por Situação</label>
                    <select id="situacao_export" name="situacao">
                        <option value="">Todas as Situações</option>
                        <option value="EM_ANDAMENTO">Em Andamento</option>
                        <option value="HOMOLOGADO">Homologadas</option>
                        <option value="FRACASSADO">Fracassadas</option>
                        <option value="REVOGADO">Revogadas</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Período de Criação</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Inicial</label>
                            <input type="date" id="data_inicio_export" name="data_inicio">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Final</label>
                            <input type="date" id="data_fim_export" name="data_fim">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Campos para Exportar</label>
                    <div style="margin-bottom: 10px;">
                        <button type="button" onclick="selecionarTodosCampos(true)" class="btn-secondary" style="margin-right: 10px; padding: 5px 10px; font-size: 12px;">
                            Selecionar Todos
                        </button>
                        <button type="button" onclick="selecionarTodosCampos(false)" class="btn-secondary" style="padding: 5px 10px; font-size: 12px;">
                            Desmarcar Todos
                        </button>
                    </div>
                    <div style="margin-top: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="nup" checked> NUP
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="numero_contratacao_final" checked> Número da Contratação
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="modalidade" checked> Modalidade
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="tipo" checked> Tipo
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="objeto" checked> Objeto
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="valor_estimado" checked> Valor Estimado
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="situacao" checked> Situação
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="pregoeiro" checked> Pregoeiro
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="data_abertura" checked> Data Abertura
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="data_homologacao"> Data Homologação
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="valor_homologado"> Valor Homologado
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="economia"> Economia
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="area_demandante"> Área Demandante
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="resp_instrucao"> Resp. Instrução
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="usuario_nome"> Criado por
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="criado_em"> Data de Criação
                        </label>
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalExportar')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="download"></i> Exportar
                    </button>
                </div>
            </form>
        </div>
    </div>
        </div>
        </main>
    </div>

<div id="modalImportJson" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="upload"></i> Importar JSON
                </h3>
                <span class="close" onclick="fecharModal('modalImportJson')">&times;</span>
            </div>
            <div class="modal-body">
                <form action="import_json.php" method="POST" enctype="multipart/form-data">
                    <?php echo getCSRFInput(); ?>
                    <div class="form-group">
                        <input type="file" name="file" required>
                    </div>
                    <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                        <button type="button" onclick="fecharModal('modalImportJson')" class="btn-secondary">
                            <i data-lucide="x"></i> Cancelar
                        </button>
                        <button type="submit" class="btn-primary">
                            <i data-lucide="upload"></i> Importar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script>
        // Dados passados do PHP para JavaScript
        window.dadosModalidade = <?php echo json_encode($dados_modalidade); ?>;
        window.dadosPregoeiro = <?php echo json_encode($dados_pregoeiro); ?>;
        window.dadosMensal = <?php echo json_encode($dados_mensal); ?>;
        window.stats = <?php echo json_encode($stats); ?>;

// ==================== SISTEMA SIMPLES FUNCIONAL ====================

console.log('Carregando sistema...');

// Dados das contratações
window.dadosContratacoes = <?php echo json_encode($contratacoes_pca); ?>;
console.log('Dados carregados:', window.dadosContratacoes.length, 'contratações');

// Funções básicas
window.abrirModalCriarLicitacao = function() {
    console.log('Abrindo modal...');
    document.getElementById('modalCriarLicitacao').style.display = 'block';
};
window.abrirModalImportarJson = function() {
    document.getElementById('modalImportJson').style.display = 'block';
};

window.fecharModal = function(id) {
    document.getElementById(id).style.display = 'none';
};

window.exportarLicitacoes = function() {
    document.getElementById('modalExportar').style.display = 'block';
};

// Abrir modal de importação de JSON
var btnImport = document.getElementById('btnImportJson');
if (btnImport) {
    btnImport.addEventListener('click', function() {
        document.getElementById('modalImportJson').style.display = 'block';
    });
}


window.mostrarTabDetalhes = function(tab) {
    document.querySelectorAll('#modalDetalhes .tab-link').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('onclick').includes("'"+tab+"'"));
    });
    document.querySelectorAll('#modalDetalhes .tab-content').forEach(div => {
        div.classList.toggle('active', div.id === 'tab-' + tab);
    });
};


window.pesquisarContratacaoInline = function(termo) {
    var sugestoes = document.getElementById('sugestoes_contratacao');
    if (!termo || termo.length < 2) {
        sugestoes.style.display = 'none';
        return;
    }

    var resultados = [];
    for (var i = 0; i < window.dadosContratacoes.length && resultados.length < 8; i++) {
        var item = window.dadosContratacoes[i];
        // Adicionando a busca por título e DFD
        if (item.numero_contratacao && item.numero_contratacao.toLowerCase().includes(termo.toLowerCase()) ||
            item.titulo_contratacao && item.titulo_contratacao.toLowerCase().includes(termo.toLowerCase()) ||
            item.numero_dfd && item.numero_dfd.toLowerCase().includes(termo.toLowerCase())) {
            resultados.push(item);
        }
    }

    var html = '';
    for (var j = 0; j < resultados.length; j++) {
        var r = resultados[j];
        // Passar numero_dfd e titulo_contratacao para a função selecionarContratacao
        html += '<div class="suggestion-item" onclick="window.selecionarContratacao(\'' + r.numero_contratacao + '\', \'' + (r.numero_dfd || '') + '\', \'' + (r.titulo_contratacao || '').replace(/'/g, "\\'") + '\')">';
        html += '<div class="suggestion-numero">' + r.numero_contratacao + '</div>';
        html += '<div class="suggestion-titulo">' + (r.titulo_contratacao || '').substring(0, 70) + '...</div>';
        html += '</div>';
    }

    sugestoes.innerHTML = html || '<div class="no-results">Nenhuma contratação encontrada</div>';
    sugestoes.style.display = 'block';
};

// Alterar a função para receber numero_dfd e titulo_contratacao
window.selecionarContratacao = function(numero, numero_dfd, titulo_contratacao) {
    console.log('Selecionando:', numero);

    // Preencher campo principal
    document.getElementById('input_contratacao').value = numero;
    document.getElementById('sugestoes_contratacao').style.display = 'none';

    // Buscar dados completos (não mais estritamente necessário para numero_dfd e titulo_contratacao se já passados)
    var dados = null;
    for (var i = 0; i < window.dadosContratacoes.length; i++) {
        if (window.dadosContratacoes[i].numero_contratacao === numero) {
            dados = window.dadosContratacoes[i];
            break;
        }
    }

    if (!dados) {
        console.log('Dados não encontrados para:', numero);
        return;
    }

    console.log('Dados encontrados:', dados);

    // Preencher campos
    var campos = {
        'area_demandante_criar': dados.area_requisitante,
        'objeto_textarea': dados.titulo_contratacao, // Objeto deve ser preenchido com titulo_contratacao
        'numero_dfd_selecionado': dados.numero_dfd, // Campo hidden para numero_dfd
        'titulo_contratacao_selecionado': dados.titulo_contratacao // Campo hidden para titulo_contratacao
    };

    for (var campo in campos) {
        var elemento = document.getElementById(campo);
        if (elemento && campos[campo]) {
            elemento.value = campos[campo];
            console.log('Preenchido', campo, ':', campos[campo]);
        }
    }

    // Valor estimado sem formatação (apenas o número)
    var valorField = document.getElementById('valor_estimado_criar');
    if (valorField && dados.valor_total_contratacao) {
        var valor = parseFloat(dados.valor_total_contratacao);
        if (!isNaN(valor) && valor > 0) {
            // Usar formato simples sem separadores de milhares
            valorField.value = valor.toFixed(2).replace('.', ',');
            console.log('Valor preenchido:', valor);
        }
    }
};

window.mostrarSugestoesInline = function() {};
window.ocultarSugestoesInline = function() {
    setTimeout(function() {
        document.getElementById('sugestoes_contratacao').style.display = 'none';
    }, 200);
};

console.log('Sistema carregado com sucesso!');

// Compatibilidade com arquivo JS externo
window.contratacoesPCA = window.dadosContratacoes;

// Formulário de criação funcionando normalmente

// Função para editar licitação
window.editarLicitacao = function(id) {
    console.log('Editando licitação ID:', id);
    
    // Buscar dados da licitação
    fetch('api/get_licitacao.php?id=' + id)
        .then(response => {
            console.log('Status da resposta:', response.status);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.text(); // Primeiro como texto para debug
        })
        .then(text => {
            console.log('Resposta da API como texto:', text);
            try {
                const data = JSON.parse(text);
                return data;
            } catch (e) {
                console.error('Erro ao parsear JSON:', e);
                throw new Error('Resposta inválida da API');
            }
        })
        .then(data => {
            if (data.success && data.data) {
                const licitacao = data.data;
                console.log('Dados da licitação:', licitacao);
                
                // Preencher o modal de edição
                document.getElementById('edit_id').value = licitacao.id;
                document.getElementById('edit_nup').value = licitacao.nup || '';
                document.getElementById('edit_data_entrada_dipli').value = licitacao.data_entrada_dipli || '';
                document.getElementById('edit_resp_instrucao').value = licitacao.resp_instrucao || '';
                document.getElementById('edit_area_demandante').value = licitacao.area_demandante || '';
                document.getElementById('edit_pregoeiro').value = licitacao.pregoeiro || '';
                document.getElementById('edit_modalidade').value = licitacao.modalidade || '';
                document.getElementById('edit_tipo').value = licitacao.tipo || '';
                
                // CORREÇÃO: Usar numero_contratacao direto da licitação ou o campo final
                const numeroContratacao = licitacao.numero_contratacao || licitacao.numero_contratacao_final || '';
                document.getElementById('edit_input_contratacao').value = numeroContratacao;
                console.log('Número contratação preenchido:', numeroContratacao);
                
                document.getElementById('edit_ano').value = licitacao.ano || '';
                document.getElementById('edit_valor_estimado').value = licitacao.valor_estimado ? parseFloat(licitacao.valor_estimado).toFixed(2).replace('.', ',') : '';
                document.getElementById('edit_data_abertura').value = licitacao.data_abertura || '';
                document.getElementById('edit_data_homologacao').value = licitacao.data_homologacao || '';
                document.getElementById('edit_valor_homologado').value = licitacao.valor_homologado ? parseFloat(licitacao.valor_homologado).toFixed(2).replace('.', ',') : '';
                document.getElementById('edit_economia').value = licitacao.economia ? parseFloat(licitacao.economia).toFixed(2).replace('.', ',') : '';
                document.getElementById('edit_link').value = licitacao.link || '';
                document.getElementById('edit_situacao').value = licitacao.situacao || 'EM_ANDAMENTO';
                document.getElementById('edit_objeto').value = licitacao.objeto || '';
                
                // Abrir modal
                document.getElementById('modalEdicao').style.display = 'block';
            } else {
                alert('Erro ao carregar dados da licitação: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao buscar dados da licitação');
        });
};

// Submeter formulário de edição
if (document.getElementById('formEditarLicitacao')) {
    document.getElementById('formEditarLicitacao').addEventListener('submit', function(e) {
    e.preventDefault();
    console.log('Submetendo edição...');
    
    const formData = new FormData(this);
    
    // Debug: mostrar dados que serão enviados
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }
    
    fetch('process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Resposta da edição:', data);
        if (data.success) {
            alert('Licitação atualizada com sucesso!');
            document.getElementById('modalEdicao').style.display = 'none';
            location.reload(); // Recarregar a página para mostrar as mudanças
        } else {
            alert('Erro ao atualizar licitação: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar requisição');
    });
    });
}

// Função para ver detalhes da licitação
window.verDetalhes = function(id) {
    window.verDetalhes = function(id, el) {
    console.log('Visualizando detalhes da licitação ID:', id);
    let tempos = {};
    if (el && el.closest('tr')) {
        try {
            tempos = JSON.parse(el.closest('tr').dataset.tempos || '{}');
        } catch (e) {}
    }
    // Buscar dados da licitação
    fetch('api/get_licitacao.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const licitacao = data.data;
                
                // Gerar HTML dos detalhes
                const temposList = Object.entries(tempos).map(([u, d]) => `<li>${u}: ${d} dias</li>`).join('');
                const tempoSection = temposList ? `
                        <div class="detail-section">
                            <h4>Tempo por Unidade</h4>
                            <ul>${temposList}</ul>
                        </div>
                        ` : '';

                const html = `
                    <div class="detalhes-licitacao">
                    <div class="tabs">
                            <button class="tab-link active" onclick="mostrarTabDetalhes('info')">Informações</button>
                            <button class="tab-link" onclick="mostrarTabDetalhes('tempo')">Tempo por Unidade</button>
                        </div>
                        <div id="tab-info" class="tab-content active">
                        <div class="detail-section">
                            <h4>Informações Básicas</h4>
                            <p><strong>NUP:</strong> ${licitacao.nup || 'N/A'}</p>
                            <p><strong>Número da Contratação:</strong> ${licitacao.numero_contratacao || licitacao.numero_contratacao_final || 'N/A'}</p>
                            <p><strong>Modalidade:</strong> ${licitacao.modalidade || 'N/A'}</p>
                            <p><strong>Tipo:</strong> ${licitacao.tipo || 'N/A'}</p>
                            <p><strong>Situação:</strong> <span class="status-badge status-${(licitacao.situacao || '').toLowerCase().replace('_', '-')}">${(licitacao.situacao || '').replace('_', ' ')}</span></p>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Responsáveis</h4>
                            <p><strong>Área Demandante:</strong> ${licitacao.area_demandante || 'N/A'}</p>
                            <p><strong>Pregoeiro:</strong> ${licitacao.pregoeiro || 'N/A'}</p>
                            <p><strong>Resp. Instrução:</strong> ${licitacao.resp_instrucao || 'N/A'}</p>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Valores e Prazos</h4>
                            <p><strong>Valor Estimado:</strong> ${licitacao.valor_estimado ? 'R$ ' + parseFloat(licitacao.valor_estimado).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : 'N/A'}</p>
                            <p><strong>Valor Homologado:</strong> ${licitacao.valor_homologado ? 'R$ ' + parseFloat(licitacao.valor_homologado).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : 'N/A'}</p>
                            <p><strong>Economia:</strong> ${licitacao.economia ? 'R$ ' + parseFloat(licitacao.economia).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : 'N/A'}</p>
                            <p><strong>Data Abertura:</strong> ${licitacao.data_abertura ? new Date(licitacao.data_abertura + 'T00:00:00').toLocaleDateString('pt-BR') : 'N/A'}</p>
                            <p><strong>Data Homologação:</strong> ${licitacao.data_homologacao ? new Date(licitacao.data_homologacao + 'T00:00:00').toLocaleDateString('pt-BR') : 'N/A'}</p>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Objeto</h4>
                            <p>${licitacao.objeto || 'N/A'}</p>
                        </div>
                        ${tempoSection}

                        
                        ${licitacao.link ? `
                        <div class="detail-section">
                            <h4>Link</h4>
                            <p><a href="${licitacao.link}" target="_blank" rel="noopener">${licitacao.link}</a></p>
                        </div>
                        ` : ''}
                        
                        <div class="detail-section">
                            <h4>Informações do Sistema</h4>
                            <p><strong>Criado em:</strong> ${licitacao.criado_em ? new Date(licitacao.criado_em).toLocaleString('pt-BR') : 'N/A'}</p>
                            <p><strong>Atualizado em:</strong> ${licitacao.atualizado_em ? new Date(licitacao.atualizado_em).toLocaleString('pt-BR') : 'N/A'}</p>
                            <p><strong>Criado por:</strong> ${licitacao.usuario_nome || 'N/A'}</p>
                        </div>
                        </div>
                        <div id="tab-tempo" class="tab-content">
                            <p>Carregando...</p>
                        </div>
                    </div>
                `;
                
                document.getElementById('detalhesContent').innerHTML = html;
                // Carregar quebra por unidade
                if (licitacao.processo_id) {
                    fetch('calcular_tempo_unidade.php?processo_id=' + licitacao.processo_id)
                        .then(r => r.json())
                        .then(dados => {
                            let linhas = '';
                            for (const [unidade, dias] of Object.entries(dados)) {
                                linhas += `<tr><td>${unidade}</td><td>${dias}</td></tr>`;
                            }
                            if (linhas) {
                                const bloco = `
                                    <div class="detail-section">
                                        <h4>Tempo por Unidade</h4>
                                        <table class="tempo-unidade-table">
                                            <thead><tr><th>Unidade</th><th>Dias</th></tr></thead>
                                            <tbody>${linhas}</tbody>
                                        </table>
                                    </div>`;
                                document.querySelector('#detalhesContent .detalhes-licitacao').insertAdjacentHTML('beforeend', bloco);
                            }
                        });
                }
                document.getElementById('modalDetalhes').style.display = 'block';
                const procId = licitacao.processo_id || licitacao.id;
                fetch('calcular_tempo_unidade.php?processo_id=' + procId)
                    .then(resp => resp.json())
                    .then(dados => {
                        let tempoHtml = '<ul>';
                        for (const unidade in dados) {
                            tempoHtml += `<li><strong>${unidade}</strong>: ${dados[unidade]} dias</li>`;
                        }
                        tempoHtml += '</ul>';
                        document.getElementById('tab-tempo').innerHTML = tempoHtml;
                    })
                    .catch(() => {
                        document.getElementById('tab-tempo').innerHTML = '<p>Erro ao carregar dados.</p>';
                    });
            } else {
                alert('Erro ao carregar detalhes da licitação: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao buscar detalhes da licitação');
        });
};

(function() {
    // Funções de pesquisa para o modal de edição
    window.pesquisarContratacaoInlineEdit = function(termo) {
        var sugestoes = document.getElementById('edit_sugestoes_contratacao');
        if (!termo || termo.length < 2) {
            sugestoes.style.display = 'none';
            return;
        }

        var resultados = [];
        for (var i = 0; i < window.dadosContratacoes.length && resultados.length < 8; i++) {
            var item = window.dadosContratacoes[i];
            // Adicionando a busca por título e DFD para edição
            if (item.numero_contratacao && item.numero_contratacao.toLowerCase().includes(termo.toLowerCase()) ||
                item.titulo_contratacao && item.titulo_contratacao.toLowerCase().includes(termo.toLowerCase()) ||
                item.numero_dfd && item.numero_dfd.toLowerCase().includes(termo.toLowerCase())) {
                resultados.push(item);
            }
        }

        var html = '';
        for (var j = 0; j < resultados.length; j++) {
            var r = resultados[j];
            // Passar numero_dfd e titulo_contratacao para a função selecionarContratacaoEdit
            html += '<div class="suggestion-item" onclick="window.selecionarContratacaoEdit(\'' + r.numero_contratacao + '\', \'' + (r.numero_dfd || '') + '\', \'' + (r.titulo_contratacao || '').replace(/'/g, "\\'") + '\')">';
            html += '<div class="suggestion-numero">' + r.numero_contratacao + '</div>';
            html += '<div class="suggestion-titulo">' + (r.titulo_contratacao || '').substring(0, 70) + '...</div>';
            html += '</div>';
        }

        sugestoes.innerHTML = html || '<div class="no-results">Nenhuma contratação encontrada</div>';
        sugestoes.style.display = 'block';
    };

    // Alterar a função para receber numero_dfd e titulo_contratacao
    window.selecionarContratacaoEdit = function(numero, numero_dfd, titulo_contratacao) {
        document.getElementById('edit_input_contratacao').value = numero;
        document.getElementById('edit_sugestoes_contratacao').style.display = 'none';

        // Buscar dados completos (não mais estritamente necessário para numero_dfd e titulo_contratacao se já passados)
        var dados = null;
        for (var i = 0; i < window.dadosContratacoes.length; i++) {
            if (window.dadosContratacoes[i].numero_contratacao === numero) {
                dados = window.dadosContratacoes[i];
                break;
            }
        }

        if (dados) {
            document.getElementById('edit_numero_dfd_selecionado').value = dados.numero_dfd || '';
            document.getElementById('edit_titulo_contratacao_selecionado').value = dados.titulo_contratacao || '';

            // Preencher outros campos se estiverem vazios
            var areaField = document.getElementById('edit_area_demandante');
            if (areaField && !areaField.value.trim() && dados.area_requisitante) {
                areaField.value = dados.area_requisitante;
            }

            var objetoField = document.getElementById('edit_objeto'); // Preencher objeto também
            if (objetoField && !objetoField.value.trim() && dados.titulo_contratacao) {
                objetoField.value = dados.titulo_contratacao;
            }

            var valorField = document.getElementById('edit_valor_estimado'); // Preencher valor estimado também
            if (valorField && !valorField.value.trim() && dados.valor_total_contratacao) {
                var valor = parseFloat(dados.valor_total_contratacao);
                if (!isNaN(valor) && valor > 0) {
                    valorField.value = valor.toFixed(2).replace('.', ',');
                }
            }
        }
    };

    window.mostrarSugestoesInlineEdit = function() {};
    window.ocultarSugestoesInlineEdit = function() {
        setTimeout(function() {
            document.getElementById('edit_sugestoes_contratacao').style.display = 'none';
        }, 200);
    };
})();

// Função para selecionar/desmarcar todos os campos
window.selecionarTodosCampos = function(selecionar) {
    const checkboxes = document.querySelectorAll('input[name="campos[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = selecionar;
    });
};

// Processar formulário de exportação
document.getElementById('formExportar').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Coletar dados do formulário
    const formato = document.getElementById('formato_export').value;
    const situacao = document.getElementById('situacao_export').value;
    const dataInicio = document.getElementById('data_inicio_export').value;
    const dataFim = document.getElementById('data_fim_export').value;
    
    // Coletar campos selecionados
    const camposSelecionados = [];
    const checkboxes = document.querySelectorAll('input[name="campos[]"]:checked');
    checkboxes.forEach(function(checkbox) {
        camposSelecionados.push(checkbox.value);
    });
    
    if (camposSelecionados.length === 0) {
        alert('Selecione pelo menos um campo para exportar!');
        return;
    }
    
    // Construir URL com parâmetros
    const params = new URLSearchParams();
    params.append('formato', formato);
    if (situacao) params.append('situacao', situacao);
    if (dataInicio) params.append('data_inicio', dataInicio);
    if (dataFim) params.append('data_fim', dataFim);
    params.append('campos', camposSelecionados.join(','));
    
    // Fazer download
    const url = 'api/exportar_licitacoes.php?' + params.toString();
    console.log('Exportando:', url);
    
    // Criar link temporário para download
    const link = document.createElement('a');
    link.href = url;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Fechar modal
    document.getElementById('modalExportar').style.display = 'none';
    
    // Mostrar mensagem de sucesso
    setTimeout(function() {
        alert('Exportação iniciada! O download deve começar automaticamente.');
    }, 500);
});
    </script>
    <script src="assets/notifications.js"></script>
</body>
</html>