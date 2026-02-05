<?php
require_once '../config.php';

$usuario_id = verificarAuth();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

if ($method === 'GET') {
    // Buscar saldo, chave PIX e transações
    $stmt = $pdo->prepare("SELECT saldo, chave_pix FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        SELECT id, tipo, valor, descricao, pix_chave, pix_nome, criado_em 
        FROM transacoes 
        WHERE usuario_id = ? 
        ORDER BY criado_em DESC 
        LIMIT 50
    ");
    $stmt->execute([$usuario_id]);
    $transacoes = $stmt->fetchAll();
    
    jsonResponse([
        'saldo' => floatval($usuario['saldo'] ?? 0),
        'chave_pix' => $usuario['chave_pix'] ?? null,
        'transacoes' => $transacoes
    ]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    if ($acao === 'pix') {
        $tipo = $data['tipo'] ?? ''; // 'enviar' ou 'receber'
        $valor = floatval($data['valor'] ?? 0);
        $chave = $data['chave'] ?? '';
        $nome = $data['nome'] ?? '';
        $descricao = $data['descricao'] ?? '';
        
        if ($valor <= 0) {
            jsonResponse(['erro' => 'Valor inválido'], 400);
        }
        
        $pdo->beginTransaction();
        
        try {
            $stmt = $pdo->prepare("SELECT saldo FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch();
            $saldo_atual = floatval($usuario['saldo']);
            
            if ($tipo === 'enviar') {
                if ($saldo_atual < $valor) {
                    throw new Exception('Saldo insuficiente');
                }
                $novo_saldo = $saldo_atual - $valor;
                $tipo_transacao = 'saida';
            } else {
                $novo_saldo = $saldo_atual + $valor;
                $tipo_transacao = 'entrada';
            }
            
            // Atualizar saldo
            $stmt = $pdo->prepare("UPDATE usuarios SET saldo = ? WHERE id = ?");
            $stmt->execute([$novo_saldo, $usuario_id]);
            
            // Buscar nome do destinatário pela chave PIX (se for chave de outro usuário)
            $nome_destinatario = '';
            if (!empty($chave)) {
                $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE chave_pix = ? LIMIT 1");
                $stmt->execute([$chave]);
                $destinatario = $stmt->fetch();
                if ($destinatario) {
                    $nome_destinatario = $destinatario['nome'];
                }
            }
            
            // Registrar transação
            $stmt = $pdo->prepare("
                INSERT INTO transacoes (usuario_id, tipo, valor, descricao, pix_chave, pix_nome) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$usuario_id, $tipo_transacao, $valor, $descricao, $chave, $nome_destinatario]);
            
            $pdo->commit();
            
            jsonResponse([
                'sucesso' => true,
                'novo_saldo' => $novo_saldo,
                'mensagem' => $tipo === 'enviar' ? 'PIX enviado com sucesso' : 'PIX recebido com sucesso'
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['erro' => $e->getMessage()], 400);
        }
    }
    
    if ($acao === 'salvar_chave_pix') {
        $chave_pix = $data['chave_pix'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET chave_pix = ? WHERE id = ?");
            $stmt->execute([$chave_pix, $usuario_id]);
            
            jsonResponse(['sucesso' => true, 'mensagem' => 'Chave PIX salva com sucesso']);
        } catch (Exception $e) {
            jsonResponse(['erro' => 'Erro ao salvar chave PIX'], 400);
        }
    }
}

jsonResponse(['erro' => 'Método não permitido'], 405);
