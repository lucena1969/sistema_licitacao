<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

if (!isset($_GET['ids'])) {
    echo '<div class="erro">IDs não fornecidos</div>';
    exit;
}

$pdo = conectarDB();
$ids = $_GET['ids'];
$ids_array = explode(',', $ids);
$placeholders = implode(',', array_fill(0, count($ids_array), '?'));

// Buscar dados do PCA
$sql = "SELECT * FROM pca_dados WHERE id IN ($placeholders) ORDER BY id";
$stmt = $pdo->prepare($sql);
$stmt->execute($ids_array);
$dados_pca = $stmt->fetchAll();

if (empty($dados_pca)) {
    echo '<div class="erro">Dados não encontrados</div>';
    exit;
}

// Buscar dados da licitação se existir
$numero_contratacao = $dados_pca[0]['numero_contratacao'];
$sql_licitacao = "SELECT l.*, u.nome as usuario_nome 
                  FROM licitacoes l 
                  LEFT JOIN usuarios u ON l.usuario_id = u.id
                  WHERE l.pca_dados_id IN ($placeholders)
                  ORDER BY l.id DESC LIMIT 1";
$stmt_licitacao = $pdo->prepare($sql_licitacao);
$stmt_licitacao->execute($ids_array);
$licitacao = $stmt_licitacao->fetch();
?>

<!-- Abas -->
<div class="abas">
    <button class="aba ativa" onclick="
        document.getElementById('aba-pca').style.display='block';
        document.getElementById('aba-licitacao').style.display='none';
        this.classList.add('ativa');
        this.parentNode.querySelector('.aba:nth-child(2)').classList.remove('ativa');
    ">Dados do PCA</button>
    <?php if ($licitacao): ?>
    <button class="aba" onclick="
        document.getElementById('aba-pca').style.display='none';
        document.getElementById('aba-licitacao').style.display='block';
        this.classList.add('ativa');
        this.parentNode.querySelector('.aba:nth-child(1)').classList.remove('ativa');
    ">Dados da Licitação</button>
    <?php endif; ?>
