<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

$pdo = conectarDB();

// Tipo de atraso para filtrar
$tipo_atraso = $_GET['tipo'] ?? 'todos';

// Construir WHERE baseado no tipo
$where = [];
$params = [];

switch ($tipo_atraso) {
    case 'inicio':
        $where[] = "p.data_inicio_processo < CURDATE() AND p.situacao_execucao = 'Não iniciado'";
        break;
    case 'conclusao':
        $where[] = "p.data_conclusao_processo < CURDATE() AND p.situacao_execucao != 'Concluído'";
        break;
    case 'vencendo':
        $where[] = "p.data_conclusao_processo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        break;
    default:
        $where[] = "(p.data_inicio_processo < CURDATE() AND p.situacao_execucao = 'Não iniciado') 
                    OR (p.data_conclusao_processo < CURDATE() AND p.situacao_execucao != 'Concluído')";
}

// Filtro adicional por área se fornecido
if (!empty($_GET['area'])) {
    $where[] = "p.area_requisitante = ?";
    $params[] = $_GET['area'];
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Query principal
$sql = "SELECT 
        p.numero_contratacao,
        MAX(p.titulo_contratacao) as titulo_contratacao,
        MAX(p.categoria_contratacao) as categoria_contratacao,
        SUM(p.valor_total_contratacao) as valor_total_contratacao,
        MAX(p.area_requisitante) as area_requisitante,
        MAX(p.situacao_execucao) as situacao_execucao,
        MAX(p.data_inicio_processo) as data_inicio_processo,
        MAX(p.data_conclusao_processo) as data_conclusao_processo,
        DATEDIFF(MAX(p.data_inicio_processo), CURDATE()) as dias_atraso_inicio,
        DATEDIFF(MAX(p.data_conclusao_processo), CURDATE()) as dias_atraso_conclusao,
        MAX(p.numero_dfd) as numero_dfd,
        MAX(p.prioridade) as prioridade,
        COUNT(*) as qtd_itens
        FROM pca_dados p 
        $whereClause 
        GROUP BY p.numero_contratacao
        ORDER BY 
            CASE 
                WHEN p.data_conclusao_processo < CURDATE() THEN 1
                WHEN p.data_inicio_processo < CURDATE() THEN 2
                ELSE 3
            END,
            ABS(DATEDIFF(p.data_conclusao_processo, CURDATE())) ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Configurar headers para download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="contratacoes_atrasadas_' . date('Y-m-d_H-i-s') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Adicionar BOM para UTF-8
echo "\xEF\xBB\xBF";

// Abrir output stream
$output = fopen('php://output', 'w');

// Escrever cabeçalho
$cabecalho = [
    'Número da Contratação',
    'Nº DFD',
    'Título da Contratação',
    'Categoria',
    'Área Requisitante',
    'Prioridade',
    'Valor Total',
    'Data Início',
    'Data Conclusão',
    'Situação Execução',
    'Tipo de Atraso',
    'Dias de Atraso',
    'Qtd Itens'
];

fputcsv($output, $cabecalho, ';');

// Escrever dados
while ($row = $stmt->fetch()) {
    // Determinar tipo e dias de atraso
    $tipo_atraso_item = '';
    $dias_atraso = 0;
    
    if ($row['dias_atraso_conclusao'] < 0) {
        $tipo_atraso_item = 'Vencida (Conclusão)';
        $dias_atraso = abs($row['dias_atraso_conclusao']);
    } elseif ($row['dias_atraso_inicio'] < 0 && $row['situacao_execucao'] == 'Não iniciado') {
        $tipo_atraso_item = 'Não Iniciada';
        $dias_atraso = abs($row['dias_atraso_inicio']);
    } elseif ($row['dias_atraso_conclusao'] >= 0 && $row['dias_atraso_conclusao'] <= 30) {
        $tipo_atraso_item = 'Vencendo em breve';
        $dias_atraso = $row['dias_atraso_conclusao'];
    }
    
    $linha = [
        $row['numero_contratacao'],
        $row['numero_dfd'],
        $row['titulo_contratacao'],
        $row['categoria_contratacao'],
        $row['area_requisitante'],
        $row['prioridade'],
        number_format($row['valor_total_contratacao'], 2, ',', '.'),
        $row['data_inicio_processo'] ? date('d/m/Y', strtotime($row['data_inicio_processo'])) : '',
        $row['data_conclusao_processo'] ? date('d/m/Y', strtotime($row['data_conclusao_processo'])) : '',
        $row['situacao_execucao'],
        $tipo_atraso_item,
        $dias_atraso,
        $row['qtd_itens']
    ];
    
    fputcsv($output, $linha, ';');
}

fclose($output);

// Registrar log
registrarLog('EXPORTACAO_ATRASADAS', 'Exportou contratações atrasadas - Tipo: ' . $tipo_atraso);
exit;
?>