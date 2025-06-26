/**
 * Dashboard JavaScript - Sistema CGLIC
 * Funcionalidades do painel de controle principal
 */

// Variável global para armazenar dados do PHP
let dashboardData = {};

// ==================== NAVEGAÇÃO E INTERFACE ====================

/**
 * Navegação da Sidebar
 */
function showSection(sectionId) {
    // Esconder todas as seções
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Remover active de todos os nav-items
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Mostrar a seção selecionada
    const targetSection = document.getElementById(sectionId);
    if (targetSection) {
        targetSection.classList.add('active');
    }
    
    // Adicionar active ao botão correto
    const activeButton = document.querySelector(`button[onclick*="showSection('${sectionId}')"]`);
    if (activeButton) {
        activeButton.classList.add('active');
    }
    
    // Carregar dados específicos da seção
    if (sectionId === 'backup-sistema') {
        atualizarEstatisticasBackup();
        atualizarHistoricoBackups();
    }
}

/**
 * Inicializar navegação ao carregar a página
 */
function initNavigation() {
    // Verificar se há uma seção na URL
    const urlParams = new URLSearchParams(window.location.search);
    const secaoAtiva = urlParams.get('secao') || 'dashboard';
    
    // Ativar a seção correta
    const secaoElement = document.getElementById(secaoAtiva);
    if (secaoElement) {
        // Simular clique para ativar a seção
        const menuItem = document.querySelector(`.nav-item[onclick*="${secaoAtiva}"]`);
        if (menuItem) {
            showSection(secaoAtiva, { currentTarget: menuItem });
        } else {
            // Se não encontrar o item do menu, apenas mostrar a seção
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            secaoElement.classList.add('active');
        }
    }
}

/**
 * Inicializar gráficos do dashboard
 */