</div>
    
    <!-- Conteúdo PCA -->
    <div id="aba-pca" class="conteudo-aba">
        <h4>Informações Gerais</h4>
        <div class="info-grid">
            <div class="info-item">
                <label>Número da Contratação:</label>
                <span><?php echo htmlspecialchars($numero_contratacao); ?></span>
            </div>
            <div class="info-item">
                <label>Status:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['status_contratacao']); ?></span>
            </div>
            <div class="info-item">
                <label>Categoria:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['categoria_contratacao']); ?></span>
            </div>
            <div class="info-item">
                <label>UASG:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['uasg_atual']); ?></span>
            </div>
            <div class="info-item">
                <label>Área Requisitante:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['area_requisitante']); ?></span>
            </div>
            <div class="info-item">
                <label>Prioridade:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['prioridade']); ?></span>
            </div>
            <div class="info-item">
                <label>Nº DFD:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['numero_dfd']); ?></span>
            </div>
            <div class="info-item">
                <label>Prazo Estimado:</label>
                <span><?php echo $dados_pca[0]['prazo_duracao_dias']; ?> dias</span>
            </div>
            <div class="info-item">
                <label>Data Início:</label>
                <span><?php echo formatarData($dados_pca[0]['data_inicio_processo']); ?></span>
            </div>
            <div class="info-item">
                <label>Data Conclusão:</label>
                <span><?php echo formatarData($dados_pca[0]['data_conclusao_processo']); ?></span>
            </div>
        </div>
        
        <h4 class="mt-20">Título da Contratação</h4>
        <p class="texto-completo"><?php echo htmlspecialchars($dados_pca[0]['titulo_contratacao']); ?></p>
        
        <h4 class="mt-20">Itens da Contratação</h4>
        <table class="tabela-detalhes">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Código Material/Serviço</th>
                    <th>Descrição</th>
                    <th>Unidade</th>
                    <th>Quantidade</th>
                    <th>Valor Unit.</th>
                    <th>Valor Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_geral = 0;
                foreach ($dados_pca as $index => $item): 
                    $total_geral += $item['valor_total'];
                ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($item['codigo_material_servico']); ?></td>
                    <td><?php echo htmlspecialchars($item['descricao_material_servico']); ?></td>
                    <td><?php echo htmlspecialchars($item['unidade_fornecimento']); ?></td>
                    <td><?php echo $item['quantidade']; ?></td>
                    <td><?php echo formatarMoeda($item['valor_unitario']); ?></td>
                    <td><?php echo formatarMoeda($item['valor_total']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6">Total Geral:</th>
                    <th><?php echo formatarMoeda($total_geral); ?></th>
                </tr>
            </tfoot>
        </table>
        
        <h4 class="mt-20">Classificação</h4>
        <div class="info-grid">
            <div class="info-item">
                <label>Classificação:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['classificacao_contratacao']); ?></span>
            </div>
            <div class="info-item">
                <label>Código Classe/Grupo:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['codigo_classe_grupo']); ?></span>
            </div>
            <div class="info-item">
                <label>Nome Classe/Grupo:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['nome_classe_grupo']); ?></span>
            </div>
            <?php if (!empty($dados_pca[0]['codigo_pdm_material'])): ?>
            <div class="info-item">
                <label>Código PDM:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['codigo_pdm_material']); ?></span>
            </div>
            <div class="info-item">
                <label>Nome PDM:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['nome_pdm_material']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Conteúdo Licitação -->
    <?php if ($licitacao): ?>
    <div id="aba-licitacao" class="conteudo-aba" style="display: none;">
        <h4>Informações da Licitação</h4>
        <div class="info-grid">
            <div class="info-item">
                <label>NUP:</label>
                <span><?php echo htmlspecialchars($licitacao['nup']); ?></span>
            </div>
            <div class="info-item">
                <label>Modalidade:</label>
                <span><?php echo htmlspecialchars($licitacao['modalidade']); ?></span>
            </div>
            <div class="info-item">
                <label>Tipo:</label>
                <span><?php echo htmlspecialchars($licitacao['tipo']); ?></span>
            </div>
            <div class="info-item">
                <label>Número/Ano:</label>
                <span><?php echo $licitacao['numero'] . '/' . $licitacao['ano']; ?></span>
            </div>
            <div class="info-item">
                <label>Situação:</label>
                <span class="badge badge-<?php echo strtolower($licitacao['situacao']); ?>">
                    <?php echo str_replace('_', ' ', $licitacao['situacao']); ?>
                </span>
            </div>
            <div class="info-item">
                <label>Pregoeiro:</label>
                <span><?php echo htmlspecialchars($licitacao['pregoeiro']); ?></span>
            </div>
            <div class="info-item">
                <label>Data Entrada DIPLI:</label>
                <span><?php echo formatarData($licitacao['data_entrada_dipli']); ?></span>
            </div>
            <div class="info-item">
                <label>Data Abertura:</label>
                <span><?php echo formatarData($licitacao['data_abertura']); ?></span>
            </div>
            <div class="info-item">
                <label>Valor Estimado:</label>
                <span><?php echo formatarMoeda($licitacao['valor_estimado']); ?></span>
            </div>
            <div class="info-item">
                <label>Qtd Itens:</label>
                <span><?php echo $licitacao['qtd_itens']; ?></span>
            </div>
        </div>
        
        <h4 class="mt-20">Objeto</h4>
        <p class="texto-completo"><?php echo htmlspecialchars($licitacao['objeto']); ?></p>
        
        <?php if (!empty($licitacao['andamentos'])): ?>
        <h4 class="mt-20">Andamentos</h4>
        <p class="texto-completo"><?php echo nl2br(htmlspecialchars($licitacao['andamentos'])); ?></p>
        <?php endif; ?>
        
        <h4 class="mt-20">Informações Adicionais</h4>
        <div class="info-grid">
            <div class="info-item">
                <label>Impugnado:</label>
                <span><?php echo $licitacao['impugnado'] ? 'Sim' : 'Não'; ?></span>
            </div>
            <div class="info-item">
                <label>Pertinente:</label>
                <span><?php echo $licitacao['pertinente'] ? 'Sim' : 'Não'; ?></span>
            </div>
            <?php if (!empty($licitacao['motivo'])): ?>
            <div class="info-item">
                <label>Motivo:</label>
                <span><?php echo htmlspecialchars($licitacao['motivo']); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <label>Item PGC:</label>
                <span><?php echo htmlspecialchars($licitacao['item_pgc']); ?></span>
            </div>
            <div class="info-item">
                <label>Estimado PGC:</label>
                <span><?php echo formatarMoeda($licitacao['estimado_pgc']); ?></span>
            </div>
            <div class="info-item">
                <label>Ano PGC:</label>
                <span><?php echo $licitacao['ano_pgc']; ?></span>
            </div>
        </div>
        
        <?php if ($licitacao['situacao'] == 'HOMOLOGADO'): ?>
        <h4 class="mt-20">Resultado</h4>
        <div class="info-grid">
            <div class="info-item">
                <label>Qtd Homologada:</label>
                <span><?php echo $licitacao['qtd_homol'] ?: '-'; ?></span>
            </div>
            <div class="info-item">
                <label>Valor Homologado:</label>
                <span><?php echo $licitacao['valor_homologado'] ? formatarMoeda($licitacao['valor_homologado']) : '-'; ?></span>
            </div>
            <div class="info-item">
                <label>Economia:</label>
                <span><?php echo $licitacao['economia'] ? formatarMoeda($licitacao['economia']) : '-'; ?></span>
            </div>
            <div class="info-item">
                <label>Data Homologação:</label>
                <span><?php echo formatarData($licitacao['data_homologacao']); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="info-rodape">
            <small>Criado por: <?php echo htmlspecialchars($licitacao['usuario_nome']); ?> em <?php echo formatarData($licitacao['criado_em']); ?></small>
            <?php if ($licitacao['atualizado_em'] != $licitacao['criado_em']): ?>
            <small>Última atualização: <?php echo formatarData($licitacao['atualizado_em']); ?></small>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function trocarAba(aba, botao) {
    // Esconder todas as abas
    var conteudos = document.querySelectorAll('.conteudo-aba');
    for(var i = 0; i < conteudos.length; i++) {
        conteudos[i].style.display = 'none';
    }
    
    // Remover classe ativa de todos os botões
    var botoes = document.querySelectorAll('.aba');
    for(var i = 0; i < botoes.length; i++) {
        botoes[i].classList.remove('ativa');
    }
    
    // Mostrar aba selecionada
    document.getElementById('aba-' + aba).style.display = 'block';
    
    // Ativar botão clicado
    botao.classList.add('ativa');
}
</script>