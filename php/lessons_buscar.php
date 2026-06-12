<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id, titulo, descricao, nivel FROM lessons WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    echo json_encode(['success' => false, 'message' => 'Aula não encontrada.']);
    exit;
}

echo json_encode(['success' => true, 'lesson' => $lesson]);
