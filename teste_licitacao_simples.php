<?php
echo "<h2>🔍 Teste Simples da Classe Licitacao</h2>";

// Carregar Database primeiro
require_once 'models/Database.php';
echo "✅ Database carregado<br>";

// Tentar carregar Licitacao diretamente
echo "<h3>Tentando carregar Licitacao...</h3>";

$arquivo = 'models/Licitacao.php';
echo "Arquivo: $arquivo<br>";
echo "Existe? " . (file_exists($arquivo) ? 'SIM' : 'NÃO') . "<br>";
echo "Tamanho: " . filesize($arquivo) . " bytes<br>";

// Ler conteúdo do arquivo
$conteudo = file_get_contents($arquivo);
echo "Primeiros 100 caracteres:<br>";
echo "<pre>" . htmlspecialchars(substr($conteudo, 0, 100)) . "</pre>";

// Verificar se tem <?php no início
if (strpos($conteudo, '<?php') === 0) {
    echo "✅ Arquivo começa com <?php<br>";
} else {
    echo "❌ Arquivo NÃO começa com <?php<br>";
}

// Verificar se tem 'class Licitacao'
if (strpos($conteudo, 'class Licitacao') !== false) {
    echo "✅ 'class Licitacao' encontrado no arquivo<br>";
} else {
    echo "❌ 'class Licitacao' NÃO encontrado no arquivo<br>";
}

// Tentar fazer include
echo "<h3>Tentando incluir arquivo...</h3>";
try {
    ob_start();
    include_once $arquivo;
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "❌ Saída inesperada do arquivo:<br>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    } else {
        echo "✅ Arquivo incluído sem erros<br>";
    }
} catch (Error $e) {
    echo "❌ Erro PHP: " . $e->getMessage() . "<br>";
} catch (Exception $e) {
    echo "❌ Exceção: " . $e->getMessage() . "<br>";
}

// Verificar se classe foi definida
if (class_exists('Licitacao')) {
    echo "✅ Classe Licitacao disponível<br>";
    
    // Tentar instanciar
    try {
        $licitacao = new Licitacao();
        echo "✅ Instância criada com sucesso<br>";
    } catch (Exception $e) {
        echo "❌ Erro ao instanciar: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Classe Licitacao NÃO disponível<br>";
    echo "Classes definidas: " . implode(', ', get_declared_classes()) . "<br>";
}
?>