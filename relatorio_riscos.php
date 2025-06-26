<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();
$riscos = [];
$dfds_disponiveis = [];
$erro_tabela = false;

// Mês/ano do relatório (padrão: mês atual)
$mes_relatorio = $_GET['mes'] ?? date('Y-m');
$mes_display = DateTime::createFromFormat('Y-m', $mes_relatorio)->format('m/Y');

try {
    // Verificar se a tabela existe
    $tables = $pdo->query("SHOW TABLES LIKE 'pca_riscos'")->fetchAll();
    
    if (empty($tables)) {
        // Criar tabela se não existe
        $sql_create = "CREATE TABLE `pca_riscos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `numero_dfd` varchar(50) NOT NULL,
            `mes_relatorio` varchar(7) NOT NULL,
            `nivel_risco` enum('baixo','medio','alto','extremo') NOT NULL,
            `categoria_risco` varchar(100) NOT NULL,
            `descricao_risco` text NOT NULL,
            `impacto` text DEFAULT NULL,
            `probabilidade` varchar(50) DEFAULT NULL,
            `acao_mitigacao` text DEFAULT NULL,
            `responsavel` varchar(100) DEFAULT NULL,
            `prazo_mitigacao` date DEFAULT NULL,
            `status_acao` enum('pendente','em_andamento','concluida','cancelada') DEFAULT 'pendente',
            `observacoes` text DEFAULT NULL,
            `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
            `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `criado_por` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $pdo->exec($sql_create);
    }
    
    // Buscar riscos do mês (com correção de collation)
    $sql_riscos = "SELECT r.*, p.titulo_contratacao, p.area_requisitante, p.valor_total_contratacao,
                   CASE 
                       WHEN r.nivel_risco = 'extremo' THEN 4
                       WHEN r.nivel_risco = 'alto' THEN 3
                       WHEN r.nivel_risco = 'medio' THEN 2
                       ELSE 1
                   END as ordem_risco,
                   -- Extrair campos do descrição_risco para compatibilidade
                   SUBSTRING_INDEX(SUBSTRING_INDEX(r.descricao_risco, 'EVENTO: ', -1), '\n', 1) as evento_risco,
                   SUBSTRING_INDEX(SUBSTRING_INDEX(r.descricao_risco, 'CAUSA: ', -1), '\n', 1) as causa_risco,
                   SUBSTRING_INDEX(SUBSTRING_INDEX(r.descricao_risco, 'CONSEQUÊNCIA: ', -1), '\n', 1) as consequencia_risco,
                   -- Usar impacto como demanda
                   r.impacto as demanda,
                   -- Separar probabilidade e impacto
                   SUBSTRING_INDEX(r.probabilidade, 'x', 1) as prob_valor,
                   SUBSTRING_INDEX(r.probabilidade, 'x', -1) as imp_valor,
                   -- Separar ações preventivas e contingência
                   SUBSTRING_INDEX(SUBSTRING_INDEX(r.acao_mitigacao, 'PREVENTIVA: ', -1), '\nCONTINGÊNCIA:', 1) as acao_preventiva,
                   SUBSTRING_INDEX(r.acao_mitigacao, 'CONTINGÊNCIA: ', -1) as acao_contingencia,
                   r.responsavel as responsavel_preventiva,
                   r.responsavel as responsavel_contingencia
                   FROM pca_riscos r
                   LEFT JOIN pca_dados p ON r.numero_dfd COLLATE utf8mb4_unicode_ci = p.numero_dfd COLLATE utf8mb4_unicode_ci
                   WHERE r.mes_relatorio = ?
                   ORDER BY ordem_risco DESC, r.numero_dfd";
    $stmt = $pdo->prepare($sql_riscos);
    $stmt->execute([$mes_relatorio]);
    $riscos = $stmt->fetchAll();
    
} catch (Exception $e) {
    $erro_tabela = true;
    $riscos = [];
}

// Estatísticas
$stats = [
    'total' => count($riscos),
    'extremo' => 0,
    'alto' => 0,
    'medio' => 0,
    'baixo' => 0
];

// Contar por nível de risco
foreach ($riscos as $risco) {
    if (isset($risco['nivel_risco'])) {
        $stats[$risco['nivel_risco']]++;
    }
}

