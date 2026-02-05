# PROMPT COMPLETO - Sistema SmartVamp para RPG Vampiro: A Máscara

## VISÃO GERAL DO PROJETO

Criar um aplicativo web mobile-first para complementar sessões de RPG (Vampiro: A Máscara), hospedado na HostGator com PHP/MySQL. O aplicativo deve simular a experiência de usar um smartphone dentro do jogo, com design minimalista inspirado em iPhone/GTA 5, usando tons de preto, branco e vermelho.

---

## ESPECIFICAÇÕES TÉCNICAS

### Stack Tecnológica
- **Backend**: PHP 7.4+ (compatível com HostGator)
- **Banco de Dados**: MySQL (via phpMyAdmin)
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Hospedagem**: HostGator (cPanel, File Manager, phpMyAdmin)
- **Versionamento**: Git/GitHub

### Estrutura de Arquivos
```
/
├── index.html (Tela de login/desbloqueio)
├── app.html (Aplicativo principal)
├── config.php (Configurações do banco - NÃO versionado)
├── config.example.php (Exemplo de configuração)
├── .htaccess (Configurações Apache)
├── assets/
│   ├── style.css (Estilos completos)
│   └── app.js (Lógica JavaScript)
├── api/
│   ├── auth.php (Autenticação)
│   ├── banco.php (Sistema bancário)
│   ├── contatos.php (Gerenciamento de contatos)
│   ├── chat.php (Sistema de mensagens)
│   ├── inventario.php (Inventário)
│   ├── noticias.php (Feed de notícias)
│   └── relogio.php (Relógio do jogo)
├── admin/
│   ├── index.php (Painel administrativo)
│   ├── admin.php (API do admin)
│   ├── admin.js (Lógica do admin)
│   └── admin.css (Estilos do admin)
├── database/
│   ├── schema.sql (Estrutura inicial)
│   ├── criar_admin.sql (Criar usuário admin)
│   ├── update_schema.sql (Atualizações)
│   └── refazer_autenticacao.sql (Sistema de autenticação)
└── uploads/ (Diretório para uploads)
```

---

## DESIGN E INTERFACE

