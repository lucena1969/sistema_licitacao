<?php
/**
 * Gerador de PDF para Relatórios
 * Utiliza biblioteca TCPDF para geração profissional de PDFs
 */

require_once 'config.php';
require_once 'functions.php';

// Verificar se TCPDF está disponível
if (!class_exists('TCPDF')) {
    // Simular com HTML para demonstração
    generateHTMLReport();
    exit;
}

class ReportPDF extends TCPDF {
    private $reportTitle;
    private $reportSubtitle;
    private $reportDate;
    
    public function __construct($title = '', $subtitle = '') {
        parent::__construct(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $this->reportTitle = $title;
        $this->reportSubtitle = $subtitle;
        $this->reportDate = date('d/m/Y H:i');
        
        $this->setupDocument();
    }
    
    private function setupDocument() {
        // Configurações do documento
        $this->SetCreator('Sistema CGLIC');
        $this->SetAuthor('Sistema de Licitações CGLIC');
        $this->SetTitle($this->reportTitle);
        $this->SetSubject('Relatório Gerencial');
        $this->SetKeywords('licitação, relatório, PCA, análise');
        
        // Configurações de página
        $this->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $this->SetMargins(15, 27, 15);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(10);
        $this->SetAutoPageBreak(TRUE, 25);
        $this->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $this->setFontSubsetting(true);
        
        // Fonte padrão
        $this->SetFont('helvetica', '', 10);
    }
    
    public function Header() {
        // Logo (se disponível)
        //$this->Image('assets/logo.png', 15, 5, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        // Título do cabeçalho
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 10, 'Sistema CGLIC - ' . $this->reportTitle, 0, 1, 'C');
        
        $this->SetFont('helvetica', '', 12);
        $this->SetTextColor(127, 140, 141);
        $this->Cell(0, 5, $this->reportSubtitle, 0, 1, 'C');
        
        // Linha separadora
        $this->SetDrawColor(52, 152, 219);
        $this->SetLineWidth(0.5);
        $this->Line(15, 25, 195, 25);
        
        $this->Ln(5);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(127, 140, 141);
        
        // Data de geração
        $this->Cell(0, 10, 'Gerado em: ' . $this->reportDate, 0, 0, 'L');
        
        // Numeração de página
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
    
    public function addStatsSection($stats, $title = 'Estatísticas Gerais') {
        $this->AddPage();
        
        // Título da seção
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 8, $title, 0, 1, 'L');
        $this->Ln(3);
        
        // Cards de estatísticas
        $cardWidth = 45;
        $cardHeight = 25;
        $cols = 4;
        $x = 15;
        $y = $this->GetY();
        
        $i = 0;
        foreach ($stats as $label => $value) {
            if ($i > 0 && $i % $cols == 0) {
                $y += $cardHeight + 5;
                $x = 15;
            }
            
            // Card background
            $this->SetFillColor(248, 249, 250);
            $this->SetDrawColor(233, 236, 239);
            $this->Rect($x, $y, $cardWidth, $cardHeight, 'DF');
            
            // Valor
            $this->SetXY($x + 2, $y + 3);
            $this->SetFont('helvetica', 'B', 12);
            $this->SetTextColor(52, 152, 219);
            $this->Cell($cardWidth - 4, 8, $this->formatValue($value), 0, 1, 'C');
            
            // Label
            $this->SetXY($x + 2, $y + 12);
            $this->SetFont('helvetica', '', 8);
            $this->SetTextColor(127, 140, 141);
            $this->MultiCell($cardWidth - 4, 3, $label, 0, 'C');
            
            $x += $cardWidth + 2;
            $i++;
        }
        
        $this->SetY($y + $cardHeight + 10);
    }
    
    public function addTableSection($data, $headers, $title = 'Dados Detalhados') {
        if (empty($data)) return;
        
        $this->AddPage();
        
        // Título da seção
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 8, $title, 0, 1, 'L');
        $this->Ln(3);
        
        // Calcular larguras das colunas
        $pageWidth = 180; // Largura útil da página
        $colCount = count($headers);
        $colWidth = $pageWidth / $colCount;
        
        // Cabeçalho da tabela
        $this->SetFont('helvetica', 'B', 9);
        $this->SetFillColor(52, 152, 219);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(52, 152, 219);
        
        foreach ($headers as $header) {
            $this->Cell($colWidth, 8, $header, 1, 0, 'C', true);
        }
        $this->Ln();
        
        // Dados da tabela
        $this->SetFont('helvetica', '', 8);
        $this->SetFillColor(248, 249, 250);
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(233, 236, 239);
        
        $fill = false;
        foreach ($data as $row) {
            foreach ($row as $cell) {
                $this->Cell($colWidth, 6, $this->formatValue($cell), 1, 0, 'C', $fill);
            }
            $this->Ln();
            $fill = !$fill;
        }
    }
    
    public function addChartSection($chartData, $title = 'Análise Gráfica') {
        $this->AddPage();
        
        // Título da seção
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 8, $title, 0, 1, 'L');
        $this->Ln(5);
        
        // Texto explicativo sobre gráficos
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(127, 140, 141);
        $this->MultiCell(0, 5, 'Os gráficos interativos estão disponíveis na versão web do relatório. Esta versão PDF contém os dados tabulares correspondentes.', 0, 'L');
        $this->Ln(5);
        
        // Adicionar dados do gráfico em formato tabular
        if (isset($chartData['labels']) && isset($chartData['data'])) {
            $this->addTableSection(
                array_map(function($label, $value) {
                    return [$label, $value];
                }, $chartData['labels'], $chartData['data']),
                ['Item', 'Valor'],
                'Dados do Gráfico'
            );
        }
    }
    
    private function formatValue($value) {
        if (is_numeric($value)) {
            if ($value > 1000000) {
                return 'R$ ' . number_format($value, 0, ',', '.');
            } elseif ($value > 1000) {
                return number_format($value, 0, ',', '.');
            }
        }
        
        return (string)$value;
    }
}