// Buscar DFDs não concluídos para o formulário
try {
    $sql_dfds = "SELECT DISTINCT numero_dfd, titulo_contratacao 
                 FROM pca_dados 
                 WHERE (situacao_execucao IS NULL OR situacao_execucao != 'Concluído')
                 AND numero_dfd IS NOT NULL AND numero_dfd != ''
                 ORDER BY numero_dfd";
    $dfds_disponiveis = $pdo->query($sql_dfds)->fetchAll();
} catch (Exception $e) {
    $dfds_disponiveis = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Gestão de Riscos - PCA</title>
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

        /* Header */
        .page-header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 35px;
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
        }

        .header-content {
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

        /* Cards de estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-card.extremo .stat-icon { background: #fee2e2; color: #dc2626; }
        .stat-card.alto .stat-icon { background: #fed7aa; color: #ea580c; }
        .stat-card.medio .stat-icon { background: #fef3c7; color: #d97706; }
        .stat-card.baixo .stat-icon { background: #d1fae5; color: #059669; }
        .stat-card.total .stat-icon { background: #e0e7ff; color: #4f46e5; }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }

        .stat-label {
            font-size: 14px;
            color: #6b7280;
            margin: 5px 0 0 0;
        }

        /* Controles */
        .controls-section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .month-selector {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .month-selector input[type="month"] {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
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

        .btn-primary:hover {
            background: linear-gradient(135deg, #4f46e5, #4338ca);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        /* Matriz de Riscos */
        .matriz-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .matriz-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f3f4f6;
        }

        .matriz-title {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .risk-matrix {
            display: grid;
            grid-template-columns: 80px repeat(5, 1fr);
            grid-template-rows: repeat(6, 60px);
            gap: 2px;
            margin: 20px auto;
            max-width: 600px;
        }

        .matrix-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            border-radius: 4px;
        }

        .matrix-header-cell {
            background: #f3f4f6;
            color: #374151;
        }

        .risk-cell {
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .risk-cell:hover {
            transform: scale(1.05);
            z-index: 10;
        }

        .risk-baixo { background: #86efac; color: #166534; }
        .risk-medio { background: #fde047; color: #713f12; }
        .risk-alto { background: #fdba74; color: #7c2d12; }
        .risk-extremo { background: #fca5a5; color: #7f1d1d; }

        .risk-count {
            position: absolute;
            top: 2px;
            right: 2px;
            background: rgba(0,0,0,0.2);
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
        }

        /* Lista de Riscos */
        .risks-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .risks-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .risks-table th {
            background: #f9fafb;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e5e7eb;
        }

        .risks-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        .risks-table tbody tr:hover {
            background: #f9fafb;
        }

        .risk-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }

        .risk-badge.extremo { background: #fee2e2; color: #dc2626; }
        .risk-badge.alto { background: #fed7aa; color: #ea580c; }
        .risk-badge.medio { background: #fef3c7; color: #d97706; }
        .risk-badge.baixo { background: #d1fae5; color: #059669; }

        .dfd-number {
            font-weight: 700;
            color: #1f2937;
        }

        .risk-description {
            font-size: 13px;
            color: #4b5563;
            margin: 4px 0;
        }

        .action-text {
            font-size: 13px;
            color: #6b7280;
        }

        .responsible-badge {
            background: #e0e7ff;
            color: #4338ca;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 0;
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        }

        .modal-header {
            padding: 25px 30px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 16px 16px 0 0;
        }

        .modal-body {
            padding: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #6366f1;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-full {
            grid-column: 1 / -1;
        }

        .probability-impact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .scale-selector {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .scale-option {
            flex: 1;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .scale-option:hover {
            border-color: #6366f1;
            background: #f3f4f6;
        }

        .scale-option.selected {
            border-color: #6366f1;
            background: #e0e7ff;
            color: #4338ca;
            font-weight: 600;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            color: #9ca3af;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover {
            color: #374151;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .page-container {
                padding: 20px;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .controls-section {
                flex-direction: column;
            }

            .risk-matrix {
                font-size: 12px;
                grid-template-columns: 60px repeat(5, 1fr);
            }

            .modal-content {
                margin: 20px;
                width: calc(100% - 40px);
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1><i data-lucide="shield-alert"></i> Relatório de Gestão de Riscos</h1>
                    <p>Plano de Contratações Anual - <?php echo $mes_display; ?></p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn-voltar">
                        <i data-lucide="arrow-left"></i> Voltar ao Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i data-lucide="alert-circle"></i>
                </div>
                <h3 class="stat-value"><?php echo $stats['total']; ?></h3>
                <p class="stat-label">Total de Riscos</p>
            </div>

            <div class="stat-card extremo">
                <div class="stat-icon">
                    <i data-lucide="alert-octagon"></i>
                </div>
                <h3 class="stat-value"><?php echo $stats['extremo']; ?></h3>
                <p class="stat-label">Risco Extremo</p>
            </div>

            <div class="stat-card alto">
                <div class="stat-icon">
                    <i data-lucide="alert-triangle"></i>
                </div>
                <h3 class="stat-value"><?php echo $stats['alto']; ?></h3>
                <p class="stat-label">Risco Alto</p>
            </div>

            <div class="stat-card medio">
                <div class="stat-icon">
                    <i data-lucide="info"></i>
                </div>
                <h3 class="stat-value"><?php echo $stats['medio']; ?></h3>
                <p class="stat-label">Risco Médio</p>
            </div>

            <div class="stat-card baixo">
                <div class="stat-icon">
                    <i data-lucide="check-circle"></i>
                </div>
                <h3 class="stat-value"><?php echo $stats['baixo']; ?></h3>
                <p class="stat-label">Risco Baixo</p>
            </div>
        </div>

        <!-- Controles -->
        <div class="controls-section">
            <div class="month-selector">
                <label for="mes_relatorio">Mês de Referência:</label>
                <input type="month" id="mes_relatorio" value="<?php echo $mes_relatorio; ?>" 
                       onchange="window.location.href='?mes=' + this.value">
            </div>
            <div style="display: flex; gap: 15px;">
                <button onclick="abrirModalRisco()" class="btn-primary">
                    <i data-lucide="plus"></i> Adicionar Risco
                </button>
                <button onclick="exportarRelatorio()" class="btn-primary" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i data-lucide="download"></i> Exportar PDF
                </button>
            </div>
        </div>

        <!-- Matriz de Riscos -->
        <div class="matriz-section">
            <div class="matriz-header">
                <h2 class="matriz-title">
                    <i data-lucide="grid-3x3"></i> Matriz de Riscos
                </h2>
            </div>

            <div class="risk-matrix">
                <!-- Cabeçalho vazio -->
                <div class="matrix-cell"></div>
                
                <!-- Cabeçalho de probabilidade -->
                <div class="matrix-cell matrix-header-cell">Muito baixo<br>(1)</div>
                <div class="matrix-cell matrix-header-cell">Baixo<br>(2)</div>
                <div class="matrix-cell matrix-header-cell">Médio<br>(3)</div>
                <div class="matrix-cell matrix-header-cell">Alto<br>(4)</div>
                <div class="matrix-cell matrix-header-cell">Muito Alto<br>(5)</div>
                
                <?php
                // Função para contar riscos por probabilidade e impacto
                function contarRiscosPorProbImp($riscos, $prob, $imp) {
                    $count = 0;
                    foreach ($riscos as $risco) {
                        // Extrair probabilidade e impacto do campo probabilidade (formato "3x4")
                        if (strpos($risco['probabilidade'], 'x') !== false) {
                            $parts = explode('x', $risco['probabilidade']);
                            $risco_prob = (int)trim($parts[0]);
                            $risco_imp = (int)trim($parts[1]);
                            if ($risco_prob == $prob && $risco_imp == $imp) {
                                $count++;
                            }
                        }
                    }
                    return $count;
                }
                
                // Definir cores por nível de risco baseado em probabilidade x impacto
                function getNivelRisco($prob, $imp) {
                    $produto = $prob * $imp;
                    if ($produto <= 6) return 'risk-baixo';
                    if ($produto <= 12) return 'risk-medio';
                    if ($produto <= 20) return 'risk-alto';
                    return 'risk-extremo';
                }
                
                // Gerar matriz 5x5 (impacto x probabilidade)
                for ($impacto = 5; $impacto >= 1; $impacto--):
                    $impacto_label = ['', 'Muito baixo', 'Baixo', 'Médio', 'Alto', 'Muito alto'];
                ?>
                <!-- Linha <?php echo $impacto; ?> - <?php echo $impacto_label[$impacto]; ?> -->
                <div class="matrix-cell matrix-header-cell" style="writing-mode: vertical-lr;"><?php echo $impacto_label[$impacto]; ?> (<?php echo $impacto; ?>)</div>
                <?php for ($probabilidade = 1; $probabilidade <= 5; $probabilidade++): 
                    $count = contarRiscosPorProbImp($riscos, $probabilidade, $impacto);
                    $nivelClass = getNivelRisco($probabilidade, $impacto);
                ?>
                <div class="matrix-cell risk-cell <?php echo $nivelClass; ?>" data-prob="<?php echo $probabilidade; ?>" data-imp="<?php echo $impacto; ?>">
                    <?php if ($count > 0): ?>
                        <span class="risk-count"><?php echo $count; ?></span>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
                <?php endfor; ?>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #86efac; border-radius: 4px;"></div>
                    <span style="font-size: 14px;">Nível de risco baixo</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #fde047; border-radius: 4px;"></div>
                    <span style="font-size: 14px;">Nível de risco médio</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #fdba74; border-radius: 4px;"></div>
                    <span style="font-size: 14px;">Nível de risco alto</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #fca5a5; border-radius: 4px;"></div>
                    <span style="font-size: 14px;">Nível de risco extremo</span>
                </div>
            </div>

            <div class="risk-matrix" style="display: none;">
                <!-- Cabeçalho vazio -->
                <div class="matrix-cell"></div>
                
                <!-- Cabeçalho de probabilidade -->
                <div class="matrix-cell matrix-header-cell">Muito baixo<br>(1)</div>
                <div class="matrix-cell matrix-header-cell">Baixo<br>(2)</div>
                <div class="matrix-cell matrix-header-cell">Médio<br>(3)</div>
                <div class="matrix-cell matrix-header-cell">Alto<br>(4)</div>
                <div class="matrix-cell matrix-header-cell">Muito Alto<br>(5)</div>
                
                <!-- Linha 5 - Muito Alto -->
                <div class="matrix-cell matrix-header-cell" style="writing-mode: vertical-lr;">Muito alto (5)</div>
                <div class="matrix-cell risk-cell risk-medio" data-prob="1" data-imp="5">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 1 && $r['impacto'] == 5));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-alto" data-prob="2" data-imp="5">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 2 && $r['impacto'] == 5));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-alto" data-prob="3" data-imp="5">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 3 && $r['impacto'] == 5));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-extremo" data-prob="4" data-imp="5">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 4 && $r['impacto'] == 5));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-extremo" data-prob="5" data-imp="5">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 5 && $r['impacto'] == 5));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                
                <!-- Linha 4 - Alto -->
                <div class="matrix-cell matrix-header-cell" style="writing-mode: vertical-lr;">Alto (4)</div>
                <div class="matrix-cell risk-cell risk-baixo" data-prob="1" data-imp="4">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 1 && $r['impacto'] == 4));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-medio" data-prob="2" data-imp="4">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 2 && $r['impacto'] == 4));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-alto" data-prob="3" data-imp="4">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 3 && $r['impacto'] == 4));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-alto" data-prob="4" data-imp="4">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 4 && $r['impacto'] == 4));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-extremo" data-prob="5" data-imp="4">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 5 && $r['impacto'] == 4));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                
                <!-- Linha 3 - Médio -->
                <div class="matrix-cell matrix-header-cell" style="writing-mode: vertical-lr;">Médio (3)</div>
                <div class="matrix-cell risk-cell risk-baixo" data-prob="1" data-imp="3">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 1 && $r['impacto'] == 3));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-medio" data-prob="2" data-imp="3">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 2 && $r['impacto'] == 3));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-medio" data-prob="3" data-imp="3">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 3 && $r['impacto'] == 3));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-alto" data-prob="4" data-imp="3">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 4 && $r['impacto'] == 3));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-alto" data-prob="5" data-imp="3">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 5 && $r['impacto'] == 3));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                
                <!-- Linha 2 - Baixo -->
                <div class="matrix-cell matrix-header-cell" style="writing-mode: vertical-lr;">Baixo (2)</div>
                <div class="matrix-cell risk-cell risk-baixo" data-prob="1" data-imp="2">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 1 && $r['impacto'] == 2));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-baixo" data-prob="2" data-imp="2">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 2 && $r['impacto'] == 2));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-medio" data-prob="3" data-imp="2">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 3 && $r['impacto'] == 2));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-medio" data-prob="4" data-imp="2">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 4 && $r['impacto'] == 2));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-alto" data-prob="5" data-imp="2">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 5 && $r['impacto'] == 2));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                
                <!-- Linha 1 - Muito Baixo -->
                <div class="matrix-cell matrix-header-cell" style="writing-mode: vertical-lr;">Muito baixo (1)</div>
                <div class="matrix-cell risk-cell risk-baixo" data-prob="1" data-imp="1">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 1 && $r['impacto'] == 1));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-baixo" data-prob="2" data-imp="1">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 2 && $r['impacto'] == 1));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-baixo" data-prob="3" data-imp="1">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 3 && $r['impacto'] == 1));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-medio" data-prob="4" data-imp="1">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 4 && $r['impacto'] == 1));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
                <div class="matrix-cell risk-cell risk-medio" data-prob="5" data-imp="1">
                    <?php 
                    $count = count(array_filter($riscos, fn($r) => $r['probabilidade'] == 5 && $r['impacto'] == 1));
                    if ($count > 0) echo '<span class="risk-count">' . $count . '</span>';
                    ?>
                </div>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #86efac; border-radius: 4px;"></div>
                    <span style="font-size: 14px;">Nível de risco baixo</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #fde047; border-radius: 4px;"></div>
                    <span style="font-size: 14px;">Nível de risco médio</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #fdba74; border-radius: 4px;"></div>
                    <span style="font-size: 14px;">Nível de risco alto</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #fca5a5; border-radius: 4px;"></div>
                    <span style="font-size: 14px;">Nível de risco extremo</span>
                </div>
            </div>
        </div>

        <!-- Lista de Riscos -->
        <div class="risks-section">
            <div class="matriz-header">
                <h2 class="matriz-title">
                    <i data-lucide="list-checks"></i> Mapa de Riscos Detalhado
                </h2>
            </div>

            <?php if (empty($riscos)): ?>
                <div style="text-align: center; padding: 60px; color: #6b7280;">
                    <i data-lucide="shield-check" style="width: 64px; height: 64px; margin-bottom: 20px;"></i>
                    <h3 style="margin: 0 0 10px 0;">Nenhum risco cadastrado</h3>
                    <p style="margin: 0;">Clique em "Adicionar Risco" para começar.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="risks-table">
                        <thead>
                            <tr>
                                <th>DFD / Demanda</th>
                                <th>Evento de Risco</th>
                                <th>Causa</th>
                                <th>Consequência</th>
                                <th style="text-align: center;">P x I</th>
                                <th style="text-align: center;">Nível</th>
                                <th>Ações Preventivas</th>
                                <th>Ações de Contingência</th>
                                <th style="width: 100px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riscos as $risco): ?>
                            <tr>
                                <td>
                                    <div class="dfd-number"><?php echo htmlspecialchars($risco['numero_dfd']); ?></div>
                                    <div class="risk-description"><?php echo htmlspecialchars($risco['demanda']); ?></div>
                                </td>
                                <td>
                                    <div class="risk-description"><?php echo htmlspecialchars($risco['evento_risco']); ?></div>
                                </td>
                                <td>
                                    <div class="risk-description"><?php echo htmlspecialchars($risco['causa_risco']); ?></div>
                                </td>
                                <td>
                                    <div class="risk-description"><?php echo htmlspecialchars($risco['consequencia_risco']); ?></div>
                                </td>
                                <td style="text-align: center;">
                                    <strong><?php echo $risco['probabilidade']; ?></strong>
                                </td>
                                <td style="text-align: center;">
                                    <span class="risk-badge <?php echo $risco['nivel_risco']; ?>">
                                        <?php echo ucfirst($risco['nivel_risco']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-text"><?php echo htmlspecialchars($risco['acao_preventiva']); ?></div>
                                    <?php if ($risco['responsavel_preventiva']): ?>
                                        <span class="responsible-badge"><?php echo htmlspecialchars($risco['responsavel_preventiva']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-text"><?php echo htmlspecialchars($risco['acao_contingencia']); ?></div>
                                    <?php if ($risco['responsavel_contingencia']): ?>
                                        <span class="responsible-badge"><?php echo htmlspecialchars($risco['responsavel_contingencia']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button onclick="editarRisco(<?php echo $risco['id']; ?>)" 
                                                title="Editar" 
                                                style="background: #6366f1; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
                                            <i data-lucide="edit" style="width: 14px; height: 14px;"></i>
                                        </button>
                                        <button onclick="excluirRisco(<?php echo $risco['id']; ?>)" 
                                                title="Excluir" 
                                                style="background: #ef4444; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
                                            <i data-lucide="trash" style="width: 14px; height: 14px;"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Cadastro/Edição -->
    <div id="modalRisco" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0; color: #1f2937; font-size: 20px;">
                    <i data-lucide="shield-alert"></i> <span id="modalTitle">Adicionar Risco</span>
                </h3>
                <span class="close" onclick="fecharModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="formRisco">
                    <input type="hidden" id="risco_id" name="risco_id">
                    <input type="hidden" name="mes_relatorio" value="<?php echo $mes_relatorio; ?>">

                    <div class="form-group form-full">
                        <label>DFD / Contratação *</label>
                        <select name="numero_dfd" id="numero_dfd" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($dfds_disponiveis as $dfd): ?>
                                <option value="<?php echo htmlspecialchars($dfd['numero_dfd']); ?>">
                                    <?php echo htmlspecialchars($dfd['numero_dfd'] . ' - ' . $dfd['titulo_contratacao']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group form-full">
                        <label>Demanda *</label>
                        <textarea name="demanda" id="demanda" required 
                                  placeholder="Ex: LOCAÇÃO DE VEÍCULOS - LEVES / PESADOS / COM MOTORISTA - 25089. R$ 40.000,00"></textarea>
                    </div>

                    <div class="form-group form-full">
                        <label>Evento de Risco *</label>
                        <textarea name="evento_risco" id="evento_risco" required 
                                  placeholder="Descreva o evento ou situação que pode impactar negativamente a contratação"></textarea>
                    </div>

                    <div class="form-group form-full">
                        <label>Causa do Risco *</label>
                        <textarea name="causa_risco" id="causa_risco" required 
                                  placeholder="Descreva a(s) causa(s) do evento de risco"></textarea>
                    </div>

                    <div class="form-group form-full">
                        <label>Consequência do Risco *</label>
                        <textarea name="consequencia_risco" id="consequencia_risco" required 
                                  placeholder="Descreva a(s) possível(is) consequência(s) do evento de risco"></textarea>
                    </div>

                    <div class="probability-impact-grid">
                        <div class="form-group">
                            <label>Probabilidade *</label>
                            <div class="scale-selector">
                                <div class="scale-option" onclick="selectProbability(1)">
                                    <strong>1</strong><br>
                                    <small>Muito baixa</small>
                                </div>
                                <div class="scale-option" onclick="selectProbability(2)">
                                    <strong>2</strong><br>
                                    <small>Baixa</small>
                                </div>
                                <div class="scale-option" onclick="selectProbability(3)">
                                    <strong>3</strong><br>
                                    <small>Média</small>
                                </div>
                                <div class="scale-option" onclick="selectProbability(4)">
                                    <strong>4</strong><br>
                                    <small>Alta</small>
                                </div>
                                <div class="scale-option" onclick="selectProbability(5)">
                                    <strong>5</strong><br>
                                    <small>Muito alta</small>
                                </div>
                            </div>
                            <input type="hidden" name="probabilidade" id="probabilidade" required>
                        </div>

                        <div class="form-group">
                            <label>Impacto *</label>
                            <div class="scale-selector">
                                <div class="scale-option" onclick="selectImpact(1)">
                                    <strong>1</strong><br>
                                    <small>Muito baixo</small>
                                </div>
                                <div class="scale-option" onclick="selectImpact(2)">
                                    <strong>2</strong><br>
                                    <small>Baixo</small>
                                </div>
                                <div class="scale-option" onclick="selectImpact(3)">
                                    <strong>3</strong><br>
                                    <small>Médio</small>
                                </div>
                                <div class="scale-option" onclick="selectImpact(4)">
                                    <strong>4</strong><br>
                                    <small>Alto</small>
                                </div>
                                <div class="scale-option" onclick="selectImpact(5)">
                                    <strong>5</strong><br>
                                    <small>Muito alto</small>
                                </div>
                            </div>
                            <input type="hidden" name="impacto" id="impacto" required>
                        </div>
                    </div>

                    <div class="form-group form-full">
                        <label>Ações Preventivas</label>
                        <textarea name="acao_preventiva" id="acao_preventiva" 
                                  placeholder="Indique as ações para neutralizar ou minimizar a probabilidade de ocorrência do risco"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Responsável pelas Ações Preventivas</label>
                        <input type="text" name="responsavel_preventiva" id="responsavel_preventiva" 
                               placeholder="Nome do setor ou servidor">
                    </div>

                    <div class="form-group form-full">
                        <label>Ações de Contingência</label>
                        <textarea name="acao_contingencia" id="acao_contingencia" 
                                  placeholder="Indique as ações caso o risco se efetive"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Responsável pelas Ações de Contingência</label>
                        <input type="text" name="responsavel_contingencia" id="responsavel_contingencia" 
                               placeholder="Nome do setor ou servidor">
                    </div>

                    <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                        <button type="button" onclick="fecharModal()" 
                                style="padding: 12px 24px; background: #e5e7eb; color: #374151; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-primary">
                            <i data-lucide="save"></i> Salvar Risco
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Variáveis globais
        let selectedProbability = 0;
        let selectedImpact = 0;

        // Seleção de probabilidade
        function selectProbability(value) {
            selectedProbability = value;
            document.getElementById('probabilidade').value = value;
            
            // Atualizar visual
            document.querySelectorAll('.probability-impact-grid .form-group:first-child .scale-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.target.closest('.scale-option').classList.add('selected');
        }

        // Seleção de impacto
        function selectImpact(value) {
            selectedImpact = value;
            document.getElementById('impacto').value = value;
            
            // Atualizar visual
            document.querySelectorAll('.probability-impact-grid .form-group:last-child .scale-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.target.closest('.scale-option').classList.add('selected');
        }

        // Abrir modal
        function abrirModalRisco() {
            document.getElementById('modalRisco').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Adicionar Risco';
            document.getElementById('formRisco').reset();
            document.getElementById('risco_id').value = '';
            selectedProbability = 0;
            selectedImpact = 0;
            
            // Limpar seleções visuais
            document.querySelectorAll('.scale-option').forEach(option => {
                option.classList.remove('selected');
            });
        }

        // Fechar modal
        function fecharModal() {
            document.getElementById('modalRisco').style.display = 'none';
        }

        // Editar risco
        function editarRisco(id) {
            fetch('api/process_risco.php?acao=buscar&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const risco = data.data;
                        
                        // Abrir modal
                        document.getElementById('modalRisco').style.display = 'block';
                        document.getElementById('modalTitle').textContent = 'Editar Risco';
                        document.getElementById('risco_id').value = risco.id;
                        
                        // Preencher campos
                        document.getElementById('numero_dfd').value = risco.numero_dfd;
                        document.getElementById('demanda').value = risco.impacto || risco.demanda || '';
                        
                        // Extrair campos da descrição_risco se disponível
                        if (risco.descricao_risco) {
                            const desc = risco.descricao_risco;
                            const eventoMatch = desc.match(/EVENTO:\s*([^\n]*)/);
                            const causaMatch = desc.match(/CAUSA:\s*([^\n]*)/);
                            const conseqMatch = desc.match(/CONSEQUÊNCIA:\s*([^\n]*)/);
                            
                            document.getElementById('evento_risco').value = eventoMatch ? eventoMatch[1] : '';
                            document.getElementById('causa_risco').value = causaMatch ? causaMatch[1] : '';
                            document.getElementById('consequencia_risco').value = conseqMatch ? conseqMatch[1] : '';
                        } else {
                            document.getElementById('evento_risco').value = risco.evento_risco || '';
                            document.getElementById('causa_risco').value = risco.causa_risco || '';
                            document.getElementById('consequencia_risco').value = risco.consequencia_risco || '';
                        }
                        
                        // Extrair ações da acao_mitigacao se disponível
                        if (risco.acao_mitigacao) {
                            const acoes = risco.acao_mitigacao;
                            const prevMatch = acoes.match(/PREVENTIVA:\s*([^\n]*)/);
                            const contMatch = acoes.match(/CONTINGÊNCIA:\s*([^\n]*)/);
                            
                            document.getElementById('acao_preventiva').value = prevMatch ? prevMatch[1] : '';
                            document.getElementById('acao_contingencia').value = contMatch ? contMatch[1] : '';
                        } else {
                            document.getElementById('acao_preventiva').value = risco.acao_preventiva || '';
                            document.getElementById('acao_contingencia').value = risco.acao_contingencia || '';
                        }
                        
                        document.getElementById('responsavel_preventiva').value = risco.responsavel || '';
                        document.getElementById('responsavel_contingencia').value = risco.responsavel || '';
                        
                        // Extrair probabilidade e impacto do campo probabilidade (formato "3x4")
                        if (risco.probabilidade && risco.probabilidade.includes('x')) {
                            const parts = risco.probabilidade.split('x');
                            selectedProbability = parseInt(parts[0]);
                            selectedImpact = parseInt(parts[1]);
                        } else {
                            selectedProbability = parseInt(risco.prob_valor) || 1;
                            selectedImpact = parseInt(risco.imp_valor) || 1;
                        }
                        
                        document.getElementById('probabilidade').value = selectedProbability;
                        document.getElementById('impacto').value = selectedImpact;
                        
                        // Atualizar visual da seleção
                        if (selectedProbability >= 1 && selectedProbability <= 5) {
                            document.querySelectorAll('.probability-impact-grid .form-group:first-child .scale-option')[selectedProbability - 1].classList.add('selected');
                        }
                        if (selectedImpact >= 1 && selectedImpact <= 5) {
                            document.querySelectorAll('.probability-impact-grid .form-group:last-child .scale-option')[selectedImpact - 1].classList.add('selected');
                        }
                    } else {
                        alert('Erro ao carregar dados do risco');
                    }
                })
                .catch(error => {
                    alert('Erro ao buscar risco: ' + error);
                });
        }

        // Excluir risco
        function excluirRisco(id) {
            if (confirm('Tem certeza que deseja excluir este risco?')) {
                window.location.href = 'api/process_risco.php?acao=excluir&id=' + id;
            }
        }

        // Exportar relatório
        function exportarRelatorio() {
            const mes = document.getElementById('mes_relatorio').value;
            
            // Verificar se quer PDF ou HTML
            if (confirm('Deseja exportar em PDF?\n\nOK = PDF\nCancelar = HTML')) {
                window.open('relatorios/exportar_relatorio_riscos_pdf.php?mes=' + mes, '_blank');
            } else {
                window.open('relatorios/exportar_relatorio_riscos.php?mes=' + mes, '_blank');
            }
        }

        // Submeter formulário
        document.getElementById('formRisco').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validar se probabilidade e impacto foram selecionados
            if (!selectedProbability || !selectedImpact) {
                alert('Por favor, selecione a probabilidade e o impacto do risco.');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('acao', document.getElementById('risco_id').value ? 'editar' : 'adicionar');
            
            fetch('api/process_risco.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Erro ao salvar risco');
                }
            })
            .catch(error => {
                alert('Erro ao processar requisição: ' + error);
            });
        });

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target == document.getElementById('modalRisco')) {
                fecharModal();
            }
        }

        // Inicializar Lucide icons
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        // Click nas células da matriz para mostrar detalhes
        document.querySelectorAll('.risk-cell').forEach(cell => {
            cell.addEventListener('click', function() {
                const prob = this.getAttribute('data-prob');
                const imp = this.getAttribute('data-imp');
                const count = this.querySelector('.risk-count');
                
                if (count) {
                    const quantidade = count.textContent;
                    const nivel = getNivelRiscoJS(parseInt(prob), parseInt(imp));
                    
                    alert(`Riscos ${nivel}:\nProbabilidade: ${prob}\nImpacto: ${imp}\nQuantidade: ${quantidade} risco(s)`);
                } else {
                    alert(`Nenhum risco cadastrado para:\nProbabilidade: ${prob}\nImpacto: ${imp}`);
                }
            });
        });
        
        // Função JavaScript para calcular nível de risco
        function getNivelRiscoJS(prob, imp) {
            const produto = prob * imp;
            if (produto <= 6) return 'BAIXO';
            if (produto <= 12) return 'MÉDIO';
            if (produto <= 20) return 'ALTO';
            return 'EXTREMO';
        }
    </script>
</body>
</html>