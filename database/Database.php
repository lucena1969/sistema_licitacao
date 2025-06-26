<?php
/**
 * Database Class - Sistema de Licitações CGLIC
 */

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            // Carrega configurações do config.php existente
            if (file_exists(__DIR__ . '/../config.php')) {
                require_once __DIR__ . '/../config.php';
            }
            
            $host = defined('DB_HOST') ? DB_HOST : 'localhost';
            $dbname = defined('DB_NAME') ? DB_NAME : 'sistema_licitacao';
            $username = defined('DB_USER') ? DB_USER : 'root';
            $password = defined('DB_PASS') ? DB_PASS : '';
            
            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $this->connection = new PDO($dsn, $username, $password, $options);
            
        } catch (PDOException $e) {
            throw new Exception("Erro ao conectar com o banco: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function select($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Erro no SELECT: " . $e->getMessage());
        }
    }
    
    public function selectOne($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Erro no SELECT ONE: " . $e->getMessage());
        }
    }
    
    public function count($table, $conditions = [], $params = []) {
        $sql = "SELECT COUNT(*) as total FROM " . $table;
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $result = $this->selectOne($sql, $params);
        return (int) $result['total'];
    }
    
    public function sum($table, $column, $conditions = [], $params = []) {
        $sql = "SELECT SUM(" . $column . ") as total FROM " . $table;
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $result = $this->selectOne($sql, $params);
        return (float) ($result['total'] ?? 0);
    }
}
?>