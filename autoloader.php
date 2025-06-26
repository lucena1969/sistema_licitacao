<?php
/**
 * Autoloader - Sistema de Licitações CGLIC
 * Carregamento automático de classes do sistema
 */

spl_autoload_register(function ($class) {
    $directories = ['controllers', 'models', 'services', 'config'];
    
    foreach ($directories as $dir) {
        $file = __DIR__ . "/$dir/$class.php";
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    return false;
});

// Pré-carregar classes essenciais na ordem correta
$essential_classes = [
    'models/Database.php',
    'models/Licitacao.php', 
    'controllers/BaseController.php',
    'services/DashboardService.php',
    'controllers/DashboardController.php'
];

foreach ($essential_classes as $class_file) {
    $full_path = __DIR__ . '/' . $class_file;
    if (file_exists($full_path)) {
        require_once $full_path;
    }
}

// Carregar configurações se existir
if (file_exists(__DIR__ . '/config/app.php')) {
    require_once __DIR__ . '/config/app.php';
}

// Carregar functions.php se existir (compatibilidade)
if (file_exists(__DIR__ . '/functions.php')) {
    require_once __DIR__ . '/functions.php';
}

// Inicializar sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>