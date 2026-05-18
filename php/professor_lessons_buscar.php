<?php
require_once __DIR__ . '/valida_professor.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id, titulo, descricao, nivel, video_link, attachment_path, attachment_name, status, review_comment FROM professor_lessons WHERE id = ? AND professor_id = ? LIMIT 1");
$stmt->execute([$id, $professorId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    echo json_encode(['success' => false, 'message' => 'Oferta de aula não encontrada.']);
    exit;
}

echo json_encode(['success' => true, 'lesson' => $lesson]);
