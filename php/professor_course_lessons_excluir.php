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
    SELECT pl.id, pc.status AS course_status
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
if ($lesson['course_status'] === 'aprovado') {
    echo json_encode(['success' => false, 'error' => 'Não é possível excluir aulas de cursos aprovados']); exit;
}

$conn->prepare("DELETE FROM professor_lessons WHERE id = ?")->execute([$lessonId]);

echo json_encode(['success' => true]);
