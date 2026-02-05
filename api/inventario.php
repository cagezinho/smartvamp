<?php
/**
 * API de Inventário - SmartVamp
 * Gerencia itens do inventário com controle de quantidade
 */

require_once __DIR__ . '/../config.php';

$usuario_id = verificarAuth();
$is_admin = $_SESSION['is_admin'] ?? false;
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

// ========== GET: Buscar inventário ==========
if ($method === 'GET') {
    $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : null;
    
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
        $cat = $item['categoria'] ?: 'Outros';
        if (!isset($inventario[$cat])) {
            $inventario[$cat] = [];
        }
        $inventario[$cat][] = $item;
    }
    
    jsonResponse(['inventario' => $inventario]);
}

// ========== POST: Operações com inventário ==========
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    // ========== ADICIONAR ITEM ==========
    if ($acao === 'adicionar') {
        $item_nome = trim($data['item_nome'] ?? '');
        $categoria = trim($data['categoria'] ?? '');
        $quantidade = max(1, intval($data['quantidade'] ?? 1));
        $descricao = trim($data['descricao'] ?? '');
        $imagem = trim($data['imagem'] ?? '');
        
        if (empty($item_nome)) {
            jsonResponse(['erro' => 'Nome do item é obrigatório'], 400);
        }
        
        // Verificar se já existe o item (mesmo nome e categoria)
        $stmt = $pdo->prepare("
            SELECT id, quantidade 
            FROM inventario 
            WHERE usuario_id = ? AND item_nome = ? AND categoria = ?
        ");
        $stmt->execute([$usuario_id, $item_nome, $categoria]);
        $existente = $stmt->fetch();
        
        if ($existente) {
            // Atualizar quantidade
            $nova_quantidade = $existente['quantidade'] + $quantidade;
            $stmt = $pdo->prepare("UPDATE inventario SET quantidade = ? WHERE id = ?");
            $stmt->execute([$nova_quantidade, $existente['id']]);
            
            jsonResponse([
                'sucesso' => true,
                'id' => (int)$existente['id'],
                'mensagem' => 'Quantidade atualizada'
            ]);
        } else {
            // Criar novo item
            $stmt = $pdo->prepare("
                INSERT INTO inventario (usuario_id, item_nome, categoria, quantidade, descricao, imagem) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$usuario_id, $item_nome, $categoria, $quantidade, $descricao, $imagem]);
            
            jsonResponse([
                'sucesso' => true,
                'id' => (int)$pdo->lastInsertId(),
                'mensagem' => 'Item adicionado com sucesso'
            ]);
        }
    }
    
    // ========== EDITAR ITEM ==========
    if ($acao === 'editar') {
        $id = (int)($data['id'] ?? 0);
        $item_nome = trim($data['item_nome'] ?? '');
        $categoria = trim($data['categoria'] ?? '');
        $quantidade = max(0, intval($data['quantidade'] ?? 1));
        $descricao = trim($data['descricao'] ?? '');
        $imagem = trim($data['imagem'] ?? '');
        
        // Verificar se o item pertence ao usuário
        $stmt = $pdo->prepare("SELECT usuario_id FROM inventario WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if (!$item || ($item['usuario_id'] != $usuario_id && !$is_admin)) {
            jsonResponse(['erro' => 'Item não encontrado'], 404);
        }
        
        $stmt = $pdo->prepare("
            UPDATE inventario 
            SET item_nome = ?, categoria = ?, quantidade = ?, descricao = ?, imagem = ?
            WHERE id = ?
        ");
        $stmt->execute([$item_nome, $categoria, $quantidade, $descricao, $imagem, $id]);
        
        jsonResponse([
            'sucesso' => true,
            'mensagem' => 'Item atualizado com sucesso'
        ]);
    }
    
    // ========== ALTERAR QUANTIDADE (botões +/-) ==========
    if ($acao === 'alterar_quantidade') {
        $id = (int)($data['id'] ?? 0);
        $delta = intval($data['delta'] ?? 0); // +1 ou -1
        
        if ($delta === 0) {
            jsonResponse(['erro' => 'Delta inválido'], 400);
        }
        
        // Verificar se o item pertence ao usuário
        $stmt = $pdo->prepare("SELECT usuario_id, quantidade FROM inventario WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if (!$item || ($item['usuario_id'] != $usuario_id && !$is_admin)) {
            jsonResponse(['erro' => 'Item não encontrado'], 404);
        }
        
        $nova_quantidade = $item['quantidade'] + $delta;
        
        // Se quantidade chegar a 0 ou menos, remover item
        if ($nova_quantidade <= 0) {
            $stmt = $pdo->prepare("DELETE FROM inventario WHERE id = ?");
            $stmt->execute([$id]);
            
            jsonResponse([
                'sucesso' => true,
                'removido' => true,
                'mensagem' => 'Item removido'
            ]);
        } else {
            // Atualizar quantidade
            $stmt = $pdo->prepare("UPDATE inventario SET quantidade = ? WHERE id = ?");
            $stmt->execute([$nova_quantidade, $id]);
            
            jsonResponse([
                'sucesso' => true,
                'quantidade' => $nova_quantidade,
                'mensagem' => 'Quantidade atualizada'
            ]);
        }
    }
    
    // ========== REMOVER ITEM ==========
    if ($acao === 'remover') {
        $id = (int)($data['id'] ?? 0);
        
        // Verificar se o item pertence ao usuário
        $stmt = $pdo->prepare("SELECT usuario_id FROM inventario WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if (!$item || ($item['usuario_id'] != $usuario_id && !$is_admin)) {
            jsonResponse(['erro' => 'Item não encontrado'], 404);
        }
        
        $stmt = $pdo->prepare("DELETE FROM inventario WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse([
            'sucesso' => true,
            'mensagem' => 'Item removido com sucesso'
        ]);
    }
}

jsonResponse(['erro' => 'Método não permitido'], 405);