-- Atualizações do schema para novas funcionalidades
-- Execute este script no phpMyAdmin

-- Adicionar coluna chave_pix na tabela usuarios
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS chave_pix VARCHAR(255) NULL AFTER saldo;

-- Atualizar contatos para correlacionar por telefone
-- Adicionar índice único em telefone para evitar duplicatas
ALTER TABLE contatos 
ADD INDEX IF NOT EXISTS idx_telefone (telefone);

-- Garantir que contatos criados por jogadores sejam visíveis para mestre
-- (já está implementado na lógica, mas podemos melhorar)
