<?php
/**
 * API de Chat/Mensagens - SmartVamp
 * Sistema de mensagens estilo WhatsApp com suporte a NPCs
 */

require_once __DIR__ . '/../config.php';

$usuario_id = verificarAuth();
$is_admin = $_SESSION['is_admin'] ?? false;
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

// ========== GET: Buscar conversas ou mensagens ==========
if ($method === 'GET') {
    $conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : null;
    
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
        $stmt = $pdo->prepare("
            UPDATE mensagens 
            SET lida = 1 
            WHERE conversa_id = ? AND remetente = 'npc' AND lida = 0
        ");
        $stmt->execute([$conversa_id]);
        
        // Atualizar contador de não lidas
        $stmt = $pdo->prepare("
            UPDATE conversas 
            SET nao_lidas = 0 
            WHERE id = ?
        ");
        $stmt->execute([$conversa_id]);
        
        jsonResponse(['mensagens' => $mensagens]);
    } else {
        // Buscar lista de conversas
        $stmt = $pdo->prepare("
            SELECT * FROM conversas 
            WHERE usuario_id = ? 
            ORDER BY COALESCE(ultima_mensagem_em, '1970-01-01') DESC, criado_em DESC
        ");
        $stmt->execute([$usuario_id]);
        $conversas = $stmt->fetchAll();
        
        jsonResponse(['conversas' => $conversas]);
    }
}

// ========== POST: Operações com chat ==========
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    // ========== ENVIAR MENSAGEM ==========
    if ($acao === 'enviar') {
        $conversa_id = (int)($data['conversa_id'] ?? 0);
        $conteudo = trim($data['conteudo'] ?? '');
        $tipo_midia = $data['tipo_midia'] ?? 'texto';
        $arquivo_midia = trim($data['arquivo_midia'] ?? '');
        
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
        
        jsonResponse([
            'sucesso' => true,
            'id' => (int)$pdo->lastInsertId(),
            'mensagem' => 'Mensagem enviada com sucesso'
        ]);
    }
    
    // ========== CRIAR CONVERSA ==========
    if ($acao === 'criar_conversa') {
        $nome_contato = trim($data['nome_contato'] ?? '');
        $avatar_contato = trim($data['avatar_contato'] ?? '');
        $contato_id = !empty($data['contato_id']) ? (int)$data['contato_id'] : null;
        
        if (empty($nome_contato)) {
            jsonResponse(['erro' => 'Nome do contato é obrigatório'], 400);
        }
        
        // Verificar se já existe conversa com este contato
        if ($contato_id) {
            $stmt = $pdo->prepare("
                SELECT id FROM conversas 
                WHERE usuario_id = ? AND contato_id = ?
            ");
            $stmt->execute([$usuario_id, $contato_id]);
            $existe = $stmt->fetch();
            
            if ($existe) {
                jsonResponse([
                    'sucesso' => true,
                    'id' => (int)$existe['id'],
                    'mensagem' => 'Conversa já existe'
                ]);
            }
        }
        
        // Criar nova conversa
        $stmt = $pdo->prepare("
            INSERT INTO conversas (usuario_id, contato_id, nome_contato, avatar_contato) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $contato_id, $nome_contato, $avatar_contato]);
        
        jsonResponse([
            'sucesso' => true,
            'id' => (int)$pdo->lastInsertId(),
            'mensagem' => 'Conversa criada com sucesso'
        ]);
    }
    
    // ========== ENVIAR COMO NPC (ADMIN) ==========
    if ($acao === 'enviar_npc' && $is_admin) {
        $usuario_destino_id = (int)($data['usuario_id'] ?? 0);
        $conversa_id = !empty($data['conversa_id']) ? (int)$data['conversa_id'] : null;
        $nome_contato = trim($data['nome_contato'] ?? '');
        $avatar_contato = trim($data['avatar_contato'] ?? '');
        $conteudo = trim($data['conteudo'] ?? '');
        
        if (empty($conteudo)) {
            jsonResponse(['erro' => 'Mensagem não pode estar vazia'], 400);
        }
        
        if (empty($nome_contato)) {
            jsonResponse(['erro' => 'Nome do contato é obrigatório'], 400);
        }
        
        // Se não tem conversa_id, criar nova conversa
        if (!$conversa_id) {
            if (!$usuario_destino_id) {
                jsonResponse(['erro' => 'Usuário destino é obrigatório'], 400);
            }
            
            // Verificar se já existe conversa
            $stmt = $pdo->prepare("
                SELECT id FROM conversas 
                WHERE usuario_id = ? AND nome_contato = ?
            ");
            $stmt->execute([$usuario_destino_id, $nome_contato]);
            $existe = $stmt->fetch();
            
            if ($existe) {
                $conversa_id = (int)$existe['id'];
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO conversas (usuario_id, nome_contato, avatar_contato) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$usuario_destino_id, $nome_contato, $avatar_contato]);
                $conversa_id = (int)$pdo->lastInsertId();
            }
        }
        
        // Inserir mensagem como NPC
        $stmt = $pdo->prepare("
            INSERT INTO mensagens (conversa_id, remetente, conteudo, tipo_midia) 
            VALUES (?, 'npc', ?, 'texto')
        ");
        $stmt->execute([$conversa_id, $conteudo]);
        
        // Atualizar conversa
        $stmt = $pdo->prepare("
            UPDATE conversas 
            SET ultima_mensagem = ?, ultima_mensagem_em = NOW(), 
                nao_lidas = nao_lidas + 1
            WHERE id = ?
        ");
        $stmt->execute([$conteudo, $conversa_id]);
        
        jsonResponse([
            'sucesso' => true,
            'id' => (int)$pdo->lastInsertId(),
            'conversa_id' => $conversa_id,
            'mensagem' => 'Mensagem enviada como NPC'
        ]);
    }
}

jsonResponse(['erro' => 'Método não permitido'], 405);