# SmartVamp - Aplicativo Web para RPG Vampiro: A Máscara

Aplicativo web mobile-first estilo GTA 5 para complementar sessões de RPG, especialmente Vampiro: A Máscara. Permite gerenciar banco, contatos, chat, inventário, notícias e relógio do jogo.

## 🎮 Funcionalidades

### Para Jogadores
- **Login simples**: Apenas nome e senha (como desbloquear celular)
- **Banco**: Sistema de PIX simulado para enviar/receber dinheiro do jogo
- **Contatos**: Lista de NPCs e contatos importantes com grupos (Clã, Aliados, Inimigos)
- **Chat**: Sistema de mensagens estilo WhatsApp para conversar com NPCs
- **Inventário**: Gerenciamento de itens incluindo munição
- **Notícias**: Feed de notícias do jogo
- **Relógio**: Relógio do jogo (normal ou controlado pelo mestre)
- **Tema claro/escuro**: Alternância entre temas

### Para Mestre/Admin
- **Painel administrativo completo**:
  - Gerenciar usuários (criar, editar, adicionar saldo)
  - Visualizar todas as transações
  - Criar contatos globais (NPCs)
  - Enviar mensagens como NPC em tempo real
  - Gerenciar notícias
  - Controlar relógio do jogo

## 📋 Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor web (Apache/Nginx)
- Acesso ao phpMyAdmin (HostGator)

## 🚀 Instalação

### 1. Upload dos Arquivos

Faça upload de todos os arquivos para seu servidor HostGator via FTP ou File Manager.

### 2. Configuração do Banco de Dados

1. Acesse o **phpMyAdmin** no painel da HostGator
2. Importe o arquivo `database/schema.sql`
3. Isso criará o banco de dados `smartvamp` com todas as tabelas necessárias
4. Um usuário admin padrão será criado:
   - **Nome**: Admin
   - **Senha**: admin123

### 3. Configuração da Conexão

Edite o arquivo `config.php` e altere as credenciais do banco de dados:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'smartvamp');
define('DB_USER', 'seu_usuario_mysql');  // Seu usuário MySQL da HostGator
define('DB_PASS', 'sua_senha_mysql');    // Sua senha MySQL
```

**Importante**: Na HostGator, o usuário MySQL geralmente é o mesmo do cPanel, mas verifique nas configurações do banco de dados.

### 4. Permissões de Diretório

Certifique-se de que o diretório `uploads/` tenha permissões de escrita (755 ou 777):

```bash
chmod 755 uploads/
chmod 755 uploads/avatars/
chmod 755 uploads/midia/
chmod 755 uploads/noticias/
chmod 755 uploads/inventario/
```

### 5. Acesso ao Sistema

- **Aplicativo**: `https://seudominio.com/index.html`
- **Painel Admin**: `https://seudominio.com/admin/index.php`
  - Login: Admin / admin123 (altere após primeiro acesso!)

## 📱 Uso

### Primeiro Acesso (Mestre)

1. Acesse o painel admin com Admin/admin123
2. Crie os usuários dos jogadores
3. Configure o relógio do jogo se necessário
4. Adicione contatos globais (NPCs)
5. Publique notícias

### Durante a Sessão

1. **Jogadores**: Acessem o aplicativo e façam login
2. **Mestre**: Use o painel admin para:
   - Adicionar/remover saldo dos jogadores
   - Enviar mensagens como NPCs
   - Publicar notícias em tempo real
   - Controlar o relógio do jogo

## 🎨 Design

O aplicativo foi desenvolvido com foco mobile-first, inspirado no design dos aplicativos do GTA 5:
- Interface moderna e responsiva
- Cores vibrantes com gradientes
- Animações suaves
- Tema claro/escuro
- Layout otimizado para celular

## 🔒 Segurança

- Senhas são criptografadas com `password_hash()`
- Sessões PHP para autenticação
- Validação de dados no backend
- Proteção contra SQL Injection (PDO prepared statements)

## 📝 Estrutura de Arquivos

```
smartvamp/
├── api/              # Endpoints da API
│   ├── auth.php
│   ├── banco.php
│   ├── chat.php
│   ├── contatos.php
│   ├── inventario.php
│   ├── noticias.php
│   ├── relogio.php
│   └── tema.php
├── admin/            # Painel administrativo
│   ├── index.php
│   ├── admin.php
│   ├── admin.css
│   └── admin.js
├── assets/           # CSS e JavaScript
│   ├── style.css
│   └── app.js
├── database/         # Scripts SQL
│   └── schema.sql
├── uploads/          # Arquivos enviados (criado automaticamente)
├── config.php        # Configurações
├── index.html        # Tela de login
├── app.html          # Aplicativo principal
└── README.md
```

## 🛠️ Personalização

### Adicionar Novos Grupos de Contatos

No phpMyAdmin, execute:

```sql
INSERT INTO grupos_contatos (nome, cor) VALUES ('Nome do Grupo', '#cor_hex');
```

### Alterar Cores do Tema

Edite `assets/style.css` e altere as cores nos gradientes e variáveis CSS.

## ⚠️ Troubleshooting

### Erro de Conexão com Banco de Dados
- Verifique as credenciais em `config.php`
- Confirme que o banco `smartvamp` foi criado
- Verifique se o usuário MySQL tem permissões

### Uploads Não Funcionam
- Verifique permissões do diretório `uploads/`
- Confirme que o diretório existe

### Sessão Expira Rapidamente
- Ajuste `session.gc_maxlifetime` no `php.ini` se necessário

## 📞 Suporte

Para dúvidas ou problemas:
1. Verifique os logs de erro do PHP
2. Confirme que todas as dependências estão instaladas
3. Verifique as permissões de arquivos e diretórios

## 📄 Licença

Este projeto é de uso livre para fins educacionais e de entretenimento.

---

**Desenvolvido para complementar sessões de RPG Vampiro: A Máscara**
