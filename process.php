<?php
// process.php
require_once 'config.php';
require_once 'functions.php';

// Configurar e iniciar sessão
configurarSessaoSegura();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$acao = $_POST['acao'] ?? '';
$pdo = conectarDB();

switch ($acao) {
    case 'login':
        $email = limpar($_POST['email']);
        $senha = $_POST['senha'];

        // Verificar se login está bloqueado
        if (isLoginBloqueado()) {
            setMensagem('Muitas tentativas de login. Tente novamente em alguns minutos.', 'erro');
            header('Location: index.php');
            exit;
        }

        try {
            $sql = "SELECT id, nome, email, senha, tipo_usuario, nivel_acesso, departamento FROM usuarios WHERE email = ? AND ativo = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Login bem-sucedido - usar nova função
                atualizarSessaoUsuario($usuario['id']);

                // Registrar login bem-sucedido
                registrarTentativaLogin($email, true, 'Login realizado com sucesso');
                registrarLog('login', 'Login realizado com sucesso', 'usuarios', null);

                header('Location: selecao_modulos.php');
            } else {
                // Login falhado
                if ($usuario) {
                    registrarTentativaLogin($email, false, 'Senha incorreta');
                } else {
                    registrarTentativaLogin($email, false, 'Usuário não encontrado');
                }

                setMensagem('E-mail ou senha incorretos!', 'erro');
                header('Location: index.php');
            }
        } catch (Exception $e) {
            registrarLog('LOGIN_ERROR', 'Erro no processo de login: ' . $e->getMessage(), null, null);
            setMensagem('Erro interno no sistema. Tente novamente.', 'erro');
            header('Location: index.php');
        }
        break;

    case 'cadastro':
        $nome = limpar($_POST['nome']);
        $email = limpar($_POST['email']);
        $senha = $_POST['senha'];
        $confirmar_senha = $_POST['confirmar_senha'];

        // Validações
        if (strlen($nome) < 3) {
            setMensagem('Nome deve ter pelo menos 3 caracteres!', 'erro');
            header('Location: index.php');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setMensagem('E-mail inválido!', 'erro');
            header('Location: index.php');
            exit;
        }

        if (strlen($senha) < 6) {
            setMensagem('Senha deve ter pelo menos 6 caracteres!', 'erro');
            header('Location: index.php');
            exit;
        }

        if ($senha !== $confirmar_senha) {
            setMensagem('As senhas não coincidem!', 'erro');
            header('Location: index.php');
            exit;
        }

        // Verificar se e-mail já existe
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            setMensagem('E-mail já cadastrado!', 'erro');
            header('Location: index.php');
            exit;
        }

        // Inserir usuário com nível padrão VISITANTE (4)
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $sql = "INSERT INTO usuarios (nome, email, senha, tipo_usuario, nivel_acesso, departamento) VALUES (?, ?, ?, 'visitante', 4, 'CGLIC')";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$nome, $email, $senha_hash])) {
            setMensagem('Cadastro realizado com sucesso! Faça login.');
            header('Location: index.php');
        } else {
            setMensagem('Erro ao cadastrar. Tente novamente!', 'erro');
            header('Location: index.php');
        }
        break;

    case 'importar_pca':
        verificarLogin();
        
        // Aumentar limites para importação
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 300); // 5 minutos
        ini_set('max_input_time', 300);
        
        // Verificar permissão para importar PCA
        if (!temPermissao('pca_importar')) {
            setMensagem('Você não tem permissão para importar dados do PCA.', 'erro');
            header('Location: dashboard.php');
            exit;
        }

        if (!isset($_FILES['arquivo_pca'])) {
            setMensagem('Nenhum arquivo selecionado!', 'erro');
            header('Location: dashboard.php');
            exit;
        }

        // Obter ano do PCA (novo parâmetro)
        $ano_pca = intval($_POST['ano_pca'] ?? 2025);
        $eh_historico = ($ano_pca <= 2024);

        $resultado = processarUpload($_FILES['arquivo_pca']);

        if (!$resultado['sucesso']) {
            setMensagem($resultado['mensagem'], 'erro');
            header('Location: dashboard.php');
            exit;
        }

        // Processar o arquivo CSV
        $arquivo = $resultado['caminho'];

        try {
            // Detectar e corrigir encoding antes de abrir
            $conteudo_original = file_get_contents($arquivo);
            if ($conteudo_original === false) {
                throw new Exception('Não foi possível ler o arquivo enviado');
            }
            
            $encoding = mb_detect_encoding($conteudo_original, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'CP1252'], true);

            if ($encoding !== 'UTF-8') {
                $conteudo_utf8 = mb_convert_encoding($conteudo_original, 'UTF-8', $encoding);
                if (file_put_contents($arquivo, $conteudo_utf8) === false) {
                    throw new Exception('Erro ao converter encoding do arquivo');
                }
            }

            $handle = fopen($arquivo, 'r');
            if (!$handle) {
                throw new Exception('Erro ao abrir arquivo para leitura');
            }
        } catch (Exception $e) {
            error_log("Erro no processamento do arquivo CSV: " . $e->getMessage());
            setMensagem('Erro ao processar arquivo: ' . $e->getMessage(), 'erro');
            header('Location: dashboard.php');
            exit;
        }

        // Verificar e corrigir AUTO_INCREMENT antes da importação
        $auto_increment_corrigido = verificarECorrigirAutoIncrement('pca_importacoes');
        error_log("AUTO_INCREMENT verificado/corrigido: " . ($auto_increment_corrigido ? $auto_increment_corrigido : 'falhou'));
        
        // Criar registro de importação
        try {
            $sql = "INSERT INTO pca_importacoes (nome_arquivo, usuario_id, ano_pca) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            // Log dos dados que serão inseridos
            error_log("Inserindo importação - Arquivo: " . $resultado['arquivo'] . ", Usuário: " . $_SESSION['usuario_id'] . ", Ano: " . $ano_pca);
            
            $stmt->execute([$resultado['arquivo'], $_SESSION['usuario_id'], $ano_pca]);
            $importacao_id = $pdo->lastInsertId();
            
            // Log do resultado
            error_log("Resultado do INSERT - lastInsertId(): " . $importacao_id . ", rowCount(): " . $stmt->rowCount());
            
            // Verificar se o ID é válido
            if ($importacao_id <= 0) {
                error_log("LastInsertId retornou valor inválido: $importacao_id");
                
                // Tentar recuperar o ID manualmente
                $sql_recuperar = "SELECT id FROM pca_importacoes WHERE nome_arquivo = ? AND usuario_id = ? AND ano_pca = ? ORDER BY criado_em DESC LIMIT 1";
                $stmt_recuperar = $pdo->prepare($sql_recuperar);
                $stmt_recuperar->execute([$resultado['arquivo'], $_SESSION['usuario_id'], $ano_pca]);
                $registro_recuperado = $stmt_recuperar->fetch();
                
                if ($registro_recuperado) {
                    $importacao_id = $registro_recuperado['id'];
                    error_log("ID recuperado manualmente: $importacao_id");
                } else {
                    throw new Exception('Erro ao obter ID da importação - não foi possível recuperar');
                }
            }
            
            error_log("Registro de importação criado com ID: $importacao_id");
            
        } catch (PDOException $e) {
            error_log("Erro PDO na criação de importação: " . $e->getMessage());
            error_log("Código do erro: " . $e->getCode());
            
            // Se for erro de chave duplicada, tentar corrigir uma vez mais
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                error_log("Erro de chave duplicada detectado. Tentando corrigir novamente...");
                
                try {
                    // Forçar correção mais agressiva
                    verificarECorrigirAutoIncrement('pca_importacoes');
                    // Tentar inserir novamente
                    $stmt->execute([$resultado['arquivo'], $_SESSION['usuario_id'], $ano_pca]);
                    $importacao_id = $pdo->lastInsertId();
                    
                    if ($importacao_id <= 0) {
                        throw new Exception('ID inválido após correção');
                    }
                    
                } catch (Exception $e2) {
                    error_log("Falha definitiva ao corrigir AUTO_INCREMENT: " . $e2->getMessage());
                    error_log("Stack trace da falha: " . $e2->getTraceAsString());
                    
                    // Verificar se o problema é mesmo de AUTO_INCREMENT
                    try {
                        $check_sql = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pca_importacoes'";
                        $check_result = $pdo->query($check_sql)->fetch();
                        error_log("AUTO_INCREMENT atual após falha: " . ($check_result ? $check_result['AUTO_INCREMENT'] : 'NULL'));
                        
                        $max_sql = "SELECT MAX(id) as max_id, COUNT(*) as total FROM pca_importacoes";
                        $max_result = $pdo->query($max_sql)->fetch();
                        error_log("Estado da tabela - MAX(id): " . $max_result['max_id'] . ", Total registros: " . $max_result['total']);
                    } catch (Exception $e3) {
                        error_log("Erro ao verificar estado da tabela: " . $e3->getMessage());
                    }
                    
                    setMensagem('Erro crítico no banco de dados: ' . $e2->getMessage() . ' (verifique logs)', 'erro');
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                setMensagem('Erro ao registrar importação: ' . $e->getMessage(), 'erro');
                header('Location: dashboard.php');
                exit;
            }
        }

        // Detectar separador automaticamente
        $primeira_linha = fgets($handle);
        rewind($handle);
        $separador = ';';
        if (substr_count($primeira_linha, ',') > substr_count($primeira_linha, ';')) {
            $separador = ',';
        }

        // Ler cabeçalho
        $header = fgetcsv($handle, 0, $separador);

        // Sistema unificado: permite importação para qualquer ano usando pca_dados
        // Anos históricos (< 2025) podem ser importados mas ficam em modo somente leitura após importação

        // Processar linhas usando nova função simplificada
        $linhas_processadas = 0;
        $linhas_novas = 0;
        $linhas_atualizadas = 0;
        $linhas_ignoradas = 0;
        $dados_para_importar = [];
        $erros = [];

        while (($linha = fgetcsv($handle, 0, $separador)) !== FALSE) {
            // Limpar encoding de cada campo individualmente
            $linha = array_map(function($campo) {
                if (!is_string($campo)) return $campo;
                // Remove BOM se presente
                $campo = str_replace("\xEF\xBB\xBF", '', $campo);
                // Garante UTF-8 válido
                return mb_convert_encoding($campo, 'UTF-8', 'UTF-8');
            }, $linha);
            // Pular linhas vazias
            if (empty($linha[0])) continue;

            $linhas_processadas++;

            try {
                // Mapear dados das colunas
                $dados_linha = [
                    'numero_contratacao' => trim($linha[0] ?? ''),
                    'status_contratacao' => trim($linha[1] ?? ''),
                    'situacao_execucao' => trim($linha[2] ?? '') ?: 'Não iniciado',
                    'titulo_contratacao' => trim($linha[3] ?? ''),
                    'categoria_contratacao' => trim($linha[4] ?? ''),
                    'uasg_atual' => trim($linha[5] ?? ''),
                    'valor_total_contratacao' => processarValorMonetario($linha[6] ?? ''),
                    'data_inicio_processo' => formatarDataDB($linha[7] ?? ''),
                    'data_conclusao_processo' => formatarDataDB($linha[8] ?? ''),
                    'prazo_duracao_dias' => !empty($linha[9]) ? intval($linha[9]) : null,
                    'area_requisitante' => trim($linha[10] ?? ''),
                    'numero_dfd' => trim($linha[11] ?? ''),
                    'prioridade' => trim($linha[12] ?? ''),
                    'numero_item_dfd' => trim($linha[13] ?? ''),
                    'data_conclusao_dfd' => formatarDataDB($linha[14] ?? ''),
                    'classificacao_contratacao' => trim($linha[15] ?? ''),
                    'codigo_classe_grupo' => trim($linha[16] ?? ''),
                    'nome_classe_grupo' => trim($linha[17] ?? ''),
                    'codigo_pdm_material' => trim($linha[18] ?? ''),
                    'nome_pdm_material' => trim($linha[19] ?? ''),
                    'codigo_material_servico' => trim($linha[20] ?? ''),
                    'descricao_material_servico' => trim($linha[21] ?? ''),
                    'unidade_fornecimento' => trim($linha[22] ?? ''),
                    'valor_unitario' => processarValorMonetario($linha[23] ?? ''),
                    'quantidade' => !empty($linha[24]) ? intval($linha[24]) : null,
                    'valor_total' => processarValorMonetario($linha[25] ?? '')
                ];

                $dados_para_importar[] = $dados_linha;
                $linhas_novas++;

            } catch (Exception $e) {
                $erros[] = "Linha $linhas_processadas: " . $e->getMessage();
                continue;
            }
        }

        // Usar nova função para importar dados
        try {
            if (empty($dados_para_importar)) {
                throw new Exception('Nenhum dado válido encontrado para importação');
            }
            
            error_log("Iniciando importação de " . count($dados_para_importar) . " registros");
            $inseridos = importarPcaParaTabela($ano_pca, $dados_para_importar, $importacao_id);
            $linhas_novas = $inseridos;
            error_log("Importação concluída: $inseridos registros inseridos");
        } catch (Exception $e) {
            error_log("ERRO CRÍTICO NA IMPORTAÇÃO: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $erros[] = "Erro na importação: " . $e->getMessage();
            
            // Atualizar status da importação como erro
            $sql_erro = "UPDATE pca_importacoes SET status = 'erro', observacoes = ? WHERE id = ?";
            $stmt_erro = $pdo->prepare($sql_erro);
            $stmt_erro->execute(["Erro: " . $e->getMessage(), $importacao_id]);
        }

        fclose($handle);

        // Atualizar registro de importação com os dados finais
        $status_final = !empty($erros) ? 'erro' : 'concluido';
        $observacoes_final = !empty($erros) ? 'Importação com ' . count($erros) . ' erro(s)' : 'Importação concluída com sucesso';
        
        $sql_update = "UPDATE pca_importacoes SET 
                       status = ?, 
                       total_registros = ?, 
                       registros_novos = ?, 
                       registros_atualizados = ?, 
                       observacoes = ? 
                       WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([
            $status_final,
            $linhas_processadas,
            $linhas_novas,
            $linhas_atualizadas,
            $observacoes_final,
            $importacao_id
        ]);

        // Mensagem detalhada
        $tipo_importacao = $eh_historico ? "histórica ($ano_pca)" : "atual ($ano_pca)";
        $mensagem = "Importação PCA $tipo_importacao concluída! ";
        $mensagem .= "Processadas: $linhas_processadas | ";
        $mensagem .= "Novas: $linhas_novas | ";
        $mensagem .= "Atualizadas: $linhas_atualizadas | ";
        $mensagem .= "Sem alterações: $linhas_ignoradas";

        if (!empty($erros)) {
            $mensagem .= " | Erros: " . count($erros);
            // Log dos erros
            foreach (array_slice($erros, 0, 5) as $erro) {
                error_log("Importação PCA - $erro");
            }
        }

        registrarLog('IMPORTACAO_PCA', $mensagem, 'pca_importacoes', $importacao_id);
        setMensagem($mensagem);

        header('Location: dashboard.php');
        break;

    case 'reverter_importacao_pca':
        verificarLogin();
        
        // Verificar permissão para reverter importação (operação crítica)
        if (!temPermissao('pca_importar') || $_SESSION['usuario_nivel'] > 2) {
            setMensagem('Você não tem permissão para reverter importações.', 'erro');
            header('Location: dashboard.php?secao=importar-pca');
            exit;
        }
        verifyCSRFToken();
        
        $importacao_id = intval($_POST['importacao_id'] ?? 0);
        
        if ($importacao_id <= 0) {
            setMensagem('ID da importação inválido!', 'erro');
            header('Location: dashboard.php?secao=importar-pca');
            exit;
        }
        
        $resultado = reverterImportacaoPCA($importacao_id, $_SESSION['usuario_id']);
        
        if ($resultado['sucesso']) {
            setMensagem($resultado['mensagem'], 'sucesso');
        } else {
            setMensagem($resultado['mensagem'], 'erro');
        }
        
        header('Location: dashboard.php?secao=importar-pca');
        break;

case 'criar_licitacao':
    verificarLogin();
    
    // Verificar permissão para criar licitação
    if (!temPermissao('licitacao_criar')) {
        setMensagem('Você não tem permissão para criar licitações.', 'erro');
        header('Location: licitacao_dashboard.php');
        exit;
    }

    try {
        // DEBUG: Log todos os dados recebidos
        error_log("=== DEBUG CRIAR LICITAÇÃO ===");
        error_log("POST data: " . print_r($_POST, true));
        error_log("numero_contratacao recebido: '" . ($_POST['numero_contratacao'] ?? 'NÃO ENVIADO') . "'");
        error_log("Trimmed: '" . (isset($_POST['numero_contratacao']) ? trim($_POST['numero_contratacao']) : 'N/A') . "'");
        
        // Verificar CSRF (usando a função que já existe)
        verifyCSRFToken();

        // Validar campos obrigatórios
        $campos_obrigatorios = ['nup', 'modalidade', 'tipo', 'situacao', 'objeto', 'numero_contratacao']; // Adicionado numero_contratacao
        foreach ($campos_obrigatorios as $campo) {
            if (empty($_POST[$campo])) {
                throw new Exception("O campo '{$campo}' é obrigatório.");
            }
        }

        // Preparar dados para inserção
        $numero_contratacao_raw = $_POST['numero_contratacao'] ?? '';
        $numero_contratacao_trimmed = trim($numero_contratacao_raw);
        
        // DEBUG: Log detalhado do numero_contratacao
        error_log("Raw numero_contratacao: '" . $numero_contratacao_raw . "' (length: " . strlen($numero_contratacao_raw) . ")");
        error_log("Trimmed numero_contratacao: '" . $numero_contratacao_trimmed . "' (length: " . strlen($numero_contratacao_trimmed) . ")");
        error_log("Empty check: " . (empty($numero_contratacao_trimmed) ? 'TRUE' : 'FALSE'));
        
        $dados = [
            'nup' => trim($_POST['nup']),
            'data_entrada_dipli' => !empty($_POST['data_entrada_dipli']) ? $_POST['data_entrada_dipli'] : null,
            'resp_instrucao' => !empty($_POST['resp_instrucao']) ? trim($_POST['resp_instrucao']) : null,
            'area_demandante' => !empty($_POST['area_demandante']) ? trim($_POST['area_demandante']) : null,
            'pregoeiro' => !empty($_POST['pregoeiro']) ? trim($_POST['pregoeiro']) : null,
            'modalidade' => $_POST['modalidade'],
            'tipo' => $_POST['tipo'],
            'ano' => !empty($_POST['ano']) ? intval($_POST['ano']) : null,
            'valor_estimado' => !empty($_POST['valor_estimado']) ? floatval($_POST['valor_estimado']) : null,
            'qtd_itens' => !empty($_POST['qtd_itens']) ? intval($_POST['qtd_itens']) : null,
            'data_abertura' => !empty($_POST['data_abertura']) ? $_POST['data_abertura'] : null,
            'data_homologacao' => !empty($_POST['data_homologacao']) ? $_POST['data_homologacao'] : null,
            'valor_homologado' => !empty($_POST['valor_homologado']) ? floatval($_POST['valor_homologado']) : null,
            'qtd_homol' => !empty($_POST['qtd_homol']) ? intval($_POST['qtd_homol']) : null,
            'economia' => !empty($_POST['economia']) ? floatval($_POST['economia']) : null,
            'link' => !empty($_POST['link']) ? trim($_POST['link']) : null,
            'situacao' => $_POST['situacao'],
            'objeto' => trim($_POST['objeto']),
            'usuario_id' => $_SESSION['usuario_id'],
            'numero_contratacao' => !empty($numero_contratacao_trimmed) ? $numero_contratacao_trimmed : null,
        ];
        
        // DEBUG: Log dados finais
        error_log("Dados finais para inserção:");
        error_log("numero_contratacao final: '" . ($dados['numero_contratacao'] ?? 'NULL') . "'");

        // Validar formato do NUP
        if (!preg_match('/^\d{5}\.\d{6}\/\d{4}-\d{2}$/', $dados['nup'])) {
            throw new Exception("Formato do NUP inválido. Use: xxxxx.xxxxxx/xxxx-xx");
        }

        // Verificar se NUP já existe
        $stmt = $pdo->prepare("SELECT id FROM licitacoes WHERE nup = ?");
        $stmt->execute([$dados['nup']]);
        if ($stmt->fetch()) {
            throw new Exception("Já existe uma licitação com este NUP.");
        }

        // Remover a lógica de pca_dados_id se não for mais necessária para manter FKs,
        // pois estamos salvando os dados relevantes do PCA diretamente na licitação.
        // Se ainda precisar de um FK para outras lógicas, descomente e ajuste.
        /*
        if (!empty($_POST['numero_contratacao'])) {
            $stmt = $pdo->prepare("SELECT id FROM pca_dados WHERE numero_contratacao = ?");
            $stmt->execute([$_POST['numero_contratacao']]);
            $pca_dados = $stmt->fetch();
            if ($pca_dados) {
                $dados['pca_dados_id'] = $pca_dados['id'];
            } else {
                $dados['pca_dados_id'] = null; // Ou trate como erro se for obrigatório
            }
        } else {
            $dados['pca_dados_id'] = null;
        }
        */

        // Calcular economia automaticamente se ambos os valores estiverem preenchidos
        if ($dados['valor_estimado'] && $dados['valor_homologado']) {
            $dados['economia'] = $dados['valor_estimado'] - $dados['valor_homologado'];
        }

        // Preparar SQL de inserção
        $campos = array_keys($dados);
        $placeholders = ':' . implode(', :', $campos);
        $sql = "INSERT INTO licitacoes (" . implode(', ', $campos) . ") VALUES (" . $placeholders . ")";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($dados);

        // Log da ação (usando a assinatura correta da função)
        $licitacao_id = $pdo->lastInsertId();
        registrarLog('CRIAR_LICITACAO', "Criou licitação: {$dados['nup']}", 'licitacoes', $licitacao_id);

        setMensagem("Licitação criada com sucesso!");
        header('Location: licitacao_dashboard.php');
        exit;

    } catch (Exception $e) {
        // Log do erro com mais detalhes
        error_log("Erro ao criar licitação: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());

        // Para debug, mostrar na tela
        setMensagem('Erro ao criar licitação: ' . $e->getMessage(), 'erro');
        header('Location: licitacao_dashboard.php');
        exit;
    } catch (Error $e) {
        // Capturar erros fatais também
        error_log("Erro fatal ao criar licitação: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());

        // Para debug, mostrar na tela
        setMensagem('Erro fatal ao processar requisição', 'erro');
        header('Location: licitacao_dashboard.php');
        exit;
    }

case 'editar_licitacao':
        verificarLogin();
        
        // Verificar permissão para editar licitação
        if (!temPermissao('licitacao_editar')) {
            echo json_encode([
                'success' => false,
                'message' => 'Você não tem permissão para editar licitações.'
            ]);
            exit;
        }

        header('Content-Type: application/json');

        $response = ['success' => false, 'message' => ''];

        try {

            $pdo = conectarDB();
 
            // Validar ID

            if (empty($_POST['id'])) {

                throw new Exception('ID da licitação não fornecido');

            }

            // Validar NUP

            if (!validarNUP($_POST['nup'])) {

                throw new Exception('Formato do NUP inválido! Use: xxxxx.xxxxxx/xxxx-xx');

            }
 
            // CORREÇÃO: Lógica para buscar o pca_dados_id a partir do numero_contratacao

            $pca_dados_id = null;

            if (!empty($_POST['numero_contratacao'])) {

                $stmt_pca = $pdo->prepare("SELECT id FROM pca_dados WHERE numero_contratacao = ? LIMIT 1");

                $stmt_pca->execute([trim($_POST['numero_contratacao'])]);

                $pca_achado = $stmt_pca->fetch();

                if ($pca_achado) {

                    $pca_dados_id = $pca_achado['id'];

                }

            }

            // Processar dados

            $id = intval($_POST['id']);

            $nup = limpar($_POST['nup']);

            $data_entrada_dipli = formatarDataDB($_POST['data_entrada_dipli']);

            $resp_instrucao = limpar($_POST['resp_instrucao']);

            $area_demandante = limpar($_POST['area_demandante']);

            $pregoeiro = limpar($_POST['pregoeiro']);

            $modalidade = $_POST['modalidade'];

            $tipo = $_POST['tipo'];

            $numero = !empty($_POST['numero']) ? intval($_POST['numero']) : null;

            $ano = !empty($_POST['ano']) ? intval($_POST['ano']) : null;

            $valor_estimado = !empty($_POST['valor_estimado']) ? formatarValorDB($_POST['valor_estimado']) : null;

            $data_abertura = formatarDataDB($_POST['data_abertura']);

            $situacao = $_POST['situacao'];

            $objeto = limpar($_POST['objeto']);

            // Campos de homologação

            $data_homologacao = null;

            $qtd_homol = null;

            $valor_homologado = null;

            $economia = null;

            if ($situacao === 'HOMOLOGADO') {

                $data_homologacao = formatarDataDB($_POST['data_homologacao']);

                $qtd_homol = !empty($_POST['qtd_homol']) ? intval($_POST['qtd_homol']) : null;

                $valor_homologado = !empty($_POST['valor_homologado']) ? formatarValorDB($_POST['valor_homologado']) : null;

                $economia = !empty($_POST['economia']) ? formatarValorDB($_POST['economia']) : null;

            }

            // CORREÇÃO: Adicionado numero_contratacao ao UPDATE
            $numero_contratacao = !empty($_POST['numero_contratacao']) ? trim($_POST['numero_contratacao']) : null;

            $sql = "UPDATE licitacoes SET 
                    nup = ?, data_entrada_dipli = ?, resp_instrucao = ?, area_demandante = ?,
                    pregoeiro = ?, modalidade = ?, tipo = ?, numero = ?, ano = ?,
                    valor_estimado = ?, data_abertura = ?, situacao = ?, objeto = ?,
                    data_homologacao = ?, qtd_homol = ?, valor_homologado = ?, economia = ?,
                    numero_contratacao = ?, pca_dados_id = ?
                    WHERE id = ?";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $nup, $data_entrada_dipli, $resp_instrucao, $area_demandante,
                $pregoeiro, $modalidade, $tipo, $numero, $ano,
                $valor_estimado, $data_abertura, $situacao, $objeto,
                $data_homologacao, $qtd_homol, $valor_homologado, $economia,
                $numero_contratacao, $pca_dados_id, // CORREÇÃO: Salvando numero_contratacao
                $id
            ]);

            registrarLog('EDITAR_LICITACAO', "Editou licitação ID: $id - NUP: $nup", 'licitacoes', $id);

            $response['success'] = true;

            $response['message'] = 'Licitação atualizada com sucesso!';

        } catch (Exception $e) {

            $response['success'] = false;

            $response['message'] = $e->getMessage();

        }

        echo json_encode($response);

        break;
    }
?>