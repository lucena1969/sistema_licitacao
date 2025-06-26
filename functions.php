<?php
require_once 'config.php';
// Função para verificar e corrigir AUTO_INCREMENT
function verificarECorrigirAutoIncrement($tabela) {
    try {
        $pdo = conectarDB();
        
        // Verificar AUTO_INCREMENT atual
        $sql = "SELECT AUTO_INCREMENT FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tabela]);
        $result = $stmt->fetch();
        
        $auto_increment_atual = $result ? $result['AUTO_INCREMENT'] : 0;
        error_log("AUTO_INCREMENT atual da tabela $tabela: $auto_increment_atual");
        
        // Obter o maior ID atual
        $sql_max = "SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM $tabela";
        $stmt_max = $pdo->prepare($sql_max);
        $stmt_max->execute();
        $max_result = $stmt_max->fetch();
        $next_id = $max_result['next_id'];
        
        error_log("Próximo ID calculado para tabela $tabela: $next_id");
        
        // Sempre corrigir se o AUTO_INCREMENT for <= próximo ID necessário
        if ($auto_increment_atual <= 0 || $auto_increment_atual < $next_id) {
            error_log("Corrigindo AUTO_INCREMENT da tabela $tabela de $auto_increment_atual para $next_id");
            
            // Corrigir AUTO_INCREMENT
            $sql_fix = "ALTER TABLE $tabela AUTO_INCREMENT = $next_id";
            $pdo->exec($sql_fix);
            
            // Verificar se a correção funcionou
            $stmt->execute([$tabela]);
            $verificacao = $stmt->fetch();
            $novo_auto_increment = $verificacao ? $verificacao['AUTO_INCREMENT'] : 0;
            
            error_log("AUTO_INCREMENT da tabela $tabela após correção: $novo_auto_increment");
            return $novo_auto_increment;
        }
        
        return $auto_increment_atual;
        
    } catch (Exception $e) {
        error_log("Erro ao verificar AUTO_INCREMENT da tabela $tabela: " . $e->getMessage());
        return false;
    }
}

// Função para agrupar áreas
function agruparArea($area) {
    if (empty($area)) return 'SEM ÁREA';
    
    $area = trim($area);
    
    // Casos especiais - unificar variações
    if (strpos($area, 'GM') === 0) {
        return 'GM.';
    }
    
    // Se tem ponto, pega a parte antes do ponto + ponto
    if (strpos($area, '.') !== false) {
        $partes = explode('.', $area);
        return trim($partes[0]) . '.';
    }
    
    return $area;
}

// Função para limpar encoding de strings
function limparEncoding($texto) {
    if (!is_string($texto)) return $texto;
    
    // Remove BOM UTF-8
    $texto = str_replace("\xEF\xBB\xBF", '', $texto);
    
    // Remove caracteres de controle
    $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $texto);
    
    // Garante UTF-8 válido
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'UTF-8//IGNORE');
    }
    
    return trim($texto);
}

// Verificar se usuário está logado
function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: index.php');
        exit;
    }
}

// Sanitizar entrada
function limpar($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Formatar data para exibição
function formatarData($data) {
    if (empty($data)) return '';
    return date('d/m/Y', strtotime($data));
}

// Formatar data para banco - CORRIGIDA
function formatarDataDB($data) {
    if (empty($data)) return null;
    
    // Se já está no formato do banco (Y-m-d)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return $data;
    }
    
    // Tentar diferentes formatos
    $formatos = [
        'd/m/Y',     // 31/12/2024
        'd-m-Y',     // 31-12-2024
        'Y-m-d',     // 2024-12-31
        'd/m/y',     // 31/12/24
        'd-m-y',     // 31-12-24
        'm/d/Y',     // 12/31/2024 (formato americano)
        'Y/m/d',     // 2024/12/31
    ];
    
    foreach ($formatos as $formato) {
        $dateTime = DateTime::createFromFormat($formato, $data);
        if ($dateTime && $dateTime->format($formato) === $data) {
            return $dateTime->format('Y-m-d');
        }
    }
    
    // Tentar usar strtotime como último recurso
    $timestamp = strtotime($data);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}

