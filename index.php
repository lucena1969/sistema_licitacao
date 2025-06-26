<?php
require_once 'config.php';
require_once 'functions.php';

// Se já estiver logado, redirecionar para dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema CGLIC</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #667eea 100%);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 200, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 50px 45px;
            border-radius: 24px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 35px 70px rgba(0, 0, 0, 0.2),
                0 0 0 1px rgba(255, 255, 255, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #667eea 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            font-size: 2.2rem;
            box-shadow: 
                0 10px 25px rgba(30, 60, 114, 0.3),
                0 0 0 4px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .logo-icon:hover {
            transform: scale(1.05);
            box-shadow: 
                0 15px 35px rgba(30, 60, 114, 0.4),
                0 0 0 4px rgba(255, 255, 255, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .logo-title {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .logo-subtitle {
            color: #64748b;
            font-size: 16px;
            font-weight: 500;
            opacity: 0.8;
        }

        .form-tabs {
            display: flex;
            margin-bottom: 35px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 16px;
            padding: 6px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);
        }

        .form-tab {
            flex: 1;
            padding: 14px 20px;
            text-align: center;
            cursor: pointer;
            border-radius: 12px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            color: #64748b;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .form-tab:hover {
            color: #475569;
            background: rgba(255, 255, 255, 0.5);
        }

        .form-tab.active {
            background: white;
            color: #1e3c72;
            box-shadow: 
                0 4px 12px rgba(0, 0, 0, 0.1),
                0 1px 3px rgba(0, 0, 0, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
            transform: translateY(-1px);
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #bdc3c7;
            font-size: 18px;
        }

        .form-group input {
            width: 100%;
            padding: 16px 16px 16px 52px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            color: #1e293b;
            font-weight: 500;
        }

        .form-group input::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2a5298;
            background: white;
            box-shadow: 
                0 0 0 3px rgba(42, 82, 152, 0.1),
                0 4px 12px rgba(0, 0, 0, 0.05);
            transform: translateY(-1px);
        }

        .form-group input:focus + .input-wrapper i {
            color: #2a5298;
        }

        .btn-primary {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #667eea 100%);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 
                0 8px 20px rgba(30, 60, 114, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 15px 35px rgba(30, 60, 114, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:active {
            transform: translateY(-1px);
            box-shadow: 
                0 8px 20px rgba(30, 60, 114, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .loading {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .loading.show {
            display: block;
        }

        .loading.show + .btn-text {
            opacity: 0;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }

        .alert-success {
            background-color: #d1e7dd;
            border-color: #198754;
            color: #0f5132;
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .logo-title {
                font-size: 24px;
            }
            
            .form-group input {
                padding: 12px 12px 12px 45px;
                font-size: 16px;
            }
            
            .btn-primary {
                padding: 12px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-icon">
                <i data-lucide="building"></i>
            </div>
            <h1 class="logo-title">CGLIC</h1>
            <p class="logo-subtitle">Sistema de Licitações</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i data-lucide="alert-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i data-lucide="check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div class="form-tabs">
            <div class="form-tab active" onclick="showForm('login')">
                <i data-lucide="log-in"></i> Login
            </div>
            <div class="form-tab" onclick="showForm('cadastro')">
                <i data-lucide="user-plus"></i> Cadastro
            </div>
        </div>

        <!-- Formulário de Login -->
        <div id="form-login" class="form-section active">
            <form action="process.php" method="POST" onsubmit="showLoading()">
                <input type="hidden" name="acao" value="login">
                <?php echo getCSRFInput(); ?>
                
                <div class="form-group">
                    <label for="email-login">Email</label>
                    <div class="input-wrapper">
                        <i data-lucide="mail"></i>
                        <input type="email" id="email-login" name="email" required 
                               placeholder="seu.email@exemplo.com" autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label for="senha-login">Senha</label>
                    <div class="input-wrapper">
                        <i data-lucide="lock"></i>
                        <input type="password" id="senha-login" name="senha" required 
                               placeholder="Digite sua senha" autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <div class="loading">
                        <i data-lucide="loader-2" class="spin"></i>
                    </div>
                    <span class="btn-text">
                        <i data-lucide="log-in"></i> Entrar
                    </span>
                </button>
            </form>
        </div>
        
        <!-- Formulário de Cadastro -->
        <div id="form-cadastro" class="form-section">
            <form action="process.php" method="POST" onsubmit="showLoading(); return validateCadastro()">
                <input type="hidden" name="acao" value="cadastro">
                <?php echo getCSRFInput(); ?>
                
                <div class="form-group">
                    <label for="nome-cadastro">Nome Completo</label>
                    <div class="input-wrapper">
                        <i data-lucide="user"></i>
                        <input type="text" id="nome-cadastro" name="nome" required 
                               placeholder="Seu nome completo" autocomplete="name">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email-cadastro">Email</label>
                    <div class="input-wrapper">
                        <i data-lucide="mail"></i>
                        <input type="email" id="email-cadastro" name="email" required 
                               placeholder="seu.email@exemplo.com" autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label for="senha-cadastro">Senha</label>
                    <div class="input-wrapper">
                        <i data-lucide="lock"></i>
                        <input type="password" id="senha-cadastro" name="senha" required 
                               placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmar-senha">Confirmar Senha</label>
                    <div class="input-wrapper">
                        <i data-lucide="lock"></i>
                        <input type="password" id="confirmar-senha" name="confirmar_senha" required 
                               placeholder="Digite a senha novamente" autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <div class="loading">
                        <i data-lucide="loader-2" class="spin"></i>
                    </div>
                    <span class="btn-text">
                        <i data-lucide="user-plus"></i> Criar Conta
                    </span>
                </button>
            </form>
        </div>

        <div class="forgot-password">
            <a href="#" onclick="alert('Entre em contato com o administrador do sistema')">
                Esqueceu sua senha?
            </a>
        </div>
    </div>

    <script>
        // Inicializar ícones Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        function showForm(formType) {
            // Ocultar todas as seções
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remover classe ativa de todas as abas
            document.querySelectorAll('.form-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Mostrar seção selecionada
            document.getElementById('form-' + formType).classList.add('active');
            
            // Ativar aba selecionada
            event.target.closest('.form-tab').classList.add('active');
        }

        function showLoading() {
            document.querySelectorAll('.loading').forEach(loader => {
                loader.classList.add('show');
            });
            
            document.querySelectorAll('.btn-primary').forEach(btn => {
                btn.disabled = true;
            });
        }

        function validateCadastro() {
            const senha = document.getElementById('senha-cadastro').value;
            const confirmarSenha = document.getElementById('confirmar-senha').value;
            
            if (senha.length < 6) {
                alert('A senha deve ter pelo menos 6 caracteres');
                return false;
            }
            
            if (senha !== confirmarSenha) {
                alert('As senhas não coincidem');
                return false;
            }
            
            return true;
        }

        // Adicionar animação ao loading
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .spin {
                animation: spin 1s linear infinite;
            }
        `;
        document.head.appendChild(style);
    </script>
    <script src="assets/notifications.js"></script>
    <script>
        // Compatibilidade com sistema de alertas do index.php
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-success, .alert-error');
            alerts.forEach(function(alert) {
                alert.classList.add('auto-hide-message');
                
                const autoHideDelay = 5000;
                const autoHideTimer = setTimeout(function() {
                    hideAlert(alert);
                }, autoHideDelay);
                
                alert.addEventListener('click', function() {
                    clearTimeout(autoHideTimer);
                    hideAlert(alert);
                });
                
                alert.style.cursor = 'pointer';
            });
            
            function hideAlert(alertElement) {
                alertElement.style.transition = 'all 0.3s ease';
                alertElement.style.opacity = '0';
                alertElement.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    if (alertElement.parentNode) {
                        alertElement.parentNode.removeChild(alertElement);
                    }
                }, 300);
            }
        });
    </script>
</body>
</html>