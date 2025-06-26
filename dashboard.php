<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'cache.php';
require_once 'pagination.php';

verificarLogin();

$pdo = conectarDB();

// ========================================
// SISTEMA DE PCAs POR ANO
// ========================================

// Ano selecionado (padrão: 2025 - atual)
$ano_selecionado = intval($_GET['ano'] ?? 2025);
$anos_disponiveis = [2026, 2025, 2024, 2023, 2022];

// Verificar se ano é válido
if (!in_array($ano_selecionado, $anos_disponiveis)) {
    $ano_selecionado = 2025;
}

// Determinar se é ano atual (editável) ou histórico (somente leitura)
$eh_ano_atual = !isAnoHistorico($ano_selecionado);
$eh_historico = isAnoHistorico($ano_selecionado);

// Usar nova função para determinar tabela
$tabela_pca = getPcaTableName($ano_selecionado);

// Definir filtro por ano usando nova estrutura unificada
// Agora filtra por importações do ano específico na tabela pca_dados
$importacoes_ano_sql = "SELECT id FROM pca_importacoes WHERE ano_pca = ?";
$importacoes_stmt = $pdo->prepare($importacoes_ano_sql);
$importacoes_stmt->execute([$ano_selecionado]);
$importacoes_ids = [];
while ($row = $importacoes_stmt->fetch()) {
    $importacoes_ids[] = $row['id'];
}

if (!empty($importacoes_ids)) {
    $where_ano = "importacao_id IN (" . implode(',', $importacoes_ids) . ") AND";
} else {
    $where_ano = "importacao_id = -1 AND"; // Força retorno vazio se não há importações
}

// Configuração de paginação melhorada
$limite = intval($_GET['limite'] ?? 20);
$pagina = intval($_GET['pagina'] ?? 1);

// Buscar áreas para o filtro (agrupadas) - usa tabela apropriada
$areas_sql = "SELECT DISTINCT area_requisitante FROM $tabela_pca WHERE $where_ano area_requisitante IS NOT NULL AND area_requisitante != '' ORDER BY area_requisitante";
$areas_result = $pdo->query($areas_sql);
$areas_agrupadas = [];

while ($row = $areas_result->fetch()) {
    $area_agrupada = agruparArea($row['area_requisitante']);
    if (!in_array($area_agrupada, $areas_agrupadas)) {
        $areas_agrupadas[] = $area_agrupada;
    }
}
sort($areas_agrupadas);

// Buscar dados com filtros
$where = [];
$params = [];
$secao_ativa = $_GET['secao'] ?? 'dashboard';

if (!empty($_GET['numero_contratacao'])) {
    $where[] = "p.numero_dfd LIKE ?";
    $params[] = '%' . $_GET['numero_contratacao'] . '%';
}

if (!empty($_GET['situacao_execucao'])) {
    if ($_GET['situacao_execucao'] === 'Não iniciado') {
        $where[] = "(p.situacao_execucao IS NULL OR p.situacao_execucao = '' OR p.situacao_execucao = 'Não iniciado')";
    } else {
        $where[] = "p.situacao_execucao = ?";
        $params[] = $_GET['situacao_execucao'];
    }
}

if (!empty($_GET['categoria'])) {
    $where[] = "p.categoria_contratacao = ?";
    $params[] = $_GET['categoria'];
}

if (!empty($_GET['area_requisitante'])) {
    $filtro_area = $_GET['area_requisitante'];
    if ($filtro_area === 'GM.') {
        $where[] = "(p.area_requisitante LIKE 'GM%' OR p.area_requisitante LIKE 'GM.%')";
    } else {
        $where[] = "p.area_requisitante LIKE ?";
        $params[] = $filtro_area . '%';
    }
}

// Construir WHERE clause segura
$whereClause = '';
if ($where) {
    $whereClause = 'AND ' . implode(' AND ', $where);
}

// Query para contar total de registros - SEGURA (usa tabela dinâmica)
$sqlCount = "SELECT COUNT(DISTINCT numero_dfd) as total FROM $tabela_pca p WHERE $where_ano numero_dfd IS NOT NULL AND numero_dfd != '' $whereClause";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRegistros = $stmtCount->fetch()['total'];

// Criar objeto de paginação
$pagination = createPagination($totalRegistros, $pagina, $limite, $_GET);

// Query principal otimizada e segura - usa tabela dinâmica baseada no ano
$sql = "SELECT 
        MAX(p.numero_contratacao) as numero_contratacao,
        p.numero_dfd,
        MAX(p.status_contratacao) as status_contratacao,
        MAX(p.titulo_contratacao) as titulo_contratacao,
        MAX(p.categoria_contratacao) as categoria_contratacao,
        MAX(p.uasg_atual) as uasg_atual,
        MAX(p.valor_total_contratacao) as valor_total_contratacao,
        MAX(p.area_requisitante) as area_requisitante,
        MAX(p.prioridade) as prioridade,
        MAX(p.situacao_execucao) as situacao_execucao,
        MAX(p.data_inicio_processo) as data_inicio_processo,
        MAX(p.data_conclusao_processo) as data_conclusao_processo,
        DATEDIFF(MAX(p.data_conclusao_processo), CURDATE()) as dias_ate_conclusao,
        COUNT(*) as qtd_itens_pca,
        GROUP_CONCAT(p.id) as ids,
        MAX(p.id) as id";