// Formatar valor monetário para exibição
function formatarMoeda($valor) {
    if (is_null($valor) || $valor === '') return 'R$ 0,00';
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Formatar valor para banco - CORRIGIDA
function formatarValorDB($valor) {
    if (empty($valor)) return null;
    
    // Remover caracteres não numéricos exceto vírgula e ponto
    $valor = preg_replace('/[^\d,.-]/', '', $valor);
    
    // Se vazio após limpeza, retorna null
    if (empty($valor)) {
        return null;
    }
    
    // Se tem vírgula e ponto, assumir formato brasileiro (1.234,56)
    if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
        // Formato brasileiro: 1.234.567,89
        $valor = str_replace('.', '', $valor); // Remove pontos de milhares
        $valor = str_replace(',', '.', $valor); // Converte vírgula para ponto decimal
    } elseif (strpos($valor, ',') !== false) {
        // Só tem vírgula - pode ser decimal brasileiro ou separador de milhares
        $partes = explode(',', $valor);
        if (count($partes) == 2 && strlen(end($partes)) <= 2) {
            // Última parte tem 2 dígitos ou menos - é decimal brasileiro
            $valor = str_replace(',', '.', $valor);
        } else {
            // É separador de milhares - remove
            $valor = str_replace(',', '', $valor);
        }
    }
    
    // Converter para float
    $valor_float = floatval($valor);
    
    // Validar se é um valor válido
    if ($valor_float < 0) {
        return null;
    }
    
    return $valor_float;
}

// Gerar mensagem de alerta
function setMensagem($mensagem, $tipo = 'success') {
    $_SESSION['mensagem'] = $mensagem;
    $_SESSION['tipo_mensagem'] = $tipo;
}

// Exibir mensagem de alerta
function getMensagem() {
    if (isset($_SESSION['mensagem'])) {
        $tipo = $_SESSION['tipo_mensagem'] ?? 'success';
        $classe = $tipo === 'success' ? 'sucesso' : 'erro';
        $id_mensagem = 'mensagem_' . uniqid();
        $mensagem = '<div id="' . $id_mensagem . '" class="mensagem ' . $classe . ' auto-hide-message">' . $_SESSION['mensagem'] . '</div>';
        unset($_SESSION['mensagem']);
        unset($_SESSION['tipo_mensagem']);
        return $mensagem;
    }
    return '';
}

// Validar formato NUP
function validarNUP($nup) {
    $pattern = '/^\d{5}\.\d{6}\/\d{4}-\d{2}$/';
    return preg_match($pattern, $nup);
}

// Validar formato Item PGC
function validarItemPGC($item) {
    $pattern = '/^\d{4}\/\d{4}$/';
    return preg_match($pattern, $item);
}

// Função para abreviar valores grandes
function abreviarValor($valor) {
    if (is_null($valor) || $valor === '') return '0';
    
    if ($valor >= 1000000000) {
        return number_format($valor / 1000000000, 1, ',', '.') . 'B';
    } elseif ($valor >= 1000000) {
        return number_format($valor / 1000000, 1, ',', '.') . 'M';
    } elseif ($valor >= 1000) {
        return number_format($valor / 1000, 1, ',', '.') . 'K';
    } else {
        return number_format($valor, 0, ',', '.');
    }
}

