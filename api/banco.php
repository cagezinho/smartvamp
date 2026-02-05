<?php
/**
 * API do Sistema Bancário - SmartVamp
 * Gerencia saldo, chave PIX e transações
 */

require_once __DIR__ . '/../config.php';

$usuario_id = verificarAuth();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

// ========== GET: Buscar dados do banco ==========
if ($method === 'GET') {
    // Buscar saldo e chave PIX
    $stmt = $pdo->prepare("
        SELECT saldo, COALESCE(chave_pix, '') as chave_pix 
        FROM usuarios 
        WHERE id = ?
    ");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        jsonResponse(['erro' => 'Usuário não encontrado'], 404);
    }
    
    // Buscar transações recentes
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
        'chave_pix' => $usuario['chave_pix'] ?: null,
        'transacoes' => $transacoes
    ]);
}

// ========== POST: Operações bancárias ==========
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    // ========== ENVIAR PIX ==========
    if ($acao === 'pix') {
        $valor = floatval($data['valor'] ?? 0);
        $chave = trim($data['chave'] ?? '');
        $descricao = trim($data['descricao'] ?? 'PIX enviado');
        
        if ($valor <= 0) {
            jsonResponse(['erro' => 'Valor deve ser maior que zero'], 400);
        }
        
        if (empty($chave)) {
            jsonResponse(['erro' => 'Chave PIX é obrigatória'], 400);
        }
        
        $pdo->beginTransaction();
        
        try {
            // Verificar saldo
            $stmt = $pdo->prepare("SELECT saldo FROM usuarios WHERE id = ? FOR UPDATE");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch();
            $saldo_atual = floatval($usuario['saldo'] ?? 0);
            
            if ($saldo_atual < $valor) {
                throw new Exception('Saldo insuficiente');
            }
            
            // Buscar nome do destinatário pela chave PIX
            $nome_destinatario = '';
            $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE chave_pix = ? LIMIT 1");
            $stmt->execute([$chave]);
            $destinatario = $stmt->fetch();
            if ($destinatario) {
                $nome_destinatario = $destinatario['nome'];
            }
            
            // Atualizar saldo (debitar)
            $novo_saldo = $saldo_atual - $valor;
            $stmt = $pdo->prepare("UPDATE usuarios SET saldo = ? WHERE id = ?");
            $stmt->execute([$novo_saldo, $usuario_id]);
            
            // Registrar transação de saída
            $stmt = $pdo->prepare("
                INSERT INTO transacoes (usuario_id, tipo, valor, descricao, pix_chave, pix_nome) 
                VALUES (?, 'saida', ?, ?, ?, ?)
            ");
            $stmt->execute([$usuario_id, $valor, $descricao, $chave, $nome_destinatario]);
            
            // Se o destinatário existe, creditar e registrar entrada
            if ($destinatario) {
                $dest_id = $destinatario['id'] ?? null;
                if ($dest_id) {
                    $stmt = $pdo->prepare("SELECT saldo FROM usuarios WHERE id = ? FOR UPDATE");
                    $stmt->execute([$dest_id]);
                    $dest = $stmt->fetch();
                    $saldo_dest = floatval($dest['saldo'] ?? 0);
                    $novo_saldo_dest = $saldo_dest + $valor;
                    
                    $stmt = $pdo->prepare("UPDATE usuarios SET saldo = ? WHERE id = ?");
                    $stmt->execute([$novo_saldo_dest, $dest_id]);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO transacoes (usuario_id, tipo, valor, descricao, pix_chave, pix_nome) 
                        VALUES (?, 'entrada', ?, ?, ?, ?)
                    ");
                    $stmt->execute([$dest_id, $valor, 'PIX recebido', $chave, $_SESSION['usuario_nome'] ?? '']);
                }
            }
            
            $pdo->commit();
            
            jsonResponse([
                'sucesso' => true,
                'novo_saldo' => $novo_saldo,
                'mensagem' => 'PIX enviado com sucesso'
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['erro' => $e->getMessage()], 400);
        }
    }
    
    // ========== SALVAR CHAVE PIX ==========
    if ($acao === 'salvar_chave_pix') {
        $chave_pix = trim($data['chave_pix'] ?? '');
        
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET chave_pix = ? WHERE id = ?");
            $stmt->execute([$chave_pix, $usuario_id]);
            
            jsonResponse([
                'sucesso' => true,
                'mensagem' => 'Chave PIX salva com sucesso'
            ]);
        } catch (Exception $e) {
            jsonResponse(['erro' => 'Erro ao salvar chave PIX: ' . $e->getMessage()], 400);
        }
    }
}

jsonResponse(['erro' => 'Método não permitido'], 405);