// Função para gerar relatório PDF
function generatePDFReport($tipo, $filtros = []) {
    verificarLogin();
    
    // FUNCIONALIDADE REMOVIDA - ReportsEngine foi deletado
    die('Funcionalidade de relatórios avançados não disponível.');
    
    $pdf = new ReportPDF($dadosRelatorio['titulo'], 'Relatório Gerencial');
    
    // Página de capa
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 20, $dadosRelatorio['titulo'], 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(127, 140, 141);
    $pdf->Cell(0, 10, 'Período: ' . ($filtros['data_inicial'] ?? '') . ' a ' . ($filtros['data_final'] ?? ''), 0, 1, 'C');
    $pdf->Cell(0, 10, 'Gerado em: ' . date('d/m/Y H:i'), 0, 1, 'C');
    
    // Seções do relatório baseadas no tipo
    switch ($tipo) {
        case 'modalidade':
            if (isset($dadosRelatorio['dados']['principal'])) {
                $stats = [];
                foreach ($dadosRelatorio['dados']['principal'] as $item) {
                    $stats[$item['modalidade']] = $item['total_licitacoes'];
                }
                $pdf->addStatsSection($stats, 'Licitações por Modalidade');
                
                // Tabela detalhada
                $headers = ['Modalidade', 'Total', 'Homologadas', 'Em Andamento', 'Valor Total'];
                $tableData = [];
                foreach ($dadosRelatorio['dados']['principal'] as $item) {
                    $tableData[] = [
                        $item['modalidade'],
                        $item['total_licitacoes'],
                        $item['homologadas'],
                        $item['em_andamento'],
                        'R$ ' . number_format($item['valor_total'] ?? 0, 2, ',', '.')
                    ];
                }
                $pdf->addTableSection($tableData, $headers, 'Detalhamento por Modalidade');
            }
            break;
            
        case 'categoria':
            if (isset($dadosRelatorio['dados']['principal'])) {
                // Similar ao modalidade, mas para categorias
                $stats = [];
                foreach ($dadosRelatorio['dados']['principal'] as $item) {
                    $stats[$item['categoria_contratacao']] = $item['total_contratacoes'];
                }
                $pdf->addStatsSection($stats, 'Contratações por Categoria');
            }
            break;
            
        // Adicionar outros tipos conforme necessário
    }
    
    // Gerar e enviar PDF
    $filename = $tipo . '_relatorio_' . date('Y-m-d') . '.pdf';
    $pdf->Output($filename, 'D');
}

// Função fallback para quando TCPDF não está disponível
function generateHTMLReport() {
    $tipo = $_GET['tipo'] ?? 'dashboard';
    $filtros = [
        'data_inicial' => $_GET['data_inicial'] ?? date('Y-01-01'),
        'data_final' => $_GET['data_final'] ?? date('Y-m-d'),
        'categoria' => $_GET['categoria'] ?? '',
        'area' => $_GET['area'] ?? '',
        'situacao' => $_GET['situacao'] ?? ''
    ];
    
    // FUNCIONALIDADE REMOVIDA - ReportsEngine foi deletado
    die('Funcionalidade de relatórios avançados não disponível.');
    
    // Headers para download
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_' . $tipo . '_' . date('Y-m-d') . '.html"');
    
    echo '<!DOCTYPE html>';
    echo '<html><head><meta charset="UTF-8"><title>' . $dadosRelatorio['titulo'] . '</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .stats { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; }
        .stat-card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; min-width: 150px; text-align: center; }
        .stat-value { font-size: 24px; font-weight: bold; color: #3498db; }
        .stat-label { font-size: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
    </style></head><body>';
    
    echo '<div class="header">';
    echo '<h1>' . htmlspecialchars($dadosRelatorio['titulo']) . '</h1>';
    echo '<p>Período: ' . htmlspecialchars($filtros['data_inicial']) . ' a ' . htmlspecialchars($filtros['data_final']) . '</p>';
    echo '<p>Gerado em: ' . date('d/m/Y H:i') . '</p>';
    echo '</div>';
    
    // Exibir dados principais se existirem
    if (isset($dadosRelatorio['dados']['principal'])) {
        echo '<h2>Dados Principais</h2>';
        echo '<table>';
        
        $first = true;
        foreach ($dadosRelatorio['dados']['principal'] as $item) {
            if ($first) {
                echo '<tr>';
                foreach (array_keys($item) as $key) {
                    echo '<th>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $key))) . '</th>';
                }
                echo '</tr>';
                $first = false;
            }
            
            echo '<tr>';
            foreach ($item as $value) {
                echo '<td>' . htmlspecialchars($value) . '</td>';
            }
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    echo '</body></html>';
}

// Processar requisição se for chamada diretamente
if (basename($_SERVER['SCRIPT_NAME']) == 'pdf_generator.php') {
    $tipo = $_GET['tipo'] ?? 'dashboard';
    $filtros = [
        'data_inicial' => $_GET['data_inicial'] ?? date('Y-01-01'),
        'data_final' => $_GET['data_final'] ?? date('Y-m-d'),
        'categoria' => $_GET['categoria'] ?? '',
        'area' => $_GET['area'] ?? '',
        'situacao' => $_GET['situacao'] ?? ''
    ];
    
    generatePDFReport($tipo, $filtros);
}
?>