// Função para processar upload de arquivo - SIMPLIFICADA E FUNCIONAL
function processarUpload($arquivo, $pasta = null) {
    // Definir pasta padrão (mantendo compatibilidade com sistema existente)
    if ($pasta === null) {
        $pasta = __DIR__ . '/storage/uploads';
    }
    
    // Se foi passada uma pasta relativa simples, converter para o caminho correto
    if ($pasta === 'uploads/' || $pasta === 'uploads') {
        $pasta = __DIR__ . '/storage/uploads';
    }
    
    // Verificar se é um arquivo válido
    if (!isset($arquivo['tmp_name']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
        return ['sucesso' => false, 'mensagem' => 'Erro no upload do arquivo'];
    }
    
    // Verificar tamanho (max 10MB)
    if ($arquivo['size'] > 10485760) {
        return ['sucesso' => false, 'mensagem' => 'Arquivo muito grande (máximo 10MB)'];
    }
    
    // Verificar se arquivo não está vazio
    if ($arquivo['size'] < 1) {
        return ['sucesso' => false, 'mensagem' => 'Arquivo vazio não é permitido'];
    }
    
    // Verificar extensão
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $extensoesPermitidas = ['csv', 'xlsx', 'xls'];
    
    if (!in_array($extensao, $extensoesPermitidas)) {
        return ['sucesso' => false, 'mensagem' => 'Apenas arquivos CSV, XLS ou XLSX são permitidos'];
    }
    
    // Criar pasta se não existir
    if (!file_exists($pasta)) {
        if (!mkdir($pasta, 0755, true)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao criar diretório de upload'];
        }
    }
    
    // Verificar permissões do diretório
    if (!is_writable($pasta)) {
        return ['sucesso' => false, 'mensagem' => 'Diretório sem permissão de escrita'];
    }
    
    // Gerar nome único e seguro
    $nomeArquivo = uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . $extensao;
    $caminhoCompleto = $pasta . '/' . $nomeArquivo;
    
    // Mover arquivo
    if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
        chmod($caminhoCompleto, 0644);
        return ['sucesso' => true, 'arquivo' => $nomeArquivo, 'caminho' => $caminhoCompleto];
    } else {
        return ['sucesso' => false, 'mensagem' => 'Erro ao salvar arquivo'];
    }
}

// Função específica para processar valores monetários da importação
function processarValorMonetario($valor) {
    if (empty($valor)) {
        return null;
    }
    
    // Remover caracteres não numéricos exceto vírgula e ponto
    $valor = preg_replace('/[^\d,.]/', '', $valor);
    
    // Se vazio após limpeza, retorna null
    if (empty($valor)) {
        return null;
    }
    
    // Se tem vírgula e ponto, assumir formato brasileiro (1.234,56)
    if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
        // Formato brasileiro: 1.234.567,89
        $valor = str_replace('.', '', $valor); // Remove pontos de milhares
        $valor = str_replace(',', '.', $valor); // Converte vírgula para ponto decimal
    } elseif (strpos($valor, ',') !== false) {
        // Só tem vírgula - pode ser decimal brasileiro ou separador de milhares
        $partes = explode(',', $valor);
        if (count($partes) == 2 && strlen(end($partes)) <= 2) {
            // Última parte tem 2 dígitos ou menos - é decimal brasileiro
            $valor = str_replace(',', '.', $valor);
        } else {
            // É separador de milhares - remove
            $valor = str_replace(',', '', $valor);
        }
    }
    
    // Converter para float
    $valor_float = floatval($valor);
    
    // Validar se é um valor válido
    if ($valor_float < 0) {
        return null;
    }
    
    return $valor_float;
}

// ========================================
// FUNÇÕES DE SEGURANÇA - PROTEÇÃO CSRF
// ========================================

// Gerar token CSRF seguro
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validar token CSRF
function validateCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Obter HTML do input CSRF para formulários
function getCSRFInput() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// Verificar token CSRF em requisições POST
function verifyCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            http_response_code(403);
            die('Token CSRF inválido. Requisição bloqueada por segurança.');
        }
    }
}

// ========================================
// FUNÇÕES ESPECÍFICAS DO PCA
// ========================================

// Buscar histórico de importações de PCA
function buscarHistoricoImportacoes($ano = null, $limite = 10) {
    try {
        $pdo = conectarDB();
        
        $sql = "SELECT 
                    pi.id,
                    pi.nome_arquivo,
                    pi.ano_pca,
                    pi.status,
                    pi.total_registros,
                    pi.registros_novos,
                    pi.registros_atualizados,
                    pi.observacoes,
                    pi.criado_em,
                    u.nome as usuario_nome
                FROM pca_importacoes pi
                LEFT JOIN usuarios u ON pi.usuario_id = u.id";
        
        $params = [];
        
        if ($ano) {
            $sql .= " WHERE pi.ano_pca = ?";
            $params[] = $ano;
        }
        
        $sql .= " ORDER BY pi.criado_em DESC LIMIT ?";
        $params[] = $limite;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Erro ao buscar histórico de importações: " . $e->getMessage());
        return [];
    }
}

