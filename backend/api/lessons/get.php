<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

$conn = getConnection();
$stmt = $conn->prepare("SELECT id, titulo, descricao, nivel, created_at FROM lessons WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aula não encontrada.']);
    exit;
}

echo json_encode([
    'success' => true,
    'lesson'  => [
        'id'         => (int)$lesson['id'],
        'titulo'     => $lesson['titulo'],
        'descricao'  => $lesson['descricao'],
        'nivel'      => $lesson['nivel'],
        'created_at' => $lesson['created_at'],
    ],
]);
