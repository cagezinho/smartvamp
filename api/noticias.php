<?php
/**
 * API de Notícias - SmartVamp
 * Feed de notícias do jogo (apenas leitura para jogadores, CRUD para admin)
 */

require_once __DIR__ . '/../config.php';

$usuario_id = verificarAuth();
$is_admin = $_SESSION['is_admin'] ?? false;
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

// ========== GET: Buscar notícias ==========
if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT * FROM noticias 
        WHERE ativo = 1 
        ORDER BY criado_em DESC 
        LIMIT 50
    ");
    $stmt->execute();
    $noticias = $stmt->fetchAll();
    
    jsonResponse(['noticias' => $noticias]);
}

// ========== POST: Operações com notícias (apenas admin) ==========
if ($method === 'POST') {
    if (!$is_admin) {
        jsonResponse(['erro' => 'Acesso negado'], 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    // ========== CRIAR NOTÍCIA ==========
    if ($acao === 'criar') {
        $titulo = trim($data['titulo'] ?? '');
        $conteudo = trim($data['conteudo'] ?? '');
        $autor = trim($data['autor'] ?? $_SESSION['usuario_nome'] ?? 'Admin');
        $imagem = trim($data['imagem'] ?? '');
        
        if (empty($titulo)) {
            jsonResponse(['erro' => 'Título é obrigatório'], 400);
        }
        
        if (empty($conteudo)) {
            jsonResponse(['erro' => 'Conteúdo é obrigatório'], 400);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO noticias (titulo, conteudo, autor, imagem) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$titulo, $conteudo, $autor, $imagem]);
        
        jsonResponse([
            'sucesso' => true,
            'id' => (int)$pdo->lastInsertId(),
            'mensagem' => 'Notícia criada com sucesso'
        ]);
    }
    
    // ========== EDITAR NOTÍCIA ==========
    if ($acao === 'editar') {
        $id = (int)($data['id'] ?? 0);
        $titulo = trim($data['titulo'] ?? '');
        $conteudo = trim($data['conteudo'] ?? '');
        $autor = trim($data['autor'] ?? '');
        $imagem = trim($data['imagem'] ?? '');
        
        if (empty($titulo)) {
            jsonResponse(['erro' => 'Título é obrigatório'], 400);
        }
        
        if (empty($conteudo)) {
            jsonResponse(['erro' => 'Conteúdo é obrigatório'], 400);
        }
        
        $stmt = $pdo->prepare("
            UPDATE noticias 
            SET titulo = ?, conteudo = ?, autor = ?, imagem = ?
            WHERE id = ?
        ");
        $stmt->execute([$titulo, $conteudo, $autor, $imagem, $id]);
        
        jsonResponse([
            'sucesso' => true,
            'mensagem' => 'Notícia atualizada com sucesso'
        ]);
    }
    
    // ========== EXCLUIR NOTÍCIA ==========
    if ($acao === 'excluir') {
        $id = (int)($data['id'] ?? 0);
        
        // Soft delete (marcar como inativo)
        $stmt = $pdo->prepare("UPDATE noticias SET ativo = 0 WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse([
            'sucesso' => true,
            'mensagem' => 'Notícia excluída com sucesso'
        ]);
    }
}

jsonResponse(['erro' => 'Método não permitido'], 405);