<?php
require_once '../config.php';

$usuario_id = verificarAuth();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

if ($method === 'GET') {
    // Buscar saldo e transações
    $stmt = $pdo->prepare("SELECT saldo FROM usuarios WHERE id = ?");
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
        'saldo' => floatval($usuario['saldo']),
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
            
            // Registrar transação
            $stmt = $pdo->prepare("
                INSERT INTO transacoes (usuario_id, tipo, valor, descricao, pix_chave, pix_nome) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$usuario_id, $tipo_transacao, $valor, $descricao, $chave, $nome]);
            
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
}

jsonResponse(['erro' => 'Método não permitido'], 405);
