<?php
/**
 * DashboardController - Sistema de Licitações CGLIC
 * Versão com Layout Moderno Completo
 */

class DashboardController extends BaseController {
    private $dashboardService;
    
    public function __construct() {
        parent::__construct();
        
        if (!class_exists('DashboardService')) {
            require_once __DIR__ . '/../services/DashboardService.php';
        }
        
        $this->dashboardService = new DashboardService();
    }
    
    /**
     * Página principal do dashboard
     */
    public function index() {
        try {
            $dados = $this->prepararDadosDashboard();
            $this->renderDashboardModerno($dados);
        } catch (Exception $e) {
            echo "❌ Erro no dashboard: " . $e->getMessage();
        }
    }
    
    /**
     * Prepara dados do dashboard
     */
    private function prepararDadosDashboard() {
        return [
            'stats' => $this->dashboardService->getMainStatistics(),
            'charts' => $this->dashboardService->getChartsData(),
            'kpis' => $this->dashboardService->getKPIs(),
            'alertas' => $this->dashboardService->getAlertas(),
            'licitacoes_recentes' => $this->dashboardService->getLicitacoesRecentes(),
            'usuario' => $this->user
        ];
    }
    
    /**
     * Renderiza dashboard com layout moderno
     */
    private function renderDashboardModerno($dados) {
        $stats = $dados['stats'];
        $charts = $dados['charts'];
        $kpis = $dados['kpis'];
        $alertas = $dados['alertas'];
        $licitacoes_recentes = $dados['licitacoes_recentes'];
        $user = $dados['usuario'];
        
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Licitações CGLIC - Dashboard</title>
    
    <!-- CSS Moderno -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
            padding: 15px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card.total::before { background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); }
        .stat-card.andamento::before { background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.homologadas::before { background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.fracassadas::before { background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%); }
        
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-card.total .stat-icon { background: rgba(102, 126, 234, 0.1); color: #667eea; }
        .stat-card.andamento .stat-icon { background: rgba(240, 147, 251, 0.1); color: #f093fb; }
        .stat-card.homologadas .stat-icon { background: rgba(79, 172, 254, 0.1); color: #4facfe; }
        .stat-card.fracassadas .stat-icon { background: rgba(67, 233, 123, 0.1); color: #43e97b; }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .stat-change {
            margin-top: 15px;
            font-size: 0.9rem;
            color: #28a745;
        }
        
        .charts-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: #333;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }
        
        .chart-card {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            border: 1px solid #f0f0f0;
        }
        
        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .recent-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .recent-list {
            list-style: none;
        }
        
        .recent-item {
            padding: 20px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        .recent-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-em-andamento { background: #fff3cd; color: #856404; }
        .status-homologado { background: #d4edda; color: #155724; }
        .status-fracassado { background: #f8d7da; color: #721c24; }
        
        .recent-content {
            flex: 1;
        }
        
        .recent-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .recent-meta {
            color: #666;
            font-size: 0.9rem;
        }
        
        .actions-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.2);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .alert {
            background: #fff3cd;
            color: #856404;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .alert-title {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .header {
                padding: 20px;
                text-align: center;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .actions-bar {
                justify-content: center;
            }
        }
    </style>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.min.js"></script>
</head>
<body>
    <div class="dashboard-container">
        
        <!-- Header -->
        <div class="header">
            <h1>🎯 Dashboard de Licitações</h1>
            <p>Sistema de Gestão de Licitações e Contratos - CGLIC</p>
            
            <?php if ($user): ?>
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['nome'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-weight: 600;"><?= htmlspecialchars($user['nome']) ?></div>
                    <div style="color: #666; font-size: 0.9rem;">
                        <?= htmlspecialchars($user['departamento']) ?> • <?= ucfirst($user['tipo']) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Alertas -->
        <?php if (!empty($alertas)): ?>
        <?php foreach ($alertas as $alerta): ?>
        <div class="alert">
            <div class="alert-title"><?= htmlspecialchars($alerta['titulo']) ?></div>
            <div><?= htmlspecialchars($alerta['mensagem']) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Actions Bar -->
        <div class="actions-bar">
            <a href="dashboard.php" class="btn btn-secondary">
                <i data-lucide="arrow-left"></i> Dashboard Original
            </a>
            <button onclick="refreshDashboard()" class="btn btn-primary">
                <i data-lucide="refresh-cw"></i> Atualizar Dados
            </button>
            <button onclick="exportData()" class="btn btn-secondary">
                <i data-lucide="download"></i> Exportar
            </button>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($stats['total_licitacoes']) ?></div>
                        <div class="stat-label">Total de Licitações</div>
                    </div>
                    <div class="stat-icon">📊</div>
                </div>
                <div class="stat-change">📈 Base completa do sistema</div>
            </div>
            
            <div class="stat-card andamento">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= $stats['em_andamento'] ?></div>
                        <div class="stat-label">Em Andamento</div>
                    </div>
                    <div class="stat-icon">⏳</div>
                </div>
                <div class="stat-change">🔄 Processos ativos</div>
            </div>
            
            <div class="stat-card homologadas">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= $stats['homologadas'] ?></div>
                        <div class="stat-label">Homologadas</div>
                    </div>
                    <div class="stat-icon">✅</div>
                </div>
                <div class="stat-change">
                    <?php 
                    $percentual = $stats['total_licitacoes'] > 0 ? 
                        round(($stats['homologadas'] / $stats['total_licitacoes']) * 100, 1) : 0;
                    ?>
                    📈 <?= $percentual ?>% de sucesso
                </div>
            </div>
            
            <div class="stat-card fracassadas">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= $stats['fracassadas'] ?></div>
                        <div class="stat-label">Fracassadas</div>
                    </div>
                    <div class="stat-icon">❌</div>
                </div>
                <div class="stat-change">📊 Processos não efetivados</div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-section">
            <h2 class="section-title">📊 Análise Gráfica</h2>
            <div class="charts-grid">
                
                <div class="chart-card">
                    <h3 class="chart-title">Licitações por Status</h3>
                    <div class="chart-container">
                        <canvas id="chartStatus"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <h3 class="chart-title">Top 5 Pregoeiros</h3>
                    <div class="chart-container">
                        <canvas id="chartPregoeiro"></canvas>
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="recent-section">
            <h2 class="section-title">📋 Atividades Recentes</h2>
            
            <?php if (!empty($licitacoes_recentes)): ?>
            <ul class="recent-list">
                <?php foreach ($licitacoes_recentes as $licitacao): ?>
                <li class="recent-item">
                    <span class="recent-status status-<?= strtolower(str_replace('_', '-', $licitacao['situacao'] ?? 'em-andamento')) ?>">
                        <?= str_replace('_', ' ', $licitacao['situacao'] ?? 'EM ANDAMENTO') ?>
                    </span>
                    <div class="recent-content">
                        <div class="recent-title">
                            Licitação #<?= $licitacao['id'] ?? 'N/A' ?>
                        </div>
                        <div class="recent-meta">
                            <?= htmlspecialchars(substr($licitacao['objeto'] ?? 'Objeto não informado', 0, 80)) ?>...
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 16px;"></i>
                <p>Nenhuma licitação recente encontrada</p>
            </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- JavaScript -->
    <script>
        // Dados do PHP para JavaScript
        window.dashboardData = {
            stats: <?= json_encode($stats) ?>,
            charts: <?= json_encode($charts) ?>
        };
        
        // Inicializar ícones
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
            initCharts();
        });
        
        // Inicializar gráficos
        function initCharts() {
            // Gráfico de Status
            const ctxStatus = document.getElementById('chartStatus').getContext('2d');
            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: ['Em Andamento', 'Homologadas', 'Fracassadas'],
                    datasets: [{
                        data: [
                            <?= $stats['em_andamento'] ?>,
                            <?= $stats['homologadas'] ?>,
                            <?= $stats['fracassadas'] ?>
                        ],
                        backgroundColor: [
                            '#f093fb',
                            '#4facfe', 
                            '#43e97b'
                        ],
                        borderWidth: 0
                    }]
                },
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
            
            // Gráfico de Pregoeiros (se houver dados)
            <?php if (!empty($charts['pregoeiro'])): ?>
            const ctxPregoeiro = document.getElementById('chartPregoeiro').getContext('2d');
            new Chart(ctxPregoeiro, {
                type: 'bar',
                data: {
                    labels: [<?= "'" . implode("','", array_slice(array_column($charts['pregoeiro'], 'pregoeiro'), 0, 5)) . "'" ?>],
                    datasets: [{
                        label: 'Licitações',
                        data: [<?= implode(',', array_slice(array_column($charts['pregoeiro'], 'total'), 0, 5)) ?>],
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderRadius: 8
                    }]
                },
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
            <?php endif; ?>
        }
        
        // Funções de ação
        function refreshDashboard() {
            location.reload();
        }
        
        function exportData() {
            alert('Função de exportação será implementada em breve!');
        }
    </script>
</body>
</html>
        <?php
    }
}
?>