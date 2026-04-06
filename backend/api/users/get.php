<?php
// Retorna os dados de um único usuário.
// Admin pode ver qualquer usuário; aluno só pode ver a si mesmo.

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
$usuario = $stmt->fetch();

if (!$usuario) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
    exit;
}

echo json_encode([
    'success' => true,
    'user'    => [
        'id'         => (int)$usuario['id'],
        'nome'       => $usuario['nome'],
        'email'      => $usuario['email'],
        'tipo'       => $usuario['tipo'],
        'created_at' => $usuario['created_at'],
    ],
]);
