<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

// Verificar se o usuário tem permissão para gerenciar usuários (apenas Coordenador - nível 1)
if ($_SESSION['usuario_nivel'] > 1) {
    header('Location: selecao_modulos.php');
    exit;
}

$pdo = conectarDB();

// Processar ações
if ($_POST['acao'] ?? '' === 'atualizar_nivel') {
    // Verificar permissão para alterar níveis de usuário (apenas Coordenador)
    if ($_SESSION['usuario_nivel'] > 1) {
        $mensagem = "Você não tem permissão para alterar níveis de usuário.";
    } else {
    $usuario_id = $_POST['usuario_id'];
    $novo_nivel = $_POST['nivel_acesso'];
    $novo_tipo = $_POST['tipo_usuario'];
    $departamento = $_POST['departamento'];
    
    try {
        // Verificar se as colunas existem, senão criar
        $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'nivel_acesso'");
        if ($stmt->rowCount() == 0) {
            // Adicionar colunas se não existirem
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN nivel_acesso INT DEFAULT 3");
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN departamento VARCHAR(50) DEFAULT 'CGLIC'");
            $pdo->exec("ALTER TABLE usuarios MODIFY tipo_usuario ENUM('admin','usuario','coordenador','diplan','dipli') DEFAULT 'usuario'");
        }
        
        $sql = "UPDATE usuarios SET nivel_acesso = ?, tipo_usuario = ?, departamento = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$novo_nivel, $novo_tipo, $departamento, $usuario_id]);
        
        $mensagem = "Usuário atualizado com sucesso!";
    } catch (Exception $e) {
        $mensagem = "Erro: " . $e->getMessage();
    }
    }
}

// Configuração de paginação e filtros
$limite = 10; // Usuários por página
$pagina = intval($_GET['pagina'] ?? 1);
$offset = ($pagina - 1) * $limite;

$filtro_nome = $_GET['nome'] ?? '';
$filtro_nivel = $_GET['nivel'] ?? '';
$filtro_departamento = $_GET['departamento'] ?? '';

// Construir WHERE clause
$where = [];
$params = [];

if (!empty($filtro_nome)) {
    $where[] = "(nome LIKE ? OR email LIKE ?)";
    $params[] = "%$filtro_nome%";
    $params[] = "%$filtro_nome%";
}

if (!empty($filtro_nivel)) {
    $where[] = "nivel_acesso = ?";
    $params[] = $filtro_nivel;
}

if (!empty($filtro_departamento)) {
    $where[] = "departamento = ?";
    $params[] = $filtro_departamento;
}

$whereClause = '';
if ($where) {
    $whereClause = 'WHERE ' . implode(' AND ', $where);
}

// Contar total de usuários
$sqlCount = "SELECT COUNT(*) as total FROM usuarios $whereClause";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalUsuarios = $stmtCount->fetch()['total'];

// Calcular total de páginas
$totalPaginas = ceil($totalUsuarios / $limite);

