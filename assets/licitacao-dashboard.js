/**
 * Licitação Dashboard JavaScript - Sistema CGLIC
 * Funcionalidades do painel de controle de licitações
 */

// ==================== NAVEGAÇÃO E INTERFACE ====================

// Variável global para armazenar instâncias dos gráficos
window.chartInstances = [];

/**
 * Navegação da Sidebar
 */
function showSection(sectionId) {
    // Esconder todas as seções
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });

    // Remover classe ativa de todos os nav-items
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });

    // Mostrar seção selecionada
    document.getElementById(sectionId).classList.add('active');

    // Ativar nav-item clicado
    // Event.target pode não ser o elemento que queremos se o clique for no ícone.
    // Usar o elemento com a função onclick.
    const clickedElement = event.target.closest('.nav-item');
    if (clickedElement) {
        clickedElement.classList.add('active');
    }
}

function formatarValorCorreto(valor) {
    if (!valor || valor === null || valor === undefined) {
        return 'R$ 0,00';
    }

    const numero = typeof valor === 'string' ? parseFloat(valor) : valor;

    if (isNaN(numero)) {
        return 'R$ 0,00';
    }

    return numero.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// ==================== GRÁFICOS ====================

/**
 * Inicializar gráficos do dashboard com correções de altura
 */
function initCharts() {
    setTimeout(() => {
        // Verificar se os dados foram passados do PHP
        if (!window.dadosModalidade || !window.dadosPregoeiro ||
            !window.dadosMensal || !window.stats) {
            console.warn('Dados do dashboard não foram carregados do PHP');
            return;
        }

        const dadosModalidade = window.dadosModalidade;
        const dadosPregoeiro = window.dadosPregoeiro;
        const dadosMensal = window.dadosMensal;
        const stats = window.stats;

        // Limpar instâncias anteriores
        destroyAllCharts();

        // Configurações globais do Chart.js
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.plugins.legend.labels.boxWidth = 12;
        Chart.defaults.plugins.legend.labels.padding = 10;

        // Gráfico de Modalidades (Donut)
        const ctxModalidade = document.getElementById('chartModalidade');
        if (ctxModalidade) {
            const chartModalidade = new Chart(ctxModalidade, {
                type: 'doughnut',
                data: {
                    labels: dadosModalidade.map(item => item.modalidade),
                    datasets: [{
                        data: dadosModalidade.map(item => item.quantidade),
                        backgroundColor: ['#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 20,
                            left: 20,
                            right: 20
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            window.chartInstances.push(chartModalidade);
        }

        // Gráfico de Pregoeiros (Barras)
        const ctxPregoeiro = document.getElementById('chartPregoeiro');
        if (ctxPregoeiro) {
            const chartPregoeiro = new Chart(ctxPregoeiro, {
                type: 'bar',
                data: {
                    labels: dadosPregoeiro.map(item => {
                        // Truncar nomes muito longos
                        const nome = item.pregoeiro;
                        return nome.length > 15 ? nome.substring(0, 15) + '...' : nome;
                    }),
                    datasets: [{
                        label: 'Licitações',
                        data: dadosPregoeiro.map(item => item.quantidade),
                        backgroundColor: '#3498db',
                        borderColor: '#2980b9',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 10,
                            left: 10,
                            right: 10
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                title: function(tooltipItems) {
                                    // Mostrar nome completo no tooltip
                                    const index = tooltipItems[0].dataIndex;
                                    return dadosPregoeiro[index].pregoeiro;
                                }
                            }
                        }
                    }
                }
            });
            window.chartInstances.push(chartPregoeiro);
        }

        // Gráfico Mensal (Linha)
        const ctxMensal = document.getElementById('chartMensal');
        if (ctxMensal) {
            const chartMensal = new Chart(ctxMensal, {
                type: 'line',
                data: {
                    labels: dadosMensal.map(item => {
                        const [ano, mes] = item.mes.split('-');
                        const data = new Date(ano, mes - 1);
                        return data.toLocaleDateString('pt-BR', {
                            month: 'short',
                            year: 'numeric'
                        }).replace('.', '');
                    }),
                    datasets: [{
                        label: 'Licitações Criadas',
                        data: dadosMensal.map(item => item.quantidade),
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        pointBackgroundColor: '#e74c3c',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 10,
                            left: 10,
                            right: 10
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            window.chartInstances.push(chartMensal);
        }

        // Gráfico de Status (Donut)
        const ctxStatus = document.getElementById('chartStatus');
        if (ctxStatus) {
            const chartStatus = new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: ['Em Andamento', 'Homologadas', 'Fracassadas', 'Revogadas'],
                    datasets: [{
                        data: [
                            stats.em_andamento || 0,
                            stats.homologadas || 0,
                            stats.fracassadas || 0,
                            stats.revogadas || 0
                        ],
                        backgroundColor: ['#f39c12', '#27ae60', '#e74c3c', '#95a5a6'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 20,
                            left: 20,
                            right: 20
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0';
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            window.chartInstances.push(chartStatus);
        }

        console.log('Gráficos inicializados com sucesso! Total de instâncias:', window.chartInstances.length);

        // Forçar redimensionamento após pequeno delay
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 100);

    }, 500);
}

/**
 * Destruir todas as instâncias de gráficos
 */
function destroyAllCharts() {
    if (window.chartInstances && window.chartInstances.length > 0) {
        window.chartInstances.forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        window.chartInstances = [];
    }
}

/**
 * Redimensionar todos os gráficos
 */
function resizeAllCharts() {
    if (window.chartInstances && window.chartInstances.length > 0) {
        window.chartInstances.forEach(chart => {
            if (chart && typeof chart.resize === 'function') {
                try {
                    chart.resize();
                } catch (error) {
                    console.warn('Erro ao redimensionar gráfico:', error);
                }
            }
        });
    }
}

// Função para redimensionar gráficos quando a janela muda
window.addEventListener('resize', () => {
    resizeAllCharts();
});

// Atualizar o event listener para o botão de voltar ao dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Reinicializar gráficos ao mudar de seção
    const originalShowSection = window.showSection;
    if (originalShowSection) {
        window.showSection = function(sectionId) {
            originalShowSection.call(this, sectionId);

            // Se voltou para o dashboard, redimensionar gráficos
            if (sectionId === 'dashboard') {
                setTimeout(() => {
                    resizeAllCharts();
                }, 100);
            }
        };
    }
});

// ==================== FUNÇÕES DA TABELA ====================

/**
 * Filtrar licitações por situação
 */
function filtrarLicitacoes(situacao) {
    const rows = document.querySelectorAll('#lista-licitacoes tbody tr');

    rows.forEach(row => {
        if (situacao === '') {
            row.style.display = '';
        } else {
            const statusCell = row.querySelector('.status-badge');
            const status = statusCell.textContent.trim().toUpperCase().replace(' ', '_');

            if (status === situacao) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

/**
 * Exportar licitações para CSV
 */
function exportarLicitacoes() {
    const dados = [];
    const rows = document.querySelectorAll('#lista-licitacoes tbody tr');

    dados.push(['NUP', 'Modalidade', 'Número/Ano', 'Objeto', 'Valor Estimado', 'Situação', 'Pregoeiro', 'Data Abertura']);

    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            dados.push([
                cells[0].textContent.trim(),
                cells[1].textContent.trim(),
                cells[2].textContent.trim(),
                cells[3].textContent.trim(),
                cells[4].textContent.trim(),
                cells[5].textContent.trim(),
                cells[6].textContent.trim(),
                cells[7].textContent.trim()
            ]);
        }
    });

    let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
    dados.forEach(row => {
        csvContent += row.map(cell => '"' + cell + '"').join(';') + '\n';
    });

    const link = document.createElement('a');
    link.setAttribute('href', encodeURI(csvContent));
    link.setAttribute('download', 'licitacoes_' + new Date().toISOString().split('T')[0] + '.csv');
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// ==================== FUNÇÕES DE RELATÓRIOS ====================

/**
 * Abrir modal de relatório
 */
function gerarRelatorio(tipo) {
    const modal = document.getElementById('modalRelatorio');
    const titulo = document.getElementById('tituloRelatorio');
    document.getElementById('tipo_relatorio').value = tipo;

    // Resetar formulário
    document.getElementById('formRelatorio').reset();
    document.getElementById('rel_data_final').value = new Date().toISOString().split('T')[0];

    // Configurar título e campos específicos
    switch(tipo) {
        case 'modalidade':
            titulo.textContent = 'Relatório por Modalidade';
            document.getElementById('filtroModalidade').style.display = 'none';
            document.getElementById('filtroPregoeiro').style.display = 'none';
            break;

        case 'pregoeiro':
            titulo.textContent = 'Relatório por Pregoeiro';
            document.getElementById('filtroModalidade').style.display = 'block';
            document.getElementById('filtroPregoeiro').style.display = 'block';
            break;

        case 'prazos':
            titulo.textContent = 'Relatório de Prazos';
            document.getElementById('filtroModalidade').style.display = 'block';
            document.getElementById('filtroPregoeiro').style.display = 'none';
            break;

        case 'financeiro':
            titulo.textContent = 'Relatório Financeiro';
            document.getElementById('filtroModalidade').style.display = 'block';
            document.getElementById('filtroPregoeiro').style.display = 'none';
            break;
    }

    modal.style.display = 'block';
}

// ==================== MODAL DE DETALHES ====================

/**
 * Ver detalhes de uma licitação
 */
function verDetalhes(id) {
    const modal = document.getElementById('modalDetalhes');
    const content = document.getElementById('detalhesContent');

    content.innerHTML = '<div style="text-align: center; padding: 40px;"><i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Carregando...</div>';
    modal.style.display = 'block';

    fetch('api/get_licitacao.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const lic = data.data;
                content.innerHTML = `
                    <div style="display: grid; gap: 25px;">
                        <div>
                            <h4 style="margin: 0 0 20px 0; color: #2c3e50; padding-bottom: 10px; border-bottom: 2px solid #f8f9fa;">
                                <i data-lucide="info"></i> Informações Gerais
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">NUP</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.nup}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Modalidade</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">
                                        <span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 14px; font-weight: 600;">${lic.modalidade}</span>
                                    </div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Tipo</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.tipo}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Número da Contratação</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.numero_contratacao || '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Situação</label>
                                    <div style="font-size: 16px; margin-top: 5px;">
                                        <span class="status-badge status-${lic.situacao.toLowerCase().replace('_', '-')}">${lic.situacao.replace('_', ' ')}</span>
                                    </div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Valor Estimado</label>
                                    <div style="font-size: 16px; color: #27ae60; font-weight: 600; margin-top: 5px;">${formatarValorCorreto(lic.valor_estimado)}</div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 style="margin: 0 0 15px 0; color: #2c3e50;">
                                <i data-lucide="file-text"></i> Objeto
                            </h4>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; line-height: 1.6;">
                                ${lic.objeto}
                            </div>
                        </div>

                        ${lic.numero_contratacao ? `
                        <div>
                            <h4 style="margin: 0 0 15px 0; color: #2c3e50;">
                                <i data-lucide="database"></i> Dados do PCA
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; background: #e8f5e9; padding: 15px; border-radius: 8px;">
                                <div>
                                    <label style="font-weight: 600; color: #388e3c; font-size: 12px; text-transform: uppercase;">Nº Contratação PCA</label>
                                    <div style="font-size: 16px; color: #2e7d32; margin-top: 5px;">${lic.numero_contratacao || '-'}</div>
                                </div>
                                <div style="grid-column: 1 / -1;">
                                    <label style="font-weight: 600; color: #388e3c; font-size: 12px; text-transform: uppercase;">Título Contratação</label>
                                    <div style="font-size: 16px; color: #2e7d32; margin-top: 5px;">${lic.titulo_contratacao || '-'}</div>
                                </div>
                            </div>
                        </div>
                        ` : ''}

                        <div>
                            <h4 style="margin: 0 0 20px 0; color: #2c3e50; padding-bottom: 10px; border-bottom: 2px solid #f8f9fa;">
                                <i data-lucide="calendar"></i> Datas e Responsáveis
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Data Entrada DIPLI</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.data_entrada_dipli ? new Date(lic.data_entrada_dipli).toLocaleDateString('pt-BR') : '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Data Abertura</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.data_abertura ? new Date(lic.data_abertura).toLocaleDateString('pt-BR') : '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Pregoeiro</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.pregoeiro || '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Área Demandante</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.area_demandante || '-'}</div>
                                </div>
                            </div>
                        </div>

                        ${lic.situacao === 'HOMOLOGADO' ? `
                        <div>
                            <h4 style="margin: 0 0 20px 0; color: #27ae60; padding-bottom: 10px; border-bottom: 2px solid #d4edda;">
                                <i data-lucide="check-circle"></i> Dados da Homologação
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Data Homologação</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.data_homologacao ? new Date(lic.data_homologacao).toLocaleDateString('pt-BR') : '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Qtd Homologada</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.qtd_homol || '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Valor Homologado</label>
                                    <div style="font-size: 16px; color: #27ae60; font-weight: 600; margin-top: 5px;">${formatarValorCorreto(lic.valor_homologado)}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Economia</label>
                                    <div style="font-size: 16px; color: #3498db; font-weight: 600; margin-top: 5px;">${formatarValorCorreto(lic.economia)}</div>
                                </div>
                            </div>
                        </div>
                        ` : ''}

                        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; color: #6c757d; font-size: 14px;">
                            <p style="margin: 0;">Criado por: <strong>${lic.usuario_nome || 'N/A'}</strong> em ${new Date(lic.criado_em).toLocaleString('pt-BR')}</p>
                            ${lic.atualizado_em !== lic.criado_em ? `<p style="margin: 5px 0 0 0;">Última atualização: ${new Date(lic.atualizado_em).toLocaleString('pt-BR')}</p>` : ''}
                        </div>
                    </div>
                `;

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            } else {
                content.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;">Erro ao carregar detalhes da licitação</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;">Erro ao conectar com o servidor</div>';
        });
}

// ==================== EDIÇÃO DE LICITAÇÕES ====================

/**
 * Editar licitação
 */
function editarLicitacao(id) {
    const modal = document.getElementById('modalEdicao');

    // Buscar dados via AJAX
    fetch('api/get_licitacao.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const lic = data.data;

                // Preencher todos os campos do formulário
                document.getElementById('edit_id').value = lic.id;
                document.getElementById('edit_nup').value = lic.nup;
                document.getElementById('edit_data_entrada_dipli').value = lic.data_entrada_dipli || '';
                document.getElementById('edit_resp_instrucao').value = lic.resp_instrucao || '';
                document.getElementById('edit_area_demandante').value = lic.area_demandante || '';
                document.getElementById('edit_pregoeiro').value = lic.pregoeiro || '';
                document.getElementById('edit_modalidade').value = lic.modalidade;
                document.getElementById('edit_tipo').value = lic.tipo;

                // Preencher campo de contratação
                document.getElementById('edit_input_contratacao').value = lic.numero_contratacao || '';
                document.getElementById('edit_titulo_contratacao_selecionado').value = lic.titulo_contratacao || '';

                document.getElementById('edit_ano').value = lic.ano || new Date().getFullYear();

                // Formatar valores monetários
                if (lic.valor_estimado) {
                    document.getElementById('edit_valor_estimado').value = parseFloat(lic.valor_estimado).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                } else {
                    document.getElementById('edit_valor_estimado').value = '';
                }

                document.getElementById('edit_data_abertura').value = lic.data_abertura || '';
                document.getElementById('edit_data_homologacao').value = lic.data_homologacao || '';

                if (lic.valor_homologado) {
                    document.getElementById('edit_valor_homologado').value = parseFloat(lic.valor_homologado).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                } else {
                    document.getElementById('edit_valor_homologado').value = '';
                }

                if (lic.economia) {
                    document.getElementById('edit_economia').value = parseFloat(lic.economia).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                } else {
                    document.getElementById('edit_economia').value = '';
                }

                document.getElementById('edit_link').value = lic.link || '';
                document.getElementById('edit_situacao').value = lic.situacao;
                document.getElementById('edit_objeto').value = lic.objeto;

                // Mostrar modal
                modal.style.display = 'block';

                // Atualizar ícones Lucide após adicionar o conteúdo
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            } else {
                alert('Erro ao carregar dados da licitação');
            }
        })
        .catch(error => {
            alert('Erro ao conectar com o servidor');
        });
}

// ==================== FUNÇÕES DE FORMATAÇÃO ====================

/**
 * Formatar NUP
 */
function formatarNUP(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 0) {
        value = value.substring(0, 17);
        let formatted = '';

        if (value.length > 0) {
            formatted = value.substring(0, 5);
        }
        if (value.length > 5) {
            formatted += '.' + value.substring(5, 11);
        }
        if (value.length > 11) {
            formatted += '/' + value.substring(11, 15);
        }
        if (value.length > 15) {
            formatted += '-' + value.substring(15, 17);
        }

        input.value = formatted;
    }
}

/**
 * Formatar valores monetários
 */
function formatarValorMonetario(input) {
    // Salvar posição do cursor
    let cursorPos = input.selectionStart;
    let originalLength = input.value.length;

    // Remover formatação anterior (manter apenas dígitos e vírgula)
    let value = input.value.replace(/[^\d,]/g, '');

    if (value.length === 0) {
        input.value = '';
        return;
    }

    // Permitir que o usuário digite sem forçar vírgula
    let parts = value.split(',');

    // Se há mais de uma vírgula, manter apenas a última
    if (parts.length > 2) {
        let decimais = parts.pop();
        value = parts.join('') + ',' + decimais;
        parts = value.split(',');
    }

    // Limitar decimais a 2 dígitos
    if (parts[1] && parts[1].length > 2) {
        parts[1] = parts[1].substring(0, 2);
    }

    // Formatar parte inteira e decimal
    let inteiros = parts[0];
    let decimais = parts[1];

    // Adicionar pontos nos milhares apenas na parte inteira
    if (inteiros.length > 3) {
        inteiros = inteiros.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Montar valor final
    if (decimais !== undefined) {
        input.value = inteiros + ',' + decimais;
    } else {
        input.value = inteiros;
    }

    // Restaurar posição do cursor aproximada
    let newLength = input.value.length;
    let newCursorPos = cursorPos + (newLength - originalLength);
    input.setSelectionRange(newCursorPos, newCursorPos);

    // Calcular economia
    // Verifica qual modal está ativo para chamar a função correta
    if (input.id.includes('_criar') && typeof calcularEconomiaModal === 'function') {
        calcularEconomiaModal();
    } else if (input.id.includes('_edit') && typeof calcularEconomiaEdit === 'function') {
        calcularEconomiaEdit();
    }
}

/**
 * Calcular economia
 */
function calcularEconomia() { // Esta função pode ser depreciada, usando calcularEconomiaModal/Edit
    const valorEstimadoField = document.getElementById('valor_estimado_criar');
    const valorHomologadoField = document.getElementById('valor_homologado_criar');
    const economiaField = document.getElementById('economia_criar');

    if (!valorEstimadoField || !valorHomologadoField || !economiaField) {
        return;
    }

    // Converter valores para números
    const valorEstimadoStr = valorEstimadoField.value.replace(/\./g, '').replace(',', '.');
    const valorHomologadoStr = valorHomologadoField.value.replace(/\./g, '').replace(',', '.');

    const valorEstimado = parseFloat(valorEstimadoStr) || 0;
    const valorHomologado = parseFloat(valorHomologadoStr) || 0;

    if (valorEstimado > 0 && valorHomologado > 0) {
        const economia = valorEstimado - valorHomologado;
        economiaField.value = economia.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else {
        economiaField.value = '';
    }
}

// ==================== INTEGRAÇÃO COM PCA ====================

/**
 * Carregar dados do PCA
 */
function carregarDadosPCA(numeroContratacao) {
    // Esta função é chamada pelo selecionarContratacao
    // O preenchimento dos campos já é feito diretamente em selecionarContratacao.
    // Esta função pode ser removida se não houver outras dependências.
    console.log('Chamada para carregarDadosPCA com:', numeroContratacao);
}

/**
 * Preencher dados do PCA selecionado
 */
function preencherDadosPCA() {
    // Esta função é chamada pelo onchange de um select que não existe mais.
    // Pode ser removida.
    console.warn('Função preencherDadosPCA chamada, mas pode não ser mais relevante.');
    return;
}

// ==================== FUNÇÕES GENÉRICAS ====================

/**
 * Fechar modal genérico
 */
function fecharModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
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

    // Máscaras e formatação para formulário de criação
    const nupInput = document.getElementById('nup_criar');
    if (nupInput) {
        nupInput.addEventListener('input', function() {
            formatarNUP(this);
        });
    }

    const valorEstimadoInput = document.getElementById('valor_estimado_criar');
    if (valorEstimadoInput) {
        valorEstimadoInput.addEventListener('input', function() {
            formatarValorMonetario(this);
        });

        valorEstimadoInput.addEventListener('blur', function() {
            calcularEconomiaModal(); // Chamada para a função específica do modal de criação
        });
    }

    const valorHomologadoInput = document.getElementById('valor_homologado_criar');
    if (valorHomologadoInput) {
        valorHomologadoInput.addEventListener('input', function() {
            formatarValorMonetario(this);
        });

        valorHomologadoInput.addEventListener('blur', function() {
            calcularEconomiaModal(); // Chamada para a função específica do modal de criação
        });
    }

    // Event listener para formulário de relatório
    const formRelatorio = document.getElementById('formRelatorio');
    if (formRelatorio) {
        formRelatorio.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const params = new URLSearchParams();

            for (const [key, value] of formData) {
                if (value) params.append(key, value);
            }

            const formato = formData.get('formato');
            const url = 'relatorios/gerar_relatorio_licitacao.php?' + params.toString();

            if (formato === 'html') {
                // Abrir em nova aba
                window.open(url, '_blank');
            } else {
                // Download direto
                window.location.href = url;
            }

            fecharModal('modalRelatorio');
        });
    }

    // Event listener para formulário de edição (já existia e foi ajustado no final do arquivo)
    // const formEditarLicitacao = document.getElementById('formEditarLicitacao');
    // if (formEditarLicitacao) { ... }

    // Event listener para formulário de exportação
    const formExportar = document.getElementById('formExportar');
    if (formExportar) {
        formExportar.addEventListener('submit', function(e) {
            e.preventDefault();

            const formato = document.getElementById('formato_export').value;
            const campos = Array.from(document.querySelectorAll('input[name="campos[]"]:checked')).map(cb => cb.value);
            const aplicarFiltros = document.getElementById('export_filtros').checked;

            // Pegar situação atual do filtro se aplicável
            let situacao = '';
            if (aplicarFiltros) {
                const filtroAtual = document.querySelector('#lista-licitacoes select').value;
                if (filtroAtual) situacao = filtroAtual;
            }

            // Construir URL
            const params = new URLSearchParams({
                formato: formato,
                campos: campos.join(','),
                situacao: situacao
            });

            // Abrir download
            window.open('exportar_licitacoes.php?' + params.toString(), '_blank');
            fecharModal('modalExportar');
        });
    }
});

/**
 * Fechar modais ao clicar fora
 */
window.onclick = function(event) {
    // Verificar se o clique foi em um modal (fundo)
    if (event.target.classList.contains('modal')) {
        // Verificar se não foi clique em sugestões de contratação ou outros elementos interativos
        if (!event.target.closest('.search-suggestions') && 
            !event.target.classList.contains('suggestion-item') &&
            !event.target.closest('.suggestion-item') &&
            !event.target.closest('.search-container')) {
            event.target.style.display = 'none';
        }
    }
}

/**
 * Abrir modal de criar licitação
 */
function abrirModalCriarLicitacao() {
    const modal = document.getElementById('modalCriarLicitacao');

    // Limpar formulário
    const form = modal.querySelector('form');
    form.reset();

    // Definir ano atual
    const anoInput = modal.querySelector('input[name="ano"]');
    if (anoInput) {
        anoInput.value = new Date().getFullYear();
    }

    // Limpar campos calculados
    const economiaField = document.getElementById('economia_criar');
    if (economiaField) {
        economiaField.value = '';
    }

    // Limpar campos de PCA ocultos
    document.getElementById('titulo_contratacao_selecionado').value = '';
    document.getElementById('input_contratacao').value = ''; // Limpar o campo de input de contratação também

    // Mostrar modal
    modal.style.display = 'block';

    // Focar no primeiro campo após um pequeno delay
    setTimeout(() => {
        const nupField = modal.querySelector('#nup_criar');
        if (nupField) {
            nupField.focus();
        }
    }, 100);
}

// Atualizar o event listener para o formulário de criação no modal
document.addEventListener('DOMContentLoaded', function() {
    // Event listener para o formulário de criação no modal
    const formCriarLicitacao = document.querySelector('#modalCriarLicitacao form');
    if (formCriarLicitacao) {
        formCriarLicitacao.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            // Converter valores monetários antes de enviar
            ['valor_estimado', 'valor_homologado', 'economia'].forEach(field => {
                const value = formData.get(field);
                if (value) {
                    // Remover separadores de milhares (pontos) e converter vírgula decimal para ponto
                    let cleanValue = value.toString().trim();
                    // Se tem vírgula, assumir que é separador decimal brasileiro
                    if (cleanValue.includes(',')) {
                        // Remover pontos (separadores de milhares) e trocar vírgula por ponto
                        cleanValue = cleanValue.replace(/\./g, '').replace(',', '.');
                    }
                    // Se não tem vírgula mas tem pontos, verificar se é separador decimal ou milhares
                    else if (cleanValue.includes('.')) {
                        const parts = cleanValue.split('.');
                        if (parts.length === 2 && parts[1].length <= 2) {
                            // Último ponto com 1-2 dígitos = decimal
                            cleanValue = cleanValue;
                        } else {
                            // Múltiplos pontos ou último com 3+ dígitos = separadores de milhares
                            cleanValue = cleanValue.replace(/\./g, '');
                        }
                    }
                    formData.set(field, cleanValue);
                }
            });

            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Criando...';
            submitBtn.disabled = true;

            fetch('process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Fechar modal e recarregar página
                    fecharModal('modalCriarLicitacao');
                    window.location.reload();
                } else {
                    alert(data.message || 'Erro ao criar licitação');
                    // Restaurar botão
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            })
            .catch(error => {
                alert('Erro ao processar requisição');
                // Restaurar botão
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
        });
    }

    // Aplicar máscaras e eventos para os campos do modal de criação
    const nupInputModal = document.querySelector('#modalCriarLicitacao #nup_criar');
    if (nupInputModal) {
        nupInputModal.addEventListener('input', function() {
            formatarNUP(this);
        });
    }

    const valorEstimadoInputModal = document.querySelector('#modalCriarLicitacao #valor_estimado_criar');
    if (valorEstimadoInputModal) {
        valorEstimadoInputModal.addEventListener('input', function() {
            formatarValorMonetario(this);
        });

        valorEstimadoInputModal.addEventListener('blur', function() {
            calcularEconomiaModal();
        });
    }

    const valorHomologadoInputModal = document.querySelector('#modalCriarLicitacao #valor_homologado_criar');
    if (valorHomologadoInputModal) {
        valorHomologadoInputModal.addEventListener('input', function() {
            formatarValorMonetario(this);
        });

        valorHomologadoInputModal.addEventListener('blur', function() {
            calcularEconomiaModal();
        });
    }
});

/**
 * Calcular economia no modal de criação
 */
function calcularEconomiaModal() {
    const valorEstimadoField = document.querySelector('#modalCriarLicitacao #valor_estimado_criar');
    const valorHomologadoField = document.querySelector('#modalCriarLicitacao #valor_homologado_criar');
    const economiaField = document.querySelector('#modalCriarLicitacao #economia_criar');

    if (!valorEstimadoField || !valorHomologadoField || !economiaField) {
        return;
    }

    // Converter valores para números
    const valorEstimadoStr = valorEstimadoField.value.replace(/\./g, '').replace(',', '.');
    const valorHomologadoStr = valorHomologadoField.value.replace(/\./g, '').replace(',', '.');

    const valorEstimado = parseFloat(valorEstimadoStr) || 0;
    const valorHomologado = parseFloat(valorHomologadoStr) || 0;

    if (valorEstimado > 0 && valorHomologado > 0) {
        const economia = valorEstimado - valorHomologado;
        economiaField.value = economia.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else {
        economiaField.value = '';
    }
}

/**
 * Atualizar a função preencherDadosPCA para funcionar no modal
 */
function preencherDadosPCA() {
    // Esta função é chamada pelo onchange de um select que não existe mais.
    // Pode ser removida.
    console.warn('Função preencherDadosPCA chamada, mas pode não ser mais relevante.');
    return;
}


// ==================== CAMPO DE PESQUISA PARA CONTRATAÇÕES ====================

// Variáveis globais para o autocomplete
let contratacoesPCA = [];
let sugestoesVisiveis = false;
let indiceSelecionado = -1;
let sugestoesFiltradas = [];
let timeoutPesquisa = null;

/**
 * Inicializar dados das contratações PCA
 */
function inicializarContratacoesPCA() {
    // Os dados são passados do PHP via window.contratacoesPCA
    if (typeof window.contratacoesPCA !== 'undefined') {
        contratacoesPCA = window.contratacoesPCA;
        console.log(`${contratacoesPCA.length} contratações PCA carregadas`);
    } else {
        console.warn('Dados das contratações PCA não foram carregados');
        contratacoesPCA = [];
    }
}

/**
 * Pesquisar contratações em tempo real
 */
function pesquisarContratacao(termo) {
    // Função desabilitada - usando sistema inline
    return;
}

/**
 * Realizar a pesquisa propriamente dita
 */
function realizarPesquisa(termo, sugestoesDiv, container, input) {
    try {
        // Verificar se os dados foram carregados
        if (!contratacoesPCA || contratacoesPCA.length === 0) {
            mostrarErro(sugestoesDiv, 'Dados das contratações não foram carregados');
            return;
        }

        // Filtrar contratações
        const termoLower = termo.toLowerCase().trim();
        sugestoesFiltradas = contratacoesPCA.filter(contratacao => {
            const numero = (contratacao.numero_contratacao || '').toLowerCase();
            const titulo = (contratacao.titulo_contratacao || '').toLowerCase();

            return numero.includes(termoLower) ||
                   titulo.includes(termoLower) ||
                   dfd.includes(termoLower);
        }).slice(0, 15); // Limitar a 15 resultados para performance

        // Mostrar sugestões
        mostrarSugestoesFiltradas(sugestoesDiv, container, termoLower);
        input.classList.remove('searching');

    } catch (error) {
        console.error('Erro na pesquisa:', error);
        mostrarErro(sugestoesDiv, 'Erro ao pesquisar contratações');
        input.classList.remove('searching');
    }
}

/**
 * Mostrar sugestões filtradas
 */
function mostrarSugestoesFiltradas(sugestoesDiv, container, termoLower) {
    if (sugestoesFiltradas.length === 0) {
        sugestoesDiv.innerHTML = `
            <div class="no-results">
                Nenhuma contratação encontrada para "${termoLower}"
            </div>
        `;
    } else {
        let html = '';

        // Adicionar contador de resultados se houver muitos
        if (contratacoesPCA.length > 15) {
            html += `<div class="suggestions-count">${sugestoesFiltradas.length} de ${contratacoesPCA.length}</div>`;
        }

        sugestoesFiltradas.forEach((contratacao, index) => {
            // Limitar o título para não ficar muito longo
            let tituloTruncado = contratacao.titulo_contratacao || 'Título não disponível';
            if (tituloTruncado.length > 100) {
                tituloTruncado = tituloTruncado.substring(0, 100) + '...';
            }

            // Destacar termo pesquisado
            const numeroDestacado = destacarTermo(contratacao.numero_contratacao || '', termoLower);
            const tituloDestacado = destacarTermo(tituloTruncado, termoLower);

            html += `
                <div class="suggestion-item"
                     data-index="${index}"
                     onclick="selecionarContratacao('${escapeHtml(contratacao.numero_contratacao)}', '${escapeHtml(contratacao.titulo_contratacao || '')}')">
                    <div class="suggestion-number">${numeroDestacado}</div>
                </div>
            `;
        });

        // Indicador se há mais resultados
        if (contratacoesPCA.length > sugestoesFiltradas.length) {
            const restantes = contratacoesPCA.length - sugestoesFiltradas.length;
            html += `<div class="more-results-indicator">Mais ${restantes} resultados disponíveis. Refine sua pesquisa.</div>`;
        }

        sugestoesDiv.innerHTML = html;
    }

    // Mostrar container de sugestões
    container.classList.add('has-suggestions');
    sugestoesDiv.style.display = 'block';
    sugestoesDiv.classList.add('show');
    sugestoesVisiveis = true;
    indiceSelecionado = -1;
}

/**
 * Destacar termo pesquisado no texto
 */
function destacarTermo(texto, termo) {
    if (!termo || !texto) return texto;

    const regex = new RegExp(`(${termo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return texto.replace(regex, '<span class="highlight">$1</span>');
}

/**
 * Escapar HTML para prevenir XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Mostrar erro nas sugestões
 */
function mostrarErro(sugestoesDiv, mensagem) {
    sugestoesDiv.innerHTML = `
        <div class="no-data-loaded">
            ${mensagem}
        </div>
    `;
    sugestoesDiv.style.display = 'block';
    sugestoesVisiveis = true;
}

/**
 * Selecionar uma contratação (função original, agora atualizada)
 */
// window.selecionarContratacao = function(numero, dfd, titulo) { ... } // Comentado, pois a função foi movida para o topo de licitacao_dashboard.php

/**
 * Preencher campos do formulário com base na contratação selecionada
 */
function preencherCamposFormulario(numeroContratacao, titulo) {
    // Preencher objeto se estiver vazio
    const objetoField = document.getElementById('objeto_textarea');
    if (objetoField && !objetoField.value.trim() && titulo) {
        objetoField.value = titulo;
    }

    // Buscar dados completos da contratação
    const contratacao = contratacoesPCA.find(c => c.numero_contratacao === numeroContratacao);
    if (contratacao) {
        // Preencher área demandante se disponível
        const areaField = document.getElementById('area_demandante_criar');
        if (areaField && !areaField.value.trim() && contratacao.area_requisitante) {
            areaField.value = contratacao.area_requisitante;
        }

        // Preencher valor estimado se disponível
        const valorField = document.getElementById('valor_estimado_criar');
        if (valorField && !valorField.value.trim() && contratacao.valor_total_contratacao) {
            const valor = parseFloat(contratacao.valor_total_contratacao);
            if (!isNaN(valor)) {
                valorField.value = valor.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        }
    }
}

/**
 * Mostrar sugestões quando input recebe foco
 */
function mostrarSugestoes() {
    // Função desabilitada - usando sistema inline
    return;
}

/**
 * Ocultar sugestões
 */
function ocultarSugestoes() {
    // Função desabilitada - usando sistema inline
    return;
}

/**
 * Limpar campos ocultos
 */
function limparCamposOcultos() {
    const tituloField = document.getElementById('titulo_contratacao_selecionado');

    if (dfdField) dfdField.value = '';
    if (tituloField) tituloField.value = '';
}

/**
 * Atualizar seleção visual (para navegação por teclado)
 */
function atualizarSelecaoVisual() {
    const sugestoes = document.querySelectorAll('.suggestion-item');

    sugestoes.forEach((item, index) => {
        if (index === indiceSelecionado) {
            item.classList.add('selected');
            // Scroll para manter item visível
            item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        } else {
            item.classList.remove('selected');
        }
    });
}

/**
 * Configurar navegação por teclado
 */
function configurarNavegacaoTeclado() {
    const input = document.getElementById('input_contratacao');

    if (!input) return;

    input.addEventListener('keydown', function(e) {
        if (!sugestoesVisiveis) return;

        const sugestoes = document.querySelectorAll('.suggestion-item');
        const totalSugestoes = sugestoes.length;

        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                indiceSelecionado = Math.min(indiceSelecionado + 1, totalSugestoes - 1);
                atualizarSelecaoVisual();
                break;

            case 'ArrowUp':
                e.preventDefault();
                indiceSelecionado = Math.max(indiceSelecionado - 1, -1);
                atualizarSelecaoVisual();
                break;

            case 'Enter':
                e.preventDefault();
                if (indiceSelecionado >= 0 && sugestoes[indiceSelecionado]) {
                    sugestoes[indiceSelecionado].click();
                } else if (totalSugestoes === 1) {
                    // Se há apenas uma sugestão, selecioná-la
                    sugestoes[0].click();
                }
                break;

            case 'Escape':
                e.preventDefault();
                ocultarSugestoes();
                input.blur();
                break;

            case 'Tab':
                // Permitir tab normal, mas ocultar sugestões
                ocultarSugestoes();
                break;
        }
    });
}

/**
 * Inicializar funcionalidades do campo de pesquisa
 */
function inicializarCampoPesquisa() {
    // Sistema desabilitado - usando sistema inline
    return;
}

// Sistema externo completamente desabilitado - usando apenas sistema inline
// document.addEventListener('DOMContentLoaded', function() {
//     inicializarCampoPesquisa();
// });

/**
 * Função compatível com código existente (manter se necessário)
 */
// Esta função é redundante se selecionarContratacao já preenche tudo
// function preencherDadosPCA() {
//     const numeroContratacao = document.getElementById('input_contratacao').value;

//     if (!numeroContratacao) return;

//     // Buscar dados da contratação selecionada
//     const contratacao = contratacoesPCA.find(c => c.numero_contratacao === numeroContratacao);

//     if (contratacao) {
//         preencherCamposFormulario(numeroContratacao, contratacao.titulo_contratacao);
//     }
// }

// Exportar funções para uso global se necessário
// window.pesquisarContratacao = pesquisarContratacao; // Já é definida no HTML
// window.selecionarContratacao = selecionarContratacao; // Já é definida no HTML
window.mostrarSugestoes = mostrarSugestoes;
window.ocultarSugestoes = ocultarSugestoes;
window.abrirModalCriarLicitacao = abrirModalCriarLicitacao;

// formatarValorCorreto é usada no verDetalhes
// window.formatarValorCorreto = formatarValorCorreto; // Já é definida no topo

// ==================== FUNÇÕES PARA O MODAL DE EDIÇÃO ====================

/**
 * Pesquisar contratação no modal de edição
 */
// window.pesquisarContratacaoInlineEdit = function(termo) { ... } // Já é definida no topo de licitacao_dashboard.php

/**
 * Selecionar contratação no modal de edição
 */
// window.selecionarContratacaoEdit = function(numero) { ... } // Já é definida no topo de licitacao_dashboard.php

// window.mostrarSugestoesInlineEdit = function() { ... } // Já é definida no topo de licitacao_dashboard.php
// window.ocultarSugestoesInlineEdit = function() { ... } // Já é definida no topo de licitacao_dashboard.php

/**
 * Calcular economia no modal de edição
 */
function calcularEconomiaEdit() {
    const valorEstimadoField = document.getElementById('edit_valor_estimado');
    const valorHomologadoField = document.getElementById('edit_valor_homologado');
    const economiaField = document.getElementById('edit_economia');

    if (!valorEstimadoField || !valorHomologadoField || !economiaField) {
        return;
    }

    // Converter valores para números
    const valorEstimadoStr = valorEstimadoField.value.replace(/\./g, '').replace(',', '.');
    const valorHomologadoStr = valorHomologadoField.value.replace(/\./g, '').replace(',', '.');

    const valorEstimado = parseFloat(valorEstimadoStr) || 0;
    const valorHomologado = parseFloat(valorHomologadoStr) || 0;

    if (valorEstimado > 0 && valorHomologado > 0) {
        const economia = valorEstimado - valorHomologado;
        economiaField.value = economia.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else {
        economiaField.value = '';
    }
}

// Adicionar event listeners para o modal de edição no DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    // Máscaras e formatação para modal de edição
    const editNupInput = document.getElementById('edit_nup');
    if (editNupInput) {
        editNupInput.addEventListener('input', function() {
            formatarNUP(this);
        });
    }

    const editValorEstimadoInput = document.getElementById('edit_valor_estimado');
    if (editValorEstimadoInput) {
        editValorEstimadoInput.addEventListener('input', function() {
            formatarValorMonetario(this);
        });

        editValorEstimadoInput.addEventListener('blur', function() {
            calcularEconomiaEdit();
        });
    }

    const editValorHomologadoInput = document.getElementById('edit_valor_homologado');
    if (editValorHomologadoInput) {
        editValorHomologadoInput.addEventListener('input', function() {
            formatarValorMonetario(this);
        });

        editValorHomologadoInput.addEventListener('blur', function() {
            calcularEconomiaEdit();
        });
    }

    // Event listener para formulário de edição atualizado
    const formEditarLicitacao = document.getElementById('formEditarLicitacao');
    if (formEditarLicitacao) {
        formEditarLicitacao.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            // Converter valores monetários
            ['valor_estimado', 'valor_homologado', 'economia'].forEach(field => {
                const value = formData.get(field);
                if (value) {
                    // Remover separadores de milhares (pontos) e converter vírgula decimal para ponto
                    let cleanValue = value.toString().trim();
                    if (cleanValue.includes(',')) {
                        cleanValue = cleanValue.replace(/\./g, '').replace(',', '.');
                    } else if (cleanValue.includes('.')) {
                        const parts = cleanValue.split('.');
                        if (parts.length === 2 && parts[1].length <= 2) {
                            cleanValue = cleanValue;
                        } else {
                            cleanValue = cleanValue.replace(/\./g, '');
                        }
                    }
                    formData.set(field, cleanValue);
                }
            });

            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Salvando...';
            submitBtn.disabled = true;

            fetch('process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    fecharModal('modalEdicao');
                    window.location.reload();
                } else {
                    alert(data.message || 'Erro ao salvar alterações');
                    // Restaurar botão
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            })
            .catch(error => {
                alert('Erro ao processar requisição');
                // Restaurar botão
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
        });
    }
});