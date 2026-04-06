<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = getJsonBody();
$id   = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

$conn = getConnection();

$stmt = $conn->prepare("SELECT id, email, tipo FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
    exit;
}

if ($user['tipo'] === 'admin' && $user['email'] === 'admin@email.com') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'O administrador padrão não pode ser excluído.']);
    exit;
}

if ($id === (int)$_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Você não pode excluir a própria conta.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$id]);

echo json_encode(['success' => true, 'message' => 'Usuário excluído com sucesso.']);
