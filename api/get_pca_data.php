<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

header('Content-Type: application/json');

if (!isset($_GET['id']) && !isset($_GET['numero_contratacao'])) {
    echo json_encode(['erro' => 'Parâmetro não fornecido']);
    exit;
}

$pdo = conectarDB();

// Se for busca por ID (funcionalidade existente)
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT numero_contratacao, titulo_contratacao, area_requisitante, 
            valor_total_contratacao, prioridade
            FROM pca_dados 
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    
    if ($data) {
        echo json_encode($data);
    } else {
        echo json_encode(['erro' => 'Dados não encontrados']);
    }
}

// Se for busca por número de contratação (nova funcionalidade)
if (isset($_GET['numero_contratacao'])) {
    $numeroContratacao = $_GET['numero_contratacao'];
    
    $sql = "SELECT 
        numero_contratacao, 
        titulo_contratacao, 
        area_requisitante, 
        valor_total_contratacao, 
        prioridade,
        numero_dfd
        FROM pca_dados 
        WHERE numero_contratacao = ?
        LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$numeroContratacao]);
    $data = $stmt->fetch();
    
    if ($data) {
        echo json_encode($data);
    } else {
        echo json_encode(['erro' => 'Dados não encontrados']);
    }
}
?>