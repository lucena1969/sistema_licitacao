// Ver histórico
function verHistorico(numero) {
    var modal = document.getElementById('modalDetalhes');
    var conteudo = document.getElementById('conteudoDetalhes');
    
    conteudo.innerHTML = '<div class="text-center" style="padding: 40px;"><p>Carregando histórico...</p></div>';
    modal.style.display = 'block';
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'historico_contratacao.php?numero=' + encodeURIComponent(numero), true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                conteudo.innerHTML = xhr.responseText;
            } else {
                conteudo.innerHTML = '<div style="padding: 40px; text-align: center;">Erro ao carregar histórico</div>';
            }
        }
    };
    xhr.send();
}

// Ver detalhes
function verDetalhes(ids) {
    var modal = document.getElementById('modalDetalhes');
    var conteudo = document.getElementById('conteudoDetalhes');
    
    // Mostrar loading
    conteudo.innerHTML = '<div class="text-center"><p>Carregando...</p></div>';
    modal.style.display = 'block';
    
    // Buscar detalhes via AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'detalhes.php?ids=' + ids, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                conteudo.innerHTML = xhr.responseText;
            } else {
                conteudo.innerHTML = '<div class="erro">Erro ao carregar detalhes</div>';
            }
        }
    };
    xhr.send();
    xhr.onerror = function() {
        conteudo.innerHTML = '<div style="padding: 40px; text-align: center;">Erro de conexão</div>';
    };
}

// Fechar modal de detalhes
function fecharModalDetalhes() {
    document.getElementById('modalDetalhes').style.display = 'none';
    document.getElementById('conteudoDetalhes').innerHTML = '';
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    var modalLicitacao = document.getElementById('modalLicitacao');
    var modalDetalhes = document.getElementById('modalDetalhes');
    
    if (event.target == modalLicitacao) {
        fecharModal();
    } else if (event.target == modalDetalhes) {
        fecharModalDetalhes();
    }
}

// Formatar moeda para exibição
function formatarMoedaBR(valor) {
    return parseFloat(valor).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Máscaras para campos
document.addEventListener('DOMContentLoaded', function() {
    // Máscara para NUP
    var nupInput = document.querySelector('[name="nup"]');
    if (nupInput) {
        nupInput.addEventListener('input', function(e) {
            var value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                value = value.substring(0, 17);
                var formatted = '';
                
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
                
                e.target.value = formatted;
            }
        });
    }
    
    // Máscara para Item PGC
    var pgcInput = document.querySelector('[name="item_pgc"]');
    if (pgcInput) {
        pgcInput.addEventListener('input', function(e) {
            var value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                value = value.substring(0, 8);
                var formatted = '';
                
                if (value.length > 0) {
                    formatted = value.substring(0, 4);
                }
                if (value.length > 4) {
                    formatted += '/' + value.substring(4, 8);
                }
                
                e.target.value = formatted;
            }
        });
    }
    
    // Máscara para valores monetários
    var moneyInputs = document.querySelectorAll('[name="estimado_pgc"], [name="valor_estimado"]');
    moneyInputs.forEach(function(input) {
        input.addEventListener('input', function(e) {
            var value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                value = (parseInt(value) / 100).toFixed(2);
                value = value.replace('.', ',');
                e.target.value = value;
            }
        });
        
        input.addEventListener('blur', function(e) {
            if (e.target.value) {
                var value = parseFloat(e.target.value.replace(',', '.'));
                e.target.value = formatarMoedaBR(value);
            }
        });
    });
    
    // Auto-fechar mensagens após 5 segundos
    var mensagens = document.querySelectorAll('.mensagem');
    mensagens.forEach(function(msg) {
        setTimeout(function() {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity = '0';
            setTimeout(function() {
                msg.remove();
            }, 500);
        }, 5000);
    });
});

// Confirmar antes de importar arquivo
document.addEventListener('DOMContentLoaded', function() {
    var formImport = document.querySelector('form[action*="importar_pca"]');
    if (formImport) {
        formImport.addEventListener('submit', function(e) {
            var fileInput = this.querySelector('input[type="file"]');
            if (fileInput && fileInput.files.length > 0) {
                var fileName = fileInput.files[0].name;
                if (!confirm('Deseja importar o arquivo "' + fileName + '"?')) {
                    e.preventDefault();
                }
            }
        });
    }
});

