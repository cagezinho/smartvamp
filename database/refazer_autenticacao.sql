-- Script para refazer sistema de autenticação
-- Execute este script no phpMyAdmin

-- Adicionar coluna usuario (username) se não existir
ALTER TABLE usuarios 
ADD COLUMN usuario VARCHAR(50) NULL AFTER nome;

-- Criar índice único em usuario
ALTER TABLE usuarios 
ADD UNIQUE INDEX idx_usuario (usuario);

-- Atualizar usuário Admin existente
UPDATE usuarios 
SET usuario = 'admin', senha = '0896' 
WHERE nome = 'Admin';

-- Se não existir Admin, criar
INSERT INTO usuarios (nome, usuario, senha, saldo, tema) 
VALUES ('Admin', 'admin', '0896', 0.00, 'escuro')
ON DUPLICATE KEY UPDATE usuario = 'admin', senha = '0896';

-- Verificar resultado
SELECT id, nome, usuario, senha FROM usuarios;
