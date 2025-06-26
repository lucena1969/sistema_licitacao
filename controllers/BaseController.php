<?php
/**
 * BaseController - Sistema de Licitações CGLIC
 * Classe base para todos os controllers do sistema
 */

abstract class BaseController {
    protected $db;
    protected $user;
    
    public function __construct() {
        session_start();
        $this->db = Database::getInstance();
        $this->loadUser();
    }
    
    /**
     * Carrega dados do usuário logado
     */
    private function loadUser() {
        if (isset($_SESSION['usuario_id'])) {
            $this->user = [
                'id' => $_SESSION['usuario_id'],
                'nome' => $_SESSION['usuario_nome'] ?? '',
                'email' => $_SESSION['usuario_email'] ?? '',
                'tipo' => $_SESSION['usuario_tipo'] ?? 'visitante',
                'departamento' => $_SESSION['usuario_departamento'] ?? ''
            ];
        }
    }
    
    /**
     * Verifica se usuário tem permissão
     */
    protected function hasPermission($permission) {
        // Implementação básica - adaptar conforme seu sistema
        if (!$this->user) return false;
        
        switch ($this->user['tipo']) {
            case 'admin':
                return true;
            case 'operador':
                return !in_array($permission, ['usuario_gerenciar', 'sistema_configurar']);
            case 'visitante':
                return in_array($permission, ['dashboard_visualizar', 'licitacao_visualizar']);
            default:
                return false;
        }
    }
    
    /**
     * Verifica se usuário está logado
     */
    protected function isLoggedIn() {
        return $this->user !== null;
    }
    
    /**
     * Renderiza uma view
     */
    protected function renderView($view, $data = []) {
        extract($data);
        $user = $this->user;
        
        $viewPath = __DIR__ . "/../views/{$view}.php";
        
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View não encontrada: {$view}";
        }
    }
    
    /**
     * Resposta JSON
     */
    protected function jsonResponse($data, $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}