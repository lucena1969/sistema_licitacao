<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

// Verificar se a biblioteca TCPDF existe
if (!class_exists('TCPDF')) {
    // Tentar incluir biblioteca TCPDF se existir
    $tcpdf_paths = [
        '../vendor/tecnickcom/tcpdf/tcpdf.php',
        '../tcpdf/tcpdf.php',
        '../../tcpdf/tcpdf.php'
    ];
    
    $tcpdf_found = false;
    foreach ($tcpdf_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $tcpdf_found = true;
            break;
        }
    }
    
    if (!$tcpdf_found) {
        // Redirecionar para exportação HTML se não tiver TCPDF
        header('Location: exportar_relatorio_riscos.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
        exit;
    }
}

$pdo = conectarDB();

// Mês do relatório
$mes_relatorio = $_GET['mes'] ?? date('Y-m');
$mes_display = DateTime::createFromFormat('Y-m', $mes_relatorio)->format('m/Y');

// Buscar riscos do mês (com correção de collation)
$sql_riscos = "SELECT r.*, p.titulo_contratacao, p.area_requisitante, p.valor_total_contratacao,
               -- Extrair campos do descrição_risco para compatibilidade
               SUBSTRING_INDEX(SUBSTRING_INDEX(r.descricao_risco, 'EVENTO: ', -1), '\n', 1) as evento_risco,
               SUBSTRING_INDEX(SUBSTRING_INDEX(r.descricao_risco, 'CAUSA: ', -1), '\n', 1) as causa_risco,
               SUBSTRING_INDEX(SUBSTRING_INDEX(r.descricao_risco, 'CONSEQUÊNCIA: ', -1), '\n', 1) as consequencia_risco,
               -- Usar impacto como demanda
               r.impacto as demanda,
               -- Separar probabilidade e impacto
               SUBSTRING_INDEX(r.probabilidade, 'x', 1) as prob_valor,
               SUBSTRING_INDEX(r.probabilidade, 'x', -1) as imp_valor,
               -- Separar ações preventivas e contingência
               SUBSTRING_INDEX(SUBSTRING_INDEX(r.acao_mitigacao, 'PREVENTIVA: ', -1), '\nCONTINGÊNCIA:', 1) as acao_preventiva,
               SUBSTRING_INDEX(r.acao_mitigacao, 'CONTINGÊNCIA: ', -1) as acao_contingencia,
               r.responsavel as responsavel_preventiva,
               r.responsavel as responsavel_contingencia
               FROM pca_riscos r
               LEFT JOIN pca_dados p ON r.numero_dfd COLLATE utf8mb4_unicode_ci = p.numero_dfd COLLATE utf8mb4_unicode_ci
               WHERE r.mes_relatorio = ?
               ORDER BY FIELD(r.nivel_risco, 'extremo', 'alto', 'medio', 'baixo'), r.numero_dfd";
$stmt = $pdo->prepare($sql_riscos);
$stmt->execute([$mes_relatorio]);
$riscos = $stmt->fetchAll();

// Estatísticas
$stats = [
    'total' => count($riscos),
    'extremo' => count(array_filter($riscos, fn($r) => $r['nivel_risco'] === 'extremo')),
    'alto' => count(array_filter($riscos, fn($r) => $r['nivel_risco'] === 'alto')),
    'medio' => count(array_filter($riscos, fn($r) => $r['nivel_risco'] === 'medio')),
    'baixo' => count(array_filter($riscos, fn($r) => $r['nivel_risco'] === 'baixo'))
];

// Buscar informações da instituição
$instituicao = [
    'nome' => 'Ministério da Saúde',
    'setor' => 'Coordenação Geral de Licitações - CGLIC',
    'responsavel' => $_SESSION['usuario_nome']
];

// Criar novo PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurações do documento
$pdf->SetCreator('Sistema CGLIC');
$pdf->SetAuthor($instituicao['responsavel']);
$pdf->SetTitle('Relatório de Gestão de Riscos - PCA ' . $mes_display);
$pdf->SetSubject('Gestão de Riscos');
$pdf->SetKeywords('PCA, Riscos, Licitações, Ministério da Saúde');

