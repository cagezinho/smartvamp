<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    if ($acao === 'login') {
        $nome = $data['nome'] ?? '';
        $senha = $data['senha'] ?? '';
        
        if (empty($nome) || empty($senha)) {
            jsonResponse(['erro' => 'Nome e senha são obrigatórios'], 400);
        }
        
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, nome, senha, saldo, tema FROM usuarios WHERE nome = ? AND ativo = 1");
        $stmt->execute([$nome]);
        $usuario = $stmt->fetch();
        
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            session_start();
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['is_admin'] = ($usuario['nome'] === 'Admin');
            
            // Atualizar último acesso
            $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
            $stmt->execute([$usuario['id']]);
            
            jsonResponse([
                'sucesso' => true,
                'usuario' => [
                    'id' => $usuario['id'],
                    'nome' => $usuario['nome'],
                    'saldo' => floatval($usuario['saldo']),
                    'tema' => $usuario['tema'],
                    'is_admin' => $_SESSION['is_admin']
                ]
            ]);
        } else {
            jsonResponse(['erro' => 'Nome ou senha incorretos'], 401);
        }
    }
    
    if ($acao === 'logout') {
        session_start();
        session_destroy();
        jsonResponse(['sucesso' => true]);
    }
    
    if ($acao === 'verificar') {
        session_start();
        if (isset($_SESSION['usuario_id'])) {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT id, nome, saldo, tema FROM usuarios WHERE id = ?");
            $stmt->execute([$_SESSION['usuario_id']]);
            $usuario = $stmt->fetch();
            
            if ($usuario) {
                jsonResponse([
                    'autenticado' => true,
                    'usuario' => [
                        'id' => $usuario['id'],
                        'nome' => $usuario['nome'],
                        'saldo' => floatval($usuario['saldo']),
                        'tema' => $usuario['tema'],
                        'is_admin' => $_SESSION['is_admin'] ?? false
                    ]
                ]);
            }
        }
        jsonResponse(['autenticado' => false], 401);
    }
}

jsonResponse(['erro' => 'Método não permitido'], 405);