### Princípios de Design
- **Minimalista**: Design limpo, sem elementos desnecessários
- **Paleta de Cores**: Preto (#000), Branco (#fff), Vermelho (#dc143c)
- **Tipografia**: Font-weight 300-400, letter-spacing ajustado
- **Sem Emojis**: Não utilizar emojis no design ou nomes
- **Estilo iPhone**: Interface que simula um smartphone real

### Tela de Login (index.html)
- **Layout**: Simula tela de bloqueio de iPhone
- **Status Bar**: No topo com bateria, hora e sinal
- **Campo de Senha**: Input numérico grande, centralizado
- **Botão de Envio**: Botão circular com ícone de seta
- **Mensagem**: "Digite a senha para desbloquear"
- **Comportamento**: 
  - Aceita apenas senha numérica OU usuário + senha
  - Enter ou clique no botão para enviar
  - Feedback visual imediato de erros

### Tela Principal (app.html)
- **Status Bar**: Fixa no topo (bateria, hora, sinal)
- **Home Screen**: Grid de ícones estilo iPhone
  - Ícones: Banco, Contatos, Mensagens, Inventário, Notícias
  - Ícone Admin (apenas para admins)
  - Cada ícone tem gradiente de cor e SVG
- **Navegação**: 
  - Ao acessar um app, mostrar botão "Início" na parte inferior centralizado
  - Botão de voltar no header de cada tela
- **Sem Menu Hambúrguer**: Removido completamente

### Ícones dos Apps
- **Banco**: Gradiente verde (#27ae60 → #1e8449)
- **Contatos**: Gradiente azul (#3498db → #2980b9)
- **Mensagens**: Gradiente roxo (#9b59b6 → #8e44ad)
- **Inventário**: Gradiente laranja (#e67e22 → #d35400)
- **Notícias**: Gradiente vermelho (#e74c3c → #c0392b)
- **Admin**: Gradiente cinza (#34495e → #2c3e50)

---

## SISTEMA DE AUTENTICAÇÃO

### Requisitos
- **Login por Senha Numérica**: Sistema principal (compatibilidade)
- **Login por Usuário + Senha**: Sistema alternativo
- **Cada jogador tem senha única**
- **Sem recuperação de senha**: Admin redefine manualmente

### Estrutura no Banco
```sql
usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NULL UNIQUE,  -- Opcional
    senha VARCHAR(255) NOT NULL,      -- Senha numérica ou hash
    saldo DECIMAL(10,2) DEFAULT 0.00,
    chave_pix VARCHAR(255) NULL,      -- Chave PIX do usuário
    tema VARCHAR(10) DEFAULT 'escuro',
    criado_em TIMESTAMP,
    ultimo_acesso TIMESTAMP NULL,
    ativo TINYINT(1) DEFAULT 1
)
```

### Funcionalidades
- Login via API POST para `api/auth.php`
- Sessão PHP para manter autenticação
- Verificação automática ao carregar `app.html`
- Logout que destrói sessão
- Identificação de admin pelo nome "Admin" ou usuário "admin"

---

## SISTEMA BANCÁRIO

### Funcionalidades
- **Saldo**: Exibição do saldo atual do jogador
- **Chave PIX**: 
  - Cada usuário tem uma chave PIX visível
  - Chave pode ser editada pelo próprio jogador
  - Exibida na tela do banco
- **Enviar PIX**: 
  - Formulário simplificado: apenas chave PIX e valor
  - Sem campo de nome ou descrição
  - Otimizado para mobile
- **Histórico**: Lista de transações (entrada/saída)
- **Sem Receber PIX**: Removido - outros jogadores enviam diretamente

### Estrutura no Banco
```sql
transacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    tipo ENUM('entrada', 'saida'),
    valor DECIMAL(10,2),
    descricao VARCHAR(255),
    pix_chave VARCHAR(255),
    pix_nome VARCHAR(100),
    criado_em TIMESTAMP
)
```

### Fluxo
1. Jogador acessa Banco
2. Vê sua chave PIX (ou "Não configurada")
3. Pode editar chave PIX
4. Clica em "Enviar PIX"
5. Preenche chave destino e valor
6. Confirma transação
7. Saldo é atualizado automaticamente

---

## SISTEMA DE CONTATOS (NPCs)

### Funcionalidades
- **Criar Contatos**: Jogadores e mestre podem criar
- **Informações do Contato**:
  - Nome (obrigatório)
  - Telefone (usado para correlação)
  - Grupo (Clã, Aliados, Inimigos, etc.)
  - Endereço
  - Profissão
  - Notas
  - Avatar/Foto (opcional)
- **Correlação por Telefone**: 
  - Se múltiplos jogadores criam contato com mesmo telefone, não duplica para mestre
  - Mestre vê apenas um NPC por telefone
  - Cada jogador pode ter o mesmo NPC com nomes diferentes
- **Grupos**: Sistema de categorização de contatos
- **Modais Otimizados**: Design mobile, clean e moderno

### Estrutura no Banco
```sql
contatos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NULL,              -- NULL = contato global (mestre)
    grupo_id INT,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20),
    avatar VARCHAR(255),
    endereco TEXT,
    profissao VARCHAR(100),
    notas TEXT,
    criado_por ENUM('jogador', 'mestre'),
    criado_em TIMESTAMP
)

grupos_contatos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50),
    cor VARCHAR(7)
)
```

### Lógica de Correlação
- Ao criar contato, verifica se já existe contato com mesmo telefone
- Se existe, cria apenas para o jogador (não duplica para mestre)
- Mestre vê todos os contatos (próprios + criados por jogadores)

---

## SISTEMA DE MENSAGENS (CHAT)

### Funcionalidades
- **Estilo WhatsApp**: Interface similar ao WhatsApp
- **Conversas**: Lista de conversas com NPCs
- **Mensagens em Tempo Real**: Atualização automática
- **Notificações**: Contador de mensagens não lidas
- **Mídia**: Suporte a fotos, áudios, documentos
- **Histórico**: Todas as conversas são salvas

### Estrutura no Banco
```sql
conversas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    contato_nome VARCHAR(100),
    contato_avatar VARCHAR(255),
    ultima_mensagem_em TIMESTAMP,
    nao_lidas INT DEFAULT 0
)

mensagens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversa_id INT,
    remetente ENUM('jogador', 'npc'),
    conteudo TEXT,
    tipo_midia ENUM('texto', 'foto', 'audio', 'documento'),
    arquivo_midia VARCHAR(255),
    criado_em TIMESTAMP
)
```

### Funcionalidades do Mestre
- Mestre pode enviar mensagens como NPC
- Mestre escolhe contato criado por jogador para conversar
- Mestre pode criar novas conversas

---

## SISTEMA DE INVENTÁRIO

### Funcionalidades
- **Foco em Armas e Munição**: Otimizado para controle de armas
- **Botões +/-**: Controle rápido de quantidade
  - Botão "-" diminui quantidade
  - Botão "+" aumenta quantidade
  - Se quantidade chegar a 0, item é removido
- **Categorias**: Organização por categoria (Armas, Munição, etc.)
- **Informações do Item**:
  - Nome
  - Categoria
  - Quantidade
  - Descrição
  - Imagem (opcional)

### Estrutura no Banco
```sql
inventario (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    item_nome VARCHAR(100),
    categoria VARCHAR(50),
    quantidade INT DEFAULT 1,
    descricao TEXT,
    imagem VARCHAR(255),
    criado_em TIMESTAMP
)
```

### Interface
- Lista agrupada por categoria
- Cada item mostra nome, descrição e quantidade
- Botões +/- ao lado da quantidade
- Modal otimizado para mobile ao adicionar/editar

---

## SISTEMA DE NOTÍCIAS

### Funcionalidades
- **Feed de Notícias**: Lista de notícias do jogo
- **Criação pelo Mestre**: Apenas admin pode criar notícias
- **Informações**: Título, conteúdo, autor, data
- **Visualização**: Cards com notícias

### Estrutura no Banco
```sql
noticias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titulo VARCHAR(200),
    conteudo TEXT,
    autor VARCHAR(100),
    imagem VARCHAR(255),
    criado_em TIMESTAMP
)
```

---

## SISTEMA DE RELÓGIO DO JOGO

### Funcionalidades
- **Modo Normal**: Relógio em tempo real
- **Modo Manual**: Mestre pode ajustar data/hora
- **Controle pelo Mestre**: Apenas admin pode alterar
- **Exibição**: Mostra hora e data atual do jogo

### Estrutura no Banco
```sql
configuracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(50) UNIQUE,
    valor TEXT
)
-- Exemplo: relogio_modo = 'normal' ou 'manual'
-- Exemplo: relogio_data = '2024-01-15'
-- Exemplo: relogio_hora = '14:30:00'
```

---

## PAINEL ADMINISTRATIVO

### Funcionalidades

#### Gerenciamento de Usuários
- **Listar Usuários**: Ver todos os jogadores
- **Criar Usuário**: 
  - Nome
  - Usuário (opcional)
  - Senha
  - Saldo inicial
  - Tema
- **Editar Usuário**: Modificar informações
- **Remover Usuário**: Deletar usuário (exceto próprio admin)
- **Adicionar Saldo**: Adicionar saldo manualmente

#### Gerenciamento de Contatos
- **Criar Contato**: 
  - Pode escolher em quais agendas criar (checkboxes de usuários)
  - Se deixar vazio, cria como contato global (visível para todos)
  - Se selecionar usuários, cria apenas nas agendas selecionadas
- **Editar Contato**: Modificar informações
- **Ver Todos os Contatos**: Contatos próprios + criados por jogadores

#### Sistema de Chat
- **Enviar como NPC**: Mestre pode enviar mensagens como NPC
- **Selecionar Contato**: Escolher contato criado por jogador
- **Criar Nova Conversa**: Criar conversa com contato específico

#### Gerenciamento de Notícias
- **Criar Notícia**: Adicionar notícias ao feed
- **Editar Notícia**: Modificar notícias
- **Remover Notícia**: Deletar notícias

#### Controle do Relógio
- **Alterar Modo**: Normal ou Manual
- **Ajustar Data/Hora**: Quando em modo manual

### Interface Admin
- **Aba Usuários**: Gerenciar jogadores
- **Aba Transações**: Ver todas as transações
- **Aba Contatos**: Gerenciar NPCs
- **Aba Chat**: Enviar mensagens como NPC
- **Aba Notícias**: Gerenciar feed
- **Aba Relógio**: Controlar tempo do jogo

---

## CONFIGURAÇÕES E DEPLOY

### Arquivo config.php
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'nome_do_banco');
define('DB_USER', 'usuario_mysql');
define('DB_PASS', 'senha_mysql');
define('TIMEZONE', 'America/Sao_Paulo');
define('UPLOAD_DIR', 'uploads/');
define('MAX_UPLOAD_SIZE', 5242880); // 5MB
```

### .htaccess
- Proteger `config.php`
- Headers de segurança
- Compressão GZIP
- Cache de arquivos estáticos
- Limite de upload (5MB)
- CORS configurado

### Deploy no HostGator
1. Upload via cPanel File Manager ou Git
2. Criar banco de dados no phpMyAdmin
3. Executar `database/schema.sql`
4. Executar `database/criar_admin.sql`
5. Criar `config.php` com credenciais corretas
6. Configurar permissões do diretório `uploads/`

---

## REQUISITOS ESPECÍFICOS

### Mobile-First
- Design responsivo priorizando mobile
- Touch-friendly (botões grandes, espaçamento adequado)
- Sem scroll horizontal
- Performance otimizada

### Segurança
- Senhas não expostas
- Sessões PHP seguras
- Validação de entrada
- Proteção contra SQL Injection (PDO prepared statements)
- Arquivos sensíveis protegidos

### Performance
- Queries otimizadas
- Índices no banco de dados
- Cache quando possível
- Arquivos minificados (opcional)

### Acessibilidade
- Contraste adequado
- Navegação por teclado
- Feedback visual claro

---

## FLUXOS PRINCIPAIS

### Fluxo de Login
1. Usuário acessa `index.html`
2. Digita senha (ou usuário + senha)
3. Clica no botão ou pressiona Enter
4. Sistema valida no banco
5. Cria sessão PHP
6. Redireciona para `app.html`

### Fluxo de Envio de PIX
1. Jogador acessa Banco
2. Vê sua chave PIX
3. Clica em "Enviar PIX"
4. Preenche chave destino e valor
5. Sistema valida saldo
6. Cria transação
7. Atualiza saldo
8. Mostra confirmação

### Fluxo de Criação de Contato
1. Jogador acessa Contatos
2. Clica em "+"
3. Preenche informações
4. Sistema verifica se telefone já existe
5. Se existe, cria apenas para jogador
6. Se não existe, cria normalmente
7. Mestre vê todos os contatos

### Fluxo de Mensagem NPC
1. Mestre acessa Admin > Chat
2. Seleciona usuário
3. Escolhe contato do usuário
4. Digita mensagem
5. Sistema cria/atualiza conversa
6. Jogador recebe notificação

---

## OBSERVAÇÕES IMPORTANTES

- **Sem Emojis**: Não usar emojis em lugar nenhum do design
- **Design Minimalista**: Preto, branco e vermelho apenas
- **Feedback Visual**: Sempre mostrar erros e sucessos claramente
- **Compatibilidade**: Sistema deve funcionar em navegadores modernos
- **Offline**: Não necessário - sistema é online apenas
- **Backup**: Não implementado - mestre faz backup manual se necessário

---

## CREDENCIAIS PADRÃO

- **Usuário Admin**: `admin` (ou apenas senha `0896`)
- **Senha Admin**: `0896`
- **Banco de Dados**: Configurado via `config.php`

---

Este prompt contém todas as especificações, funcionalidades e requisitos do sistema SmartVamp para RPG Vampiro: A Máscara.
