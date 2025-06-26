<?php
/**
 * DashboardService - Sistema de Licitações CGLIC
 * Service responsável por agregar dados para o dashboard
 */

class DashboardService {
    private $licitacaoModel;
    
    public function __construct() {
        // Garantir que a classe Licitacao está carregada
        if (!class_exists('Licitacao')) {
            require_once __DIR__ . '/../models/Licitacao.php';
        }
        
        $this->licitacaoModel = new Licitacao();
    }
    
    /**
     * Retorna estatísticas principais do dashboard
     */
    public function getMainStatistics() {
        $stats = $this->licitacaoModel->getStatistics();
        
        // Adicionar estatísticas calculadas
        $stats['percentual_homologadas'] = $stats['total_licitacoes'] > 0 
            ? round(($stats['homologadas'] / $stats['total_licitacoes']) * 100, 1)
            : 0;
            
        $stats['percentual_fracassadas'] = $stats['total_licitacoes'] > 0 
            ? round(($stats['fracassadas'] / $stats['total_licitacoes']) * 100, 1)
            : 0;
            
        $stats['ticket_medio'] = $stats['homologadas'] > 0 
            ? round($stats['valor_homologado'] / $stats['homologadas'], 2)
            : 0;
        
        return $stats;
    }
    
    /**
     * Retorna dados para todos os gráficos do dashboard
     */
    public function getChartsData() {
        return [
            'modalidade' => $this->licitacaoModel->getByModalidade(),
            'pregoeiro' => $this->licitacaoModel->getByPregoeiro(),
            'mensal' => $this->licitacaoModel->getMonthlyData(),
            'status' => $this->licitacaoModel->getByStatus()
        ];
    }
    
    /**
     * Dados para widget de licitações recentes
     */
    public function getLicitacoesRecentes($limite = 5) {
        return $this->licitacaoModel->getAll([], 1, $limite);
    }
    
    /**
     * Retorna alertas e notificações para o dashboard
     */
    public function getAlertas() {
        $alertas = [];
        
        $stats = $this->getMainStatistics();
        
        // Verificar se há muitas licitações fracassadas
        if ($stats['percentual_fracassadas'] > 25) {
            $alertas[] = [
                'tipo' => 'warning',
                'titulo' => 'Alto Índice de Fracassos',
                'mensagem' => 'Taxa de fracasso: ' . $stats['percentual_fracassadas'] . '% - Requer atenção',
                'icone' => 'alert-triangle'
            ];
        }
        
        return $alertas;
    }
    
    /**
     * Retorna KPIs principais para o dashboard
     */
    public function getKPIs() {
        $stats = $this->getMainStatistics();
        
        return [
            [
                'titulo' => 'Taxa de Sucesso',
                'valor' => $stats['percentual_homologadas'] . '%',
                'icone' => 'trending-up',
                'cor' => $stats['percentual_homologadas'] >= 70 ? 'success' : 'warning'
            ],
            [
                'titulo' => 'Total de Licitações',
                'valor' => $stats['total_licitacoes'],
                'icone' => 'file-text',
                'cor' => 'info'
            ],
            [
                'titulo' => 'Valor Homologado',
                'valor' => 'R$ ' . number_format($stats['valor_homologado'], 2, ',', '.'),
                'icone' => 'dollar-sign',
                'cor' => 'success'
            ],
            [
                'titulo' => 'Em Andamento',
                'valor' => $stats['em_andamento'],
                'icone' => 'clock',
                'cor' => 'warning'
            ]
        ];
    }
}
?>