<?php
/**
 * API de Autenticação - SmartVamp
 * Sistema de login por senha numérica ou usuário + senha
 */

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

require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    jsonResponse(['erro' => 'Método não permitido'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$acao = $data['acao'] ?? '';

try {
    $pdo = getDB();
} catch (Exception $e) {
    error_log('Erro de conexão DB: ' . $e->getMessage());
    jsonResponse(['erro' => 'Erro ao conectar com o banco de dados'], 500);
}

// ========== LOGIN ==========
if ($acao === 'login') {
    $usuario_input = trim($data['usuario'] ?? '');
    $senha_input = trim($data['senha'] ?? '');
    
    if (empty($senha_input)) {
        jsonResponse(['erro' => 'Senha é obrigatória'], 400);
    }
    
    $usuario = null;
    
    // Tentar login por usuário + senha (se campo usuario existir)
    if (!empty($usuario_input)) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, nome, COALESCE(usuario, '') as usuario, senha, saldo, COALESCE(chave_pix, '') as chave_pix, 
                       COALESCE(tema, 'escuro') as tema 
                FROM usuarios 
                WHERE usuario = ? AND senha = ? AND ativo = 1
            ");
            $stmt->execute([$usuario_input, $senha_input]);
            $usuario = $stmt->fetch();
        } catch (PDOException $e) {
            // Campo usuario pode não existir, continuar
        }
    }
    
    // Se não encontrou, tentar apenas por senha (compatibilidade)
    if (!$usuario) {
        $stmt = $pdo->prepare("
            SELECT id, nome, usuario, senha, saldo, COALESCE(chave_pix, '') as chave_pix, 
                   COALESCE(tema, 'escuro') as tema 
            FROM usuarios 
            WHERE senha = ? AND ativo = 1
        ");
        $stmt->execute([$senha_input]);
        $usuario = $stmt->fetch();
    }
    
    // Verificar hash de senha se necessário
    if (!$usuario && function_exists('password_verify')) {
        $stmt = $pdo->prepare("
            SELECT id, nome, usuario, senha, saldo, COALESCE(chave_pix, '') as chave_pix, 
                   COALESCE(tema, 'escuro') as tema 
            FROM usuarios 
            WHERE ativo = 1
        ");
        $stmt->execute();
        $todos = $stmt->fetchAll();
        
        foreach ($todos as $u) {
            if (password_verify($senha_input, $u['senha'])) {
                $usuario = $u;
                break;
            }
        }
    }
    
    if ($usuario) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $usuario_field = $usuario['usuario'] ?? '';
        $_SESSION['is_admin'] = ($usuario['nome'] === 'Admin' || $usuario_field === 'admin');
        
        // Atualizar último acesso
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
            $stmt->execute([$usuario['id']]);
        } catch (PDOException $e) {
            // Ignora se coluna não existir
        }
        
        jsonResponse([
            'sucesso' => true,
            'usuario' => [
                'id' => (int)$usuario['id'],
                'nome' => $usuario['nome'],
                'saldo' => floatval($usuario['saldo'] ?? 0),
                'chave_pix' => $usuario['chave_pix'] ?? '',
                'tema' => $usuario['tema'] ?? 'escuro',
                'is_admin' => $_SESSION['is_admin']
            ]
        ]);
    } else {
        error_log('Tentativa de login falhou. Usuário: ' . $usuario_input . ', Senha recebida: ' . substr($senha_input, 0, 2) . '***');
        jsonResponse(['erro' => 'Usuário ou senha incorretos'], 401);
    }
}

// ========== LOGOUT ==========
if ($acao === 'logout') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
    jsonResponse(['sucesso' => true]);
}

// ========== VERIFICAR AUTENTICAÇÃO ==========
if ($acao === 'verificar') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['usuario_id'])) {
        jsonResponse(['autenticado' => false], 401);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, COALESCE(usuario, '') as usuario, saldo, COALESCE(chave_pix, '') as chave_pix, 
                   COALESCE(tema, 'escuro') as tema 
            FROM usuarios 
            WHERE id = ? AND ativo = 1
        ");
        $stmt->execute([$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            $usuario_field = $usuario['usuario'] ?? '';
            $is_admin = ($usuario['nome'] === 'Admin' || $usuario_field === 'admin');
            $_SESSION['is_admin'] = $is_admin;
            
            jsonResponse([
                'autenticado' => true,
                'usuario' => [
                    'id' => (int)$usuario['id'],
                    'nome' => $usuario['nome'],
                    'saldo' => floatval($usuario['saldo']),
                    'chave_pix' => $usuario['chave_pix'] ?? '',
                    'tema' => $usuario['tema'] ?? 'escuro',
                    'is_admin' => $is_admin
                ]
            ]);
        }
    } catch (Exception $e) {
        error_log('Erro ao verificar: ' . $e->getMessage());
    }
    
    jsonResponse(['autenticado' => false], 401);
}

jsonResponse(['erro' => 'Ação não reconhecida'], 400);