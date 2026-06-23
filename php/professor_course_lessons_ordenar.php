<?php
require_once __DIR__ . '/valida_professor.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

// Expects: course_id and ordered_ids (JSON array of lesson IDs in new order)
$courseId   = (int)($_POST['course_id'] ?? 0);
$orderedIds = json_decode($_POST['ordered_ids'] ?? '[]', true);

if (!$courseId || !is_array($orderedIds) || empty($orderedIds)) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']); exit;
}

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

// Verify course ownership
$stmt = $conn->prepare("SELECT id, status, public_course_id FROM professor_courses WHERE id = ? AND professor_id = ?");
$stmt->execute([$courseId, $professorId]);
$course = $stmt->fetch();

if (!$course) {
    echo json_encode(['success' => false, 'error' => 'Curso não encontrado']); exit;
}
if ($course['status'] === 'pendente') {
    echo json_encode(['success' => false, 'error' => 'Não é possível reordenar aulas de cursos em análise']); exit;
}

$conn->beginTransaction();
$stmt = $conn->prepare("
    UPDATE professor_lessons SET order_index=? WHERE id=? AND professor_course_id=? AND professor_id=?
");
foreach ($orderedIds as $index => $lessonId) {
    $stmt->execute([(int)$index + 1, (int)$lessonId, $courseId, $professorId]);
}
$conn->commit();

// If course is published, sync new order to public course_lessons immediately
if ($course['status'] === 'aprovado' && !empty($course['public_course_id'])) {
    require_once __DIR__ . '/aprovacao_utils.php';
    syncAllCourseLessons($conn, $courseId, (int)$course['public_course_id']);
}

echo json_encode(['success' => true]);
