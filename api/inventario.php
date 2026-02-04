<?php
require_once '../config.php';

$usuario_id = verificarAuth();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

if ($method === 'GET') {
    $categoria = $_GET['categoria'] ?? null;
    
    $sql = "SELECT * FROM inventario WHERE usuario_id = ?";
    $params = [$usuario_id];
    
    if ($categoria) {
        $sql .= " AND categoria = ?";
        $params[] = $categoria;
    }
    
    $sql .= " ORDER BY categoria, item_nome";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $itens = $stmt->fetchAll();
    
    // Agrupar por categoria
    $inventario = [];
    foreach ($itens as $item) {
        $cat = $item['categoria'] ?: 'outros';
        if (!isset($inventario[$cat])) {
            $inventario[$cat] = [];
        }
        $inventario[$cat][] = $item;
    }
    
    jsonResponse(['inventario' => $inventario]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    if ($acao === 'adicionar') {
        $item_nome = $data['item_nome'] ?? '';
        $categoria = $data['categoria'] ?? '';
        $quantidade = intval($data['quantidade'] ?? 1);
        $descricao = $data['descricao'] ?? '';
        $imagem = $data['imagem'] ?? '';
        
        if (empty($item_nome)) {
            jsonResponse(['erro' => 'Nome do item é obrigatório'], 400);
        }
        
        // Verificar se já existe o item
        $stmt = $pdo->prepare("SELECT id, quantidade FROM inventario WHERE usuario_id = ? AND item_nome = ? AND categoria = ?");
        $stmt->execute([$usuario_id, $item_nome, $categoria]);
        $existente = $stmt->fetch();
        
        if ($existente) {
            // Atualizar quantidade
            $nova_quantidade = $existente['quantidade'] + $quantidade;
            $stmt = $pdo->prepare("UPDATE inventario SET quantidade = ? WHERE id = ?");
            $stmt->execute([$nova_quantidade, $existente['id']]);
            jsonResponse(['sucesso' => true, 'id' => $existente['id']]);
        } else {
            // Criar novo item
            $stmt = $pdo->prepare("
                INSERT INTO inventario (usuario_id, item_nome, categoria, quantidade, descricao, imagem) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$usuario_id, $item_nome, $categoria, $quantidade, $descricao, $imagem]);
            jsonResponse(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
        }
    }
    
    if ($acao === 'editar') {
        $id = $data['id'] ?? 0;
        $item_nome = $data['item_nome'] ?? '';
        $categoria = $data['categoria'] ?? '';
        $quantidade = intval($data['quantidade'] ?? 1);
        $descricao = $data['descricao'] ?? '';
        $imagem = $data['imagem'] ?? '';
        
        // Verificar se o item pertence ao usuário
        $stmt = $pdo->prepare("SELECT usuario_id FROM inventario WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if (!$item || ($item['usuario_id'] != $usuario_id && !isset($_SESSION['is_admin']))) {
            jsonResponse(['erro' => 'Item não encontrado'], 404);
        }
        
        $stmt = $pdo->prepare("
            UPDATE inventario 
            SET item_nome = ?, categoria = ?, quantidade = ?, descricao = ?, imagem = ?
            WHERE id = ?
        ");
        $stmt->execute([$item_nome, $categoria, $quantidade, $descricao, $imagem, $id]);
        
        jsonResponse(['sucesso' => true]);
    }
    
    if ($acao === 'remover') {
        $id = $data['id'] ?? 0;
        $quantidade = intval($data['quantidade'] ?? 1);
        
        // Verificar se o item pertence ao usuário
        $stmt = $pdo->prepare("SELECT usuario_id, quantidade FROM inventario WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if (!$item || ($item['usuario_id'] != $usuario_id && !isset($_SESSION['is_admin']))) {
            jsonResponse(['erro' => 'Item não encontrado'], 404);
        }
        
        if ($item['quantidade'] <= $quantidade) {
            // Remover completamente
            $stmt = $pdo->prepare("DELETE FROM inventario WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            // Reduzir quantidade
            $nova_quantidade = $item['quantidade'] - $quantidade;
            $stmt = $pdo->prepare("UPDATE inventario SET quantidade = ? WHERE id = ?");
            $stmt->execute([$nova_quantidade, $id]);
        }
        
        jsonResponse(['sucesso' => true]);
    }
}

jsonResponse(['erro' => 'Método não permitido'], 405);
