<?php
require_once '../config.php';

$usuario_id = verificarAuth();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $tema = $data['tema'] ?? 'escuro';
    
    if (!in_array($tema, ['claro', 'escuro'])) {
        jsonResponse(['erro' => 'Tema inválido'], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE usuarios SET tema = ? WHERE id = ?");
    $stmt->execute([$tema, $usuario_id]);
    
    jsonResponse(['sucesso' => true, 'tema' => $tema]);
}

jsonResponse(['erro' => 'Método não permitido'], 405);
