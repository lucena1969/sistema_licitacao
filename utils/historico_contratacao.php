<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

if (!isset($_GET['numero'])) {
    echo '<div class="erro">Número não fornecido</div>';
    exit;
}

$pdo = conectarDB();
$numero_dfd = $_GET['numero'];

// Buscar dados básicos da contratação pelo DFD
$sql_contratacao = "SELECT DISTINCT 
    numero_contratacao,
    numero_dfd,
    titulo_contratacao,
    area_requisitante,
    situacao_execucao,
    data_inicio_processo,
    data_conclusao_processo,
    valor_total_contratacao
    FROM pca_dados 
    WHERE numero_dfd = ? 
    LIMIT 1";
$stmt_contratacao = $pdo->prepare($sql_contratacao);
$stmt_contratacao->execute([$numero_dfd]);
$contratacao = $stmt_contratacao->fetch();

if (!$contratacao) {
    echo '<div class="erro">Contratação não encontrada</div>';
    exit;
}

$numero_contratacao = $contratacao['numero_contratacao'];

// Buscar histórico de mudanças importantes (apenas campos relevantes)
$sql_historico = "SELECT 
    h.data_alteracao,
    h.campo_alterado,
    h.valor_anterior,
    h.valor_novo,
    u.nome as usuario_nome
    FROM pca_historico h
    LEFT JOIN usuarios u ON h.usuario_id = u.id
    WHERE h.numero_contratacao = ?
    AND h.campo_alterado IN ('situacao_execucao', 'data_inicio_processo', 'data_conclusao_processo', 'valor_total_contratacao', 'prioridade')
    ORDER BY h.data_alteracao DESC
    LIMIT 20";
$stmt_historico = $pdo->prepare($sql_historico);
$stmt_historico->execute([$numero_contratacao]);
$historico = $stmt_historico->fetchAll();

// Buscar tempo em cada estado
$sql_estados = "SELECT * FROM pca_estados_tempo 
                WHERE numero_contratacao = ? 
                ORDER BY data_inicio DESC";
$stmt_estados = $pdo->prepare($sql_estados);
$stmt_estados->execute([$numero_contratacao]);
$estados = $stmt_estados->fetchAll();

// Buscar licitação se existir
$sql_licitacao = "SELECT l.*, u.nome as usuario_nome 
                  FROM licitacoes l 
                  LEFT JOIN usuarios u ON l.usuario_id = u.id
                  WHERE l.pca_dados_id IN (
                      SELECT id FROM pca_dados WHERE numero_contratacao = ?
                  )
                  ORDER BY l.id DESC LIMIT 1";
$stmt_licitacao = $pdo->prepare($sql_licitacao);
$stmt_licitacao->execute([$numero_contratacao]);
$licitacao = $stmt_licitacao->fetch();
?>

