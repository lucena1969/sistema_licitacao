<?php
/**
 * API Simplificada para Backup XAMPP
 * Funciona sem dependências complexas
 */

// Headers primeiro
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Capturar erros
ob_start();

try {
    // Incluir arquivos básicos
    require_once '../config.php';
    require_once '../functions.php';
    
    // Verificar autenticação
    verificarLogin();
    
    // Processar requisição
    $metodo = $_SERVER['REQUEST_METHOD'];
    $acao = null;
    
    if ($metodo === 'GET') {
        $acao = $_GET['acao'] ?? null;
    } elseif ($metodo === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $acao = $input['acao'] ?? null;
    }
    
    $response = ['sucesso' => false, 'erro' => 'Ação não especificada'];
    
    switch ($acao) {
        case 'executar_backup':
            if ($metodo !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $tipo = $input['tipo'] ?? 'database';
            
            if ($tipo === 'database') {
                $resultado = executarBackupDatabase();
            } elseif ($tipo === 'arquivos') {
                $resultado = executarBackupArquivos();
            } else {
                throw new Exception('Tipo de backup inválido');
            }
            
            $response = $resultado;
            break;
            
        case 'estatisticas':
            $response = obterEstatisticas();
            break;
            
        case 'historico':
            $response = obterHistorico();
            break;
            
        case 'limpar_antigos':
            if ($metodo !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $response = limparBackupsAntigos();
            break;
            
        case 'verificar_integridade':
            if ($metodo !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $backup_id = $input['backup_id'] ?? null;
            if (!$backup_id) {
                throw new Exception('ID do backup não fornecido');
            }
            $response = verificarIntegridade($backup_id);
            break;
            
        default:
            $response = ['sucesso' => false, 'erro' => 'Ação não reconhecida: ' . $acao];
    }
    
} catch (Exception $e) {
    $response = [
        'sucesso' => false,
        'erro' => $e->getMessage(),
        'debug' => $e->getFile() . ':' . $e->getLine()
    ];
} catch (Error $e) {
    $response = [
        'sucesso' => false,
        'erro' => 'Erro Fatal: ' . $e->getMessage(),
        'debug' => $e->getFile() . ':' . $e->getLine()
    ];
}

// Limpar qualquer saída indesejada
$output = ob_get_clean();
if (!empty($output)) {
    $response['debug_output'] = $output;
}

// Enviar resposta
echo json_encode($response, JSON_UNESCAPED_UNICODE);

/**
 * Backup do banco de dados (método PHP)
 */
function executarBackupDatabase() {
    $inicio_tempo = microtime(true);
    $inicio_datetime = date('Y-m-d H:i:s');
    
    try {
        $pdo = conectarDB();
        
        // Criar diretório (caminho correto relativo ao projeto principal)
        $backup_dir = '../backups/database/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $nome_arquivo = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $caminho_arquivo = $backup_dir . $nome_arquivo;
        
        $sql_content = "-- Backup Sistema CGLIC - " . date('Y-m-d H:i:s') . "\n";
        $sql_content .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        // Tabelas importantes
        $tabelas_importantes = ['usuarios', 'backups_sistema', 'backup_configuracoes', 'licitacoes'];
        
        // Verificar quais existem
        $tabelas_existentes = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        $tabelas_backup = [];
        foreach ($tabelas_importantes as $tabela) {
            if (in_array($tabela, $tabelas_existentes)) {
                $tabelas_backup[] = $tabela;
            }
        }
        
        // Incluir pca_dados limitado
        if (in_array('pca_dados', $tabelas_existentes)) {
            $tabelas_backup[] = 'pca_dados';
        }
        
        foreach ($tabelas_backup as $tabela) {
            $sql_content .= "\n-- Tabela: $tabela\n";
            
            // Estrutura
            $create_result = $pdo->query("SHOW CREATE TABLE `$tabela`")->fetch();
            $sql_content .= "DROP TABLE IF EXISTS `$tabela`;\n";
            $sql_content .= $create_result['Create Table'] . ";\n\n";
            
            // Dados
            if ($tabela === 'pca_dados') {
                $query = "SELECT * FROM `$tabela` ORDER BY id DESC LIMIT 100";
            } else {
                $query = "SELECT * FROM `$tabela`";
            }
            
            $stmt = $pdo->query($query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $sql_content .= "INSERT INTO `$tabela` (`" . implode('`, `', $columns) . "`) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $escaped_values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $escaped_values[] = 'NULL';
                        } else {
                            $clean_value = str_replace(["'", "\\"], ["''", "\\\\"], $value);
                            $escaped_values[] = "'" . $clean_value . "'";
                        }
                    }
                    $values[] = '(' . implode(', ', $escaped_values) . ')';
                }
                
                $sql_content .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        $sql_content .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
        
        // Salvar
        $bytes_written = file_put_contents($caminho_arquivo, $sql_content);
        
        if ($bytes_written === false) {
            throw new Exception("Erro ao salvar arquivo de backup");
        }
        
        // Calcular tempo de execução
        $fim_tempo = microtime(true);
        $tempo_execucao = round($fim_tempo - $inicio_tempo, 2);
        $fim_datetime = date('Y-m-d H:i:s');
        
        // Registrar no banco
        try {
            if (in_array('backups_sistema', $tabelas_existentes)) {
                $sql = "INSERT INTO backups_sistema (tipo, status, inicio, fim, tamanho_total, arquivo_database, tempo_execucao, criado_por) 
                        VALUES ('database', 'sucesso', ?, ?, ?, ?, ?, 'api_simple')";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$inicio_datetime, $fim_datetime, $bytes_written, $nome_arquivo, $tempo_execucao]);
            }
        } catch (Exception $e) {
            // Ignorar erro de registro se tabela não existir
        }
        
        return [
            'sucesso' => true,
            'arquivo' => $nome_arquivo,
            'tamanho' => $bytes_written,
            'tamanho_formatado' => formatarTamanho($bytes_written),
            'tabelas' => count($tabelas_backup)
        ];
        
    } catch (Exception $e) {
        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
}

/**
 * Backup dos arquivos
 */
function executarBackupArquivos() {
    $inicio_tempo = microtime(true);
    $inicio_datetime = date('Y-m-d H:i:s');
    
    try {
        // Criar diretório (caminho correto relativo ao projeto principal)
        $backup_dir = '../backups/files/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $nome_arquivo = 'backup_files_' . date('Y-m-d_H-i-s') . '.zip';
        $caminho_arquivo = $backup_dir . $nome_arquivo;
        
        $zip = new ZipArchive();
        
        if ($zip->open($caminho_arquivo, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception("Não foi possível criar arquivo ZIP");
        }
        
        $arquivos_adicionados = 0;
        
        // Arquivos importantes
        $arquivos_importantes = [
            'config.php',
            'functions.php', 
            'index.php',
            'dashboard.php',
            'process.php'
        ];
        
        foreach ($arquivos_importantes as $arquivo) {
            if (file_exists($arquivo)) {
                $zip->addFile($arquivo, $arquivo);
                $arquivos_adicionados++;
            }
        }
        
        // Uploads
        if (is_dir('uploads/')) {
            $files = glob('uploads/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $zip->addFile($file, $file);
                    $arquivos_adicionados++;
                }
            }
        }
        
        $zip->close();
        
        // Calcular tempo de execução
        $fim_tempo = microtime(true);
        $tempo_execucao = round($fim_tempo - $inicio_tempo, 2);
        $fim_datetime = date('Y-m-d H:i:s');
        $tamanho_arquivo = filesize($caminho_arquivo);
        
        // Registrar no banco
        try {
            $pdo = conectarDB();
            $tabelas_existentes = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array('backups_sistema', $tabelas_existentes)) {
                $sql = "INSERT INTO backups_sistema (tipo, status, inicio, fim, tamanho_total, arquivo_files, tempo_execucao, criado_por) 
                        VALUES ('files', 'sucesso', ?, ?, ?, ?, ?, 'api_simple')";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$inicio_datetime, $fim_datetime, $tamanho_arquivo, $nome_arquivo, $tempo_execucao]);
            }
        } catch (Exception $e) {
            // Ignorar erro de registro se tabela não existir
        }
        
        return [
            'sucesso' => true,
            'arquivo' => $nome_arquivo,
            'tamanho' => $tamanho_arquivo,
            'tamanho_formatado' => formatarTamanho($tamanho_arquivo),
            'arquivos_count' => $arquivos_adicionados
        ];
        
    } catch (Exception $e) {
        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
}

/**
 * Obter estatísticas
 */
function obterEstatisticas() {
    try {
        $pdo = conectarDB();
        
        // Verificar se tabela existe
        $tables = $pdo->query("SHOW TABLES LIKE 'backups_sistema'")->fetchAll();
        
        if (empty($tables)) {
            return [
                'sucesso' => true,
                'ultimo_backup' => 'Nunca',
                'backups_mes' => 0,
                'tamanho_total' => '0 B',
                'sistema_ok' => true
            ];
        }
        
        // Último backup
        $ultimo_sql = "SELECT inicio FROM backups_sistema WHERE status = 'sucesso' ORDER BY inicio DESC LIMIT 1";
        $ultimo_result = $pdo->query($ultimo_sql)->fetch();
        $ultimo_backup = $ultimo_result ? date('d/m/Y H:i', strtotime($ultimo_result['inicio'])) : 'Nunca';
        
        // Backups este mês
        $mes_sql = "SELECT COUNT(*) as total FROM backups_sistema WHERE DATE_FORMAT(inicio, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
        $backups_mes = $pdo->query($mes_sql)->fetch()['total'];
        
        // Tamanho total
        $tamanho_sql = "SELECT SUM(tamanho_total) as total FROM backups_sistema WHERE status = 'sucesso'";
        $tamanho_result = $pdo->query($tamanho_sql)->fetch();
        $tamanho_total = formatarTamanho($tamanho_result['total'] ?? 0);
        
        return [
            'sucesso' => true,
            'ultimo_backup' => $ultimo_backup,
            'backups_mes' => $backups_mes,
            'tamanho_total' => $tamanho_total,
            'sistema_ok' => true
        ];
        
    } catch (Exception $e) {
        return [
            'sucesso' => true,
            'ultimo_backup' => 'Erro',
            'backups_mes' => 0,
            'tamanho_total' => '0 B',
            'sistema_ok' => false
        ];
    }
}

/**
 * Obter histórico
 */
function obterHistorico() {
    try {
        $pdo = conectarDB();
        
        // Verificar se tabela existe
        $tables = $pdo->query("SHOW TABLES LIKE 'backups_sistema'")->fetchAll();
        
        if (empty($tables)) {
            return [
                'sucesso' => true,
                'backups' => []
            ];
        }
        
        $sql = "SELECT * FROM backups_sistema ORDER BY inicio DESC LIMIT 20";
        $stmt = $pdo->query($sql);
        $historico = $stmt->fetchAll();
        
        $backups_formatados = array_map(function($backup) {
            return [
                'id' => $backup['id'],
                'tipo' => ucfirst($backup['tipo']),
                'status' => $backup['status'],
                'inicio' => $backup['inicio'],
                'fim' => $backup['fim'],
                'tamanho_formatado' => formatarTamanho($backup['tamanho_total']),
                'tempo_execucao' => $backup['tempo_execucao'],
                'arquivo_database' => $backup['arquivo_database'],
                'arquivo_files' => $backup['arquivo_files'],
                'erros' => $backup['erros']
            ];
        }, $historico);
        
        return [
            'sucesso' => true,
            'backups' => $backups_formatados
        ];
        
    } catch (Exception $e) {
        return [
            'sucesso' => true,
            'backups' => []
        ];
    }
}

/**
 * Formatar tamanho
 */
function formatarTamanho($bytes) {
    if ($bytes == 0) return '0 B';
    
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $potencia = floor(log($bytes, 1024));
    
    return round($bytes / pow(1024, $potencia), 2) . ' ' . $unidades[$potencia];
}

/**
 * Limpar backups antigos (retenção de 7 dias)
 */
function limparBackupsAntigos() {
    try {
        $arquivos_removidos = 0;
        $tamanho_liberado = 0;
        
        // Diretórios de backup (caminho correto relativo ao projeto principal)
        $diretorios = [
            '../backups/database/' => 7, // 7 dias
            '../backups/files/' => 7     // 7 dias
        ];
        
        foreach ($diretorios as $diretorio => $dias_retencao) {
            if (!is_dir($diretorio)) {
                continue;
            }
            
            $data_limite = time() - ($dias_retencao * 24 * 60 * 60);
            
            // Buscar arquivos
            $arquivos = glob($diretorio . 'backup_*');
            
            foreach ($arquivos as $arquivo) {
                $timestamp_arquivo = filemtime($arquivo);
                
                if ($timestamp_arquivo < $data_limite) {
                    $tamanho = filesize($arquivo);
                    
                    if (unlink($arquivo)) {
                        $arquivos_removidos++;
                        $tamanho_liberado += $tamanho;
                    }
                }
            }
        }
        
        // Remover registros antigos do banco
        try {
            $pdo = conectarDB();
            $tables = $pdo->query("SHOW TABLES LIKE 'backups_sistema'")->fetchAll();
            
            if (!empty($tables)) {
                $data_limite_db = date('Y-m-d', time() - (7 * 24 * 60 * 60));
                $sql = "DELETE FROM backups_sistema WHERE DATE(inicio) < ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data_limite_db]);
            }
        } catch (Exception $e) {
            // Ignorar erro do banco
        }
        
        return [
            'sucesso' => true,
            'arquivos_removidos' => $arquivos_removidos,
            'tamanho_liberado' => formatarTamanho($tamanho_liberado),
            'mensagem' => "Limpeza concluída: $arquivos_removidos arquivos removidos"
        ];
        
    } catch (Exception $e) {
        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
}

/**
 * Verificar integridade de um backup
 */
function verificarIntegridade($backup_id) {
    try {
        $pdo = conectarDB();
        
        // Buscar backup
        $sql = "SELECT * FROM backups_sistema WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$backup_id]);
        $backup = $stmt->fetch();
        
        if (!$backup) {
            throw new Exception("Backup não encontrado");
        }
        
        $problemas = [];
        $tudo_ok = true;
        
        // Verificar arquivo do banco
        if ($backup['arquivo_database']) {
            $arquivo_db = '../backups/database/' . $backup['arquivo_database'];
            if (!file_exists($arquivo_db)) {
                $problemas[] = "Arquivo do banco não encontrado";
                $tudo_ok = false;
            } else {
                $tamanho = filesize($arquivo_db);
                if ($tamanho < 1024) { // Menor que 1KB
                    $problemas[] = "Arquivo do banco muito pequeno";
                    $tudo_ok = false;
                }
            }
        }
        
        // Verificar arquivo de arquivos
        if ($backup['arquivo_files']) {
            $arquivo_files = '../backups/files/' . $backup['arquivo_files'];
            if (!file_exists($arquivo_files)) {
                $problemas[] = "Arquivo de arquivos não encontrado";
                $tudo_ok = false;
            } else {
                // Tentar abrir ZIP
                $zip = new ZipArchive();
                if ($zip->open($arquivo_files) !== TRUE) {
                    $problemas[] = "Arquivo ZIP corrompido";
                    $tudo_ok = false;
                } else {
                    if ($zip->numFiles == 0) {
                        $problemas[] = "Arquivo ZIP vazio";
                        $tudo_ok = false;
                    }
                    $zip->close();
                }
            }
        }
        
        return [
            'sucesso' => true,
            'integro' => $tudo_ok,
            'erros' => $problemas,
            'mensagem' => $tudo_ok ? 'Backup íntegro' : 'Problemas encontrados'
        ];
        
    } catch (Exception $e) {
        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
}
?>