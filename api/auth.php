<?php
// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros na tela, apenas no log

try {
    require_once '../config.php';
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Erro ao carregar configuração: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    if ($acao === 'login') {
        $senha = $data['senha'] ?? '';
        
        if (empty($senha)) {
            jsonResponse(['erro' => 'Senha é obrigatória'], 400);
        }
        
        try {
            $pdo = getDB();
        } catch (Exception $e) {
            error_log('Erro getDB: ' . $e->getMessage());
            jsonResponse(['erro' => 'Erro ao conectar com o banco de dados'], 500);
        }
        
        try {
            // Buscar usuário apenas pela senha (senha numérica como identificador único)
            // Tenta com 'ativo' primeiro, se falhar tenta sem
            try {
                $stmt = $pdo->prepare("SELECT id, nome, senha, saldo, tema FROM usuarios WHERE senha = ? AND ativo = 1");
                $stmt->execute([$senha]);
            } catch (PDOException $e) {
                // Se falhar, tenta sem a coluna 'ativo'
                $stmt = $pdo->prepare("SELECT id, nome, senha, saldo, tema FROM usuarios WHERE senha = ?");
                $stmt->execute([$senha]);
            }
            $usuario = $stmt->fetch();
            
            // Se não encontrou com senha direta, tentar verificar hash
            if (!$usuario) {
                try {
                    $stmt = $pdo->prepare("SELECT id, nome, senha, saldo, tema FROM usuarios WHERE ativo = 1");
                    $stmt->execute();
                } catch (PDOException $e) {
                    $stmt = $pdo->prepare("SELECT id, nome, senha, saldo, tema FROM usuarios");
                    $stmt->execute();
                }
                $todos_usuarios = $stmt->fetchAll();
                
                foreach ($todos_usuarios as $u) {
                    if (password_verify($senha, $u['senha']) || $senha === $u['senha']) {
                        $usuario = $u;
                        break;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Erro SQL: ' . $e->getMessage());
            jsonResponse(['erro' => 'Erro ao buscar usuário no banco de dados'], 500);
        }
        
        if ($usuario) {
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
            jsonResponse(['erro' => 'Senha incorreta'], 401);
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
