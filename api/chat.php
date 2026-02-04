<?php
require_once '../config.php';

$usuario_id = verificarAuth();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

if ($method === 'GET') {
    $conversa_id = $_GET['conversa_id'] ?? null;
    
    if ($conversa_id) {
        // Buscar mensagens de uma conversa específica
        $stmt = $pdo->prepare("
            SELECT m.*, c.nome_contato, c.avatar_contato 
            FROM mensagens m
            JOIN conversas c ON m.conversa_id = c.id
            WHERE m.conversa_id = ? AND c.usuario_id = ?
            ORDER BY m.criado_em ASC
        ");
        $stmt->execute([$conversa_id, $usuario_id]);
        $mensagens = $stmt->fetchAll();
        
        // Marcar mensagens como lidas
        $stmt = $pdo->prepare("UPDATE mensagens SET lida = 1 WHERE conversa_id = ? AND remetente = 'npc'");
        $stmt->execute([$conversa_id]);
        
        $stmt = $pdo->prepare("UPDATE conversas SET nao_lidas = 0 WHERE id = ?");
        $stmt->execute([$conversa_id]);
        
        jsonResponse(['mensagens' => $mensagens]);
    } else {
        // Buscar lista de conversas
        $stmt = $pdo->prepare("
            SELECT * FROM conversas 
            WHERE usuario_id = ? 
            ORDER BY ultima_mensagem_em DESC, criado_em DESC
        ");
        $stmt->execute([$usuario_id]);
        $conversas = $stmt->fetchAll();
        
        jsonResponse(['conversas' => $conversas]);
    }
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    if ($acao === 'enviar') {
        $conversa_id = $data['conversa_id'] ?? 0;
        $conteudo = $data['conteudo'] ?? '';
        $tipo_midia = $data['tipo_midia'] ?? 'texto';
        $arquivo_midia = $data['arquivo_midia'] ?? '';
        
        if (empty($conteudo) && $tipo_midia === 'texto') {
            jsonResponse(['erro' => 'Mensagem não pode estar vazia'], 400);
        }
        
        // Verificar se a conversa pertence ao usuário
        $stmt = $pdo->prepare("SELECT usuario_id FROM conversas WHERE id = ?");
        $stmt->execute([$conversa_id]);
        $conversa = $stmt->fetch();
        
        if (!$conversa || $conversa['usuario_id'] != $usuario_id) {
            jsonResponse(['erro' => 'Conversa não encontrada'], 404);
        }
        
        // Inserir mensagem
        $stmt = $pdo->prepare("
            INSERT INTO mensagens (conversa_id, remetente, conteudo, tipo_midia, arquivo_midia) 
            VALUES (?, 'jogador', ?, ?, ?)
        ");
        $stmt->execute([$conversa_id, $conteudo, $tipo_midia, $arquivo_midia]);
        
        // Atualizar última mensagem da conversa
        $stmt = $pdo->prepare("
            UPDATE conversas 
            SET ultima_mensagem = ?, ultima_mensagem_em = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$conteudo, $conversa_id]);
        
        jsonResponse(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
    }
    
    if ($acao === 'criar_conversa') {
        $nome_contato = $data['nome_contato'] ?? '';
        $avatar_contato = $data['avatar_contato'] ?? '';
        $contato_id = $data['contato_id'] ?? null;
        
        if (empty($nome_contato)) {
            jsonResponse(['erro' => 'Nome do contato é obrigatório'], 400);
        }
        
        // Verificar se já existe conversa com este contato
        $stmt = $pdo->prepare("SELECT id FROM conversas WHERE usuario_id = ? AND contato_id = ?");
        $stmt->execute([$usuario_id, $contato_id]);
        $existe = $stmt->fetch();
        
        if ($existe) {
            jsonResponse(['sucesso' => true, 'id' => $existe['id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO conversas (usuario_id, contato_id, nome_contato, avatar_contato) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$usuario_id, $contato_id, $nome_contato, $avatar_contato]);
            jsonResponse(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
        }
    }
}

jsonResponse(['erro' => 'Método não permitido'], 405);