// Reverter importação PCA - remove todos os dados de uma importação específica
function reverterImportacaoPCA($importacao_id, $usuario_id) {
    try {
        $pdo = conectarDB();
        $pdo->beginTransaction();
        
        // Verificar se a importação existe e buscar informações
        $sql_check = "SELECT id, nome_arquivo, ano_pca, status, total_registros FROM pca_importacoes WHERE id = ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$importacao_id]);
        $importacao = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$importacao) {
            throw new Exception("Importação não encontrada.");
        }
        
        // Verificar se é ano atual (editável) ou histórico
        $eh_historico = ($importacao['ano_pca'] <= 2024);
        $tabela_dados = $eh_historico ? 'pca_historico_anos' : 'pca_dados';
        
        // Contar registros que serão removidos
        $sql_count = "SELECT COUNT(*) as total FROM $tabela_dados WHERE importacao_id = ?";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute([$importacao_id]);
        $total_removidos = $stmt_count->fetch(PDO::FETCH_COLUMN);
        
        // Remover todos os dados relacionados à importação
        $sql_delete_dados = "DELETE FROM $tabela_dados WHERE importacao_id = ?";
        $stmt_delete_dados = $pdo->prepare($sql_delete_dados);
        $stmt_delete_dados->execute([$importacao_id]);
        
        // Atualizar status da importação para "removido"
        $sql_update_importacao = "UPDATE pca_importacoes SET 
                                  status = 'removido',
                                  observacoes = CONCAT(COALESCE(observacoes, ''), ' | REVERTIDA em ', NOW(), ' pelo usuário ID: ', ?)
                                  WHERE id = ?";
        $stmt_update_importacao = $pdo->prepare($sql_update_importacao);
        $stmt_update_importacao->execute([$usuario_id, $importacao_id]);
        
        // Registrar log da operação
        $mensagem_log = "Importação PCA revertida - ID: $importacao_id | Arquivo: {$importacao['nome_arquivo']} | Ano: {$importacao['ano_pca']} | $total_removidos registros removidos";
        registrarLog('REVERSAO_PCA', $mensagem_log, 'pca_importacoes', $importacao_id);
        
        $pdo->commit();
        
        return [
            'sucesso' => true,
            'mensagem' => "Importação revertida com sucesso! $total_removidos registros foram removidos.",
            'registros_removidos' => $total_removidos,
            'arquivo' => $importacao['nome_arquivo']
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao reverter importação PCA: " . $e->getMessage());
        return [
            'sucesso' => false,
            'mensagem' => 'Erro ao reverter importação: ' . $e->getMessage()
        ];
    }
}

// ========================================
// FUNÇÕES DE SEGURANÇA E LOGIN
// ========================================

// Verificar se login está bloqueado
function isLoginBloqueado() {
    if (isset($_SESSION['login_blocked_until'])) {
        if (time() < $_SESSION['login_blocked_until']) {
            return true;
        } else {
            unset($_SESSION['login_blocked_until']);
        }
    }
    return false;
}

// Registrar tentativas de login
function registrarTentativaLogin($email, $sucesso = false, $motivo = '') {
    // Função simplificada - não registra no banco temporariamente
    return true;
}

// ========================================
// SISTEMA DE NÍVEIS - TEMPORARIAMENTE SIMPLIFICADO
// ========================================

// Verificar se usuário tem permissão específica
function temPermissao($permissao, $usuario_id = null) {
    $nivel = $_SESSION['usuario_nivel'] ?? 1;
    
    // Permissões por nível - ATUALIZADO
    $permissoes = [
        1 => ['*'], // Coordenador - acesso total
        2 => [ // DIPLAN - Apenas edição em PLANEJAMENTO, visualização em licitações
            'pca_importar', 'pca_visualizar', 'pca_relatorios', 'pca_exportar', 'pca_editar',
            'licitacao_visualizar', 'licitacao_exportar', 'licitacao_relatorios',
            'risco_visualizar', 'risco_exportar'
        ],
        3 => [ // DIPLI - Apenas edição em LICITAÇÕES, visualização em planejamento
            'licitacao_criar', 'licitacao_editar', 'licitacao_visualizar', 'licitacao_exportar', 'licitacao_relatorios',
            'pca_visualizar', 'pca_exportar', 'pca_relatorios',
            'risco_visualizar', 'risco_criar', 'risco_editar'
        ],
        4 => [ // Visitante - apenas visualização e exportação
            'pca_visualizar', 'pca_exportar', 'pca_relatorios',
            'licitacao_visualizar', 'licitacao_exportar', 'licitacao_relatorios',
            'risco_visualizar', 'risco_exportar', 'risco_relatorios'
        ]
    ];
    
    // Coordenador tem acesso total
    if ($nivel == 1) return true;
    
    // Verificar se o nível tem a permissão específica
    return in_array($permissao, $permissoes[$nivel] ?? []);
}

