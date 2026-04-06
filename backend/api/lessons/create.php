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
$titulo    = trim($data['titulo']    ?? '');
$descricao = trim($data['descricao'] ?? '');
$nivel     = trim($data['nivel']     ?? '');

$niveisValidos = ['basico', 'intermediario', 'avancado'];

if ($titulo === '' || $descricao === '' || $nivel === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Título, descrição e nível são obrigatórios.']);
    exit;
}

if (!in_array($nivel, $niveisValidos, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nível inválido.']);
    exit;
}

$conn = getConnection();

// RETURNING id — forma idiomática de recuperar o ID no PostgreSQL
$stmt = $conn->prepare("INSERT INTO lessons (titulo, descricao, nivel) VALUES (?, ?, ?) RETURNING id");
$stmt->execute([$titulo, $descricao, $nivel]);
$newId = $stmt->fetchColumn();

echo json_encode([
    'success' => true,
    'message' => 'Aula criada com sucesso.',
    'id'      => (int)$newId,
]);