// Configurações da página
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Remover header e footer padrão
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Adicionar página
$pdf->AddPage();

// Definir fonte
$pdf->SetFont('helvetica', '', 10);

// Cabeçalho do documento
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'MINISTÉRIO DA SAÚDE', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'Coordenação Geral de Licitações - CGLIC', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 8, 'Relatório de Gestão de Riscos - PCA', 0, 1, 'C');
$pdf->Cell(0, 6, 'Período de Referência: ' . $mes_display, 0, 1, 'C');
$pdf->Ln(10);

// Informações do relatório
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, '1. INFORMAÇÕES DO RELATÓRIO', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(40, 6, 'Instituição:', 0, 0, 'L');
$pdf->Cell(0, 6, $instituicao['nome'], 0, 1, 'L');
$pdf->Cell(40, 6, 'Setor:', 0, 0, 'L');
$pdf->Cell(0, 6, $instituicao['setor'], 0, 1, 'L');
$pdf->Cell(40, 6, 'Responsável:', 0, 0, 'L');
$pdf->Cell(0, 6, $instituicao['responsavel'], 0, 1, 'L');
$pdf->Cell(40, 6, 'Data de Geração:', 0, 0, 'L');
$pdf->Cell(0, 6, date('d/m/Y H:i'), 0, 1, 'L');
$pdf->Ln(8);

// Resumo executivo
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, '2. RESUMO EXECUTIVO', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);

// Estatísticas em tabela
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(38, 8, 'Total de Riscos', 1, 0, 'C', true);
$pdf->Cell(38, 8, 'Risco Extremo', 1, 0, 'C', true);
$pdf->Cell(38, 8, 'Risco Alto', 1, 0, 'C', true);
$pdf->Cell(38, 8, 'Risco Médio', 1, 0, 'C', true);
$pdf->Cell(38, 8, 'Risco Baixo', 1, 1, 'C', true);

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(38, 10, $stats['total'], 1, 0, 'C');
$pdf->SetTextColor(220, 38, 38); // Vermelho
$pdf->Cell(38, 10, $stats['extremo'], 1, 0, 'C');
$pdf->SetTextColor(255, 140, 0); // Laranja
$pdf->Cell(38, 10, $stats['alto'], 1, 0, 'C');
$pdf->SetTextColor(255, 193, 7); // Amarelo
$pdf->Cell(38, 10, $stats['medio'], 1, 0, 'C');
$pdf->SetTextColor(40, 167, 69); // Verde
$pdf->Cell(38, 10, $stats['baixo'], 1, 1, 'C');

$pdf->SetTextColor(0, 0, 0); // Voltar ao preto
$pdf->SetFont('helvetica', '', 10);
$pdf->Ln(8);

// Matriz de riscos
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, '3. MATRIZ DE RISCOS', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 9);

