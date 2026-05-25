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

$stmt = $conn->prepare("SELECT id, status FROM professor_courses WHERE id = ? AND professor_id = ?");
$stmt->execute([$id, $professorId]);
$course = $stmt->fetch();

if (!$course) {
    echo json_encode(['success' => false, 'error' => 'Curso não encontrado']); exit;
}
if ($course['status'] === 'pendente') {
    echo json_encode(['success' => false, 'error' => 'Não é possível excluir cursos em análise. Aguarde o resultado da revisão.']); exit;
}
if ($course['status'] === 'aprovado') {
    echo json_encode(['success' => false, 'error' => 'Cursos aprovados e publicados não podem ser excluídos.']); exit;
}

// CASCADE will remove associated professor_lessons
$conn->prepare("DELETE FROM professor_courses WHERE id = ?")->execute([$id]);

echo json_encode(['success' => true]);
