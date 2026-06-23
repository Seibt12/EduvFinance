<?php
require_once __DIR__ . '/valida_professor.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

$lessonId = (int)($_POST['id'] ?? 0);
if (!$lessonId) { echo json_encode(['success' => false, 'error' => 'ID inválido']); exit; }

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT pl.id, pl.public_lesson_id, pc.status AS course_status, pc.public_course_id
    FROM professor_lessons pl
    JOIN professor_courses pc ON pc.id = pl.professor_course_id
    WHERE pl.id = ? AND pl.professor_id = ?
");
$stmt->execute([$lessonId, $professorId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    echo json_encode(['success' => false, 'error' => 'Aula não encontrada']); exit;
}
if ($lesson['course_status'] === 'pendente') {
    echo json_encode(['success' => false, 'error' => 'Não é possível excluir aulas de cursos em análise']); exit;
}

// If course is published, remove lesson from public tables first
if ($lesson['course_status'] === 'aprovado' && !empty($lesson['public_lesson_id'])) {
    require_once __DIR__ . '/aprovacao_utils.php';
    if (!empty($lesson['public_course_id'])) {
        $conn->prepare("DELETE FROM course_lessons WHERE lesson_id = ? AND course_id = ?")->execute([(int)$lesson['public_lesson_id'], (int)$lesson['public_course_id']]);
    }
    removePublicLesson($conn, (int)$lesson['public_lesson_id']);
}

$conn->prepare("DELETE FROM professor_lessons WHERE id = ?")->execute([$lessonId]);

echo json_encode(['success' => true]);
