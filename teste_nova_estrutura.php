<?php
/**
 * Teste da Nova Estrutura
 * Script para verificar se tudo foi criado corretamente
 */

echo "<h1>🧪 Testando Nova Estrutura</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
</style>";

// Verificar autoloader
echo "<div class='section'>";
echo "<h2>1. Verificando Autoloader</h2>";

if (file_exists('autoloader.php')) {
    echo "<span class='success'>✅ autoloader.php encontrado</span><br>";
    
    require_once 'autoloader.php';
    
    if (function_exists('spl_autoload_register')) {
        echo "<span class='success'>✅ Autoloader funcionando</span><br>";
    } else {
        echo "<span class='error'>❌ Problema com autoloader</span><br>";
    }
} else {
    echo "<span class='error'>❌ autoloader.php não encontrado</span><br>";
}
echo "</div>";

// Verificar estrutura de diretórios
echo "<div class='section'>";
echo "<h2>2. Verificando Estrutura de Diretórios</h2>";

$directories = [
    'controllers' => 'Controllers (lógica de controle)',
    'models' => 'Models (dados e regras de negócio)',
    'services' => 'Services (lógica de aplicação)',
    'config' => 'Configurações do sistema',
    'views' => 'Views (apresentação)',
    'views/dashboard' => 'Views do dashboard'
];

foreach ($directories as $dir => $desc) {
    if (is_dir($dir)) {
        echo "<span class='success'>✅ $dir</span> - $desc<br>";
    } else {
        echo "<span class='error'>❌ $dir</span> - $desc (NÃO ENCONTRADO)<br>";
    }
}
echo "</div>";

// Verificar sistema atual
echo "<div class='section'>";
echo "<h2>3. Verificando Sistema Atual</h2>";

$arquivos_criticos = [
    'dashboard.php' => 'Dashboard principal',
    'config.php' => 'Configurações do banco',
    'functions.php' => 'Funções auxiliares'
];

foreach ($arquivos_criticos as $arquivo => $desc) {
    if (file_exists($arquivo)) {
        echo "<span class='success'>✅ $arquivo</span> - $desc (preservado)<br>";
    } else {
        echo "<span class='warning'>⚠️ $arquivo</span> - $desc (não encontrado)<br>";
    }
}
echo "</div>";

// Verificar permissões (se for possível)
echo "<div class='section'>";
echo "<h2>4. Verificando Ambiente</h2>";

echo "<span class='info'>📋 Versão PHP:</span> " . PHP_VERSION . "<br>";
echo "<span class='info'>📋 Sistema:</span> " . PHP_OS . "<br>";

if (extension_loaded('pdo')) {
    echo "<span class='success'>✅ PDO habilitado</span><br>";
} else {
    echo "<span class='error'>❌ PDO não habilitado</span><br>";
}

if (extension_loaded('pdo_mysql')) {
    echo "<span class='success'>✅ PDO MySQL habilitado</span><br>";
} else {
    echo "<span class='error'>❌ PDO MySQL não habilitado</span><br>";
}

echo "</div>";

// Status final
echo "<div class='section'>";
echo "<h2>🎉 Status Final</h2>";

$estrutura_ok = is_dir('controllers') && is_dir('models') && is_dir('services') && 
                is_dir('config') && is_dir('views') && is_dir('views/dashboard');

$sistema_ok = file_exists('dashboard.php') && file_exists('autoloader.php');

if ($estrutura_ok && $sistema_ok) {
    echo "<span class='success'>🎉 TUDO PRONTO! Estrutura criada com sucesso!</span><br><br>";
    echo "<strong>Próximos passos:</strong><br>";
    echo "1. Copiar classes do Claude para os diretórios apropriados<br>";
    echo "2. Criar dashboard_novo.php para teste<br>";
    echo "3. Testar nova arquitetura<br>";
} else {
    echo "<span class='error'>❌ Problemas encontrados - verificar itens marcados acima</span><br>";
}

echo "</div>";

echo "<hr>";
echo "<p><a href='dashboard.php'>← Voltar para Dashboard Atual</a></p>";
?>