// Verificar se usuário tem acesso a módulo
function temAcessoModulo($modulo, $usuario_id = null) {
    return true; // Temporariamente sempre true
}

// Obter nível de acesso do usuário
function getNivelUsuario($usuario_id = null) {
    return [
        'nivel_acesso' => 1,
        'tipo_usuario' => $_SESSION['usuario_tipo'] ?? 'admin',
        'departamento' => 'CGLIC'
    ];
}

// Verificar se usuário é coordenador
function isCoordenador($usuario_id = null) {
    return true;
}

// Verificar se usuário é DIPLAN
function isDiplan($usuario_id = null) {
    return true;
}

// Verificar se usuário é DIPLI
function isDipli($usuario_id = null) {
    return ($_SESSION['usuario_nivel'] ?? 1) == 3;
}

// Verificar se usuário é Visitante
function isVisitante($usuario_id = null) {
    return ($_SESSION['usuario_nivel'] ?? 1) == 4;
}

// Middleware de verificação de permissão
function verificarPermissao($permissao, $redirecionar = true) {
    return true;
}

// Middleware de verificação de módulo
function verificarAcessoModulo($modulo, $redirecionar = true) {
    return true;
}

// Registrar log de acesso/ação
function registrarLog($acao, $detalhes, $tabela = null, $registro_id = null) {
    return true; // Não registrar logs temporariamente
}

// Obter nome do nível de acesso
function getNomeNivel($nivel_acesso) {
    switch ($nivel_acesso) {
        case 1: return 'Coordenador';
        case 2: return 'DIPLAN';
        case 3: return 'DIPLI';
        case 4: return 'Visitante';
        default: return 'Admin';
    }
}

