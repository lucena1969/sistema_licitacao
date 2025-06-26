<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

// Verificar permissão para exportar licitações
if (!temPermissao('licitacao_exportar')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Você não tem permissão para exportar dados de licitações.'
    ]);
    exit;
}

try {
    $formato = $_GET['formato'] ?? 'csv';
    $situacao = $_GET['situacao'] ?? '';
    $data_inicio = $_GET['data_inicio'] ?? '';
    $data_fim = $_GET['data_fim'] ?? '';
    $campos = $_GET['campos'] ?? '';
    
    // Construir query baseada nos filtros
    $where_conditions = ['1=1'];
    $params = [];
    
    if (!empty($situacao)) {
        $where_conditions[] = "l.situacao = ?";
        $params[] = $situacao;
    }
    
    if (!empty($data_inicio)) {
        $where_conditions[] = "l.criado_em >= ?";
        $params[] = $data_inicio . ' 00:00:00';
    }
    
    if (!empty($data_fim)) {
        $where_conditions[] = "l.criado_em <= ?";
        $params[] = $data_fim . ' 23:59:59';
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Query principal
    $sql = "SELECT 
                l.*, 
                u.nome as usuario_nome,
                COALESCE(l.numero_contratacao, p.numero_contratacao) as numero_contratacao_final
            FROM licitacoes l 
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            LEFT JOIN pca_dados p ON l.pca_dados_id = p.id
            WHERE $where_clause
            ORDER BY l.criado_em DESC";
    
    $pdo = conectarDB();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $licitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($licitacoes)) {
        throw new Exception('Nenhuma licitação encontrada para exportar');
    }
    
    // Campos disponíveis para exportação
    $campos_disponiveis = [
        'nup' => 'NUP',
        'numero_contratacao_final' => 'Número da Contratação',
        'modalidade' => 'Modalidade',
        'tipo' => 'Tipo',
        'objeto' => 'Objeto',
        'valor_estimado' => 'Valor Estimado',
        'situacao' => 'Situação',
        'pregoeiro' => 'Pregoeiro',
        'data_abertura' => 'Data Abertura',
        'data_homologacao' => 'Data Homologação',
        'valor_homologado' => 'Valor Homologado',
        'economia' => 'Economia',
        'area_demandante' => 'Área Demandante',
        'resp_instrucao' => 'Resp. Instrução',
        'criado_em' => 'Criado em',
        'usuario_nome' => 'Criado por'
    ];
    
    // Se não especificado, usar campos padrão
    if (empty($campos)) {
        $campos_selecionados = ['nup', 'numero_contratacao_final', 'modalidade', 'tipo', 'objeto', 'valor_estimado', 'situacao', 'pregoeiro', 'data_abertura'];
    } else {
        $campos_selecionados = explode(',', $campos);
    }
    
    // Registrar log da exportação
    registrarLog('EXPORTAR_LICITACOES', "Exportou " . count($licitacoes) . " licitações em formato $formato", 'licitacoes', null);
    
    switch ($formato) {
        case 'csv':
            exportarCSV($licitacoes, $campos_selecionados, $campos_disponiveis);
            break;
            
        case 'excel':
            exportarExcel($licitacoes, $campos_selecionados, $campos_disponiveis);
            break;
            
        case 'json':
            exportarJSON($licitacoes, $campos_selecionados);
            break;
            
        default:
            throw new Exception('Formato de exportação não suportado');
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function exportarCSV($licitacoes, $campos_selecionados, $campos_disponiveis) {
    $filename = 'licitacoes_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Adicionar BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Cabeçalho
    $cabecalho = [];
    foreach ($campos_selecionados as $campo) {
        $cabecalho[] = $campos_disponiveis[$campo] ?? $campo;
    }
    fputcsv($output, $cabecalho, ';');
    
    // Dados
    foreach ($licitacoes as $licitacao) {
        $linha = [];
        foreach ($campos_selecionados as $campo) {
            $valor = $licitacao[$campo] ?? '';
            
            // Formatações específicas
            switch ($campo) {
                case 'valor_estimado':
                case 'valor_homologado':
                case 'economia':
                    $valor = $valor ? 'R$ ' . number_format($valor, 2, ',', '.') : '';
                    break;
                case 'data_abertura':
                case 'data_homologacao':
                    $valor = $valor ? date('d/m/Y', strtotime($valor)) : '';
                    break;
                case 'criado_em':
                    $valor = $valor ? date('d/m/Y H:i', strtotime($valor)) : '';
                    break;
                case 'situacao':
                    $valor = str_replace('_', ' ', $valor);
                    break;
            }
            
            $linha[] = $valor;
        }
        fputcsv($output, $linha, ';');
    }
    
    fclose($output);
}

function exportarJSON($licitacoes, $campos_selecionados) {
    $filename = 'licitacoes_' . date('Y-m-d_H-i-s') . '.json';
    
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Filtrar apenas os campos selecionados
    $dados_filtrados = [];
    foreach ($licitacoes as $licitacao) {
        $item = [];
        foreach ($campos_selecionados as $campo) {
            $item[$campo] = $licitacao[$campo] ?? null;
        }
        $dados_filtrados[] = $item;
    }
    
    echo json_encode([
        'exportado_em' => date('Y-m-d H:i:s'),
        'total_registros' => count($dados_filtrados),
        'licitacoes' => $dados_filtrados
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function exportarExcel($licitacoes, $campos_selecionados, $campos_disponiveis) {
    // Para Excel, vamos usar CSV com formato específico
    $filename = 'licitacoes_' . date('Y-m-d_H-i-s') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Início do HTML para Excel
    echo "\xEF\xBB\xBF"; // BOM
    echo '<table border="1">';
    
    // Cabeçalho
    echo '<tr>';
    foreach ($campos_selecionados as $campo) {
        echo '<th style="background-color: #4CAF50; color: white; font-weight: bold;">';
        echo htmlspecialchars($campos_disponiveis[$campo] ?? $campo);
        echo '</th>';
    }
    echo '</tr>';
    
    // Dados
    foreach ($licitacoes as $licitacao) {
        echo '<tr>';
        foreach ($campos_selecionados as $campo) {
            $valor = $licitacao[$campo] ?? '';
            
            // Formatações específicas
            switch ($campo) {
                case 'valor_estimado':
                case 'valor_homologado':
                case 'economia':
                    $valor = $valor ? 'R$ ' . number_format($valor, 2, ',', '.') : '';
                    break;
                case 'data_abertura':
                case 'data_homologacao':
                    $valor = $valor ? date('d/m/Y', strtotime($valor)) : '';
                    break;
                case 'criado_em':
                    $valor = $valor ? date('d/m/Y H:i', strtotime($valor)) : '';
                    break;
                case 'situacao':
                    $valor = str_replace('_', ' ', $valor);
                    break;
            }
            
            echo '<td>' . htmlspecialchars($valor) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
}
?>