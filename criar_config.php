<?php
/**
 * Script para criar o arquivo config.php no servidor
 * Acesse este arquivo uma vez via navegador para criar o config.php
 * Depois delete este arquivo por segurança
 */

$config_content = '<?php
// Configurações do SmartVamp
// Ajuste estas configurações conforme sua hospedagem HostGator

// Configurações do banco de dados
define(\'DB_HOST\', \'localhost\');
define(\'DB_NAME\', \'nico2190_vampiro\');
define(\'DB_USER\', \'nico2190_admin\');
define(\'DB_PASS\', \'+FWh}!R,tVt~\');

// Configurações gerais
define(\'TIMEZONE\', \'America/Sao_Paulo\');
define(\'UPLOAD_DIR\', \'uploads/\');
define(\'MAX_UPLOAD_SIZE\', 5242880); // 5MB

// Configurar timezone
date_default_timezone_set(TIMEZONE);

// Conexão com banco de dados
function getDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        header(\'Content-Type: application/json; charset=utf-8\');
        // Em produção, não mostrar detalhes do erro por segurança
        // Em desenvolvimento, pode mostrar: $e->getMessage()
        echo json_encode([\'erro\' => \'Erro ao conectar com o banco de dados. Verifique as configurações.\']);
        error_log(\'Erro de conexão DB: \' . $e->getMessage());
        exit;
    }
}

// Função para retornar JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header(\'Content-Type: application/json; charset=utf-8\');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para validar autenticação
function verificarAuth() {
    session_start();
    if (!isset($_SESSION[\'usuario_id\'])) {
        jsonResponse([\'erro\' => \'Não autenticado\'], 401);
    }
    return $_SESSION[\'usuario_id\'];
}

// Função para verificar se é admin
function verificarAdmin() {
    session_start();
    if (!isset($_SESSION[\'usuario_id\']) || !isset($_SESSION[\'is_admin\']) || !$_SESSION[\'is_admin\']) {
        jsonResponse([\'erro\' => \'Acesso negado\'], 403);
    }
    return $_SESSION[\'usuario_id\'];
}

// Criar diretório de uploads se não existir
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
    mkdir(UPLOAD_DIR . \'avatars/\', 0755, true);
    mkdir(UPLOAD_DIR . \'midia/\', 0755, true);
    mkdir(UPLOAD_DIR . \'noticias/\', 0755, true);
    mkdir(UPLOAD_DIR . \'inventario/\', 0755, true);
}
?>
';

$config_path = __DIR__ . '/config.php';

if (file_exists($config_path)) {
    die('O arquivo config.php já existe!');
}

if (file_put_contents($config_path, $config_content)) {
    echo '<h1>✅ Arquivo config.php criado com sucesso!</h1>';
    echo '<p>O arquivo foi criado em: ' . $config_path . '</p>';
    echo '<p><strong>IMPORTANTE:</strong> Delete este arquivo (criar_config.php) por segurança agora!</p>';
    echo '<p><a href="index.html">Ir para o login</a></p>';
} else {
    die('❌ Erro ao criar o arquivo config.php. Verifique as permissões da pasta.');
}
?>
