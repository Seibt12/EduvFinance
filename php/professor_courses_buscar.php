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

$stmt = $conn->prepare("SELECT id, nome, descricao, nivel, status, review_comment FROM professor_courses WHERE id = ? AND professor_id = ? LIMIT 1");
$stmt->execute([$id, $professorId]);
$course = $stmt->fetch();

if (!$course) {
    echo json_encode(['success' => false, 'message' => 'Oferta de curso não encontrada.']);
    exit;
}

$stmtLessons = $conn->prepare("SELECT professor_lesson_id FROM professor_course_lessons WHERE professor_course_id = ?");
$stmtLessons->execute([$id]);
$course['lesson_ids'] = array_column($stmtLessons->fetchAll(), 'professor_lesson_id');

echo json_encode(['success' => true, 'course' => $course]);
