<?php
require_once __DIR__ . '/valida_admin.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']); exit;
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/aprovacao_utils.php';

$stmt = $conn->prepare("SELECT * FROM professor_courses WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$oferta = $stmt->fetch();

if (!$oferta) {
    echo json_encode(['success' => false, 'error' => 'Oferta de curso não encontrada']); exit;
}
if ($oferta['status'] !== 'pendente') {
    echo json_encode(['success' => false, 'error' => 'Apenas cursos com status pendente podem ser aprovados']); exit;
}

// Check that course has at least one lesson
$stmt2 = $conn->prepare("SELECT COUNT(*) FROM professor_lessons WHERE professor_course_id = ?");
$stmt2->execute([$id]);
$lessonCount = (int)$stmt2->fetchColumn();
if ($lessonCount === 0) {
    echo json_encode(['success' => false, 'error' => 'O curso não possui aulas. Solicite ao professor que adicione aulas antes de aprovar.']); exit;
}

$conn->beginTransaction();

// Sync course to public courses
$publicCourseId = syncPublicCourse($conn, $oferta);

// Sync all lessons to public lessons and link them (new schema)
syncAllCourseLessons($conn, $id, $publicCourseId);

// Update professor_course status
$conn->prepare("
    UPDATE professor_courses
    SET status='aprovado', public_course_id=?, review_comment=NULL, updated_at=CURRENT_TIMESTAMP
    WHERE id=?
")->execute([$publicCourseId, $id]);

$conn->commit();

echo json_encode(['success' => true, 'message' => 'Curso aprovado e publicado com sucesso!']);
