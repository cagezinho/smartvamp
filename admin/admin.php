<?php
require_once '../config.php';

$admin_id = verificarAdmin();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

if ($method === 'GET') {
    $acao = $_GET['acao'] ?? '';
    
    if ($acao === 'usuarios') {
        $stmt = $pdo->query("SELECT id, nome, saldo, tema, criado_em, ultimo_acesso, ativo FROM usuarios ORDER BY nome");
        $usuarios = $stmt->fetchAll();
        jsonResponse(['usuarios' => $usuarios]);
    }
    
    if ($acao === 'transacoes') {
        $stmt = $pdo->query("
            SELECT t.*, u.nome as usuario_nome 
            FROM transacoes t
            JOIN usuarios u ON t.usuario_id = u.id
            ORDER BY t.criado_em DESC 
            LIMIT 100
        ");
        $transacoes = $stmt->fetchAll();
        jsonResponse(['transacoes' => $transacoes]);
    }
    
    if ($acao === 'contatos') {
        $stmt = $pdo->query("
            SELECT c.*, g.nome as grupo_nome, g.cor as grupo_cor 
            FROM contatos c
            LEFT JOIN grupos_contatos g ON c.grupo_id = g.id
            WHERE c.criado_por = 'mestre'
            ORDER BY c.nome
        ");
        $contatos = $stmt->fetchAll();
        jsonResponse(['contatos' => $contatos]);
    }
    
    if ($acao === 'noticias') {
        $stmt = $pdo->query("SELECT * FROM noticias ORDER BY criado_em DESC");
        $noticias = $stmt->fetchAll();
        jsonResponse(['noticias' => $noticias]);
    }
    
    if ($acao === 'conversas') {
        $usuario_id = $_GET['usuario_id'] ?? 0;
        if ($usuario_id) {
            $stmt = $pdo->prepare("SELECT * FROM conversas WHERE usuario_id = ? ORDER BY ultima_mensagem_em DESC");
            $stmt->execute([$usuario_id]);
            $conversas = $stmt->fetchAll();
            jsonResponse(['conversas' => $conversas]);
        }
    }
    
    if ($acao === 'grupos') {
        $stmt = $pdo->query("SELECT id, nome, cor FROM grupos_contatos ORDER BY nome");
        $grupos = $stmt->fetchAll();
        jsonResponse(['grupos' => $grupos]);
    }
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    if ($acao === 'criar_usuario') {
        $nome = $data['nome'] ?? '';
        $senha = $data['senha'] ?? '';
        $saldo = floatval($data['saldo'] ?? 0);
        $tema = $data['tema'] ?? 'escuro';
        
        if (empty($nome) || empty($senha)) {
            jsonResponse(['erro' => 'Nome e senha são obrigatórios'], 400);
        }
        
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, senha, saldo, tema) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $senha_hash, $saldo, $tema]);
        
        jsonResponse(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
    }
    
    if ($acao === 'editar_usuario') {
        $id = $data['id'] ?? 0;
        $nome = $data['nome'] ?? '';
        $senha = $data['senha'] ?? '';
        $saldo = floatval($data['saldo'] ?? 0);
        $tema = $data['tema'] ?? 'escuro';
        $ativo = $data['ativo'] ?? 1;
        
        if (empty($nome)) {
            jsonResponse(['erro' => 'Nome é obrigatório'], 400);
        }
        
        if (!empty($senha)) {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, senha = ?, saldo = ?, tema = ?, ativo = ? WHERE id = ?");
            $stmt->execute([$nome, $senha_hash, $saldo, $tema, $ativo, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, saldo = ?, tema = ?, ativo = ? WHERE id = ?");
            $stmt->execute([$nome, $saldo, $tema, $ativo, $id]);
        }
        
        jsonResponse(['sucesso' => true]);
    }
    
    if ($acao === 'adicionar_saldo') {
        $usuario_id = $data['usuario_id'] ?? 0;
        $valor = floatval($data['valor'] ?? 0);
        $descricao = $data['descricao'] ?? 'Adicionado pelo admin';
        
        if ($valor == 0) {
            jsonResponse(['erro' => 'Valor inválido'], 400);
        }
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT saldo FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch();
            $novo_saldo = floatval($usuario['saldo']) + $valor;
            
            $stmt = $pdo->prepare("UPDATE usuarios SET saldo = ? WHERE id = ?");
            $stmt->execute([$novo_saldo, $usuario_id]);
            
            $stmt = $pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, descricao) VALUES (?, 'entrada', ?, ?)");
            $stmt->execute([$usuario_id, $valor, $descricao]);
            
            $pdo->commit();
            jsonResponse(['sucesso' => true, 'novo_saldo' => $novo_saldo]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['erro' => $e->getMessage()], 400);
        }
    }
    
    if ($acao === 'criar_contato') {
        $nome = $data['nome'] ?? '';
        $telefone = $data['telefone'] ?? '';
        $grupo_id = $data['grupo_id'] ?? null;
        $endereco = $data['endereco'] ?? '';
        $profissao = $data['profissao'] ?? '';
        $notas = $data['notas'] ?? '';
        
        if (empty($nome)) {
            jsonResponse(['erro' => 'Nome é obrigatório'], 400);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO contatos (usuario_id, grupo_id, nome, telefone, endereco, profissao, notas, criado_por) 
            VALUES (NULL, ?, ?, ?, ?, ?, ?, 'mestre')
        ");
        $stmt->execute([$grupo_id, $nome, $telefone, $endereco, $profissao, $notas]);
        
        jsonResponse(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
    }
    
    if ($acao === 'editar_contato') {
        $id = $data['id'] ?? 0;
        $nome = $data['nome'] ?? '';
        $telefone = $data['telefone'] ?? '';
        $grupo_id = $data['grupo_id'] ?? null;
        $endereco = $data['endereco'] ?? '';
        $profissao = $data['profissao'] ?? '';
        $notas = $data['notas'] ?? '';
        
        $stmt = $pdo->prepare("
            UPDATE contatos 
            SET nome = ?, telefone = ?, grupo_id = ?, endereco = ?, profissao = ?, notas = ?
            WHERE id = ?
        ");
        $stmt->execute([$nome, $telefone, $grupo_id, $endereco, $profissao, $notas, $id]);
        
        jsonResponse(['sucesso' => true]);
    }
    
    if ($acao === 'enviar_mensagem_npc') {
        $conversa_id = $data['conversa_id'] ?? 0;
        $conteudo = $data['conteudo'] ?? '';
        $tipo_midia = $data['tipo_midia'] ?? 'texto';
        $arquivo_midia = $data['arquivo_midia'] ?? '';
        
        if (empty($conteudo) && $tipo_midia === 'texto') {
            jsonResponse(['erro' => 'Mensagem não pode estar vazia'], 400);
        }
        
        $pdo->beginTransaction();
        try {
            // Inserir mensagem
            $stmt = $pdo->prepare("
                INSERT INTO mensagens (conversa_id, remetente, conteudo, tipo_midia, arquivo_midia) 
                VALUES (?, 'npc', ?, ?, ?)
            ");
            $stmt->execute([$conversa_id, $conteudo, $tipo_midia, $arquivo_midia]);
            
            // Atualizar conversa
            $stmt = $pdo->prepare("
                UPDATE conversas 
                SET ultima_mensagem = ?, ultima_mensagem_em = NOW(), nao_lidas = nao_lidas + 1
                WHERE id = ?
            ");
            $stmt->execute([$conteudo, $conversa_id]);
            
            $pdo->commit();
            jsonResponse(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['erro' => $e->getMessage()], 400);
        }
    }
    
    if ($acao === 'criar_conversa_npc') {
        $usuario_id = $data['usuario_id'] ?? 0;
        $nome_contato = $data['nome_contato'] ?? '';
        $avatar_contato = $data['avatar_contato'] ?? '';
        $contato_id = $data['contato_id'] ?? null;
        
        if (empty($nome_contato)) {
            jsonResponse(['erro' => 'Nome do contato é obrigatório'], 400);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO conversas (usuario_id, contato_id, nome_contato, avatar_contato) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $contato_id, $nome_contato, $avatar_contato]);
        
        jsonResponse(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
    }
    
    if ($acao === 'criar_noticia') {
        $titulo = $data['titulo'] ?? '';
        $conteudo = $data['conteudo'] ?? '';
        $autor = $data['autor'] ?? '';
        
        if (empty($titulo) || empty($conteudo)) {
            jsonResponse(['erro' => 'Título e conteúdo são obrigatórios'], 400);
        }
        
        $stmt = $pdo->prepare("INSERT INTO noticias (titulo, conteudo, autor) VALUES (?, ?, ?)");
        $stmt->execute([$titulo, $conteudo, $autor]);
        
        jsonResponse(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
    }
    
    if ($acao === 'editar_noticia') {
        $id = $data['id'] ?? 0;
        $titulo = $data['titulo'] ?? '';
        $conteudo = $data['conteudo'] ?? '';
        $autor = $data['autor'] ?? '';
        $ativo = $data['ativo'] ?? 1;
        
        $stmt = $pdo->prepare("UPDATE noticias SET titulo = ?, conteudo = ?, autor = ?, ativo = ? WHERE id = ?");
        $stmt->execute([$titulo, $conteudo, $autor, $ativo, $id]);
        
        jsonResponse(['sucesso' => true]);
    }
    
    if ($acao === 'atualizar_relogio') {
        $modo = $data['modo'] ?? 'normal';
        $data_jogo = $data['data'] ?? null;
        $hora_jogo = $data['hora'] ?? null;
        
        $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'relogio_modo'");
        $stmt->execute([$modo]);
        
        if ($modo === 'manual' && $data_jogo && $hora_jogo) {
            $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'relogio_data'");
            $stmt->execute([$data_jogo]);
            
            $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'relogio_hora'");
            $stmt->execute([$hora_jogo]);
        }
        
        jsonResponse(['sucesso' => true]);
    }
}

jsonResponse(['erro' => 'Método não permitido'], 405);
