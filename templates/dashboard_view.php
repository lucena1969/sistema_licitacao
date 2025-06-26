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
        <?php include 'templates/dashboard_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <?php echo getMensagem(); ?>

            <!-- Dashboard Section -->
            <div id="dashboard" class="content-section <?php echo $secao_ativa === 'dashboard' ? 'active' : ''; ?>">
                <?php include 'templates/dashboard_main.php'; ?>
            </div>

            <!-- Lista de Contratações Section -->
            <?php if (temPermissao('pca_visualizar')): ?>
            <div id="lista-contratacoes" class="content-section <?php echo $secao_ativa === 'lista-contratacoes' ? 'active' : ''; ?>">
                <?php include 'templates/dashboard_listagem.php'; ?>
            </div>
            <?php endif; ?>

            <!-- Relatórios Section -->
            <?php if (temPermissao('pca_relatorios')): ?>
            <div id="relatorios" class="content-section <?php echo $secao_ativa === 'relatorios' ? 'active' : ''; ?>">
                <?php include 'templates/dashboard_relatorios.php'; ?>
            </div>
            <?php endif; ?>

            <!-- Importar PCA Section -->
            <?php if (temPermissao('pca_importar')): ?>
            <div id="importar-pca" class="content-section <?php echo $secao_ativa === 'importar-pca' ? 'active' : ''; ?>">
                <?php include 'templates/dashboard_importar.php'; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts JavaScript -->
    <?php include 'templates/dashboard_scripts.php'; ?>
</body>
</html>