// Atualizar informações de login na sessão
function atualizarSessaoUsuario($usuario_id) {
    try {
        $pdo = conectarDB();
        $sql = "SELECT * FROM usuarios WHERE id = ? AND ativo = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'];
            $_SESSION['usuario_nivel'] = $usuario['nivel_acesso'] ?? 3;
            $_SESSION['usuario_departamento'] = $usuario['departamento'] ?? 'CGLIC';
            $_SESSION['total_permissoes'] = 0;
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// =====================================================
// FUNÇÕES PARA TABELAS PCA POR ANO
// =====================================================

/**
 * Obter nome da tabela PCA baseado no ano
 * Agora sempre retorna 'pca_dados' - usando apenas uma tabela unificada para todos os anos
 */
function getPcaTableName($ano) {
    return 'pca_dados';
}

/**
 * Verificar se um ano é histórico (somente leitura)
 * Anos anteriores a 2025 são considerados históricos, mas ainda podem ser importados
 * A diferença é que anos históricos só permitem visualização após importação
 */
function isAnoHistorico($ano) {
    return $ano < 2025;
}

/**
 * Obter dados do PCA por ano
 * Agora filtra por importação do ano específico na tabela unificada pca_dados
 */
function getPcaDataByYear($ano, $limite = 50, $offset = 0) {
    $pdo = conectarDB();
    $tabela = getPcaTableName($ano);
    
    // Obter IDs das importações do ano
    $importacoes_sql = "SELECT id FROM pca_importacoes WHERE ano_pca = ?";
    $importacoes_stmt = $pdo->prepare($importacoes_sql);
    $importacoes_stmt->execute([$ano]);
    $importacoes_ids = $importacoes_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($importacoes_ids)) {
        return []; // Sem dados para o ano
    }
    
    $where_ano = "importacao_id IN (" . implode(',', $importacoes_ids) . ")";
    $sql = "SELECT * FROM {$tabela} WHERE {$where_ano} ORDER BY numero_contratacao LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limite, $offset]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Função de debug para imprimir estrutura de dados (somente em modo debug)
 */
function debugPCA($dados, $titulo = "Debug PCA") {
    if (DEBUG_MODE) {
        error_log("=== $titulo ===");
        error_log("Tipo: " . gettype($dados));
        if (is_array($dados)) {
            error_log("Total de elementos: " . count($dados));
            if (!empty($dados)) {
                error_log("Primeiro elemento: " . print_r(array_slice($dados, 0, 1), true));
                error_log("Chaves do primeiro elemento: " . implode(", ", array_keys($dados[0])));
            }
        } else {
            error_log("Valor: " . print_r($dados, true));
        }
        error_log("=== Fim $titulo ===");
    }
}

/**
 * Validar dados de linha PCA antes da importação
 */
function validarDadosLinhaPCA($linha) {
    $erros = [];
    
    // Validações essenciais
    if (empty($linha['numero_contratacao'])) {
        $erros[] = "Número de contratação é obrigatório";
    }
    
    // Validar valores monetários
    if (isset($linha['valor_total_contratacao']) && $linha['valor_total_contratacao'] !== null) {
        if (!is_numeric($linha['valor_total_contratacao']) || $linha['valor_total_contratacao'] < 0) {
            $erros[] = "Valor total de contratação inválido";
        }
    }
    
    if (isset($linha['valor_unitario']) && $linha['valor_unitario'] !== null) {
        if (!is_numeric($linha['valor_unitario']) || $linha['valor_unitario'] < 0) {
            $erros[] = "Valor unitário inválido";
        }
    }
    
    if (isset($linha['valor_total']) && $linha['valor_total'] !== null) {
        if (!is_numeric($linha['valor_total']) || $linha['valor_total'] < 0) {
            $erros[] = "Valor total inválido";
        }
    }
    
    // Validar quantidade
    if (isset($linha['quantidade']) && $linha['quantidade'] !== null) {
        if (!is_numeric($linha['quantidade']) || $linha['quantidade'] < 0) {
            $erros[] = "Quantidade inválida";
        }
    }
    
    // Validar prazo
    if (isset($linha['prazo_duracao_dias']) && $linha['prazo_duracao_dias'] !== null) {
        if (!is_numeric($linha['prazo_duracao_dias']) || $linha['prazo_duracao_dias'] < 0) {
            $erros[] = "Prazo de duração inválido";
        }
    }
    
    // Validar tamanhos de campos
    if (isset($linha['numero_contratacao']) && strlen($linha['numero_contratacao']) > 50) {
        $erros[] = "Número de contratação muito longo (máx 50 caracteres)";
    }
    
    if (isset($linha['titulo_contratacao']) && strlen($linha['titulo_contratacao']) > 500) {
        $erros[] = "Título de contratação muito longo (máx 500 caracteres)";
    }
    
    if (isset($linha['descricao_material_servico']) && strlen($linha['descricao_material_servico']) > 1000) {
        $erros[] = "Descrição material/serviço muito longa (máx 1000 caracteres)";
    }
    
    return $erros;
}

/**
 * Importar dados PCA para ano específico
 */
function importarPcaParaTabela($ano, $dados, $importacao_id) {
    $pdo = conectarDB();
    $tabela = getPcaTableName($ano);
    
    // Debug dos dados recebidos
    debugPCA($dados, "Dados recebidos para importação - Ano: $ano");
    
    // Sistema unificado: permite importação para qualquer ano usando apenas pca_dados
    // Anos históricos (< 2025) são importados normalmente mas ficam protegidos após importação
    $eh_historico = isAnoHistorico($ano);
    if ($eh_historico) {
        error_log("Importando dados históricos para o ano $ano - dados ficarão protegidos contra edição");
    }
    
    // Verificar e corrigir AUTO_INCREMENT antes da importação
    $auto_increment_result = verificarECorrigirAutoIncrement($tabela);
    if ($auto_increment_result === false) {
        error_log("Aviso: Não foi possível verificar AUTO_INCREMENT da tabela $tabela");
    } else {
        error_log("AUTO_INCREMENT da tabela $tabela verificado/corrigido: $auto_increment_result");
    }
    
    $inseridos = 0;
    $erros_detalhados = [];
    
    // Log informações básicas
    error_log("Iniciando importação PCA - Ano: $ano, Tabela: $tabela, Total de registros: " . count($dados));
    
    foreach ($dados as $index => $linha) {
        try {
            // Validar dados da linha antes da inserção
            $erros_validacao = validarDadosLinhaPCA($linha);
            if (!empty($erros_validacao)) {
                throw new Exception("Linha " . ($index + 1) . ": " . implode(", ", $erros_validacao));
            }
            
            // Agora sempre usa pca_dados com importacao_id
            // Verificar se já existe registro com mesmo numero_contratacao e numero_dfd
            $check_sql = "SELECT COUNT(*) FROM {$tabela} WHERE numero_contratacao = ? AND numero_dfd = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([
                $linha['numero_contratacao'] ?? null,
                $linha['numero_dfd'] ?? null
            ]);
            
            if ($check_stmt->fetchColumn() > 0) {
                // Registro já existe - pular para evitar duplicação
                continue;
            }
            
            $sql = "INSERT INTO {$tabela} (
                importacao_id, numero_contratacao, status_contratacao, situacao_execucao,
                titulo_contratacao, categoria_contratacao, uasg_atual, valor_total_contratacao,
                data_inicio_processo, data_conclusao_processo, prazo_duracao_dias,
                area_requisitante, numero_dfd, prioridade, numero_item_dfd,
                data_conclusao_dfd, classificacao_contratacao, codigo_classe_grupo,
                nome_classe_grupo, codigo_pdm_material, nome_pdm_material,
                codigo_material_servico, descricao_material_servico, unidade_fornecimento,
                valor_unitario, quantidade, valor_total
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $importacao_id,
                $linha['numero_contratacao'] ?? null,
                $linha['status_contratacao'] ?? null,
                $linha['situacao_execucao'] ?? 'Não iniciado',
                $linha['titulo_contratacao'] ?? null,
                $linha['categoria_contratacao'] ?? null,
                $linha['uasg_atual'] ?? null,
                $linha['valor_total_contratacao'] ?? null,
                $linha['data_inicio_processo'] ?? null,
                $linha['data_conclusao_processo'] ?? null,
                $linha['prazo_duracao_dias'] ?? null,
                $linha['area_requisitante'] ?? null,
                $linha['numero_dfd'] ?? null,
                $linha['prioridade'] ?? null,
                $linha['numero_item_dfd'] ?? null,
                $linha['data_conclusao_dfd'] ?? null,
                $linha['classificacao_contratacao'] ?? null,
                $linha['codigo_classe_grupo'] ?? null,
                $linha['nome_classe_grupo'] ?? null,
                $linha['codigo_pdm_material'] ?? null,
                $linha['nome_pdm_material'] ?? null,
                $linha['codigo_material_servico'] ?? null,
                $linha['descricao_material_servico'] ?? null,
                $linha['unidade_fornecimento'] ?? null,
                $linha['valor_unitario'] ?? null,
                $linha['quantidade'] ?? null,
                $linha['valor_total'] ?? null
            ];
            
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $inseridos++;
            } else {
                $erros_detalhados[] = "Erro ao executar statement para contratação: " . ($linha['numero_contratacao'] ?? 'N/A');
            }
            
        } catch (PDOException $e) {
            $erro_msg = "Erro PDO para contratação " . ($linha['numero_contratacao'] ?? 'N/A') . ": " . $e->getMessage();
            error_log($erro_msg);
            $erros_detalhados[] = $erro_msg;
        } catch (Exception $e) {
            $erro_msg = "Erro geral para contratação " . ($linha['numero_contratacao'] ?? 'N/A') . ": " . $e->getMessage();
            error_log($erro_msg);
            $erros_detalhados[] = $erro_msg;
        }
    }
    
    // Se houver erros, logar e lançar exceção com detalhes
    if (!empty($erros_detalhados)) {
        $erro_completo = "Erros durante importação: " . implode("; ", array_slice($erros_detalhados, 0, 3));
        error_log("Importação PCA - Total de erros: " . count($erros_detalhados) . " - Primeiros erros: " . $erro_completo);
        
        // Se mais de 50% das linhas falharam, lançar exceção
        if (count($erros_detalhados) > (count($dados) / 2)) {
            throw new Exception("Muitos erros na importação: " . $erro_completo);
        }
    }
    
    return $inseridos;
}

