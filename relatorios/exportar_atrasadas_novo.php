<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

$pdo = conectarDB();

// Filtros recebidos
$tipo = $_GET['tipo'] ?? 'todos';
$filtro_area = $_GET['area'] ?? '';

// Construir WHERE para área
$where_area = '';
$params_area = [];
if (!empty($filtro_area)) {
    if ($filtro_area === 'GM.') {
        $where_area = " AND (area_requisitante LIKE 'GM%' OR area_requisitante LIKE 'GM.%')";
    } else {
        $where_area = " AND area_requisitante LIKE ?";
        $params_area[] = $filtro_area . '%';
    }
}

// Escolher consulta baseada no tipo
if ($tipo === 'vencidas') {
    $sql = "SELECT 
        MAX(numero_contratacao) as numero_contratacao,
        numero_dfd,
        MAX(titulo_contratacao) as titulo_contratacao,
        MAX(area_requisitante) as area_requisitante,
        MAX(data_inicio_processo) as data_inicio_processo,
        MAX(data_conclusao_processo) as data_conclusao_processo,
        MAX(situacao_execucao) as situacao_execucao,
        MAX(valor_total_contratacao) as valor_total_contratacao,
        MAX(prioridade) as prioridade,
        DATEDIFF(CURDATE(), MAX(data_conclusao_processo)) as dias_atraso
        FROM pca_dados 
        WHERE data_conclusao_processo < CURDATE()
        AND (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao = 'Não iniciado')
        AND numero_dfd IS NOT NULL 
        AND numero_dfd != ''
        $where_area
        GROUP BY numero_dfd
        ORDER BY dias_atraso DESC";
    
    $titulo_arquivo = 'contratacoes_vencidas_nao_iniciadas';
    $tipo_relatorio = 'Contratações Vencidas (Não Iniciadas)';
    
} elseif ($tipo === 'nao-iniciadas') {
    $sql = "SELECT 
        MAX(numero_contratacao) as numero_contratacao,
        numero_dfd,
        MAX(titulo_contratacao) as titulo_contratacao,
        MAX(area_requisitante) as area_requisitante,
        MAX(data_inicio_processo) as data_inicio_processo,
        MAX(data_conclusao_processo) as data_conclusao_processo,
        MAX(situacao_execucao) as situacao_execucao,
        SUM(valor_total_contratacao) as valor_total_contratacao,
        MAX(prioridade) as prioridade,
        DATEDIFF(CURDATE(), MAX(data_inicio_processo)) as dias_atraso
        FROM pca_dados 
        WHERE data_inicio_processo < CURDATE() 
        AND (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao = 'Não iniciado')
        AND data_conclusao_processo >= CURDATE()
        AND numero_dfd IS NOT NULL 
        AND numero_dfd != ''
        $where_area
        GROUP BY numero_dfd
        ORDER BY dias_atraso DESC";
    
    $titulo_arquivo = 'contratacoes_nao_iniciadas';
    $tipo_relatorio = 'Contratações Não Iniciadas';
    $dias_atraso = 'dias_atraso'; // Para não iniciadas usa dias_atraso em vez de dias_atraso
    
} else {
    // Todos (combinar as duas consultas)
    $sql_vencidas = "SELECT 
        MAX(numero_contratacao) as numero_contratacao,
        numero_dfd,
        MAX(titulo_contratacao) as titulo_contratacao,
        MAX(area_requisitante) as area_requisitante,
        MAX(data_inicio_processo) as data_inicio_processo,
        MAX(data_conclusao_processo) as data_conclusao_processo,
        MAX(situacao_execucao) as situacao_execucao,
        SUM(valor_total_contratacao) as valor_total_contratacao,
        MAX(prioridade) as prioridade,
        DATEDIFF(CURDATE(), MAX(data_conclusao_processo)) as dias_atraso,
        'Vencida (Não Iniciada)' as tipo_atraso
        FROM pca_dados 
        WHERE data_conclusao_processo < CURDATE()
        AND (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao = 'Não iniciado')
        AND numero_dfd IS NOT NULL 
        AND numero_dfd != ''
        $where_area
        GROUP BY numero_dfd
        
        UNION ALL
        
        SELECT 
        MAX(numero_contratacao) as numero_contratacao,
        numero_dfd,
        MAX(titulo_contratacao) as titulo_contratacao,
        MAX(area_requisitante) as area_requisitante,
        MAX(data_inicio_processo) as data_inicio_processo,
        MAX(data_conclusao_processo) as data_conclusao_processo,
        MAX(situacao_execucao) as situacao_execucao,
        SUM(valor_total_contratacao) as valor_total_contratacao,
        MAX(prioridade) as prioridade,
        DATEDIFF(CURDATE(), MAX(data_inicio_processo)) as dias_atraso,
        'Não Iniciada' as tipo_atraso
        FROM pca_dados 
        WHERE data_inicio_processo < CURDATE() 
        AND (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao = 'Não iniciado')
        AND data_conclusao_processo >= CURDATE()
        AND numero_dfd IS NOT NULL 
        AND numero_dfd != ''
        $where_area
        GROUP BY numero_dfd
        
        ORDER BY dias_atraso DESC";
    
    $sql = $sql_vencidas;
    $titulo_arquivo = 'contratacoes_atrasadas_todas';
    $tipo_relatorio = 'Todas as Contratações Atrasadas';
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params_area);

// Configurar headers para download
$nome_arquivo = $titulo_arquivo . '_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Adicionar BOM para UTF-8
echo "\xEF\xBB\xBF";

// Abrir output stream
$output = fopen('php://output', 'w');

// Cabeçalho do CSV
$cabecalho = [
    'Nº DFD',
    'Nº Contratação',
    'Título',
    'Área',
    'Data Início',
    'Data Conclusão',
    'Situação',
    'Valor Total (R$)',
    'Prioridade',
    'Dias de Atraso'
];

// Se exportar todos, adicionar coluna tipo
if ($tipo === 'todos') {
    $cabecalho[] = 'Tipo de Atraso';
}

fputcsv($output, $cabecalho, ';');

// Escrever dados
while ($row = $stmt->fetch()) {
    $linha = [
        $row['numero_dfd'],
        $row['numero_contratacao'],
        $row['titulo_contratacao'],
        agruparArea($row['area_requisitante']),
        $row['data_inicio_processo'] ? date('d/m/Y', strtotime($row['data_inicio_processo'])) : '',
        $row['data_conclusao_processo'] ? date('d/m/Y', strtotime($row['data_conclusao_processo'])) : '',
        empty($row['situacao_execucao']) ? 'Não iniciado' : $row['situacao_execucao'],
        number_format($row['valor_total_contratacao'], 2, ',', '.'),
        $row['prioridade'],
        $row['dias_atraso']
    ];
    
    // Se exportar todos, adicionar tipo de atraso
    if ($tipo === 'todos') {
        $linha[] = $row['tipo_atraso'];
    }
    
    fputcsv($output, $linha, ';');
}

fclose($output);

// Registrar log
$filtro_info = !empty($filtro_area) ? " - Área: $filtro_area" : "";
registrarLog('EXPORTACAO_ATRASADAS', "Exportou $tipo_relatorio$filtro_info");
exit;
?>