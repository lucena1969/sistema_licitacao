<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

header('Content-Type: application/json');

$pdo = conectarDB();
$response = ['success' => false, 'message' => ''];

try {
    $acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
    
    switch ($acao) {
        case 'adicionar':
            // Validar dados obrigatórios
            $required = ['numero_dfd', 'demanda', 'evento_risco', 'causa_risco', 'consequencia_risco', 'probabilidade', 'impacto'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Campo obrigatório não preenchido: $field");
                }
            }
            
            // Calcular nível de risco baseado em probabilidade x impacto
            $prob = intval($_POST['probabilidade']);
            $imp = intval($_POST['impacto']);
            $produto = $prob * $imp;
            
            if ($produto <= 6) {
                $nivel_risco = 'baixo';
            } elseif ($produto <= 12) {
                $nivel_risco = 'medio';
            } elseif ($produto <= 20) {
                $nivel_risco = 'alto';
            } else {
                $nivel_risco = 'extremo';
            }
            
            // Inserir risco na estrutura correta da tabela
            $sql = "INSERT INTO pca_riscos (
                numero_dfd, mes_relatorio, nivel_risco, categoria_risco, descricao_risco,
                impacto, probabilidade, acao_mitigacao, responsavel, prazo_mitigacao,
                status_acao, observacoes, criado_por
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            // Construir descrição do risco concatenando os campos
            $descricao_risco = "EVENTO: " . $_POST['evento_risco'] . "\n" .
                              "CAUSA: " . $_POST['causa_risco'] . "\n" .
                              "CONSEQUÊNCIA: " . $_POST['consequencia_risco'];
            
            // Construir ações de mitigação
            $acao_mitigacao = '';
            if (!empty($_POST['acao_preventiva'])) {
                $acao_mitigacao .= "PREVENTIVA: " . $_POST['acao_preventiva'];
            }
            if (!empty($_POST['acao_contingencia'])) {
                if ($acao_mitigacao) $acao_mitigacao .= "\n";
                $acao_mitigacao .= "CONTINGÊNCIA: " . $_POST['acao_contingencia'];
            }
            
            // Responsável (combinar preventiva e contingência)
            $responsavel = '';
            if (!empty($_POST['responsavel_preventiva'])) {
                $responsavel = $_POST['responsavel_preventiva'];
            }
            if (!empty($_POST['responsavel_contingencia']) && $_POST['responsavel_contingencia'] != $responsavel) {
                if ($responsavel) $responsavel .= ' / ';
                $responsavel .= $_POST['responsavel_contingencia'];
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['numero_dfd'],
                $_POST['mes_relatorio'],
                $nivel_risco,
                'Operacional', // categoria padrão
                $descricao_risco,
                $_POST['demanda'], // usar demanda como impacto descritivo
                $prob . 'x' . $imp, // formato "3x4"
                $acao_mitigacao,
                $responsavel,
                null, // prazo_mitigacao (pode ser adicionado depois)
                'pendente',
                null,
                $_SESSION['usuario_nome'] ?? 'Sistema'
            ]);
            
            $response['success'] = true;
            $response['message'] = 'Risco cadastrado com sucesso!';
            registrarLog('ADICIONAR_RISCO', "Adicionou risco para DFD: {$_POST['numero_dfd']}", 'pca_riscos', $pdo->lastInsertId());
            break;
            
        case 'editar':
            if (empty($_POST['risco_id'])) {
                throw new Exception("ID do risco não fornecido");
            }
            
            // Calcular nível de risco baseado em probabilidade x impacto
            $prob = intval($_POST['probabilidade']);
            $imp = intval($_POST['impacto']);
            $produto = $prob * $imp;
            
            if ($produto <= 6) {
                $nivel_risco = 'baixo';
            } elseif ($produto <= 12) {
                $nivel_risco = 'medio';
            } elseif ($produto <= 20) {
                $nivel_risco = 'alto';
            } else {
                $nivel_risco = 'extremo';
            }
            
            // Construir descrição do risco concatenando os campos
            $descricao_risco = "EVENTO: " . $_POST['evento_risco'] . "\n" .
                              "CAUSA: " . $_POST['causa_risco'] . "\n" .
                              "CONSEQUÊNCIA: " . $_POST['consequencia_risco'];
            
            // Construir ações de mitigação
            $acao_mitigacao = '';
            if (!empty($_POST['acao_preventiva'])) {
                $acao_mitigacao .= "PREVENTIVA: " . $_POST['acao_preventiva'];
            }
            if (!empty($_POST['acao_contingencia'])) {
                if ($acao_mitigacao) $acao_mitigacao .= "\n";
                $acao_mitigacao .= "CONTINGÊNCIA: " . $_POST['acao_contingencia'];
            }
            
            // Responsável (combinar preventiva e contingência)
            $responsavel = '';
            if (!empty($_POST['responsavel_preventiva'])) {
                $responsavel = $_POST['responsavel_preventiva'];
            }
            if (!empty($_POST['responsavel_contingencia']) && $_POST['responsavel_contingencia'] != $responsavel) {
                if ($responsavel) $responsavel .= ' / ';
                $responsavel .= $_POST['responsavel_contingencia'];
            }
            
            // Atualizar risco usando a estrutura correta
            $sql = "UPDATE pca_riscos SET 
                numero_dfd = ?, nivel_risco = ?, descricao_risco = ?, 
                impacto = ?, probabilidade = ?, acao_mitigacao = ?, responsavel = ?
                WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['numero_dfd'],
                $nivel_risco,
                $descricao_risco,
                $_POST['demanda'], // usar demanda como impacto descritivo
                $prob . 'x' . $imp, // formato "3x4"
                $acao_mitigacao,
                $responsavel,
                $_POST['risco_id']
            ]);
            
            $response['success'] = true;
            $response['message'] = 'Risco atualizado com sucesso!';
            registrarLog('EDITAR_RISCO', "Editou risco ID: {$_POST['risco_id']}", 'pca_riscos', $_POST['risco_id']);
            break;
            
        case 'excluir':
            if (empty($_GET['id'])) {
                throw new Exception("ID do risco não fornecido");
            }
            
            $sql = "DELETE FROM pca_riscos WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_GET['id']]);
            
            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'Risco excluído com sucesso!';
                registrarLog('EXCLUIR_RISCO', "Excluiu risco ID: {$_GET['id']}", 'pca_riscos', $_GET['id']);
                
                // Redirecionar para a página de riscos
                header('Location: ../relatorio_riscos.php');
                exit;
            } else {
                throw new Exception("Risco não encontrado");
            }
            break;
            
        case 'buscar':
            if (empty($_GET['id'])) {
                throw new Exception("ID do risco não fornecido");
            }
            
            $sql = "SELECT * FROM pca_riscos WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_GET['id']]);
            $risco = $stmt->fetch();
            
            if ($risco) {
                $response['success'] = true;
                $response['data'] = $risco;
            } else {
                throw new Exception("Risco não encontrado");
            }
            break;
            
        default:
            throw new Exception("Ação inválida");
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);