/**
 * Função para testar conectividade e estrutura do banco
 */
function testarBancoPCA($ano = 2025) {
    try {
        $pdo = conectarDB();
        $tabela = getPcaTableName($ano);
        
        // Verificar se a tabela existe
        $sql_check = "SHOW TABLES LIKE ?";
        $stmt = $pdo->prepare($sql_check);
        $stmt->execute([$tabela]);
        $tabela_existe = $stmt->fetch();
        
        if (!$tabela_existe) {
            return ['sucesso' => false, 'erro' => "Tabela $tabela não existe"];
        }
        
        // Verificar estrutura da tabela
        $sql_desc = "DESCRIBE $tabela";
        $stmt = $pdo->prepare($sql_desc);
        $stmt->execute();
        $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Verificar colunas essenciais
        $colunas_essenciais = ['id', 'numero_contratacao', 'titulo_contratacao'];
        $colunas_faltando = array_diff($colunas_essenciais, $colunas);
        
        if (!empty($colunas_faltando)) {
            return ['sucesso' => false, 'erro' => "Colunas faltando: " . implode(', ', $colunas_faltando)];
        }
        
        // Testar inserção simples
        if ($tabela == 'pca_dados') {
            $sql_test = "SELECT COUNT(*) FROM $tabela WHERE importacao_id IS NOT NULL";
        } else {
            $sql_test = "SELECT COUNT(*) FROM $tabela";
        }
        $stmt = $pdo->prepare($sql_test);
        $stmt->execute();
        $total = $stmt->fetchColumn();
        
        return [
            'sucesso' => true, 
            'tabela' => $tabela, 
            'total_registros' => $total,
            'colunas' => count($colunas)
        ];
        
    } catch (Exception $e) {
        return ['sucesso' => false, 'erro' => $e->getMessage()];
    }

}

