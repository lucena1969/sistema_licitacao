<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

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

// Gerar HTML do relatório
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Relatório de Gestão de Riscos - PCA ' . $mes_display . '</title>
    <style>
        @page { margin: 20mm; }
        body { font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.6; color: #333; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 18pt; margin: 0 0 10px 0; }
        .header h2 { font-size: 14pt; margin: 0 0 5px 0; font-weight: normal; }
        .header p { margin: 0; font-size: 10pt; color: #666; }
        
        .section { margin-bottom: 30px; }
        .section-title { font-size: 14pt; font-weight: bold; margin-bottom: 15px; padding-bottom: 5px; border-bottom: 2px solid #333; }
        
        .info-box { background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .info-box p { margin: 5px 0; }
        
        .stats-grid { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .stat-box { flex: 1; text-align: center; padding: 15px; margin: 0 5px; background: #f9f9f9; border-radius: 5px; }
        .stat-box .number { font-size: 24pt; font-weight: bold; display: block; margin-bottom: 5px; }
        .stat-box .label { font-size: 10pt; color: #666; }
        
        .matrix-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .matrix-table th, .matrix-table td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        .matrix-table th { background: #f0f0f0; font-weight: bold; }
        
        .risk-baixo { background: #d4edda; }
        .risk-medio { background: #fff3cd; }
        .risk-alto { background: #f8d7da; }
        .risk-extremo { background: #dc3545; color: white; }
        
        .risks-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10pt; }
        .risks-table th { background: #e9ecef; padding: 10px; text-align: left; font-weight: bold; border: 1px solid #dee2e6; }
        .risks-table td { padding: 8px; border: 1px solid #dee2e6; vertical-align: top; }
        .risks-table tr:nth-child(even) { background: #f8f9fa; }
        
        .badge { padding: 2px 8px; border-radius: 3px; font-size: 9pt; font-weight: bold; }
        .badge.baixo { background: #28a745; color: white; }
        .badge.medio { background: #ffc107; color: #333; }
        .badge.alto { background: #fd7e14; color: white; }
        .badge.extremo { background: #dc3545; color: white; }
        
        .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ccc; text-align: center; font-size: 10pt; color: #666; }
        
        @media print {
            .section { page-break-inside: avoid; }
            .risks-table tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório de Gestão de Riscos</h1>
        <h2>Plano de Contratações Anual - PCA</h2>
        <p>Período de Referência: ' . $mes_display . '</p>
    </div>
    
    <div class="section">
        <h3 class="section-title">1. Introdução</h3>
        <div class="info-box">
            <p><strong>Instituição:</strong> ' . htmlspecialchars($instituicao['nome']) . '</p>
            <p><strong>Setor Responsável:</strong> ' . htmlspecialchars($instituicao['setor']) . '</p>
            <p><strong>Responsável pelo Relatório:</strong> ' . htmlspecialchars($instituicao['responsavel']) . '</p>
            <p><strong>Data de Geração:</strong> ' . date('d/m/Y H:i') . '</p>
        </div>
        <p>Este relatório apresenta a gestão de riscos referentes às contratações planejadas e não efetivadas do Plano de Contratações Anual, 
        conforme previsto no art. 19 do Decreto nº 10.947, de 25 de janeiro de 2022.</p>
    </div>
    
    <div class="section">
        <h3 class="section-title">2. Resumo Executivo</h3>
        <div class="stats-grid">
            <div class="stat-box">
                <span class="number">' . $stats['total'] . '</span>
                <span class="label">Total de Riscos</span>
            </div>
            <div class="stat-box" style="background: #fee2e2;">
                <span class="number">' . $stats['extremo'] . '</span>
                <span class="label">Risco Extremo</span>
            </div>
            <div class="stat-box" style="background: #fed7aa;">
                <span class="number">' . $stats['alto'] . '</span>
                <span class="label">Risco Alto</span>
            </div>
            <div class="stat-box" style="background: #fef3c7;">
                <span class="number">' . $stats['medio'] . '</span>
                <span class="label">Risco Médio</span>
            </div>
            <div class="stat-box" style="background: #d1fae5;">
                <span class="number">' . $stats['baixo'] . '</span>
                <span class="label">Risco Baixo</span>
            </div>
        </div>
    </div>
    
    <div class="section">
        <h3 class="section-title">3. Matriz de Riscos</h3>
        <table class="matrix-table">
            <tr>
                <th rowspan="2" colspan="2" style="width: 20%;">MATRIZ DE RISCOS</th>
                <th colspan="5">PROBABILIDADE</th>
            </tr>
            <tr>
                <th>Muito baixa (1)</th>
                <th>Baixa (2)</th>
                <th>Média (3)</th>
                <th>Alta (4)</th>
                <th>Muito Alta (5)</th>
            </tr>';

// Criar matriz
$impactos = [
    5 => 'Muito alto',
    4 => 'Alto',
    3 => 'Médio',
    2 => 'Baixo',
    1 => 'Muito baixo'
];

foreach ($impactos as $imp_value => $imp_label) {
    $html .= '<tr><th rowspan="1">IMPACTO</th><th>' . $imp_label . ' (' . $imp_value . ')</th>';
    
    for ($prob = 1; $prob <= 5; $prob++) {
        // Contar riscos com essa probabilidade e impacto (formato "3x4")
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
        
        $nivel = '';
        $produto = $prob * $imp_value;
        
        if ($produto >= 20) $nivel = 'risk-extremo';
        elseif ($produto >= 12) $nivel = 'risk-alto';
        elseif ($produto >= 6) $nivel = 'risk-medio';
        else $nivel = 'risk-baixo';
        
        $html .= '<td class="' . $nivel . '">' . ($count > 0 ? $count : '-') . '</td>';
    }
    $html .= '</tr>';
}

$html .= '</table>
    </div>
    
    <div class="section">
        <h3 class="section-title">4. Mapa de Riscos Detalhado</h3>
        <table class="risks-table">
            <thead>
                <tr>
                    <th style="width: 15%;">DFD</th>
                    <th style="width: 25%;">Demanda</th>
                    <th style="width: 20%;">Evento de Risco</th>
                    <th style="width: 15%;">Causa</th>
                    <th style="width: 15%;">Consequência</th>
                    <th style="width: 5%;">P x I</th>
                    <th style="width: 5%;">Nível</th>
                </tr>
            </thead>
            <tbody>';

foreach ($riscos as $risco) {
    $html .= '<tr>
        <td>' . htmlspecialchars($risco['numero_dfd']) . '</td>
        <td>' . htmlspecialchars($risco['demanda'] ?? '') . '</td>
        <td>' . htmlspecialchars($risco['evento_risco'] ?? '') . '</td>
        <td>' . htmlspecialchars($risco['causa_risco'] ?? '') . '</td>
        <td>' . htmlspecialchars($risco['consequencia_risco'] ?? '') . '</td>
        <td style="text-align: center;">' . htmlspecialchars($risco['probabilidade']) . '</td>
        <td style="text-align: center;"><span class="badge ' . $risco['nivel_risco'] . '">' . strtoupper($risco['nivel_risco']) . '</span></td>
    </tr>';
}

$html .= '</tbody>
        </table>
    </div>
    
    <div class="section">
        <h3 class="section-title">5. Ações de Mitigação</h3>
        <table class="risks-table">
            <thead>
                <tr>
                    <th style="width: 15%;">DFD</th>
                    <th style="width: 30%;">Ações Preventivas</th>
                    <th style="width: 15%;">Responsável</th>
                    <th style="width: 30%;">Ações de Contingência</th>
                    <th style="width: 10%;">Responsável</th>
                </tr>
            </thead>
            <tbody>';

foreach ($riscos as $risco) {
    if (!empty($risco['acao_preventiva']) || !empty($risco['acao_contingencia'])) {
        $html .= '<tr>
            <td>' . htmlspecialchars($risco['numero_dfd']) . '</td>
            <td>' . htmlspecialchars($risco['acao_preventiva'] ?? '') . '</td>
            <td>' . htmlspecialchars($risco['responsavel_preventiva'] ?? '') . '</td>
            <td>' . htmlspecialchars($risco['acao_contingencia'] ?? '') . '</td>
            <td>' . htmlspecialchars($risco['responsavel_contingencia'] ?? '') . '</td>
        </tr>';
    }
}

$html .= '</tbody>
        </table>
    </div>
    
    <div class="section">
        <h3 class="section-title">6. Considerações Finais</h3>
        <p>As ações de gestão de riscos devem ser monitoradas continuamente para garantir que as respostas adotadas 
        resultem na manutenção do risco em níveis adequados, de acordo com a política de gestão de riscos da organização.</p>
        <p>Recomenda-se a revisão periódica deste relatório, com atualização mensal das informações e acompanhamento 
        das ações preventivas e de contingência estabelecidas.</p>
    </div>
    
    <div class="footer">
        <p>Relatório gerado automaticamente pelo Sistema de Gestão de Licitações</p>
        <p>' . date('d/m/Y H:i:s') . '</p>
    </div>
</body>
</html>';

// Para simplificar, vamos gerar como HTML com cabeçalhos para download
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="relatorio_riscos_pca_' . $mes_relatorio . '.html"');

echo $html;

// Registrar log
registrarLog('EXPORTAR_RELATORIO_RISCOS', "Exportou relatório de riscos do mês $mes_display");