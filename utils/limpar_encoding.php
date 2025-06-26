<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

if (!isset($_GET['executar'])) {
    echo '<h2>Limpar Encoding dos Dados</h2>';
    echo '<p>Este script irá corrigir o encoding de todos os dados já importados.</p>';
    echo '<p><strong>ATENÇÃO:</strong> Faça backup do banco antes de executar!</p>';
    echo '<a href="?executar=1" style="background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Executar Limpeza</a>';
    exit;
}

$pdo = conectarDB();

echo '<h2>Executando limpeza...</h2>';

// Campos de texto que precisam ser limpos
$campos_texto = [
    'numero_contratacao', 'status_contratacao', 'situacao_execucao', 'titulo_contratacao',
    'categoria_contratacao', 'uasg_atual', 'area_requisitante', 'numero_dfd', 'prioridade',
    'numero_item_dfd', 'classificacao_contratacao', 'codigo_classe_grupo', 'nome_classe_grupo',
    'codigo_pdm_material', 'nome_pdm_material', 'codigo_material_servico', 
    'descricao_material_servico', 'unidade_fornecimento'
];

$sql_select = "SELECT id, " . implode(', ', $campos_texto) . " FROM pca_dados";
$stmt_select = $pdo->prepare($sql_select);
$stmt_select->execute();

$total = 0;
$corrigidos = 0;

while ($row = $stmt_select->fetch()) {
    $total++;
    $precisa_update = false;
    $updates = [];
    $params = [];
    
    foreach ($campos_texto as $campo) {
        $valor_original = $row[$campo];
        $valor_limpo = limparEncoding($valor_original);
        
        if ($valor_original !== $valor_limpo) {
            $updates[] = "$campo = ?";
            $params[] = $valor_limpo;
            $precisa_update = true;
        }
    }
    
    if ($precisa_update) {
        $params[] = $row['id'];
        $sql_update = "UPDATE pca_dados SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute($params);
        $corrigidos++;
        
        if ($corrigidos % 100 == 0) {
            echo "<p>Processados: $corrigidos registros...</p>";
            flush();
        }
    }
}

echo "<h3>Concluído!</h3>";
echo "<p>Total de registros processados: $total</p>";
echo "<p>Registros corrigidos: $corrigidos</p>";
echo '<a href="dashboard.php">Voltar ao Dashboard</a>';
?>