/**
 * Soma os dias de andamento para cada unidade a partir de um array de andamentos.
 * Cada item deve conter as chaves 'unidade' e 'dias'.
 */
function calcularDiasPorUnidade(array $andamentos) {
    $totais = [];
    foreach ($andamentos as $item) {
        if (!isset($item['unidade']) || !isset($item['dias'])) {
            continue;
        }
        $unidade = $item['unidade'];
        $dias = (int)$item['dias'];
        if (!isset($totais[$unidade])) {
            $totais[$unidade] = 0;
        }
        $totais[$unidade] += $dias;
    }
    return $totais;
}

/**
 * Calcula o tempo de tramitação por unidade para um processo.
 * Retorna um array no formato [unidade => dias].
 */
function calcularTempoUnidade($processoId) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare('SELECT andamentos_json FROM processo_andamentos WHERE processo_id = ?');
        $stmt->execute([$processoId]);

        $totais = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $andamentos = json_decode($row['andamentos_json'], true);
            if (is_array($andamentos)) {
                $parciais = calcularDiasPorUnidade($andamentos);
                foreach ($parciais as $unidade => $dias) {
                    if (!isset($totais[$unidade])) {
                        $totais[$unidade] = 0;
                    }
                    $totais[$unidade] += $dias;
                }
            }
        }

        return $totais;
    } catch (Exception $e) {
        error_log('Erro ao calcular tempo por unidade: ' . $e->getMessage());
        return [];
    }


// functions.php (adicione ao final do arquivo)

require_once 'config.php';
require_once 'functions.php';

function promoverUsuarioUnico() {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
        $total = $stmt->fetch()["total"] ?? 0;

        if ($total == 1) {
            // Promove o único usuário a admin
            $pdo->exec("UPDATE usuarios SET nivel_acesso = 1, tipo_usuario = 'admin' WHERE id IS NOT NULL");

            // Atualiza a sessão com novos dados
            $stmt2 = $pdo->query("SELECT id, nivel_acesso, tipo_usuario FROM usuarios LIMIT 1");
            $usuario = $stmt2->fetch();

            if ($usuario) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nivel'] = $usuario['nivel_acesso'];
                $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao promover usuário único: " . $e->getMessage());
    }
}
}
?>