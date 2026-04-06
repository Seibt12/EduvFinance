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

$data      = getJsonBody();
$id        = isset($data['id']) ? (int)$data['id'] : 0;
$titulo    = trim($data['titulo']    ?? '');
$descricao = trim($data['descricao'] ?? '');
$nivel     = trim($data['nivel']     ?? '');

$niveisValidos = ['basico', 'intermediario', 'avancado'];

if ($id <= 0 || $titulo === '' || $descricao === '' || $nivel === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
    exit;
}

if (!in_array($nivel, $niveisValidos, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nível inválido.']);
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

$stmt = $conn->prepare("UPDATE lessons SET titulo = ?, descricao = ?, nivel = ? WHERE id = ?");
$stmt->execute([$titulo, $descricao, $nivel, $id]);

echo json_encode(['success' => true, 'message' => 'Aula atualizada com sucesso.']);