// Cabeçalho da matriz
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(30, 15, 'IMPACTO', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'PROBABILIDADE', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Muito baixa', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Baixa', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Média', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Alta', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Muito Alta', 1, 1, 'C', true);

$pdf->Cell(30, 8, '', 0, 0);
$pdf->Cell(20, 8, '', 1, 0, 'C', true);
$pdf->Cell(20, 8, '(1)', 1, 0, 'C', true);
$pdf->Cell(20, 8, '(2)', 1, 0, 'C', true);
$pdf->Cell(20, 8, '(3)', 1, 0, 'C', true);
$pdf->Cell(20, 8, '(4)', 1, 0, 'C', true);
$pdf->Cell(20, 8, '(5)', 1, 1, 'C', true);

// Dados da matriz
$impactos = [
    5 => 'Muito alto',
    4 => 'Alto',
    3 => 'Médio',
    2 => 'Baixo',
    1 => 'Muito baixo'
];

foreach ($impactos as $imp_value => $imp_label) {
    $pdf->Cell(30, 8, $imp_label . ' (' . $imp_value . ')', 1, 0, 'C', true);
    
    for ($prob = 1; $prob <= 5; $prob++) {
        // Contar riscos com essa probabilidade e impacto
        $count = 0;
        foreach ($riscos as $risco) {
            if (strpos($risco['probabilidade'], 'x') !== false) {
                $parts = explode('x', $risco['probabilidade']);
                $risco_prob = (int)trim($parts[0]);
                $risco_imp = (int)trim($parts[1]);
                if ($risco_prob == $prob && $risco_imp == $imp_value) {
                    $count++;
                }
            }
        }
        
        // Definir cor da célula
        $produto = $prob * $imp_value;
        if ($produto >= 20) {
            $pdf->SetFillColor(252, 165, 165); // Extremo - vermelho claro
        } elseif ($produto >= 12) {
            $pdf->SetFillColor(253, 186, 116); // Alto - laranja claro
        } elseif ($produto >= 6) {
            $pdf->SetFillColor(253, 224, 71); // Médio - amarelo claro
        } else {
            $pdf->SetFillColor(134, 239, 172); // Baixo - verde claro
        }
        
        $pdf->Cell(20, 8, $count > 0 ? $count : '-', 1, 0, 'C', true);
    }
    $pdf->Ln();
}

$pdf->SetFillColor(240, 240, 240); // Resetar cor
$pdf->Ln(8);

// Lista detalhada de riscos
if (!empty($riscos)) {
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, '4. MAPA DE RISCOS DETALHADO', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8);
    
    // Cabeçalho da tabela
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(20, 8, 'DFD', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Evento de Risco', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Causa', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Consequência', 1, 0, 'C', true);
    $pdf->Cell(15, 8, 'P x I', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Nível', 1, 1, 'C', true);
    
    foreach ($riscos as $risco) {
        // Verificar se precisa de nova página
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            // Repetir cabeçalho
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(20, 8, 'DFD', 1, 0, 'C', true);
            $pdf->Cell(35, 8, 'Evento de Risco', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Causa', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Consequência', 1, 0, 'C', true);
            $pdf->Cell(15, 8, 'P x I', 1, 0, 'C', true);
            $pdf->Cell(20, 8, 'Nível', 1, 1, 'C', true);
        }
        
        $altura_linha = 12; // Altura mínima da linha
        
        $pdf->Cell(20, $altura_linha, $risco['numero_dfd'], 1, 0, 'C');
        $pdf->Cell(35, $altura_linha, substr($risco['evento_risco'] ?? '', 0, 40), 1, 0, 'L');
        $pdf->Cell(30, $altura_linha, substr($risco['causa_risco'] ?? '', 0, 30), 1, 0, 'L');
        $pdf->Cell(30, $altura_linha, substr($risco['consequencia_risco'] ?? '', 0, 30), 1, 0, 'L');
        $pdf->Cell(15, $altura_linha, $risco['probabilidade'], 1, 0, 'C');
        $pdf->Cell(20, $altura_linha, strtoupper($risco['nivel_risco']), 1, 1, 'C');
    }
}

// Considerações finais
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, '5. CONSIDERAÇÕES FINAIS', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->MultiCell(0, 6, 'As ações de gestão de riscos devem ser monitoradas continuamente para garantir que as respostas adotadas resultem na manutenção do risco em níveis adequados, de acordo com a política de gestão de riscos da organização.');
$pdf->Ln(4);
$pdf->MultiCell(0, 6, 'Recomenda-se a revisão periódica deste relatório, com atualização mensal das informações e acompanhamento das ações preventivas e de contingência estabelecidas.');

// Rodapé
$pdf->Ln(15);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 6, 'Relatório gerado automaticamente pelo Sistema de Gestão de Licitações', 0, 1, 'C');
$pdf->Cell(0, 6, date('d/m/Y H:i:s'), 0, 1, 'C');

// Gerar o PDF
$filename = 'relatorio_riscos_pca_' . $mes_relatorio . '.pdf';
$pdf->Output($filename, 'D');

// Registrar log
registrarLog('EXPORTAR_RELATORIO_RISCOS_PDF', "Exportou relatório de riscos em PDF do mês $mes_display");
?>