// Buscar usuários com paginação
$sql = "SELECT * FROM usuarios $whereClause ORDER BY nome LIMIT ? OFFSET ?";
$params[] = $limite;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Sistema CGLIC</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #667eea 100%);
            background-attachment: fixed;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            color: #1e3c72;
            font-size: 32px;
            margin: 0 0 10px 0;
            font-weight: 800;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 60, 114, 0.3);
        }

        .usuarios-grid {
            display: grid;
            gap: 20px;
        }

        .usuario-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 2px solid #f1f5f9;
        }

        .usuario-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .usuario-dados h3 {
            margin: 0 0 5px 0;
            color: #1e3c72;
        }

        .usuario-dados p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .nivel-atual {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .nivel-1 { background: #dcfce7; color: #166534; }
        .nivel-2 { background: #dbeafe; color: #1d4ed8; }
        .nivel-3 { background: #fef3c7; color: #a16207; }
        .nivel-4 { background: #f3f4f6; color: #374151; }

        .form-niveis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .form-group select {
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .btn-atualizar {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-atualizar:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .explicacao {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #1e3c72;
        }

        .niveis-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .nivel-info {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid;
        }

        .nivel-info.coordenador { border-color: #22c55e; }
        .nivel-info.diplan { border-color: #3b82f6; }
        .nivel-info.dipli { border-color: #f59e0b; }
        .nivel-info.visitante { border-color: #6b7280; }

        .nivel-info h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
        }

        .nivel-info p {
            margin: 0;
            font-size: 13px;
            color: #64748b;
        }

        .filtros-container {
            background: #f8fafc;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px solid #e2e8f0;
        }

        .filtros-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .filtro-grupo {
            display: flex;
            flex-direction: column;
        }

        .filtro-grupo label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .filtro-grupo input,
        .filtro-grupo select {
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            transition: all 0.3s ease;
        }

        .filtro-grupo input:focus,
        .filtro-grupo select:focus {
            outline: none;
            border-color: #2a5298;
            box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.1);
        }

        .btn-filtrar {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-filtrar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 60, 114, 0.3);
        }

        .btn-limpar {
            background: #64748b;
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            margin-left: 10px;
            transition: all 0.3s ease;
        }

        .btn-limpar:hover {
            background: #475569;
            transform: translateY(-1px);
        }

        .resultados-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px 20px;
            background: white;
            border-radius: 12px;
            border: 2px solid #f1f5f9;
        }

        .paginacao {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            padding: 20px;
        }

        .paginacao a,
        .paginacao span {
            padding: 10px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .paginacao a {
            background: white;
            color: #64748b;
            border: 2px solid #e2e8f0;
        }

        .paginacao a:hover {
            background: #1e3c72;
            color: white;
            border-color: #1e3c72;
            transform: translateY(-1px);
        }

        .paginacao .atual {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border: 2px solid #1e3c72;
        }

        .paginacao .disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .usuarios-vazio {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .usuarios-vazio i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .filtros-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .resultados-info {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .paginacao {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="selecao_modulos.php" class="back-btn">
            <i data-lucide="arrow-left"></i> Voltar ao Menu
        </a>

        <div class="header">
            <h1><i data-lucide="users"></i> Gerenciar Níveis de Usuário</h1>
            <p>Configure os níveis de acesso e permissões dos usuários do sistema</p>
        </div>

        <?php if (isset($mensagem)): ?>
            <div class="alert"><?php echo $mensagem; ?></div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filtros-container">
            <h3 style="margin: 0 0 20px 0; color: #1e3c72;">
                <i data-lucide="filter"></i> Filtrar Usuários
            </h3>
            
            <form method="GET" class="filtros-grid">
                <div class="filtro-grupo">
                    <label>Buscar por Nome ou Email</label>
                    <input type="text" name="nome" placeholder="Digite o nome ou email..." 
                           value="<?php echo htmlspecialchars($filtro_nome); ?>">
                </div>
                
                <div class="filtro-grupo">
                    <label>Nível de Acesso</label>
                    <select name="nivel">
                        <option value="">Todos os níveis</option>
                        <option value="1" <?php echo $filtro_nivel == '1' ? 'selected' : ''; ?>>Coordenador</option>
                        <option value="2" <?php echo $filtro_nivel == '2' ? 'selected' : ''; ?>>DIPLAN</option>
                        <option value="3" <?php echo $filtro_nivel == '3' ? 'selected' : ''; ?>>DIPLI</option>
                        <option value="4" <?php echo $filtro_nivel == '4' ? 'selected' : ''; ?>>Visitante</option>
                    </select>
                </div>
                
                <div class="filtro-grupo">
                    <label>Departamento</label>
                    <select name="departamento">
                        <option value="">Todos os departamentos</option>
                        <option value="CGLIC" <?php echo $filtro_departamento == 'CGLIC' ? 'selected' : ''; ?>>CGLIC</option>
                        <option value="DIPLAN" <?php echo $filtro_departamento == 'DIPLAN' ? 'selected' : ''; ?>>DIPLAN</option>
                        <option value="DIPLI" <?php echo $filtro_departamento == 'DIPLI' ? 'selected' : ''; ?>>DIPLI</option>
                    </select>
                </div>
                
                <div style="display: flex;">
                    <button type="submit" class="btn-filtrar">
                        <i data-lucide="search"></i> Filtrar
                    </button>
                    <a href="gerenciar_usuarios.php" class="btn-limpar">
                        <i data-lucide="x"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Informações dos Resultados -->
        <div class="resultados-info">
            <div>
                <strong><?php echo number_format($totalUsuarios); ?> usuários encontrados</strong>
                <?php if ($filtro_nome || $filtro_nivel || $filtro_departamento): ?>
                    - Filtros aplicados
                <?php endif; ?>
            </div>
            <div style="font-size: 14px; color: #64748b;">
                Página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?> 
                (<?php echo $limite; ?> por página)
            </div>
        </div>

        <div class="explicacao">
            <h3 style="margin: 0 0 15px 0; color: #1e3c72;">
                <i data-lucide="info"></i> Como Funciona o Sistema de Níveis
            </h3>
            <p style="margin: 0 0 15px 0; color: #64748b;">
                O sistema possui 4 níveis hierárquicos com permissões específicas:
            </p>
            
            <div class="niveis-info">
                <div class="nivel-info coordenador">
                    <h4>🎖️ Nível 1 - Coordenador</h4>
                    <p>Acesso total ao sistema. Pode gerenciar usuários, executar backups, acessar todos os módulos e relatórios.</p>
                </div>
                <div class="nivel-info diplan">
                    <h4>📊 Nível 2 - DIPLAN</h4>
                    <p>Foco em planejamento. Pode importar PCA, gerar relatórios de planejamento, visualizar licitações (sem editar).</p>
                </div>
                <div class="nivel-info dipli">
                    <h4>⚖️ Nível 3 - DIPLI</h4>
                    <p>Foco em licitações. Pode criar e gerenciar licitações, visualizar PCA (sem importar), relatórios básicos.</p>
                </div>
                <div class="nivel-info visitante">
                    <h4>👁️ Nível 4 - Visitante</h4>
                    <p>Apenas visualização e exportação. Não pode inserir, editar ou excluir dados. Ideal para consultas e relatórios.</p>
                </div>
            </div>
        </div>

        <?php if (empty($usuarios)): ?>
            <div class="usuarios-vazio">
                <i data-lucide="users-x"></i>
                <h3>Nenhum usuário encontrado</h3>
                <p>Tente ajustar os filtros ou verifique se há usuários cadastrados.</p>
            </div>
        <?php else: ?>
        <div class="usuarios-grid">
            <?php foreach ($usuarios as $usuario): ?>
            <div class="usuario-card">
                <div class="usuario-info">
                    <div class="usuario-dados">
                        <h3><?php echo htmlspecialchars($usuario['nome']); ?></h3>
                        <p><?php echo htmlspecialchars($usuario['email']); ?></p>
                    </div>
                    <span class="nivel-atual nivel-<?php echo $usuario['nivel_acesso'] ?? 3; ?>">
                        <?php 
                        $nivel = $usuario['nivel_acesso'] ?? 3;
                        switch($nivel) {
                            case 1: echo 'Coordenador'; break;
                            case 2: echo 'DIPLAN'; break;
                            case 3: echo 'DIPLI'; break;
                            case 4: echo 'Visitante'; break;
                            default: echo 'Usuário';
                        }
                        ?>
                    </span>
                </div>

                <form method="POST" class="form-niveis">
                    <input type="hidden" name="acao" value="atualizar_nivel">
                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                    
                    <div class="form-group">
                        <label>Nível de Acesso</label>
                        <select name="nivel_acesso" required>
                            <option value="1" <?php echo ($usuario['nivel_acesso'] ?? 3) == 1 ? 'selected' : ''; ?>>
                                1 - Coordenador (Acesso Total)
                            </option>
                            <option value="2" <?php echo ($usuario['nivel_acesso'] ?? 3) == 2 ? 'selected' : ''; ?>>
                                2 - DIPLAN (Planejamento)
                            </option>
                            <option value="3" <?php echo ($usuario['nivel_acesso'] ?? 3) == 3 ? 'selected' : ''; ?>>
                                3 - DIPLI (Licitações)
                            </option>
                            <option value="4" <?php echo ($usuario['nivel_acesso'] ?? 3) == 4 ? 'selected' : ''; ?>>
                                4 - Visitante (Somente Leitura)
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Usuário</label>
                        <select name="tipo_usuario" required>
                            <option value="coordenador" <?php echo ($usuario['tipo_usuario'] ?? '') == 'coordenador' ? 'selected' : ''; ?>>
                                Coordenador
                            </option>
                            <option value="diplan" <?php echo ($usuario['tipo_usuario'] ?? '') == 'diplan' ? 'selected' : ''; ?>>
                                DIPLAN
                            </option>
                            <option value="dipli" <?php echo ($usuario['tipo_usuario'] ?? '') == 'dipli' ? 'selected' : ''; ?>>
                                DIPLI
                            </option>
                            <option value="visitante" <?php echo ($usuario['tipo_usuario'] ?? '') == 'visitante' ? 'selected' : ''; ?>>
                                Visitante
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Departamento</label>
                        <select name="departamento" required>
                            <option value="CGLIC" <?php echo ($usuario['departamento'] ?? '') == 'CGLIC' ? 'selected' : ''; ?>>
                                CGLIC
                            </option>
                            <option value="DIPLAN" <?php echo ($usuario['departamento'] ?? '') == 'DIPLAN' ? 'selected' : ''; ?>>
                                DIPLAN
                            </option>
                            <option value="DIPLI" <?php echo ($usuario['departamento'] ?? '') == 'DIPLI' ? 'selected' : ''; ?>>
                                DIPLI
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn-atualizar">
                        <i data-lucide="save"></i> Atualizar
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginação -->
        <?php if ($totalPaginas > 1): ?>
        <div class="paginacao">
            <!-- Primeira página -->
            <?php if ($pagina > 1): ?>
                <a href="?pagina=1&nome=<?php echo urlencode($filtro_nome); ?>&nivel=<?php echo $filtro_nivel; ?>&departamento=<?php echo $filtro_departamento; ?>">
                    <i data-lucide="chevrons-left"></i>
                </a>
            <?php endif; ?>

            <!-- Página anterior -->
            <?php if ($pagina > 1): ?>
                <a href="?pagina=<?php echo $pagina - 1; ?>&nome=<?php echo urlencode($filtro_nome); ?>&nivel=<?php echo $filtro_nivel; ?>&departamento=<?php echo $filtro_departamento; ?>">
                    <i data-lucide="chevron-left"></i>
                </a>
            <?php endif; ?>

            <!-- Páginas numeradas -->
            <?php
            $inicio = max(1, $pagina - 2);
            $fim = min($totalPaginas, $pagina + 2);
            
            for ($i = $inicio; $i <= $fim; $i++):
            ?>
                <?php if ($i == $pagina): ?>
                    <span class="atual"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?pagina=<?php echo $i; ?>&nome=<?php echo urlencode($filtro_nome); ?>&nivel=<?php echo $filtro_nivel; ?>&departamento=<?php echo $filtro_departamento; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <!-- Próxima página -->
            <?php if ($pagina < $totalPaginas): ?>
                <a href="?pagina=<?php echo $pagina + 1; ?>&nome=<?php echo urlencode($filtro_nome); ?>&nivel=<?php echo $filtro_nivel; ?>&departamento=<?php echo $filtro_departamento; ?>">
                    <i data-lucide="chevron-right"></i>
                </a>
            <?php endif; ?>

            <!-- Última página -->
            <?php if ($pagina < $totalPaginas): ?>
                <a href="?pagina=<?php echo $totalPaginas; ?>&nome=<?php echo urlencode($filtro_nome); ?>&nivel=<?php echo $filtro_nivel; ?>&departamento=<?php echo $filtro_departamento; ?>">
                    <i data-lucide="chevrons-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Busca em tempo real
        let searchTimeout;
        const searchInput = document.querySelector('input[name="nome"]');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    // Auto-submit do formulário após 500ms de inatividade
                    this.form.submit();
                }, 500);
            });
        }

        // Limpar filtros com confirmação
        document.addEventListener('DOMContentLoaded', function() {
            const btnLimpar = document.querySelector('.btn-limpar');
            
            if (btnLimpar) {
                btnLimpar.addEventListener('click', function(e) {
                    const hasFiltros = <?php echo json_encode($filtro_nome || $filtro_nivel || $filtro_departamento); ?>;
                    
                    if (hasFiltros) {
                        if (!confirm('Tem certeza que deseja limpar todos os filtros?')) {
                            e.preventDefault();
                        }
                    }
                });
            }
        });

        // Feedback visual ao atualizar usuário
        document.querySelectorAll('form[method="POST"]').forEach(form => {
            form.addEventListener('submit', function() {
                const button = this.querySelector('.btn-atualizar');
                button.innerHTML = '<i data-lucide="loader-2"></i> Salvando...';
                button.disabled = true;
                
                // Re-inicializar ícones Lucide
                lucide.createIcons();
            });
        });
    </script>
    <script src="assets/notifications.js"></script>
</body>
</html>