<?php
// Arquivo de teste para verificar conexão com banco
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../config.php';
    echo json_encode(['status' => 'Config carregado com sucesso'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['erro' => 'Erro ao carregar config: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDB();
    echo json_encode(['status' => 'Conexão com banco OK'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['erro' => 'Erro ao conectar: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    $tabela = $stmt->fetch();
    if ($tabela) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
        $count = $stmt->fetch();
        echo json_encode([
            'status' => 'Tabela usuarios existe',
            'total_usuarios' => $count['total']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['erro' => 'Tabela usuarios não existe'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['erro' => 'Erro ao verificar tabela: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
