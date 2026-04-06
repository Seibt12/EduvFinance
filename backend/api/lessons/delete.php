<?php
// Remove uma aula. ON DELETE CASCADE limpa os vínculos com cursos e o progresso dos alunos.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$dados = getJsonBody();
$id    = isset($dados['id']) ? (int)$dados['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

$conn = getConnection();

$stmt = $conn->prepare("SELECT id FROM lessons WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aula não encontrada.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM lessons WHERE id = ?");
$stmt->execute([$id]);

echo json_encode(['success' => true, 'message' => 'Aula excluída com sucesso.']);
