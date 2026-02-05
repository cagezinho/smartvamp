<?php
require_once '../config.php';

$usuario_id = verificarAuth();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

if ($method === 'GET') {
    // Buscar grupos
    $stmt = $pdo->query("SELECT id, nome, cor FROM grupos_contatos ORDER BY nome");
    $grupos = $stmt->fetchAll();
    
    // Buscar contatos do usuário e contatos globais (criados pelo mestre)
    $stmt = $pdo->prepare("
        SELECT c.*, g.nome as grupo_nome, g.cor as grupo_cor 
        FROM contatos c
        LEFT JOIN grupos_contatos g ON c.grupo_id = g.id
        WHERE c.usuario_id = ? OR c.criado_por = 'mestre'
        ORDER BY c.nome
    ");
    $stmt->execute([$usuario_id]);
    $contatos = $stmt->fetchAll();
    
    jsonResponse([
        'grupos' => $grupos,
        'contatos' => $contatos
    ]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    if ($acao === 'adicionar') {
        $nome = $data['nome'] ?? '';
        $telefone = $data['telefone'] ?? '';
        $grupo_id = $data['grupo_id'] ?? null;
        $endereco = $data['endereco'] ?? '';
        $profissao = $data['profissao'] ?? '';
        $notas = $data['notas'] ?? '';
        $avatar = $data['avatar'] ?? '';
        
        if (empty($nome)) {
            jsonResponse(['erro' => 'Nome é obrigatório'], 400);
        }
        
        // Verificar se já existe contato com este telefone (correlacionar)
        $contato_existente = null;
        if (!empty($telefone)) {
            $stmt = $pdo->prepare("
                SELECT id, nome, usuario_id, criado_por 
                FROM contatos 
                WHERE telefone = ? 
                LIMIT 1
            ");
            $stmt->execute([$telefone]);
            $contato_existente = $stmt->fetch();
        }
        
        // Se já existe contato com este telefone, criar apenas para este usuário (não duplicar para mestre)
        if ($contato_existente) {
            // Verificar se o usuário já tem este contato
            $stmt = $pdo->prepare("
                SELECT id FROM contatos 
                WHERE usuario_id = ? AND telefone = ?
            ");
            $stmt->execute([$usuario_id, $telefone]);
            $ja_tem = $stmt->fetch();
            
            if ($ja_tem) {
                jsonResponse(['erro' => 'Você já possui este contato'], 400);
            }
            
            // Criar contato para este usuário (mesmo telefone, pode ter nome diferente)
            $stmt = $pdo->prepare("
                INSERT INTO contatos (usuario_id, grupo_id, nome, telefone, avatar, endereco, profissao, notas, criado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'jogador')
            ");
            $stmt->execute([$usuario_id, $grupo_id, $nome, $telefone, $avatar, $endereco, $profissao, $notas]);
            
            jsonResponse(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            // Novo contato, criar normalmente
            $stmt = $pdo->prepare("
                INSERT INTO contatos (usuario_id, grupo_id, nome, telefone, avatar, endereco, profissao, notas, criado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'jogador')
            ");
            $stmt->execute([$usuario_id, $grupo_id, $nome, $telefone, $avatar, $endereco, $profissao, $notas]);
            
            jsonResponse(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
        }
    }
    
    if ($acao === 'editar') {
        $id = $data['id'] ?? 0;
        $nome = $data['nome'] ?? '';
        $telefone = $data['telefone'] ?? '';
        $grupo_id = $data['grupo_id'] ?? null;
        $endereco = $data['endereco'] ?? '';
        $profissao = $data['profissao'] ?? '';
        $notas = $data['notas'] ?? '';
        $avatar = $data['avatar'] ?? '';
        
        // Verificar se o contato pertence ao usuário
        $stmt = $pdo->prepare("SELECT usuario_id FROM contatos WHERE id = ?");
        $stmt->execute([$id]);
        $contato = $stmt->fetch();
        
        if (!$contato || ($contato['usuario_id'] != $usuario_id && !isset($_SESSION['is_admin']))) {
            jsonResponse(['erro' => 'Contato não encontrado'], 404);
        }
        
        $stmt = $pdo->prepare("
            UPDATE contatos 
            SET nome = ?, telefone = ?, grupo_id = ?, endereco = ?, profissao = ?, notas = ?, avatar = ?
            WHERE id = ?
        ");
        $stmt->execute([$nome, $telefone, $grupo_id, $endereco, $profissao, $notas, $avatar, $id]);
        
        jsonResponse(['sucesso' => true]);
    }
    
    if ($acao === 'excluir') {
        $id = $data['id'] ?? 0;
        
        // Verificar se o contato pertence ao usuário
        $stmt = $pdo->prepare("SELECT usuario_id FROM contatos WHERE id = ?");
        $stmt->execute([$id]);
        $contato = $stmt->fetch();
        
        if (!$contato || ($contato['usuario_id'] != $usuario_id && !isset($_SESSION['is_admin']))) {
            jsonResponse(['erro' => 'Contato não encontrado'], 404);
        }
        
        $stmt = $pdo->prepare("DELETE FROM contatos WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse(['sucesso' => true]);
    }
}

jsonResponse(['erro' => 'Método não permitido'], 405);
