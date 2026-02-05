<?php
// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
// Permitir CORS se necessário
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder a requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Carregar config
$config_path = __DIR__ . '/../config.php';
if (!file_exists($config_path)) {
    http_response_code(500);
    echo json_encode(['erro' => 'Arquivo config.php não encontrado em: ' . $config_path]);
    exit;
}

require_once $config_path;

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
        
        // Buscar usuário pela senha (simplificado)
        $usuario = null;
        
        try {
            // Primeiro tenta buscar direto pela senha
            $stmt = $pdo->prepare("SELECT id, nome, senha, saldo, COALESCE(tema, 'escuro') as tema FROM usuarios WHERE senha = ?");
            $stmt->execute([$senha]);
            $usuario = $stmt->fetch();
            
            // Se não encontrou, busca todos e compara
            if (!$usuario) {
                $stmt = $pdo->prepare("SELECT id, nome, senha, saldo, COALESCE(tema, 'escuro') as tema FROM usuarios");
                $stmt->execute();
                $todos_usuarios = $stmt->fetchAll();
                
                foreach ($todos_usuarios as $u) {
                    // Compara senha direta ou hash
                    if ($senha === $u['senha'] || (function_exists('password_verify') && password_verify($senha, $u['senha']))) {
                        $usuario = $u;
                        break;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Erro SQL: ' . $e->getMessage());
            error_log('SQL State: ' . $e->getCode());
            jsonResponse(['erro' => 'Erro ao buscar usuário: ' . $e->getMessage()], 500);
        }
        
        if ($usuario) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['is_admin'] = ($usuario['nome'] === 'Admin');
            
            // Atualizar último acesso (tenta, mas não falha se a coluna não existir)
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
            // Log para debug
            error_log('Tentativa de login falhou. Senha recebida: ' . $senha);
            jsonResponse(['erro' => 'Senha incorreta'], 401);
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
