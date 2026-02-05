<?php
// Sistema de autenticação simplificado e funcional
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Carregar config
$config_path = __DIR__ . '/../config.php';
if (!file_exists($config_path)) {
    http_response_code(500);
    echo json_encode(['erro' => 'Arquivo config.php não encontrado']);
    exit;
}

require_once $config_path;

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $acao = $data['acao'] ?? '';
    
    if ($acao === 'login') {
        $usuario_input = trim($data['usuario'] ?? '');
        $senha_input = trim($data['senha'] ?? '');
        
        if (empty($usuario_input) && empty($senha_input)) {
            // Se não enviou usuário nem senha, tenta apenas com senha (compatibilidade)
            $senha_input = trim($data['senha'] ?? '');
            if (empty($senha_input)) {
                jsonResponse(['erro' => 'Usuário e senha são obrigatórios'], 400);
            }
        }
        
        try {
            $pdo = getDB();
        } catch (Exception $e) {
            error_log('Erro getDB: ' . $e->getMessage());
            jsonResponse(['erro' => 'Erro ao conectar com o banco de dados'], 500);
        }
        
        $usuario = null;
        
        try {
            // Primeiro tenta buscar por usuário e senha
            if (!empty($usuario_input)) {
                $stmt = $pdo->prepare("SELECT id, nome, usuario, senha, saldo, COALESCE(tema, 'escuro') as tema FROM usuarios WHERE usuario = ? AND senha = ?");
                $stmt->execute([$usuario_input, $senha_input]);
                $usuario = $stmt->fetch();
            }
            
            // Se não encontrou, tenta apenas por senha (compatibilidade com sistema antigo)
            if (!$usuario) {
                $stmt = $pdo->prepare("SELECT id, nome, usuario, senha, saldo, COALESCE(tema, 'escuro') as tema FROM usuarios WHERE senha = ?");
                $stmt->execute([$senha_input]);
                $usuario = $stmt->fetch();
            }
            
            // Se ainda não encontrou, busca todos e compara
            if (!$usuario) {
                $stmt = $pdo->prepare("SELECT id, nome, usuario, senha, saldo, COALESCE(tema, 'escuro') as tema FROM usuarios");
                $stmt->execute();
                $todos_usuarios = $stmt->fetchAll();
                
                foreach ($todos_usuarios as $u) {
                    // Compara senha direta
                    if ($senha_input === $u['senha']) {
                        $usuario = $u;
                        break;
                    }
                    // Ou compara hash se existir
                    if (function_exists('password_verify') && password_verify($senha_input, $u['senha'])) {
                        $usuario = $u;
                        break;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Erro SQL: ' . $e->getMessage());
            jsonResponse(['erro' => 'Erro ao buscar usuário: ' . $e->getMessage()], 500);
        }
        
        if ($usuario) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['is_admin'] = ($usuario['nome'] === 'Admin' || ($usuario['usuario'] ?? '') === 'admin');
            
            // Atualizar último acesso
            try {
                $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                $stmt->execute([$usuario['id']]);
            } catch (PDOException $e) {
                // Ignora erro se coluna não existir
            }
            
            jsonResponse([
                'sucesso' => true,
                'usuario' => [
                    'id' => $usuario['id'],
                    'nome' => $usuario['nome'],
                    'saldo' => floatval($usuario['saldo'] ?? 0),
                    'tema' => $usuario['tema'] ?? 'escuro',
                    'is_admin' => $_SESSION['is_admin']
                ]
            ]);
        } else {
            error_log('Tentativa de login falhou. Usuário: ' . $usuario_input . ', Senha recebida: ' . $senha_input);
            jsonResponse(['erro' => 'Usuário ou senha incorretos'], 401);
        }
    }
    
    if ($acao === 'logout') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        jsonResponse(['sucesso' => true]);
    }
    
    if ($acao === 'verificar') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['usuario_id'])) {
            try {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT id, nome, saldo, COALESCE(tema, 'escuro') as tema FROM usuarios WHERE id = ?");
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
            } catch (Exception $e) {
                error_log('Erro ao verificar: ' . $e->getMessage());
            }
        }
        jsonResponse(['autenticado' => false], 401);
    }
}

jsonResponse(['erro' => 'Método não permitido'], 405);
