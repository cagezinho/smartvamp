-- Banco de dados para SmartVamp - Aplicativo RPG Vampiro: A Máscara
-- Execute este script no phpMyAdmin da HostGator

CREATE DATABASE IF NOT EXISTS smartvamp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartvamp;

-- Tabela de usuários (jogadores)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    saldo DECIMAL(10,2) DEFAULT 0.00,
    tema VARCHAR(10) DEFAULT 'escuro',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso TIMESTAMP NULL,
    ativo TINYINT(1) DEFAULT 1,
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de transações bancárias
CREATE TABLE IF NOT EXISTS transacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM('entrada', 'saida') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    descricao TEXT,
    pix_chave VARCHAR(255),
    pix_nome VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de grupos de contatos
CREATE TABLE IF NOT EXISTS grupos_contatos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    cor VARCHAR(7) DEFAULT '#3498db',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir grupos padrão
INSERT INTO grupos_contatos (nome, cor) VALUES
('Clã', '#8B0000'),
('Aliados', '#006400'),
('Inimigos', '#DC143C'),
('Contatos', '#3498db');

-- Tabela de contatos
CREATE TABLE IF NOT EXISTS contatos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    grupo_id INT,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20),
    avatar VARCHAR(255),
    endereco TEXT,
    profissao VARCHAR(100),
    notas TEXT,
    criado_por ENUM('jogador', 'mestre') DEFAULT 'jogador',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos_contatos(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de conversas (chats)
CREATE TABLE IF NOT EXISTS conversas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    contato_id INT,
    nome_contato VARCHAR(100) NOT NULL,
    avatar_contato VARCHAR(255),
    ultima_mensagem TEXT,
    ultima_mensagem_em TIMESTAMP NULL,
    nao_lidas INT DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (contato_id) REFERENCES contatos(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_ultima_msg (ultima_mensagem_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de mensagens
CREATE TABLE IF NOT EXISTS mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    remetente ENUM('jogador', 'npc') NOT NULL,
    conteudo TEXT,
    tipo_midia ENUM('texto', 'imagem', 'audio', 'documento') DEFAULT 'texto',
    arquivo_midia VARCHAR(255),
    lida TINYINT(1) DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversa_id) REFERENCES conversas(id) ON DELETE CASCADE,
    INDEX idx_conversa (conversa_id),
    INDEX idx_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de inventário
CREATE TABLE IF NOT EXISTS inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    item_nome VARCHAR(100) NOT NULL,
    categoria VARCHAR(50),
    quantidade INT DEFAULT 1,
    descricao TEXT,
    imagem VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de notícias (feed)
CREATE TABLE IF NOT EXISTS noticias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    conteudo TEXT NOT NULL,
    imagem VARCHAR(255),
    autor VARCHAR(100),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativo TINYINT(1) DEFAULT 1,
    INDEX idx_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de configurações do sistema (relógio, etc)
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(50) UNIQUE NOT NULL,
    valor TEXT,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configurações padrão
INSERT INTO configuracoes (chave, valor) VALUES
('relogio_modo', 'normal'),
('relogio_data', NOW()),
('relogio_hora', TIME(NOW()));

-- Criar usuário admin padrão (senha: admin123)
INSERT INTO usuarios (nome, senha, saldo) VALUES
('Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0.00);
