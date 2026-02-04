<?php
require_once '../config.php';

$usuario_id = verificarAuth();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

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

jsonResponse(['erro' => 'Método não permitido'], 405);
