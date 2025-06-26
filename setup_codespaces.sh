#!/bin/bash
echo "🚀 Configurando ambiente Codespaces para SCGLIC..."

# Configurar MySQL (sem senha para desenvolvimento)
sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';"
sudo mysql -e "CREATE DATABASE IF NOT EXISTS sistema_licitacao CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "GRANT ALL PRIVILEGES ON sistema_licitacao.* TO 'root'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# Importar estrutura do banco
if [ -f "database/sistema_licitacao*.sql" ]; then
    echo "📊 Importando estrutura do banco..."
    sudo mysql sistema_licitacao < database/sistema_licitacao*.sql
fi

# Corrigir AUTO_INCREMENT
if [ -f "fix_auto_increment.sql" ]; then
    echo "🔧 Corrigindo AUTO_INCREMENT..."
    sudo mysql sistema_licitacao < fix_auto_increment.sql
fi

# Iniciar servidor PHP
echo "🌐 Iniciando servidor PHP na porta 8080..."
php -S localhost:8080 -t /workspaces/codespaces-blank/SCGLIC &

echo "✅ Setup completo!"
echo "🌍 Acesse via PORTS tab -> 8080"
echo "🔑 Login: admin@cglic.gov.br / admin123"