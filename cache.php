<?php
/**
 * Sistema de Cache Simples para Performance
 * Utiliza cache em arquivo para consultas frequentes
 */

class CacheSystem {
    private $cacheDir;
    private $defaultTTL;
    
    public function __construct($cacheDir = 'cache/', $defaultTTL = 3600) {
        $this->cacheDir = $cacheDir;
        $this->defaultTTL = $defaultTTL;
        
        // Criar diretório de cache se não existir
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0750, true);
        }
    }
    
    /**
     * Gerar chave de cache baseada em parâmetros
     */
    private function generateKey($key, $params = []) {
        $fullKey = $key;
        if (!empty($params)) {
            $fullKey .= '_' . md5(serialize($params));
        }
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $fullKey);
    }
    
    /**
     * Obter item do cache
     */
    public function get($key, $params = []) {
        $cacheKey = $this->generateKey($key, $params);
        $cacheFile = $this->cacheDir . $cacheKey . '.cache';
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $cacheData = unserialize(file_get_contents($cacheFile));
        
        // Verificar se expirou
        if ($cacheData['expires'] < time()) {
            unlink($cacheFile);
            return null;
        }
        
        return $cacheData['data'];
    }
    
    /**
     * Armazenar item no cache
     */
    public function set($key, $data, $ttl = null, $params = []) {
        $cacheKey = $this->generateKey($key, $params);
        $cacheFile = $this->cacheDir . $cacheKey . '.cache';
        
        $ttl = $ttl ?: $this->defaultTTL;
        
        $cacheData = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        file_put_contents($cacheFile, serialize($cacheData));
        return true;
    }
    
    /**
     * Remover item específico do cache
     */
    public function delete($key, $params = []) {
        $cacheKey = $this->generateKey($key, $params);
        $cacheFile = $this->cacheDir . $cacheKey . '.cache';
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return true;
    }
    
    /**
     * Limpar todo o cache
     */
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    /**
     * Limpar cache expirado
     */
    public function clearExpired() {
        $files = glob($this->cacheDir . '*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            $cacheData = unserialize(file_get_contents($file));
            if ($cacheData['expires'] < time()) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Obter estatísticas do cache
     */
    public function getStats() {
        $files = glob($this->cacheDir . '*.cache');
        $totalSize = 0;
        $validFiles = 0;
        $expiredFiles = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            $cacheData = unserialize(file_get_contents($file));
            
            if ($cacheData['expires'] < time()) {
                $expiredFiles++;
            } else {
                $validFiles++;
            }
        }
        
        return [
            'total_files' => count($files),
            'valid_files' => $validFiles,
            'expired_files' => $expiredFiles,
            'total_size' => $totalSize,
            'cache_dir' => $this->cacheDir
        ];
    }
}

/**
 * Instância global do cache
 */
function getCache() {
    static $cache = null;
    if ($cache === null) {
        $cache = new CacheSystem();
    }
    return $cache;
}

/**
 * Função helper para cache de consultas SQL
 */
function cacheQuery($sql, $params = [], $ttl = 3600) {
    $cache = getCache();
    $cacheKey = 'sql_' . md5($sql);
    
    // Tentar obter do cache
    $result = $cache->get($cacheKey, $params);
    if ($result !== null) {
        return $result;
    }
    
    // Executar consulta
    $pdo = conectarDB();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll();
    
    // Armazenar no cache
    $cache->set($cacheKey, $result, $ttl, $params);
    
    return $result;
}

/**
 * Cache específico para estatísticas do dashboard
 */
function getCachedStats($ttl = 300) { // 5 minutos
    $cache = getCache();
    $result = $cache->get('dashboard_stats');
    
    if ($result === null) {
        $pdo = conectarDB();
        
        $sql = "SELECT 
            COUNT(DISTINCT p.numero_dfd) as total_dfds,
            COUNT(DISTINCT p.numero_contratacao) as total_contratacoes,
            SUM(DISTINCT p.valor_total_contratacao) as valor_total,
            COUNT(DISTINCT CASE WHEN l.situacao = 'HOMOLOGADO' THEN p.numero_contratacao END) as homologadas,
            COUNT(DISTINCT CASE WHEN p.data_inicio_processo < CURDATE() AND (p.situacao_execucao IS NULL OR p.situacao_execucao = '' OR p.situacao_execucao = 'Não iniciado') THEN p.numero_contratacao END) as atrasadas_inicio,
            COUNT(DISTINCT CASE WHEN p.data_conclusao_processo < CURDATE() AND p.situacao_execucao != 'Concluído' THEN p.numero_contratacao END) as atrasadas_conclusao,
            COUNT(DISTINCT CASE WHEN p.data_conclusao_processo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN p.numero_contratacao END) as vencendo_30_dias,
            COUNT(DISTINCT CASE WHEN l.situacao IN ('EM_ANDAMENTO') THEN p.numero_contratacao END) as em_andamento
            FROM pca_dados p
            LEFT JOIN licitacoes l ON l.pca_dados_id = p.id";
        
        $result = $pdo->query($sql)->fetch();
        $cache->set('dashboard_stats', $result, $ttl);
    }
    
    return $result;
}

/**
 * Cache para dados de gráficos
 */
function getCachedChartData($tipo, $ttl = 600) { // 10 minutos
    $cache = getCache();
    $result = $cache->get("chart_data_$tipo");
    
    if ($result === null) {
        $pdo = conectarDB();
        
        switch ($tipo) {
            case 'categoria':
                $sql = "SELECT categoria_contratacao, COUNT(DISTINCT numero_dfd) as quantidade 
                        FROM pca_dados 
                        WHERE categoria_contratacao IS NOT NULL
                        GROUP BY categoria_contratacao
                        ORDER BY quantidade DESC
                        LIMIT 5";
                break;
                
            case 'area':
                $sql = "SELECT area_requisitante, COUNT(DISTINCT numero_dfd) as quantidade 
                        FROM pca_dados 
                        WHERE area_requisitante IS NOT NULL
                        GROUP BY area_requisitante
                        ORDER BY quantidade DESC
                        LIMIT 5";
                break;
                
            case 'mensal_pca':
                $sql = "SELECT 
                            DATE_FORMAT(data_inicio_processo, '%Y-%m') as mes,
                            COUNT(DISTINCT numero_dfd) as quantidade
                        FROM pca_dados 
                        WHERE data_inicio_processo >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                        AND data_inicio_processo IS NOT NULL
                        GROUP BY DATE_FORMAT(data_inicio_processo, '%Y-%m')
                        ORDER BY mes";
                break;
                
            default:
                return [];
        }
        
        $result = $pdo->query($sql)->fetchAll();
        $cache->set("chart_data_$tipo", $result, $ttl);
    }
    
    return $result;
}

/**
 * Invalidar cache relacionado quando dados são atualizados
 */
function invalidateRelatedCache($tabela) {
    $cache = getCache();
    
    switch ($tabela) {
        case 'pca_dados':
        case 'licitacoes':
            $cache->delete('dashboard_stats');
            $cache->delete('chart_data_categoria');
            $cache->delete('chart_data_area');
            $cache->delete('chart_data_mensal_pca');
            break;
    }
}
?>