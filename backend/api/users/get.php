<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SESSION['user_tipo'] !== 'admin' && $id !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

$conn = getConnection();
$stmt = $conn->prepare("SELECT id, nome, email, tipo, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
    exit;
}

echo json_encode([
    'success' => true,
    'user'    => [
        'id'         => (int)$user['id'],
        'nome'       => $user['nome'],
        'email'      => $user['email'],
        'tipo'       => $user['tipo'],
        'created_at' => $user['created_at'],
    ],
]);