<div style="padding: 20px;">
    <!-- Cabeçalho com informações básicas -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 15px 0; color: #2c3e50;">
            <i data-lucide="history" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 8px;"></i>
            Histórico - DFD <?php echo htmlspecialchars($numero_dfd); ?>
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <small style="color: #666;">Contratação:</small><br>
                <strong><?php echo htmlspecialchars($contratacao['numero_contratacao']); ?></strong>
            </div>
            <div>
                <small style="color: #666;">Situação Atual:</small><br>
                <strong style="color: <?php echo $contratacao['situacao_execucao'] == 'Concluído' ? '#27ae60' : '#f39c12'; ?>">
                    <?php echo htmlspecialchars($contratacao['situacao_execucao'] ?: 'Não iniciado'); ?>
                </strong>
            </div>
            <div>
                <small style="color: #666;">Área:</small><br>
                <strong><?php echo htmlspecialchars(agruparArea($contratacao['area_requisitante'])); ?></strong>
            </div>
            <div>
                <small style="color: #666;">Valor Total:</small><br>
                <strong><?php echo formatarMoeda($contratacao['valor_total_contratacao']); ?></strong>
            </div>
        </div>
    </div>

    <!-- Timeline de Estados -->
    <?php if (!empty($estados)): ?>
    <h4 style="margin-bottom: 15px; color: #2c3e50;">
        <i data-lucide="clock" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 8px;"></i>
        Linha do Tempo
    </h4>
    <div style="background: white; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
        <?php 
        $total_dias = 0;
        foreach ($estados as $index => $estado): 
            $dias_no_estado = 0;
            
            if ($estado['ativo']) {
                $dias_no_estado = (new DateTime())->diff(new DateTime($estado['data_inicio']))->days;
                $cor = '#28a745';
                $icone = 'play-circle';
            } else {
                $dias_no_estado = $estado['dias_no_estado'];
                $total_dias += $dias_no_estado;
                $cor = '#6c757d';
                $icone = 'check-circle';
            }
        ?>
        
        <div style="display: flex; align-items: center; margin-bottom: <?php echo $index == count($estados) - 1 ? '0' : '20px'; ?>;">
            <!-- Ícone e linha -->
            <div style="position: relative; margin-right: 20px;">
                <div style="width: 40px; height: 40px; background: <?php echo $cor; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="<?php echo $icone; ?>" style="width: 20px; height: 20px; color: white;"></i>
                </div>
                <?php if ($index < count($estados) - 1): ?>
                <div style="position: absolute; top: 40px; left: 19px; width: 2px; height: 40px; background: #dee2e6;"></div>
                <?php endif; ?>
            </div>
            
            <!-- Conteúdo -->
            <div style="flex: 1;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <h5 style="margin: 0; font-size: 16px; color: #2c3e50;">
                            <?php echo htmlspecialchars($estado['situacao_execucao']); ?>
                        </h5>
                        <small style="color: #666;">
                            <?php echo formatarData($estado['data_inicio']); ?>
                            <?php if (!$estado['ativo'] && $estado['data_fim']): ?>
                                → <?php echo formatarData($estado['data_fim']); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 18px; font-weight: bold; color: <?php echo $cor; ?>;">
                            <?php echo $dias_no_estado; ?> <?php echo $dias_no_estado == 1 ? 'dia' : 'dias'; ?>
                        </span>
                        <?php if ($estado['ativo']): ?>
                            <br><small style="color: #28a745;">(em andamento)</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Mudanças Relevantes -->
    <?php if (!empty($historico)): ?>
    <h4 style="margin-bottom: 15px; color: #2c3e50;">
        <i data-lucide="activity" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 8px;"></i>
        Alterações Recentes
    </h4>
    <div style="background: white; border: 1px solid #e9ecef; border-radius: 8px; padding: 15px;">
        <?php 
        $campos_nome = [
            'situacao_execucao' => 'Situação',
            'data_inicio_processo' => 'Data de Início',
            'data_conclusao_processo' => 'Data de Conclusão',
            'valor_total_contratacao' => 'Valor Total',
            'prioridade' => 'Prioridade'
        ];
        
        foreach ($historico as $item): 
            $nome_campo = $campos_nome[$item['campo_alterado']] ?? $item['campo_alterado'];
            
            // Formatar valores conforme o tipo
            $valor_anterior = $item['valor_anterior'];
            $valor_novo = $item['valor_novo'];
            
            if ($item['campo_alterado'] == 'valor_total_contratacao') {
                $valor_anterior = formatarMoeda($valor_anterior);
                $valor_novo = formatarMoeda($valor_novo);
            } elseif (strpos($item['campo_alterado'], 'data_') !== false) {
                $valor_anterior = formatarData($valor_anterior);
                $valor_novo = formatarData($valor_novo);
            }
        ?>
        <div style="padding: 10px 0; border-bottom: 1px solid #f8f9fa;">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div style="flex: 1;">
                    <strong style="color: #2c3e50;"><?php echo $nome_campo; ?></strong>
                    <div style="margin-top: 5px; font-size: 14px;">
                        <span style="color: #dc3545; text-decoration: line-through;">
                            <?php echo htmlspecialchars($valor_anterior ?: 'Vazio'); ?>
                        </span>
                        <span style="margin: 0 10px; color: #666;">→</span>
                        <span style="color: #28a745; font-weight: 600;">
                            <?php echo htmlspecialchars($valor_novo ?: 'Vazio'); ?>
                        </span>
                    </div>
                </div>
                <div style="text-align: right;">
                    <small style="color: #666;">
                        <?php echo date('d/m/Y H:i', strtotime($item['data_alteracao'])); ?>
                        <?php if ($item['usuario_nome']): ?>
                            <br>por <?php echo htmlspecialchars($item['usuario_nome']); ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="background: #f8f9fa; padding: 30px; text-align: center; border-radius: 8px; color: #666;">
        <i data-lucide="info" style="width: 32px; height: 32px; margin-bottom: 10px;"></i>
        <p style="margin: 0;">Nenhuma alteração registrada ainda.</p>
    </div>
    <?php endif; ?>

    <!-- Informações da Licitação -->
    <?php if ($licitacao): ?>
    <h4 style="margin-top: 30px; margin-bottom: 15px; color: #2c3e50;">
        <i data-lucide="gavel" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 8px;"></i>
        Licitação Vinculada
    </h4>
    <div style="background: #e8f5e9; border: 1px solid #4caf50; border-radius: 8px; padding: 15px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <small style="color: #666;">NUP:</small><br>
                <strong><?php echo htmlspecialchars($licitacao['nup']); ?></strong>
            </div>
            <div>
                <small style="color: #666;">Modalidade:</small><br>
                <strong><?php echo htmlspecialchars($licitacao['modalidade']); ?></strong>
            </div>
            <div>
                <small style="color: #666;">Situação:</small><br>
                <strong style="color: <?php echo $licitacao['situacao'] == 'HOMOLOGADO' ? '#27ae60' : '#f39c12'; ?>">
                    <?php echo str_replace('_', ' ', $licitacao['situacao']); ?>
                </strong>
            </div>
            <div>
                <small style="color: #666;">Criada em:</small><br>
                <strong><?php echo formatarData($licitacao['criado_em']); ?></strong>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Carregar ícones Lucide
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>