function initCharts() {
    setTimeout(() => {
        // Verificar se os dados foram carregados do PHP
        if (!window.dashboardData) {
            console.warn('Dados do dashboard não carregados');
            return;
        }

        // Verificar se Chart.js está disponível
        if (typeof Chart === 'undefined') {
            console.error('Chart.js não está carregado!');
            return;
        }

        const dadosCategoria = window.dashboardData.dados_categoria || [];
        const dadosArea = window.dashboardData.dados_area || [];
        const dadosMensal = window.dashboardData.dados_mensal || [];
        const stats = window.dashboardData.stats || {};

        // Debug: verificar dados
        console.log('Dashboard - dados_area:', dadosArea.length, 'itens');

        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;

        // Gráfico de Áreas (Contratação por Área)
        if (document.getElementById('chartArea')) {
            try {
                console.log('Criando gráfico de área com', dadosArea.length, 'itens');
                if (dadosArea && dadosArea.length > 0) {
                    new Chart(document.getElementById('chartArea'), {
                        type: 'bar',
                        data: {
                            labels: dadosArea.map(item => {
                                // Truncar nomes muito longos para melhor visualização
                                const nome = item.area || 'Não definido';
                                return nome.length > 25 ? nome.substring(0, 25) + '...' : nome;
                            }),
                            datasets: [{
                                label: 'Contratações',
                                data: dadosArea.map(item => item.total || item.quantidade || 0),
                                backgroundColor: [
                                    '#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6',
                                    '#1abc9c', '#e67e22', '#8e44ad', '#34495e', '#95a5a6'
                                ]
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        title: function(context) {
                                            // Mostrar o nome completo no tooltip
                                            const index = context[0].dataIndex;
                                            return dadosArea[index].area || 'Não definido';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                },
                                y: {
                                    ticks: {
                                        font: {
                                            size: 11
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    // Exibir gráfico vazio quando não há dados
                    console.log('Sem dados reais, exibindo gráfico vazio');
                    document.getElementById('chartArea').innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 200px; color: #7f8c8d; text-align: center;"><div><i data-lucide="bar-chart-3" style="width: 48px; height: 48px; margin-bottom: 10px; opacity: 0.5;"></i><p>Nenhum dado disponível<br><small>Importe dados do PCA para ver os gráficos</small></p></div></div>';
                    lucide.createIcons();
                }
            } catch (error) {
                console.error('Erro ao criar gráfico de áreas:', error);
            }
        }

        // Gráfico Mensal (Evolução Mensal)
        if (document.getElementById('chartMensal')) {
            try {
                if (dadosMensal.length > 0) {
                    new Chart(document.getElementById('chartMensal'), {
                        type: 'line',
                        data: {
                            labels: dadosMensal.map(item => {
                                if (item.mes) {
                                    const [ano, mes] = item.mes.split('-');
                                    return new Date(ano, mes - 1).toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' });
                                }
                                return item.periodo || 'N/A';
                            }),
                            datasets: [{
                                label: 'Contratações Iniciadas',
                                data: dadosMensal.map(item => item.quantidade || item.total || 0),
                                borderColor: '#3498db',
                                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                tension: 0.4,
                                fill: true,
                                pointBackgroundColor: '#3498db',
                                pointBorderColor: '#2980b9',
                                pointBorderWidth: 2,
                                pointRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { display: false }
                            },
                            scales: {
                                x: {
                                    display: true,
                                    grid: {
                                        display: false
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                } else {
                    // Exibir gráfico vazio quando não há dados mensais
                    console.log('Sem dados mensais, exibindo gráfico vazio');
                    document.getElementById('chartMensal').innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 200px; color: #7f8c8d; text-align: center;"><div><i data-lucide="trending-up" style="width: 48px; height: 48px; margin-bottom: 10px; opacity: 0.5;"></i><p>Nenhum dado mensal disponível<br><small>Importe dados do PCA para ver a evolução mensal</small></p></div></div>';
                    lucide.createIcons();
                }
            } catch (error) {
                console.error('Erro ao criar gráfico mensal:', error);
            }
        }

        // Gráfico de Categorias (se existir)
        if (document.getElementById('chartCategoria')) {
            try {
                if (dadosCategoria.length > 0) {
                    new Chart(document.getElementById('chartCategoria'), {
                        type: 'doughnut',
                        data: {
                            labels: dadosCategoria.map(item => item.categoria || item.categoria_contratacao || 'Não definido'),
                            datasets: [{
                                data: dadosCategoria.map(item => item.total || item.quantidade || 0),
                                backgroundColor: ['#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6', '#1abc9c', '#e67e22']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
            } catch (error) {
                console.error('Erro ao criar gráfico de categorias:', error);
            }
        }

        console.log('Gráficos inicializados com sucesso');
    }, 500);
}

/**
 * Configurar dados do dashboard vindos do PHP
 */
function setDashboardData(data) {
    dashboardData = data;
}

// ==================== FUNÇÕES DA TABELA ====================

/**
 * Ver detalhes de uma contratação
 */
function verDetalhes(ids) {
    const modal = document.getElementById('modalDetalhes');
    const conteudo = document.getElementById('conteudoDetalhes');
    
    conteudo.innerHTML = '<div style="text-align: center; padding: 40px;"><p>Carregando...</p></div>';
    modal.style.display = 'block';
    
    fetch('utils/detalhes.php?ids=' + ids)
        .then(response => response.text())
        .then(html => {
            conteudo.innerHTML = html;
        })
        .catch(() => {
            conteudo.innerHTML = '<div style="padding: 40px; text-align: center;">Erro ao carregar detalhes</div>';
        });
}

/**
 * Fechar modal de detalhes
 */
function fecharModalDetalhes() {
    document.getElementById('modalDetalhes').style.display = 'none';
    document.getElementById('conteudoDetalhes').innerHTML = '';
}

/**
 * Ver histórico de uma contratação
 */
function verHistorico(numero) {
    const modal = document.getElementById('modalDetalhes');
    const conteudo = document.getElementById('conteudoDetalhes');
    
    conteudo.innerHTML = '<div style="text-align: center; padding: 40px;"><p>Carregando histórico...</p></div>';
    modal.style.display = 'block';
    
    fetch('utils/historico_contratacao.php?numero=' + encodeURIComponent(numero))
        .then(response => response.text())
        .then(html => {
            conteudo.innerHTML = html;
        })
        .catch(() => {
            conteudo.innerHTML = '<div style="padding: 40px; text-align: center;">Erro ao carregar histórico</div>';
        });
}


/**
 * Filtrar por limite de registros
 */
function filtrarPorLimite(limite) {
    const url = new URL(window.location);
    url.searchParams.set('limite', limite);
    url.searchParams.set('secao', 'lista-contratacoes');
    window.location.href = url.toString();
}

// ==================== FUNÇÕES DE RELATÓRIOS ====================

/**
 * Gerar relatório PCA
 */
function gerarRelatorioPCA(tipo) {
    const modal = document.getElementById('modalRelatorioPCA');
    const titulo = document.getElementById('tituloRelatorioPCA');
    document.getElementById('tipo_relatorio_pca').value = tipo;
    
    // Resetar formulário
    document.getElementById('formRelatorioPCA').reset();
    document.getElementById('pca_data_inicial').value = new Date().getFullYear() + '-01-01';
    document.getElementById('pca_data_final').value = new Date().toISOString().split('T')[0];
    document.getElementById('pca_graficos').checked = true;
    
    // Configurar título e visibilidade dos campos
    switch(tipo) {
        case 'categoria':
            titulo.textContent = 'Relatório por Categoria';
            document.getElementById('filtroCategoriaPCA').style.display = 'block';
            document.getElementById('filtroAreaPCA').style.display = 'block';
            document.getElementById('filtroSituacaoPCA').style.display = 'block';
            break;
            
        case 'area':
            titulo.textContent = 'Relatório por Área Requisitante';
            document.getElementById('filtroCategoriaPCA').style.display = 'block';
            document.getElementById('filtroAreaPCA').style.display = 'block';
            document.getElementById('filtroSituacaoPCA').style.display = 'block';
            break;
            
        case 'prazos':
            titulo.textContent = 'Relatório de Análise de Prazos';
            document.getElementById('filtroCategoriaPCA').style.display = 'block';
            document.getElementById('filtroAreaPCA').style.display = 'block';
            document.getElementById('filtroSituacaoPCA').style.display = 'none';
            break;
            
        case 'financeiro':
            titulo.textContent = 'Relatório Financeiro do PCA';
            document.getElementById('filtroCategoriaPCA').style.display = 'block';
            document.getElementById('filtroAreaPCA').style.display = 'block';
            document.getElementById('filtroSituacaoPCA').style.display = 'block';
            break;
    }
    
    modal.style.display = 'block';
}

/**
 * Fechar modal genérico
 */
function fecharModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// ==================== SISTEMA DE BACKUP ====================

/**
 * Executar backup manual
 */
function executarBackup(tipo) {
    const button = document.getElementById(`btn-backup-${tipo === 'database' ? 'db' : 'files'}`);
    const statusDiv = document.getElementById('backup-status');
    const progressBar = document.getElementById('backup-progress-bar');
    const messageDiv = document.getElementById('backup-message');
    
    // Desabilitar botão e mostrar progresso
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Executando...';
    statusDiv.style.display = 'block';
    statusDiv.style.background = '#e3f2fd';
    statusDiv.style.border = '1px solid #2196f3';
    
    // Simular progresso
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += Math.random() * 20;
        if (progress > 90) progress = 90;
        progressBar.style.width = progress + '%';
    }, 1000);
    
    // Fazer requisição para executar backup
    fetch('api/backup_api_simple.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            acao: 'executar_backup',
            tipo: tipo
        })
    })
    .then(response => response.json())
    .then(data => {
        clearInterval(progressInterval);
        progressBar.style.width = '100%';
        
        if (data.sucesso) {
            statusDiv.style.background = '#e8f5e8';
            statusDiv.style.border = '1px solid #4caf50';
            messageDiv.innerHTML = `✅ Backup ${tipo} concluído com sucesso!<br>
                <small>Arquivo: ${data.arquivo || 'N/A'} | Tamanho: ${data.tamanho_formatado || 'N/A'}</small>`;
            
            // Atualizar estatísticas e histórico imediatamente
            atualizarEstatisticasBackup();
            atualizarHistoricoBackups();
            
            // Atualizar novamente após 1 segundo para garantir
            setTimeout(() => {
                atualizarHistoricoBackups();
                atualizarEstatisticasBackup();
            }, 1000);
            
        } else {
            statusDiv.style.background = '#ffebee';
            statusDiv.style.border = '1px solid #f44336';
            messageDiv.innerHTML = `❌ Erro no backup: ${data.erro || 'Erro desconhecido'}`;
        }
        
        // Reabilitar botão
        setTimeout(() => {
            button.disabled = false;
            button.innerHTML = getButtonIcon(tipo) + ' ' + getButtonText(tipo);
            lucide.createIcons();
        }, 3000);
        
        // Esconder status após delay
        setTimeout(() => {
            statusDiv.style.display = 'none';
            progressBar.style.width = '0%';
        }, 10000);
        
    })
    .catch(error => {
        clearInterval(progressInterval);
        console.error('Erro:', error);
        
        statusDiv.style.background = '#ffebee';
        statusDiv.style.border = '1px solid #f44336';
        messageDiv.innerHTML = '❌ Erro de comunicação com o servidor';
        
        button.disabled = false;
        button.innerHTML = getButtonIcon(tipo) + ' ' + getButtonText(tipo);
        lucide.createIcons();
    });
}

/**
 * Obter ícone do botão de backup
 */
function getButtonIcon(tipo) {
    switch(tipo) {
        case 'database': return '<i data-lucide="database"></i>';
        case 'arquivos': return '<i data-lucide="folder"></i>';
        default: return '<i data-lucide="shield"></i>';
    }
}

/**
 * Obter texto do botão de backup
 */
function getButtonText(tipo) {
    switch(tipo) {
        case 'database': return 'Backup do Banco de Dados';
        case 'arquivos': return 'Backup dos Arquivos';
        default: return 'Backup';
    }
}

/**
 * Atualizar estatísticas de backup
 */
function atualizarEstatisticasBackup() {
    fetch('api/backup_api_simple.php?acao=estatisticas')
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            document.getElementById('ultimo-backup').textContent = data.ultimo_backup || 'Nunca';
            document.getElementById('backups-mes').textContent = data.backups_mes || '0';
            document.getElementById('tamanho-backups').textContent = data.tamanho_total || '0 MB';
            
            // Atualizar status do sistema
            const statusElement = document.getElementById('status-sistema');
            if (data.sistema_ok) {
                statusElement.innerHTML = '🟢 Online';
                statusElement.style.color = '#27ae60';
            } else {
                statusElement.innerHTML = '🔴 Problemas';
                statusElement.style.color = '#e74c3c';
            }
        }
    })
    .catch(error => {
        console.error('Erro ao carregar estatísticas:', error);
    });
}

/**
 * Atualizar histórico de backups
 */
function atualizarHistoricoBackups() {
    const loadingDiv = document.getElementById('loading-backups');
    const tabelaDiv = document.getElementById('tabela-backups');
    const tbody = document.getElementById('tbody-backups');
    
    loadingDiv.style.display = 'block';
    tabelaDiv.style.display = 'none';
    
    fetch('api/backup_api_simple.php?acao=historico')
    .then(response => response.json())
    .then(data => {
        if (data.sucesso && data.backups) {
            tbody.innerHTML = '';
            
            data.backups.forEach(backup => {
                const row = document.createElement('tr');
                
                // Status badge
                let statusBadge = '';
                if (backup.status === 'sucesso') {
                    statusBadge = '<span style="background: #e8f5e8; color: #2e7d32; padding: 4px 8px; border-radius: 12px; font-size: 12px;">✅ Sucesso</span>';
                } else if (backup.status === 'erro') {
                    statusBadge = '<span style="background: #ffebee; color: #c62828; padding: 4px 8px; border-radius: 12px; font-size: 12px;">❌ Erro</span>';
                } else {
                    statusBadge = '<span style="background: #fff3e0; color: #f57c00; padding: 4px 8px; border-radius: 12px; font-size: 12px;">⏳ Em andamento</span>';
                }
                
                // Tipo badge
                let tipoBadge = `<span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 12px;">${backup.tipo}</span>`;
                
                row.innerHTML = `
                    <td>${formatarDataHora(backup.inicio)}</td>
                    <td>${tipoBadge}</td>
                    <td>${statusBadge}</td>
                    <td>${backup.tamanho_formatado || '-'}</td>
                    <td>${backup.tempo_execucao ? backup.tempo_execucao + 's' : '-'}</td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            ${backup.status === 'sucesso' ? `
                                <button onclick="verificarBackup(${backup.id})" class="btn-acao btn-ver" title="Verificar integridade">
                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                </button>
                            ` : ''}
                            ${backup.arquivo_database ? `
                                <button onclick="downloadBackup('${backup.arquivo_database}')" class="btn-acao btn-historico" title="Download DB">
                                    <i data-lucide="database" style="width: 14px; height: 14px;"></i>
                                </button>
                            ` : ''}
                            ${backup.arquivo_files ? `
                                <button onclick="downloadBackup('${backup.arquivo_files}')" class="btn-acao btn-historico" title="Download Files">
                                    <i data-lucide="folder" style="width: 14px; height: 14px;"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
            
            lucide.createIcons();
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #7f8c8d;">Nenhum backup encontrado</td></tr>';
        }
        
        loadingDiv.style.display = 'none';
        tabelaDiv.style.display = 'block';
    })
    .catch(error => {
        console.error('Erro ao carregar histórico:', error);
        loadingDiv.style.display = 'none';
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #e74c3c;">Erro ao carregar dados</td></tr>';
        tabelaDiv.style.display = 'block';
    });
}

/**
 * Formatar data e hora
 */
function formatarDataHora(dataISO) {
    const data = new Date(dataISO);
    return data.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Verificar integridade de um backup
 */
function verificarBackup(backupId) {
    if (!confirm('Verificar a integridade deste backup? Esta operação pode demorar alguns minutos.')) {
        return;
    }
    
    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-2" style="width: 14px; height: 14px; animation: spin 1s linear infinite;"></i>';
    
    fetch('api/backup_api_simple.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            acao: 'verificar_integridade',
            backup_id: backupId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            if (data.integro) {
                alert('✅ Backup verificado com sucesso! Todos os arquivos estão íntegros.');
            } else {
                alert('❌ Problemas encontrados no backup:\n' + (data.erros ? data.erros.join('\n') : 'Erro desconhecido'));
            }
        } else {
            alert('❌ Erro ao verificar backup: ' + (data.erro || 'Erro desconhecido'));
        }
        
        button.disabled = false;
        button.innerHTML = originalHTML;
        lucide.createIcons();
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro de comunicação com o servidor');
        button.disabled = false;
        button.innerHTML = originalHTML;
        lucide.createIcons();
    });
}

/**
 * Download de arquivo de backup
 */
function downloadBackup(nomeArquivo) {
    if (!confirm(`Fazer download do arquivo ${nomeArquivo}?`)) {
        return;
    }
    
    window.open(`backup_api.php?acao=download&arquivo=${encodeURIComponent(nomeArquivo)}`, '_blank');
}

/**
 * Limpar backups antigos
 */
function limparBackupsAntigos() {
    if (!confirm('Limpar backups antigos conforme política de retenção? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    const button = event.target;
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Limpando...';
    
    fetch('api/backup_api_simple.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            acao: 'limpar_antigos'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            alert(`✅ Limpeza concluída! ${data.arquivos_removidos || 0} arquivos removidos.`);
            atualizarHistoricoBackups();
            atualizarEstatisticasBackup();
        } else {
            alert('❌ Erro na limpeza: ' + (data.erro || 'Erro desconhecido'));
        }
        
        button.disabled = false;
        button.innerHTML = originalHTML;
        lucide.createIcons();
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro de comunicação com o servidor');
        button.disabled = false;
        button.innerHTML = originalHTML;
        lucide.createIcons();
    });
}

/**
 * Mostrar ajuda sobre automação
 */
function mostrarAjudaAutomacao() {
    alert('📋 Para automação no Windows/XAMPP:\n\n1. Abrir "Agendador de Tarefas"\n2. Criar Tarefa Básica\n3. Configurar execução diária\n4. Programa: C:\\xampp\\php\\php.exe\n5. Argumentos: C:\\xampp\\htdocs\\sistema_licitacao\\cron_backup.php --tipo=database\n\nPara mais detalhes, consulte o arquivo INSTALACAO_BACKUP.md');
}

/**
 * Gerenciar arquivos de backup
 */
function gerenciarArquivos() {
    const modal = document.getElementById('modalDetalhes');
    const conteudo = document.getElementById('conteudoDetalhes');
    
    // Criar interface para gerenciar arquivos
    conteudo.innerHTML = `
        <div style="padding: 20px;">
            <h3 style="margin: 0 0 20px 0; color: #2c3e50; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="folder-open"></i> Gerenciador de Arquivos de Backup
            </h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div style="background: #f8f9fa; border-radius: 8px; padding: 15px;">
                    <h4 style="margin: 0 0 10px 0; color: #495057;">Banco de Dados</h4>
                    <div id="arquivos-database">
                        <div style="text-align: center; padding: 20px; color: #6c757d;">
                            <i data-lucide="loader-2" style="width: 24px; height: 24px; animation: spin 1s linear infinite;"></i>
                            <p>Carregando...</p>
                        </div>
                    </div>
                </div>
                
                <div style="background: #f8f9fa; border-radius: 8px; padding: 15px;">
                    <h4 style="margin: 0 0 10px 0; color: #495057;">Arquivos</h4>
                    <div id="arquivos-files">
                        <div style="text-align: center; padding: 20px; color: #6c757d;">
                            <i data-lucide="loader-2" style="width: 24px; height: 24px; animation: spin 1s linear infinite;"></i>
                            <p>Carregando...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #dee2e6;">
                <div style="display: flex; gap: 10px;">
                    <button onclick="abrirDiretorioBackups()" class="btn-info">
                        <i data-lucide="external-link"></i> Abrir Pasta de Backups
                    </button>
                    <button onclick="verificarEspaco()" class="btn-secondary">
                        <i data-lucide="hard-drive"></i> Verificar Espaço
                    </button>
                </div>
                <button onclick="fecharModalDetalhes()" class="btn-primary">
                    <i data-lucide="x"></i> Fechar
                </button>
            </div>
        </div>
    `;
    
    modal.style.display = 'block';
    lucide.createIcons();
    
    // Carregar lista de arquivos
    carregarListaArquivos();
}

/**
 * Carregar lista de arquivos
 */
function carregarListaArquivos() {
    // Simular carregamento de arquivos (em produção, usar uma API real)
    setTimeout(() => {
        const arquivosDB = document.getElementById('arquivos-database');
        const arquivosFiles = document.getElementById('arquivos-files');
        
        if (arquivosDB) {
            arquivosDB.innerHTML = `
                <div style="max-height: 200px; overflow-y: auto; display: flex; align-items: center; justify-content: center; color: #6c757d; text-align: center;">
                    <div>
                        <i data-lucide="database" style="width: 32px; height: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>Nenhum backup de banco encontrado<br><small>Execute um backup para visualizar os arquivos</small></p>
                    </div>
                </div>
            `;
            lucide.createIcons();
        }
        
        if (arquivosFiles) {
            arquivosFiles.innerHTML = `
                <div style="max-height: 200px; overflow-y: auto; display: flex; align-items: center; justify-content: center; color: #6c757d; text-align: center;">
                    <div>
                        <i data-lucide="folder" style="width: 32px; height: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>Nenhum backup de arquivos encontrado<br><small>Execute um backup para visualizar os arquivos</small></p>
                    </div>
                </div>
            `;
            lucide.createIcons();
        }
        
    }, 1000);
}

/**
 * Abrir diretório de backups
 */
function abrirDiretorioBackups() {
    if (confirm('Abrir a pasta de backups no explorador de arquivos?')) {
        // Em um ambiente real, isso seria feito via API do sistema
        alert('🗂️ Pasta de backups está localizada em:\n\nC:\\xampp\\htdocs\\sistema_licitacao\\backups\\');
    }
}

/**
 * Abrir modal de criar licitação
 */
function abrirModalCriarLicitacao() {
    const modal = document.getElementById('modalCriarLicitacao');
    
    // Limpar formulário
    modal.querySelector('form').reset();
    
    // Definir ano atual
    modal.querySelector('input[name="ano"]').value = new Date().getFullYear();
    
    // Mostrar modal
    modal.style.display = 'block';
    
    // Focar no primeiro campo
    setTimeout(() => {
        modal.querySelector('#nup_criar').focus();
    }, 100);
}

/**
 * Verificar espaço em disco
 */
function verificarEspaco() {
    alert('💾 Verificação de Espaço em Disco:\n\n' +
          'Esta funcionalidade requer implementação de API no servidor.\n' +
          'Entre em contato com o administrador do sistema para verificar o espaço em disco.');
}

// ==================== EVENT LISTENERS ====================

/**
 * Inicialização quando DOM estiver carregado
 */
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar ícones Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Inicializar gráficos se Chart.js estiver disponível
    if (typeof Chart !== 'undefined') {
        initCharts();
    }
    
    // Event listener para formulário de relatório PCA
    const formRelatorioPCA = document.getElementById('formRelatorioPCA');
    if (formRelatorioPCA) {
        formRelatorioPCA.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const params = new URLSearchParams();
            
            for (const [key, value] of formData) {
                if (value) params.append(key, value);
            }
            
            const formato = formData.get('formato');
            const url = 'relatorios/gerar_relatorio_planejamento.php?' + params.toString();
            
            if (formato === 'html') {
                // Abrir em nova aba
                window.open(url, '_blank');
            } else {
                // Download direto
                window.location.href = url;
            }
            
            fecharModal('modalRelatorioPCA');
        });
    }
});

/**
 * Fechar modais ao clicar fora
 */
window.onclick = function(event) {
    const modalPCA = document.getElementById('modalRelatorioPCA');
    const modalDetalhes = document.getElementById('modalDetalhes');
    
    if (event.target == modalPCA) {
        fecharModal('modalRelatorioPCA');
    } else if (event.target == modalDetalhes) {
        fecharModalDetalhes();
    }
};