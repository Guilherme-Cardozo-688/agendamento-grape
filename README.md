# Sistema de Agendamento GrapeTech

Sistema de agendamento de espaços para o GrapeTech, permitindo agendamentos públicos com aprovação administrativa.

## Funcionalidades

- ✅ Criar e atualizar agendamentos
- ✅ Visualização em calendário
- ✅ Painel administrativo para aprovação/rejeição
- ✅ Interface moderna e responsiva
- ✅ Validação de limites por espaço:
  - Laboratório IFMaker: máximo 3 agendamentos simultâneos
  - CoWorking: máximo 8 agendamentos simultâneos
  - Sala de Reunião: máximo 1 agendamento simultâneo
- ✅ Eventos que ocupam todo o espaço (bloqueiam outros agendamentos)
- ✅ Envio de email quando agendamento é criado/aprovado/rejeitado
- ✅ Integração com Google Calendar

## Requisitos

- PHP 7.4 ou superior
- SQLite (incluído no PHP)
- Servidor web (Apache/Nginx) ou servidor PHP embutido

## Instalação Rápida

Para instruções detalhadas, consulte [INSTALL.md](INSTALL.md).

### Passos Básicos:

1. **Clone ou baixe este repositório**

2. **Crie o diretório de dados e configure permissões:**
   ```bash
   mkdir data
   chmod 755 data
   ```

3. **Configure as credenciais:**
   ```bash
   cp config/credentials.example.php config/credentials.php
   ```
   Edite `config/credentials.php` com suas credenciais.

4. **Configure Google Calendar API** (veja INSTALL.md para detalhes)

5. **Inicie o servidor de desenvolvimento:**
   ```bash
   php -S localhost:8000
   ```
   Acesse: `http://localhost:8000`

6. **Acesse o painel admin:**
   - URL: `http://localhost:8000/admin/login.php`
   - Email: `admin@grapetech.com`
   - Senha: `admin123` (altere após primeiro acesso!)

## Uso

### Acesso Público
- Acesse `index.php` para visualizar o calendário e criar agendamentos

### Área Administrativa
- Acesse `admin/login.php`
- Credenciais padrão:
  - Email: `admin@grapetech.com`
  - Senha: `admin123`
- **IMPORTANTE**: Altere a senha padrão após o primeiro acesso!

## Estrutura do Projeto

```
agendamento-grapetech/
├── admin/              # Área administrativa
│   ├── login.php
│   ├── dashboard.php
│   └── logout.php
├── api/                # Endpoints da API
│   ├── agendamentos.php
│   └── admin.php
├── assets/             # Arquivos estáticos
│   ├── css/
│   │   └── style.css
│   └── js/
│       ├── main.js
│       └── admin.js
├── config/             # Configurações
│   ├── database.php
│   ├── credentials.php (não commitado)
│   ├── credentials.example.php
│   └── google_oauth_callback.php
├── includes/           # Funções auxiliares
│   ├── functions.php
│   ├── email.php
│   └── google_calendar.php
├── data/               # Banco de dados SQLite
│   └── agendamento.db (gerado automaticamente)
├── index.php           # Página principal
└── README.md
```

## Configuração de Email

O sistema usa a função `mail()` nativa do PHP. Para usar SMTP (recomendado), você pode:

1. Instalar PHPMailer via Composer:
   ```bash
   composer require phpmailer/phpmailer
   ```

2. Atualizar `includes/email.php` para usar PHPMailer

## Segurança

- ✅ Senhas hashadas com `password_hash()`
- ✅ Prepared statements para prevenir SQL injection
- ✅ Validação de dados de entrada
- ✅ Proteção contra CSRF (recomendado adicionar tokens)
- ✅ `.gitignore` configurado para não commitar credenciais

## Licença

Este projeto é fornecido "como está" para uso interno.

## Suporte

Para problemas ou dúvidas, entre em contato com a equipe de desenvolvimento.

