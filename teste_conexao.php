<?php
// Teste básico de conexão
try {
    $pdo = new PDO("mysql:host=localhost;dbname=sistema_licitacao;charset=utf8mb4", "root", "");
    echo "✅ Conexão OK!<br>";
    
    $usuarios = $pdo->query("SELECT * FROM usuarios")->fetchAll();
    echo "✅ Usuários encontrados: " . count($usuarios) . "<br>";
    
    foreach($usuarios as $user) {
        echo "👤 " . $user['nome'] . " (" . $user['email'] . ")<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>