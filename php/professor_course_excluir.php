<?php
require_once __DIR__ . '/valida_professor.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'error' => 'ID inválido']); exit; }

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/aprovacao_utils.php';
$professorId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id, status, public_course_id FROM professor_courses WHERE id = ? AND professor_id = ?");
$stmt->execute([$id, $professorId]);
$course = $stmt->fetch();

if (!$course) {
    echo json_encode(['success' => false, 'error' => 'Curso não encontrado']); exit;
}
if ($course['status'] === 'pendente') {
    echo json_encode(['success' => false, 'error' => 'Não é possível excluir cursos em análise. Aguarde o resultado da revisão.']); exit;
}

try {
    $conn->beginTransaction();

    // If the course was ever published, purge all public artifacts so it
    // disappears completely for students:
    //   - public lessons → cascades course_lessons + progress (student progress)
    //   - public course  → cascades course_enrollments (enrollments)
    //                      + course_reviews (ratings) + course_lessons
    if (!empty($course['public_course_id'])) {
        removePublicCourseLessons($conn, $id);
        removePublicCourse($conn, (int)$course['public_course_id']);
    }

    // Delete the professor course → cascades professor_lessons + professor_course_lessons
    $conn->prepare("DELETE FROM professor_courses WHERE id = ?")->execute([$id]);

    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    echo json_encode(['success' => false, 'error' => 'Erro ao excluir o curso. Tente novamente.']); exit;
}

echo json_encode(['success' => true]);
