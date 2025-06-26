<?php
/**
 * Configuração segura do sistema
 * Utiliza variáveis de ambiente com fallbacks seguros
 */

// Carregar arquivo .env se existir (para desenvolvimento)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos(trim($line), '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Configurações do banco de dados com variáveis de ambiente
// define('DB_HOST', getenv('DB_HOST') ?: '10.1.42.58');
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'sistema_licitacao');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', 'utf8mb4');

// Configurações do sistema
define('SITE_NAME', getenv('SITE_NAME') ?: 'Sistema de Licitações');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/sistema_licitacao/');
define('DEBUG_MODE', filter_var(getenv('DEBUG_MODE') ?: 'true', FILTER_VALIDATE_BOOLEAN));

// Configurações de segurança
define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 7200)); // 2 horas
define('CSRF_TOKEN_LIFETIME', (int)(getenv('CSRF_TOKEN_LIFETIME') ?: 3600)); // 1 hora
define('MAX_LOGIN_ATTEMPTS', (int)(getenv('MAX_LOGIN_ATTEMPTS') ?: 5));
define('LOGIN_BLOCK_TIME', (int)(getenv('LOGIN_BLOCK_TIME') ?: 900)); // 15 minutos

// Configurações de upload
define('MAX_UPLOAD_SIZE', (int)(getenv('MAX_UPLOAD_SIZE') ?: 5242880)); // 5MB
define('UPLOAD_DIR', getenv('UPLOAD_DIR') ?: 'uploads/');

// Timezone
date_default_timezone_set(getenv('TIMEZONE') ?: 'America/Sao_Paulo');

// Configurar sessão segura ANTES de iniciar
function configurarSessaoSegura() {
    // Só configurar se a sessão ainda não foi iniciada
    if (session_status() === PHP_SESSION_NONE) {
        // Configurações de segurança da sessão
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        ini_set('session.cookie_lifetime', SESSION_LIFETIME);
        ini_set('session.name', 'CGLIC_SESSION');
        
        // Configurações adicionais de segurança
        ini_set('session.entropy_length', 32);
        ini_set('session.hash_function', 'sha256');
        ini_set('session.hash_bits_per_character', 5);
        
        // Iniciar sessão
        session_start();
    }
    
    // Regenerar ID da sessão periodicamente (após sessão iniciada)
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 600) { // 10 minutos
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Função para conectar ao banco com melhor tratamento de erro
function conectarDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            // Adicionar configuração MySQL apenas se a constante existir
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
            }
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            // Log do erro sem expor informações sensíveis
            error_log("Erro de conexão com banco: " . $e->getMessage());
            
            if (DEBUG_MODE) {
                die("Erro na conexão com o banco de dados: " . $e->getMessage());
            } else {
                die("Erro interno do servidor. Tente novamente mais tarde.");
            }
        }
    }
    
    return $pdo;
}

// Configurar e iniciar sessão segura
configurarSessaoSegura();

// Configurar exibição de erros baseado no ambiente
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
}

// Headers de segurança
function definirHeadersSeguranca() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if (isset($_SERVER['HTTPS'])) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Aplicar headers de segurança
definirHeadersSeguranca();
?>