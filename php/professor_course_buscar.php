<?php
require_once __DIR__ . '/valida_professor.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'error' => 'ID inválido']); exit; }

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM professor_courses WHERE id = ? AND professor_id = ?");
$stmt->execute([$id, $professorId]);
$course = $stmt->fetch();

if (!$course) {
    echo json_encode(['success' => false, 'error' => 'Curso não encontrado']);
    exit;
}

$stmt = $conn->prepare("
    SELECT * FROM professor_lessons
    WHERE professor_course_id = ?
    ORDER BY order_index ASC, id ASC
");
$stmt->execute([$id]);
$lessons = $stmt->fetchAll();

$course['lessons'] = $lessons;
echo json_encode(['success' => true, 'course' => $course]);
