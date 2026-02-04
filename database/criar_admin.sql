-- Script para criar usuário Admin com senha numérica
-- Execute este script no phpMyAdmin

-- Criar ou atualizar usuário Admin com senha 0896
INSERT INTO usuarios (nome, senha, saldo, tema) 
VALUES ('Admin', '0896', 0.00, 'escuro')
ON DUPLICATE KEY UPDATE senha = '0896';

-- Se o usuário Admin já existir, atualizar a senha
UPDATE usuarios 
SET senha = '0896' 
WHERE nome = 'Admin';
