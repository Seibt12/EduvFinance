<?php
require_once __DIR__ . '/valida_professor.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'error' => 'ID inválido']); exit; }

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM professor_courses WHERE id = ? AND professor_id = ?");
$stmt->execute([$id, $professorId]);
$course = $stmt->fetch();

if (!$course) {
    echo json_encode(['success' => false, 'error' => 'Curso não encontrado']); exit;
}
if (!in_array($course['status'], ['draft', 'rejeitado'], true)) {
    echo json_encode(['success' => false, 'error' => 'Apenas rascunhos ou cursos rejeitados podem ser submetidos para revisão']); exit;
}

// Validate required fields
$errors = [];
if (empty(trim($course['nome'] ?? '')))      $errors[] = 'Título obrigatório';
if (empty(trim($course['descricao'] ?? ''))) $errors[] = 'Descrição obrigatória';
if (empty(trim($course['nivel'] ?? '')))     $errors[] = 'Nível obrigatório';

// Require at least 1 lesson
$stmt2 = $conn->prepare("SELECT COUNT(*) FROM professor_lessons WHERE professor_course_id = ?");
$stmt2->execute([$id]);
$lessonCount = (int)$stmt2->fetchColumn();
if ($lessonCount === 0) $errors[] = 'O curso precisa ter pelo menos 1 aula antes de ser enviado para revisão';

if ($errors) {
    echo json_encode(['success' => false, 'error' => implode('. ', $errors)]);
    exit;
}

$conn->beginTransaction();
$conn->prepare("
    UPDATE professor_courses
    SET status='pendente', review_comment=NULL, updated_at=CURRENT_TIMESTAMP
    WHERE id=?
")->execute([$id]);
$conn->prepare("
    UPDATE professor_lessons
    SET status='pendente', updated_at=CURRENT_TIMESTAMP
    WHERE professor_course_id=?
")->execute([$id]);
$conn->commit();

echo json_encode(['success' => true, 'message' => 'Curso enviado para revisão com sucesso! Você será notificado quando o administrador analisar.']);
