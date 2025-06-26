<?php

// Arquivo: api/get_licitacao.php
 
require_once '../config.php';

require_once '../functions.php';
 
// Verificar se está logado

verificarLogin();

// Verificar permissão para visualizar licitações
if (!temPermissao('licitacao_visualizar')) {
    echo json_encode([
        'success' => false,
        'message' => 'Você não tem permissão para visualizar dados de licitações.'
    ]);
    exit;
}
 
// Definir header JSON
header('Content-Type: application/json; charset=utf-8');
 
try {

    // Verificar se foi passado o ID

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

        throw new Exception('ID da licitação não fornecido ou inválido.');

    }

    $id = intval($_GET['id']);

    $pdo = conectarDB();

    // CORREÇÃO: Buscar numero_contratacao direto da tabela licitacoes + JOIN com pca_dados como backup

    $sql = "SELECT 
                l.*, 
                u.nome as usuario_nome,
                COALESCE(l.numero_contratacao, p.numero_contratacao) as numero_contratacao_final
            FROM licitacoes l 
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            LEFT JOIN pca_dados p ON l.pca_dados_id = p.id
            WHERE l.id = ?";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([$id]);

    $licitacao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$licitacao) {

        throw new Exception('Licitação não encontrada.');

    }

    // Retornar dados

    echo json_encode([

        'success' => true,

        'data' => $licitacao

    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    // Log do erro

    error_log("Erro na API get_licitacao: " . $e->getMessage());

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage()

    ], JSON_UNESCAPED_UNICODE);

}

?>
 