// Adicionar verificação de licitação apenas para ano atual        
if ($eh_ano_atual) {
    $sql .= ",
        COUNT(DISTINCT l.id) > 0 as tem_licitacao
        FROM $tabela_pca p 
        LEFT JOIN licitacoes l ON l.pca_dados_id = p.id";
} else {
    $sql .= ",
        0 as tem_licitacao
        FROM $tabela_pca p";
}

$sql .= " WHERE $where_ano p.numero_dfd IS NOT NULL AND p.numero_dfd != ''
        $whereClause 
        GROUP BY p.numero_dfd
        ORDER BY p.numero_dfd DESC
        LIMIT ? OFFSET ?";

// Adicionar LIMIT e OFFSET aos parâmetros
$params[] = $pagination->getLimit();
$params[] = $pagination->getOffset();

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dados = $stmt->fetchAll();

// Buscar listas únicas para os filtros - usa tabela dinâmica
$situacao_sql = "SELECT DISTINCT situacao_execucao FROM $tabela_pca WHERE $where_ano situacao_execucao IS NOT NULL AND situacao_execucao != '' ORDER BY situacao_execucao";
$situacao_lista = $pdo->query($situacao_sql)->fetchAll(PDO::FETCH_COLUMN);

// Adicionar "Não iniciado" se não estiver na lista
if (!in_array('Não iniciado', $situacao_lista)) {
    array_unshift($situacao_lista, 'Não iniciado');
}

$categoria_sql = "SELECT DISTINCT categoria_contratacao FROM $tabela_pca WHERE $where_ano categoria_contratacao IS NOT NULL ORDER BY categoria_contratacao";
$categoria_lista = $pdo->query($categoria_sql)->fetchAll(PDO::FETCH_COLUMN);

