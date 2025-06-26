# 📁 Pasta de Backups - Sistema CGLIC

## 📋 Estrutura

```
backups/
├── database/          # Backups do banco de dados (.sql, .zip)
├── files/             # Backups de arquivos (.zip)
├── .htaccess         # Proteção de acesso via web
└── README.md         # Este arquivo
```

## 🔐 Segurança

- Esta pasta está protegida contra acesso direto via web
- Apenas scripts PHP autorizados podem acessar os arquivos
- Backups são organizados por data e hora

## 🗂️ Tipos de Backup

### Banco de Dados
- **Formato:** `.sql` (texto) e `.zip` (compactado)
- **Conteúdo:** Estrutura completa do banco `sistema_licitacao`
- **Localização:** `/database/`

### Arquivos
- **Formato:** `.zip`
- **Conteúdo:** Arquivos do sistema (uploads, logs, cache)
- **Localização:** `/files/`

## 📝 Nomenclatura

- **Banco:** `backup_database_YYYYMMDD_HHMMSS.sql`
- **Banco ZIP:** `backup_database_YYYYMMDD_HHMMSS.zip`
- **Arquivos:** `backup_files_YYYYMMDD_HHMMSS.zip`

## ⚠️ Importante

- Não edite ou remova arquivos manualmente
- Use sempre a interface do sistema para gerenciar backups
- Mantenha backups em local seguro (nuvem, storage externo)