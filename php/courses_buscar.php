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

$stmt = $conn->prepare("SELECT id, nome, descricao, nivel FROM courses WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$course = $stmt->fetch();

if (!$course) {
    echo json_encode(['success' => false, 'message' => 'Curso não encontrado.']);
    exit;
}

$stmtAulas = $conn->prepare("SELECT lesson_id FROM course_lessons WHERE course_id = ?");
$stmtAulas->execute([$id]);
$course['lesson_ids'] = array_column($stmtAulas->fetchAll(), 'lesson_id');

echo json_encode(['success' => true, 'course' => $course]);