// Buscar estatísticas para os cards e gráficos - usando estrutura unificada
// Agora sempre filtra por importações do ano específico na tabela pca_dados
if (!empty($importacoes_ids)) {
    $where_stats = "importacao_id IN (" . implode(',', $importacoes_ids) . ")";
    
    $stats = [
        'total_dfds' => $pdo->query("
            SELECT COUNT(DISTINCT numero_dfd) 
            FROM $tabela_pca 
            WHERE $where_stats 
            AND numero_dfd IS NOT NULL 
            AND numero_dfd != ''
        ")->fetchColumn(),
        
        'total_contratacoes' => $pdo->query("
            SELECT COUNT(DISTINCT numero_contratacao) 
            FROM $tabela_pca 
            WHERE $where_stats 
            AND numero_dfd IS NOT NULL 
            AND numero_dfd != ''
        ")->fetchColumn(),
        
        'valor_total' => $pdo->query("
            SELECT COALESCE(SUM(DISTINCT valor_total), 0) 
            FROM $tabela_pca 
            WHERE $where_stats 
            AND numero_dfd IS NOT NULL 
            AND numero_dfd != ''
        ")->fetchColumn(),
        
        'homologadas' => $pdo->query("
            SELECT COUNT(DISTINCT numero_dfd) 
            FROM $tabela_pca 
            WHERE $where_stats 
            AND numero_dfd IS NOT NULL 
            AND numero_dfd != ''
            AND situacao_execucao = 'Concluído'
        ")->fetchColumn(),
        
        'atrasadas_inicio' => $pdo->query("
            SELECT COUNT(DISTINCT numero_dfd) 
            FROM $tabela_pca 
            WHERE $where_stats 
            AND numero_dfd IS NOT NULL 
            AND numero_dfd != ''
            AND data_inicio_processo < CURDATE() 
            AND (situacao_execucao IS NULL OR situacao_execucao = 'Não iniciado')
        ")->fetchColumn(),
        
        'atrasadas_conclusao' => $pdo->query("
            SELECT COUNT(DISTINCT numero_dfd) 
            FROM $tabela_pca 
            WHERE $where_stats 
            AND numero_dfd IS NOT NULL 
            AND numero_dfd != ''
            AND data_conclusao_processo < CURDATE() 
            AND situacao_execucao != 'Concluído'
        ")->fetchColumn()
    ];
    
    $dados_categoria = $pdo->query("
        SELECT categoria_contratacao as categoria, COUNT(DISTINCT numero_dfd) as total 
        FROM $tabela_pca 
        WHERE $where_stats 
        AND categoria_contratacao IS NOT NULL 
        AND numero_dfd IS NOT NULL 
        AND numero_dfd != ''
        GROUP BY categoria_contratacao 
        ORDER BY total DESC
    ")->fetchAll();
    
    $dados_area = $pdo->query("
        SELECT area_requisitante as area, COUNT(DISTINCT numero_dfd) as total 
        FROM $tabela_pca 
        WHERE $where_stats 
        AND area_requisitante IS NOT NULL 
        AND numero_dfd IS NOT NULL 
        AND numero_dfd != ''
        GROUP BY area_requisitante 
        ORDER BY total DESC 
        LIMIT 10
    ")->fetchAll();
    
    $dados_mensal_pca = [];
    
} else {
    // Sem importações do ano - dados zerados
    $stats = [
        'total_dfds' => 0,
        'total_contratacoes' => 0,
        'valor_total' => 0,
        'homologadas' => 0,
        'atrasadas_inicio' => 0,
        'atrasadas_conclusao' => 0
    ];
    $dados_categoria = [];
    $dados_area = [];
    $dados_mensal_pca = [];
}

// Buscar histórico de importações para o ano selecionado
$historico_importacoes = buscarHistoricoImportacoes($ano_selecionado, 10);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Planejamento - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/dashboard.css">
    <link rel="stylesheet" href="assets/mobile-improvements.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i data-lucide="clipboard-check"></i> Planejamento</h2>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Visão Geral</div>
                    <button class="nav-item <?php echo $secao_ativa === 'dashboard' ? 'active' : ''; ?>" onclick="showSection('dashboard')">
                        <i data-lucide="bar-chart-3"></i> Dashboard
                    </button>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Gerenciar</div>
                    <?php if (temPermissao('pca_importar')): ?>
                    <button class="nav-item <?php echo $secao_ativa === 'importar-pca' ? 'active' : ''; ?>" onclick="showSection('importar-pca')">
                        <i data-lucide="upload"></i> Importar PCA
                    </button>
                    <?php endif; ?>
                    <?php if (temPermissao('pca_visualizar')): ?>
                    <button class="nav-item <?php echo $secao_ativa === 'lista-contratacoes' ? 'active' : ''; ?>" onclick="showSection('lista-contratacoes')">
                        <i data-lucide="list"></i> Lista de Contratações
                    </button>
                    <a href="contratacoes_atrasadas.php" class="nav-item">
                        <i data-lucide="alert-triangle"></i> Contratações Atrasadas
                    </a>
                    <?php endif; ?>
                    <?php if (isVisitante()): ?>
                    <div style="margin: 10px 15px; padding: 8px; background: #fff3cd; border-radius: 6px; border-left: 3px solid #f39c12;">
                        <small style="color: #856404; font-size: 11px; font-weight: 600;">
                            <i data-lucide="eye" style="width: 12px; height: 12px;"></i> MODO VISITANTE<br>
                            Somente visualização e exportação
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Relatórios</div>
                    <?php if (temPermissao('pca_relatorios')): ?>
                    <button class="nav-item" onclick="showSection('relatorios')">
                        <i data-lucide="file-text"></i> Relatórios
                    </button>
                    <?php endif; ?>
                    <?php if (temPermissao('risco_visualizar')): ?>
                    <button class="nav-item" onclick="window.location.href='gestao_riscos.php'">
                        <i data-lucide="shield-alert"></i> Gestão de Riscos
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Navegação</div>
                    <a href="licitacao_dashboard.php" class="nav-item">
                        <i data-lucide="gavel"></i> Dashboard Licitações
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Sistema</div>
                    <?php if (temPermissao('backup_executar')): ?>
                    <button class="nav-item" onclick="showSection('backup-sistema')">
                        <i data-lucide="shield"></i> Backup & Segurança
                    </button>
                    <?php endif; ?>
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

        <!-- Main Content -->
        <div class="main-content">
            <?php echo getMensagem(); ?>

            <!-- Dashboard Section -->
            <div id="dashboard" class="content-section <?php echo $secao_ativa === 'dashboard' ? 'active' : ''; ?>">
                <div class="dashboard-header">
                    <h1><i data-lucide="bar-chart-3"></i> Dashboard de Planejamento</h1>
                    <p>Visão geral do Plano de Contratações Anual (PCA) e indicadores de desempenho</p>
                    
                    <!-- Seletor de Ano PCA -->
                    <div style="margin-top: 20px; padding: 20px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i data-lucide="calendar" style="color: #1e3c72;"></i>
                                <strong style="color: #1e3c72; font-size: 16px;">Ano do PCA:</strong>
                            </div>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <form method="GET" style="display: flex; align-items: center; gap: 10px;">
                                    <input type="hidden" name="secao" value="<?php echo $secao_ativa; ?>">
                                    <select name="ano" style="padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: 600;" onchange="this.form.submit()">
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
                </div>

                <!-- Cards de Estatísticas -->
                <div class="stats-grid">
                    <div class="stat-card info">
                        <div class="stat-number"><?php echo number_format($stats['total_dfds']); ?></div>
                        <div class="stat-label">Total de DFDs</div>
                    </div>
                    
                    <div class="stat-card primary">
                        <div class="stat-number"><?php echo number_format($stats['total_contratacoes']); ?></div>
                        <div class="stat-label">Total Contratações</div>
                    </div>
                    
                    <div class="stat-card money">
                        <div class="stat-number"><?php echo abreviarValor($stats['valor_total']); ?></div>
                        <div class="stat-label">Valor Total (R$)</div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-number"><?php echo $stats['homologadas']; ?></div>
                        <div class="stat-label">Homologadas</div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-number"><?php echo $stats['atrasadas_inicio'] + $stats['atrasadas_conclusao']; ?></div>
                        <div class="stat-label">Atrasadas</div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="users"></i> Contratações por Área</h3>
                        <canvas id="chartArea" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="trending-up"></i> Evolução Mensal</h3>
                        <canvas id="chartMensal" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="activity"></i> Status das Contratações</h3>
                        <canvas id="chartStatus" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Importar PCA Section -->
            <?php if (temPermissao('pca_importar')): ?>
            <div id="importar-pca" class="content-section <?php echo $secao_ativa === 'importar-pca' ? 'active' : ''; ?>">
                <div class="dashboard-header">
                    <h1><i data-lucide="upload"></i> Importar Planilha PCA</h1>
                    <p>Faça upload da planilha do Plano de Contratações Anual</p>
                </div>

                <?php if ($eh_historico): ?>
                    <!-- Aviso para anos históricos com importação permitida -->
                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <i data-lucide="archive" style="color: #856404;"></i>
                            <strong style="color: #856404;">Dados Históricos - Ano <?php echo $ano_selecionado; ?></strong>
                        </div>
                        <p style="color: #856404; margin: 0 0 15px 0; line-height: 1.5;">
                            Este é um ano histórico. Você pode importar dados, mas eles ficarão em modo somente leitura após a importação.
                        </p>
                        
                        <?php if (temPermissao('pca_importar')): ?>
                            <!-- Formulário de importação para ano histórico -->
                            <div class="upload-card" style="background: #fefefe; border: 1px solid #ffc107;">
                                <h4 style="color: #856404; margin: 0 0 15px 0;">Importar PCA Histórico - Ano <?php echo $ano_selecionado; ?></h4>
                                <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 14px;">
                                    ⚠️ Dados históricos ficam protegidos contra edição após importação
                                </p>
                                
                                <form action="process.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="acao" value="importar_pca">
                                    <input type="hidden" name="ano_pca" value="<?php echo $ano_selecionado; ?>">
                                    <?php echo getCSRFInput(); ?>
                                    <input type="file" name="arquivo_pca" accept=".csv,.xls,.xlsx" required>
                                    <br><br>
                                    <button type="submit" class="btn-primary" style="background: #e67e22;">
                                        <i data-lucide="upload"></i> Importar Dados Históricos
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div style="background: white; border: 2px solid #ffc107; padding: 20px; border-radius: 8px; text-align: center;">
                                <i data-lucide="eye" style="width: 48px; height: 48px; color: #856404; margin-bottom: 15px;"></i>
                                <h3 style="color: #856404; margin: 0 0 10px 0;">Modo Somente Visualização</h3>
                                <p style="color: #7f8c8d; margin: 0 0 20px 0;">Você não tem permissão para importar dados</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Formulário de importação para ano atual -->
                    <div class="upload-card">
                        <h3>Importar Planilha PCA - Ano <?php echo $ano_selecionado; ?></h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Selecione um arquivo CSV, XLS ou XLSX para importar</p>
                        
                        <form action="process.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="acao" value="importar_pca">
                            <input type="hidden" name="ano_pca" value="<?php echo $ano_selecionado; ?>">
                            <?php echo getCSRFInput(); ?>
                            <input type="file" name="arquivo_pca" accept=".csv,.xls,.xlsx" required>
                            <br><br>
                            <button type="submit" class="btn-primary">
                                <i data-lucide="upload"></i> Importar Arquivo
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Histórico de Importações -->
                <div class="table-container" style="margin-top: 30px;">
                    <div class="table-header">
                        <h3 class="table-title">
                            <i data-lucide="history"></i> Histórico de Importações - Ano <?php echo $ano_selecionado; ?>
                        </h3>
                        <div class="table-actions">
                            <span style="color: #7f8c8d;">Últimas <?php echo count($historico_importacoes); ?> importações</span>
                        </div>
                    </div>
                    
                    <?php if (empty($historico_importacoes)): ?>
                        <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                            <i data-lucide="file-x" style="width: 48px; height: 48px; margin-bottom: 15px;"></i>
                            <h4 style="margin: 0 0 8px 0;">Nenhuma importação encontrada</h4>
                            <p style="margin: 0;">Não há histórico de importações para este ano.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Arquivo</th>
                                    <th>Usuário</th>
                                    <th>Status</th>
                                    <th>Registros</th>
                                    <th>Novos</th>
                                    <th>Atualizados</th>
                                    <th>Observações</th>
                                    <th style="width: 100px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historico_importacoes as $importacao): ?>
                                <tr>
                                    <td style="font-size: 12px;">
                                        <strong><?php echo date('d/m/Y', strtotime($importacao['criado_em'])); ?></strong><br>
                                        <small style="color: #7f8c8d;"><?php echo date('H:i:s', strtotime($importacao['criado_em'])); ?></small>
                                    </td>
                                    <td>
                                        <div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                                             title="<?php echo htmlspecialchars($importacao['nome_arquivo']); ?>">
                                            <?php echo htmlspecialchars($importacao['nome_arquivo']); ?>
                                        </div>
                                        <small style="color: #3498db; font-weight: 600;">Ano: <?php echo $importacao['ano_pca']; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($importacao['usuario_nome'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        $statusClass = '';
                                        $statusText = '';
                                        switch($importacao['status']) {
                                            case 'concluido':
                                                $statusClass = 'success';
                                                $statusText = 'Concluído';
                                                break;
                                            case 'processando':
                                                $statusClass = 'warning';
                                                $statusText = 'Processando';
                                                break;
                                            case 'erro':
                                                $statusClass = 'error';
                                                $statusText = 'Erro';
                                                break;
                                            case 'removido':
                                                $statusClass = 'error';
                                                $statusText = 'Revertida';
                                                break;
                                            default:
                                                $statusClass = 'info';
                                                $statusText = ucfirst($importacao['status']);
                                        }
                                        ?>
                                        <span class="situacao-badge <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center; font-weight: 600;">
                                        <?php echo number_format($importacao['total_registros']); ?>
                                    </td>
                                    <td style="text-align: center; color: #27ae60; font-weight: 600;">
                                        <?php echo number_format($importacao['registros_novos']); ?>
                                    </td>
                                    <td style="text-align: center; color: #3498db; font-weight: 600;">
                                        <?php echo number_format($importacao['registros_atualizados']); ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($importacao['observacoes'])): ?>
                                            <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                                                 title="<?php echo htmlspecialchars($importacao['observacoes']); ?>">
                                                <?php echo htmlspecialchars($importacao['observacoes']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #7f8c8d; font-style: italic;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($importacao['status'] !== 'removido' && temPermissao('pca_importar') && $_SESSION['usuario_nivel'] <= 2): ?>
                                            <button onclick="confirmarReversao(<?php echo $importacao['id']; ?>, '<?php echo htmlspecialchars($importacao['nome_arquivo'], ENT_QUOTES); ?>')" 
                                                    class="btn-acao btn-excluir" 
                                                    title="Reverter importação - REMOVE todos os dados desta importação"
                                                    style="background: #e74c3c; color: white; border: none; padding: 6px 8px; border-radius: 4px; cursor: pointer;">
                                                <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                            </button>
                                        <?php elseif ($importacao['status'] === 'removido'): ?>
                                            <span style="color: #7f8c8d; font-size: 12px; font-style: italic;">Revertida</span>
                                        <?php else: ?>
                                            <span style="color: #7f8c8d; font-size: 12px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Lista de Contratações Section -->
            <?php if (temPermissao('pca_visualizar')): ?>
            <div id="lista-contratacoes" class="content-section <?php echo $secao_ativa === 'lista-contratacoes' ? 'active' : ''; ?>">
                <div class="dashboard-header">
                    <h1><i data-lucide="list"></i> Lista de Contratações</h1>
                    <p>Visualize e gerencie todas as contratações do PCA</p>
                </div>

                <!-- Filtros -->
                <div class="filtros-card">
                    <h3 style="margin: 0 0 20px 0; color: #2c3e50;">Filtros</h3>
                    <form method="GET" class="filtros-form">
                        <input type="hidden" name="limite" value="<?php echo $limite; ?>">
                        <input type="hidden" name="secao" value="lista-contratacoes">
                        <div>
                            <input type="text" name="numero_contratacao" placeholder="Número do DFD"
                                   value="<?php echo $_GET['numero_contratacao'] ?? ''; ?>">
                        </div>
                        <div>
                            <select name="situacao_execucao">
                                <option value="">Todas as Situações</option>
                                <?php foreach ($situacao_lista as $situacao): ?>
                                    <option value="<?php echo htmlspecialchars($situacao); ?>" 
                                            <?php echo ($_GET['situacao_execucao'] ?? '') == $situacao ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($situacao); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select name="categoria">
                                <option value="">Todas as Categorias</option>
                                <?php foreach ($categoria_lista as $categoria): ?>
                                    <option value="<?php echo $categoria; ?>" 
                                            <?php echo ($_GET['categoria'] ?? '') == $categoria ? 'selected' : ''; ?>>
                                        <?php echo $categoria; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select name="area_requisitante">
                                <option value="">Todas as áreas</option>
                                <?php foreach ($areas_agrupadas as $area): ?>
                                    <option value="<?php echo htmlspecialchars($area); ?>" 
                                            <?php echo ($_GET['area_requisitante'] ?? '') == $area ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($area); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn-primary">Filtrar</button>
                            <a href="?secao=lista-contratacoes" class="btn-secondary" style="margin-left: 10px;">
        <i data-lucide="x"></i> Limpar Filtros
    </a>
                        </div>
                    </form>
                </div>

                <!-- Tabela -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Dados do PCA - Ano <?php echo $ano_selecionado; ?></h3>
                        <div class="table-actions">
                            <span style="color: #7f8c8d;">Total: <?php echo $totalRegistros; ?> contratações</span>
                            <select onchange="filtrarPorLimite(this.value)">
                                <option value="10" <?php echo $limite == 10 ? 'selected' : ''; ?>>10 por página</option>
                                <option value="20" <?php echo $limite == 20 ? 'selected' : ''; ?>>20 por página</option>
                                <option value="50" <?php echo $limite == 50 ? 'selected' : ''; ?>>50 por página</option>
                                <option value="100" <?php echo $limite == 100 ? 'selected' : ''; ?>>100 por página</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php if (empty($dados)): ?>
                        <div style="text-align: center; padding: 60px; color: #7f8c8d;">
                            <i data-lucide="inbox" style="width: 64px; height: 64px; margin-bottom: 20px;"></i>
                            <h3 style="margin: 0 0 10px 0;">Nenhum registro encontrado</h3>
                            <p style="margin: 0;">Importe uma planilha PCA para começar.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nº DFD</th>
                                    <th>Situação</th>
                                    <th>Título</th>
                                    <th>Categoria</th>
                                    <th>Valor Total</th>
                                    <th>Área</th>
                                    <th>Datas</th>
                                    <th style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dados as $item): ?>
                                <?php
                                    $classeSituacao = '';
                                    if ($item['data_inicio_processo'] < date('Y-m-d') && $item['situacao_execucao'] == 'Não iniciado') {
                                        $classeSituacao = 'atrasado-inicio';
                                    } elseif ($item['data_conclusao_processo'] < date('Y-m-d') && $item['situacao_execucao'] != 'Concluído') {
                                        $classeSituacao = 'atrasado-conclusao';
                                    }
                                ?>
                                <tr class="<?php echo $classeSituacao ? 'linha-' . $classeSituacao : ''; ?>">
                                    <td><strong><?php echo htmlspecialchars($item['numero_dfd']); ?></strong></td>
                                    <td>
                                        <span class="situacao-badge <?php echo $classeSituacao; ?>">
                                            <?php echo htmlspecialchars($item['situacao_execucao']); ?>
                                        </span>
                                        <?php if ($item['dias_ate_conclusao'] !== null && $item['dias_ate_conclusao'] >= 0 && $item['dias_ate_conclusao'] <= 30): ?>
                                            <br><small style="color: #f39c12; font-weight: 600;"><?php echo $item['dias_ate_conclusao']; ?> dias</small>
                                        <?php elseif ($item['dias_ate_conclusao'] !== null && $item['dias_ate_conclusao'] < 0): ?>
                                            <br><small style="color: #e74c3c; font-weight: 600;">Vencido há <?php echo abs($item['dias_ate_conclusao']); ?> dias</small>
                                        <?php endif; ?>
                                    </td>
                                    <td title="<?php echo htmlspecialchars($item['titulo_contratacao']); ?>">
                                        <?php echo htmlspecialchars(substr($item['titulo_contratacao'], 0, 60)) . '...'; ?>
                                    </td>
                                    <td><span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($item['categoria_contratacao']); ?></span></td>
                                    <td style="font-weight: 600; color: #27ae60;"><?php echo formatarMoeda($item['valor_total_contratacao']); ?></td>
                                    <td><?php echo htmlspecialchars($item['area_requisitante']); ?></td>
                                    <td style="font-size: 12px;">
                                        <strong>Início:</strong> <?php echo formatarData($item['data_inicio_processo']); ?><br>
                                        <strong>Fim:</strong> <?php echo formatarData($item['data_conclusao_processo']); ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px; align-items: center;">
                                            <button onclick="verDetalhes('<?php echo $item['ids']; ?>')" 
                                                    class="btn-acao btn-ver" title="Ver detalhes">
                                                <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                                            </button>
                                            <button onclick="verHistorico('<?php echo $item['numero_dfd']; ?>')"
                                                    class="btn-acao btn-historico" title="Ver histórico">
                                                <i data-lucide="history" style="width: 14px; height: 14px;"></i>
                                            </button>
                                            <?php if ($item['tem_licitacao'] > 0): ?>
                                                <span style="color: #28a745; font-size: 13px; display: flex; align-items: center; gap: 4px;">
                                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                                    Licitado
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Paginação Melhorada -->
                        <?php echo $pagination->render(); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Relatórios Section -->
            <?php if (temPermissao('pca_relatorios')): ?>
            <div id="relatorios" class="content-section">
    <div class="dashboard-header">
        <h1><i data-lucide="file-text"></i> Relatórios do PCA</h1>
        <p>Relatórios detalhados sobre o planejamento de contratações</p>
    </div>

    <div class="stats-grid">
        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorioPCA('categoria')">
            <h3 class="chart-title"><i data-lucide="layers"></i> Relatório por Categoria</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Análise detalhada das contratações por categoria com indicadores de performance</p>
            <div style="text-align: center;">
                <i data-lucide="pie-chart" style="width: 64px; height: 64px; color: #3498db; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>
        
        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorioPCA('area')">
            <h3 class="chart-title"><i data-lucide="building"></i> Relatório por Área</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Performance e distribuição por área requisitante com métricas de eficiência</p>
            <div style="text-align: center;">
                <i data-lucide="users" style="width: 64px; height: 64px; color: #e74c3c; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>
        
        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorioPCA('prazos')">
            <h3 class="chart-title"><i data-lucide="clock"></i> Relatório de Prazos</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Análise de cumprimento de cronogramas e identificação de gargalos</p>
            <div style="text-align: center;">
                <i data-lucide="calendar-check" style="width: 64px; height: 64px; color: #f39c12; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>
        
        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorioPCA('financeiro')">
            <h3 class="chart-title"><i data-lucide="trending-up"></i> Relatório Financeiro</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Evolução temporal dos valores planejados e análise de investimentos</p>
            <div style="text-align: center;">
                <i data-lucide="dollar-sign" style="width: 64px; height: 64px; color: #16a085; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Parâmetros do Relatório PCA -->
<div id="modalRelatorioPCA" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="file-text"></i> <span id="tituloRelatorioPCA">Configurar Relatório</span>
            </h3>
            <span class="close" onclick="fecharModal('modalRelatorioPCA')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formRelatorioPCA">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="tipo_relatorio_pca" name="tipo">
                
                <div class="form-group">
                    <label>Período de Análise</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Inicial</label>
                            <input type="date" name="data_inicial" id="pca_data_inicial" value="<?php echo date('Y-01-01'); ?>">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Final</label>
                            <input type="date" name="data_final" id="pca_data_final" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="filtroCategoriaPCA">
                    <label>Categoria</label>
                    <select name="categoria" id="pca_categoria">
                        <option value="">Todas as Categorias</option>
                        <?php
                        $categorias_pca = $pdo->query("SELECT DISTINCT categoria_contratacao FROM pca_dados WHERE categoria_contratacao IS NOT NULL ORDER BY categoria_contratacao")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($categorias_pca as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="filtroAreaPCA">
                    <label>Área Requisitante</label>
                    <select name="area" id="pca_area">
                        <option value="">Todas as Áreas</option>
                        <?php foreach ($areas_agrupadas as $area): ?>
                            <option value="<?php echo htmlspecialchars($area); ?>"><?php echo htmlspecialchars($area); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="filtroSituacaoPCA">
                    <label>Situação de Execução</label>
                    <select name="situacao" id="pca_situacao">
                        <option value="">Todas as Situações</option>
                        <option value="Não iniciado">Não Iniciado</option>
                        <option value="Em andamento">Em Andamento</option>
                        <option value="Concluído">Concluído</option>
                        <option value="Suspenso">Suspenso</option>
                        <option value="Cancelado">Cancelado</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Formato de Saída</label>
                    <select name="formato" id="pca_formato" required>
                        <option value="html">Visualizar (HTML)</option>
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel (CSV)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="incluir_graficos" id="pca_graficos" checked>
                        <span>Incluir gráficos e visualizações no relatório</span>
                    </label>
                    <small style="color: #6c757d; margin-top: 5px; display: block;">
                        Recomendado para relatórios HTML. Gráficos não são incluídos em exportações CSV.
                    </small>
                </div>
                
                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalRelatorioPCA')" class="btn-secondary">
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
            <?php endif; ?>


            <!-- Backup & Segurança Section -->
            <?php if (temPermissao('backup_executar')): ?>
            <div id="backup-sistema" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="shield"></i> Backup & Segurança</h1>
                    <p>Gerencie backups automáticos e monitore a segurança dos dados</p>
                </div>

                <!-- Status do Sistema -->
                <div class="stats-grid" style="margin-bottom: 30px;">
                    <div class="stat-card info" style="min-height: 100px; display: flex; flex-direction: column; justify-content: center;">
                        <div class="stat-number" id="ultimo-backup" style="font-size: 16px; margin-bottom: 8px;">Carregando...</div>
                        <div class="stat-label">Último Backup</div>
                    </div>
                    
                    <div class="stat-card success" style="min-height: 100px; display: flex; flex-direction: column; justify-content: center;">
                        <div class="stat-number" id="backups-mes" style="margin-bottom: 8px;">0</div>
                        <div class="stat-label">Backups este Mês</div>
                    </div>
                    
                    <div class="stat-card warning" style="min-height: 100px; display: flex; flex-direction: column; justify-content: center;">
                        <div class="stat-number" id="tamanho-backups" style="margin-bottom: 8px;">0 MB</div>
                        <div class="stat-label">Espaço Usado</div>
                    </div>
                    
                    <div class="stat-card primary" style="min-height: 100px; display: flex; flex-direction: column; justify-content: center;">
                        <div class="stat-number" id="status-sistema" style="margin-bottom: 8px;">🟢 Online</div>
                        <div class="stat-label">Status Sistema</div>
                    </div>
                </div>

                <!-- Ações de Backup -->
                <div class="charts-grid">
                    <div class="chart-card backup-manual-card">
                        <h3 class="chart-title"><i data-lucide="database"></i> Backup Manual</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Execute backups manuais quando necessário</p>
                        
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <button onclick="executarBackup('database')" class="btn-backup btn-backup-primary" id="btn-backup-db">
                                <i data-lucide="database"></i> Backup do Banco de Dados
                            </button>
                            
                            <button onclick="executarBackup('arquivos')" class="btn-backup btn-backup-secondary" id="btn-backup-files">
                                <i data-lucide="folder"></i> Backup dos Arquivos
                            </button>
                            
                            <small style="color: #7f8c8d; margin-top: 10px; text-align: center;">
                                ⚡ Otimizado para XAMPP - Backup rápido e confiável
                            </small>
                        </div>
                        
                        <div id="backup-status" style="margin-top: 20px; padding: 20px; border-radius: 12px; background: #f8f9fa; border: 1px solid #e9ecef; display: none;">
                            <div style="margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span style="font-weight: 600; color: #2c3e50; font-size: 14px;">Progresso do Backup</span>
                                    <span id="backup-percentage" style="font-size: 13px; color: #7f8c8d;">0%</span>
                                </div>
                                <div id="backup-progress" style="background: #e5e7eb; border-radius: 6px; height: 12px; overflow: hidden;">
                                    <div id="backup-progress-bar" style="background: linear-gradient(90deg, #3498db 0%, #2980b9 100%); height: 100%; border-radius: 6px; width: 0%; transition: width 0.3s ease;"></div>
                                </div>
                            </div>
                            <div id="backup-message" style="color: #5a6c7d; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                                <i data-lucide="loader-2" style="width: 16px; height: 16px; animation: spin 1s linear infinite;"></i>
                                Preparando backup...
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Histórico de Backups -->
                <div class="table-container" style="margin-top: 30px;">
                    <div class="table-header" style="flex-wrap: wrap; gap: 15px;">
                        <h3 class="table-title" style="margin: 0;">Histórico de Backups</h3>
                        <div class="table-actions" style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <button onclick="atualizarHistoricoBackups()" class="btn-secondary" style="white-space: nowrap;">
                                <i data-lucide="refresh-cw"></i> Atualizar
                            </button>
                            <button onclick="limparBackupsAntigos()" class="btn-warning" style="white-space: nowrap;">
                                <i data-lucide="trash-2"></i> Limpar Antigos
                            </button>
                            <button onclick="gerenciarArquivos()" class="btn-info" style="white-space: nowrap;">
                                <i data-lucide="folder-open"></i> Gerenciar Arquivos
                            </button>
                        </div>
                    </div>
                    
                    <div id="loading-backups" style="text-align: center; padding: 40px; color: #7f8c8d;">
                        <i data-lucide="loader-2" style="width: 32px; height: 32px; animation: spin 1s linear infinite;"></i>
                        <p>Carregando histórico...</p>
                    </div>
                    
                    <div id="tabela-backups" style="display: none; overflow-x: auto;">
                        <table style="min-width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="min-width: 140px; padding: 12px 8px; text-align: left;">Data/Hora</th>
                                    <th style="min-width: 100px; padding: 12px 8px; text-align: left;">Tipo</th>
                                    <th style="min-width: 100px; padding: 12px 8px; text-align: center;">Status</th>
                                    <th style="min-width: 90px; padding: 12px 8px; text-align: right;">Tamanho</th>
                                    <th style="min-width: 80px; padding: 12px 8px; text-align: center;">Tempo</th>
                                    <th style="min-width: 120px; padding: 12px 8px; text-align: center;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-backups">
                                <!-- Dados carregados via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Estado vazio -->
                    <div id="empty-backups" style="display: none; text-align: center; padding: 60px; color: #7f8c8d;">
                        <i data-lucide="database" style="width: 64px; height: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                        <h3 style="margin: 0 0 10px 0;">Nenhum backup encontrado</h3>
                        <p style="margin: 0;">Execute seu primeiro backup para ver o histórico aqui.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Detalhes -->
    <div id="modalDetalhes" class="modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 50px auto; padding: 0; border-radius: 12px; max-width: 900px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div style="padding: 20px; background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0;">
                <h3 style="margin: 0; color: #2c3e50;">Detalhes da Contratação</h3>
                <span onclick="fecharModalDetalhes()" style="font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; transition: color 0.3s;">&times;</span>
            </div>
            <div id="conteudoDetalhes" style="padding: 20px;">
                <!-- Conteúdo será carregado via AJAX -->
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Reversão -->
    <div id="modalReversaoImportacao" class="modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 15% auto; padding: 0; border-radius: 12px; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div style="padding: 20px; background-color: #fff3cd; border-bottom: 1px solid #ffeaa7; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0;">
                <h3 style="margin: 0; color: #856404; display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="alert-triangle" style="color: #f39c12;"></i>
                    Confirmar Reversão
                </h3>
                <span onclick="fecharModalReversao()" style="font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; transition: color 0.3s;">&times;</span>
            </div>
            <div style="padding: 20px;">
                <div style="margin-bottom: 20px;">
                    <p style="margin: 0 0 15px 0; color: #2c3e50; font-weight: 600;">
                        <strong>⚠️ ATENÇÃO:</strong> Esta ação é irreversível!
                    </p>
                    <p style="margin: 0 0 15px 0; color: #7f8c8d;">
                        Você está prestes a reverter a importação do arquivo:
                    </p>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; border-left: 4px solid #e74c3c;">
                        <strong id="nomeArquivoReversao" style="color: #2c3e50;">arquivo.csv</strong>
                    </div>
                    <p style="margin: 15px 0 0 0; color: #e74c3c; font-weight: 600;">
                        🗑️ Todos os dados desta importação serão REMOVIDOS permanentemente do sistema.
                    </p>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModalReversao()" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="button" onclick="executarReversao()" class="btn-danger" style="background: #e74c3c; border-color: #e74c3c;">
                        <i data-lucide="trash-2"></i> Sim, Reverter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- CSS e JS da Paginação -->
    <?php echo PaginationHelper::renderCSS(); ?>
    <?php echo PaginationHelper::renderJS(); ?>

    <script>
        // Configurar dados do PHP para o JavaScript
        window.dashboardData = {
            dados_categoria: <?php echo json_encode($dados_categoria); ?>,
            dados_area: <?php echo json_encode($dados_area); ?>,
            dados_mensal: <?php echo json_encode($dados_mensal_pca); ?>,
            stats: <?php echo json_encode($stats); ?>
        };

        /**
 * Abrir modal de criar licitação
 */
function abrirModalCriarLicitacao() {
    const modal = document.getElementById('modalCriarLicitacao');
    
    // Limpar formulário
    modal.querySelector('form').reset();
    
    // Definir ano atual
    modal.querySelector('input[name="ano"]').value = new Date().getFullYear();
    
    // Mostrar modal
    modal.style.display = 'block';
    
    // Focar no primeiro campo
    setTimeout(() => {
        modal.querySelector('#nup_criar').focus();
    }, 100);
}

// Variável global para armazenar o ID da importação a ser revertida
let importacaoParaReverter = null;

/**
 * Confirmar reversão de importação
 */
function confirmarReversao(importacaoId, nomeArquivo) {
    importacaoParaReverter = importacaoId;
    document.getElementById('nomeArquivoReversao').textContent = nomeArquivo;
    document.getElementById('modalReversaoImportacao').style.display = 'block';
}

/**
 * Fechar modal de reversão
 */
function fecharModalReversao() {
    document.getElementById('modalReversaoImportacao').style.display = 'none';
    importacaoParaReverter = null;
}

/**
 * Executar reversão da importação
 */
function executarReversao() {
    if (!importacaoParaReverter) {
        alert('Erro: ID da importação não encontrado.');
        return;
    }
    
    // Criar formulário para envio
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'process.php';
    
    // Campo de ação
    const acaoInput = document.createElement('input');
    acaoInput.type = 'hidden';
    acaoInput.name = 'acao';
    acaoInput.value = 'reverter_importacao_pca';
    form.appendChild(acaoInput);
    
    // Campo do ID da importação
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'importacao_id';
    idInput.value = importacaoParaReverter;
    form.appendChild(idInput);
    
    // Token CSRF
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?php echo generateCSRFToken(); ?>';
    form.appendChild(csrfInput);
    
    // Adicionar ao DOM e enviar
    document.body.appendChild(form);
    form.submit();
}

// Fechar modal ao clicar fora dele
window.onclick = function(event) {
    const modalReversao = document.getElementById('modalReversaoImportacao');
    if (event.target === modalReversao) {
        fecharModalReversao();
    }
}
    </script>
    <script src="assets/notifications.js"></script>
    <script src="assets/dashboard.js"></script>
    <script src="assets/ux-improvements.js"></script>
    <script src="assets/mobile-improvements.js"></script>
</body>
</html>