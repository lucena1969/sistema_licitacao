<?php
/**
 * Script para execução via CRON
 * Sistema de Backup Automatizado
 */

// Verificar se está sendo executado via linha de comando
if (php_sapi_name() !== 'cli') {
    die('Este script deve ser executado apenas via linha de comando');
}

require_once 'backup/backup_sistema.php';

// Processar argumentos da linha de comando
$opcoes = getopt('', ['tipo:', 'help']);

if (isset($opcoes['help'])) {
    echo "Uso: php cron_backup.php --tipo=[completo|database|arquivos|limpeza]\n";
    echo "Tipos disponíveis:\n";
    echo "  completo  - Backup completo (banco + arquivos)\n";
    echo "  database  - Apenas backup do banco de dados\n";
    echo "  arquivos  - Apenas backup dos arquivos\n";
    echo "  limpeza   - Limpeza de backups antigos\n";
    exit(0);
}

$tipo = $opcoes['tipo'] ?? 'completo';

try {
    $backup = new BackupSistema();
    
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando backup tipo: $tipo\n";
    
    switch ($tipo) {
        case 'completo':
            $resultado = $backup->backupCompleto();
            break;
            
        case 'database':
            $resultado = $backup->backupDatabase();
            break;
            
        case 'arquivos':
            $resultado = $backup->backupArquivos();
            break;
            
        case 'limpeza':
            $arquivos_removidos = $backup->limparBackupsAntigos();
            echo "Limpeza concluída. $arquivos_removidos arquivos removidos.\n";
            exit(0);
            
        default:
            echo "Tipo de backup inválido: $tipo\n";
            echo "Use --help para ver as opções disponíveis.\n";
            exit(1);
    }
    
    // Exibir resultado
    if (isset($resultado['sucesso']) && $resultado['sucesso']) {
        echo "✅ Backup concluído com sucesso!\n";
        if (isset($resultado['arquivo'])) {
            echo "Arquivo: {$resultado['arquivo']}\n";
        }
        if (isset($resultado['tamanho'])) {
            $tamanho_mb = round($resultado['tamanho'] / 1024 / 1024, 2);
            echo "Tamanho: {$tamanho_mb} MB\n";
        }
    } else {
        echo "❌ Erro no backup:\n";
        if (isset($resultado['erro'])) {
            echo "Erro: {$resultado['erro']}\n";
        }
        if (isset($resultado['erros'])) {
            foreach ($resultado['erros'] as $erro) {
                echo "Erro: $erro\n";
            }
        }
        exit(1);
    }
    
    // Para backup completo, exibir detalhes
    if ($tipo === 'completo') {
        echo "\nDetalhes do backup completo:\n";
        echo "- Database: " . ($resultado['database'] ? '✅' : '❌') . "\n";
        echo "- Arquivos: " . ($resultado['arquivos'] ? '✅' : '❌') . "\n";
        echo "- Tempo execução: {$resultado['tempo_execucao']} segundos\n";
        
        $tamanho_mb = round($resultado['tamanho_total'] / 1024 / 1024, 2);
        echo "- Tamanho total: {$tamanho_mb} MB\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Backup finalizado\n";
    
} catch (Exception $e) {
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>