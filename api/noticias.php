<?php
require_once '../config.php';

$usuario_id = verificarAuth();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT * FROM noticias 
        WHERE ativo = 1 
        ORDER BY criado_em DESC 
        LIMIT 50
    ");
    $stmt->execute();
    $noticias = $stmt->fetchAll();
    
    jsonResponse(['noticias' => $noticias]);
}

jsonResponse(['erro' => 'Método não permitido'], 405);
