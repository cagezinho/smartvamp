<?php
/**
 * API do Relógio do Jogo - SmartVamp
 * Controla o tempo do jogo (normal ou manual)
 */

require_once __DIR__ . '/../config.php';

$usuario_id = verificarAuth();
$is_admin = $_SESSION['is_admin'] ?? false;
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

// ========== GET: Buscar configuração do relógio ==========
if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE chave LIKE 'relogio_%'");
    $stmt->execute();
    $configs = $stmt->fetchAll();
    
    $relogio = [
        'modo' => 'normal',
        'data' => date('Y-m-d'),
        'hora' => date('H:i:s')
    ];
    
    foreach ($configs as $config) {
        if ($config['chave'] === 'relogio_modo') {
            $relogio['modo'] = $config['valor'];
        } elseif ($config['chave'] === 'relogio_data') {
            $relogio['data'] = $config['valor'];
        } elseif ($config['chave'] === 'relogio_hora') {
            $relogio['hora'] = $config['valor'];
        }
    }
    
    jsonResponse($relogio);
}

// ========== POST: Atualizar relógio (apenas admin) ==========
if ($method === 'POST') {
    if (!$is_admin) {
        jsonResponse(['erro' => 'Acesso negado'], 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    // ========== ATUALIZAR MODO ==========
    if ($acao === 'atualizar_modo') {
        $modo = trim($data['modo'] ?? 'normal');
        
        if (!in_array($modo, ['normal', 'manual'])) {
            jsonResponse(['erro' => 'Modo inválido'], 400);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO configuracoes (chave, valor) 
            VALUES ('relogio_modo', ?)
            ON DUPLICATE KEY UPDATE valor = ?
        ");
        $stmt->execute([$modo, $modo]);
        
        jsonResponse([
            'sucesso' => true,
            'mensagem' => 'Modo atualizado com sucesso'
        ]);
    }
    
    // ========== ATUALIZAR DATA/HORA ==========
    if ($acao === 'atualizar_data_hora') {
        $data_relogio = trim($data['data'] ?? '');
        $hora_relogio = trim($data['hora'] ?? '');
        
        if (empty($data_relogio) || empty($hora_relogio)) {
            jsonResponse(['erro' => 'Data e hora são obrigatórias'], 400);
        }
        
        // Validar formato
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_relogio)) {
            jsonResponse(['erro' => 'Formato de data inválido'], 400);
        }
        
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora_relogio)) {
            jsonResponse(['erro' => 'Formato de hora inválido'], 400);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO configuracoes (chave, valor) 
            VALUES ('relogio_data', ?)
            ON DUPLICATE KEY UPDATE valor = ?
        ");
        $stmt->execute([$data_relogio, $data_relogio]);
        
        $stmt = $pdo->prepare("
            INSERT INTO configuracoes (chave, valor) 
            VALUES ('relogio_hora', ?)
            ON DUPLICATE KEY UPDATE valor = ?
        ");
        $stmt->execute([$hora_relogio, $hora_relogio]);
        
        jsonResponse([
            'sucesso' => true,
            'mensagem' => 'Data e hora atualizadas com sucesso'
        ]);
    }
}

jsonResponse(['erro' => 'Método não permitido'], 405);