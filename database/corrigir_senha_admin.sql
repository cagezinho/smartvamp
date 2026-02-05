-- Script para corrigir a senha do Admin
-- Execute este script no phpMyAdmin

-- Verificar se existe usuário Admin
SELECT id, nome, senha FROM usuarios WHERE nome = 'Admin';

-- Atualizar senha do Admin para 0896 (senha numérica direta)
UPDATE usuarios 
SET senha = '0896' 
WHERE nome = 'Admin';

-- Se não existir, criar
INSERT INTO usuarios (nome, senha, saldo, tema) 
VALUES ('Admin', '0896', 0.00, 'escuro')
ON DUPLICATE KEY UPDATE senha = '0896';

-- Verificar resultado
SELECT id, nome, senha FROM usuarios WHERE nome = 'Admin';
