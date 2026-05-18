<?php
require_once __DIR__ . '/valida_professor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/professor.html');
    exit;
}

$id    = (int)($_POST['id'] ?? 0);
$redir = '../home/professor.html';

if ($id <= 0) {
    header('Location: ' . $redir . '?erro=' . urlencode('ID inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT public_course_id FROM professor_courses WHERE id = ? AND professor_id = ? LIMIT 1");
$stmt->execute([$id, $professorId]);
$course = $stmt->fetch();
if (!$course) {
    header('Location: ' . $redir . '?erro=' . urlencode('Oferta de curso não encontrada.'));
    exit;
}

$conn->beginTransaction();
if (!empty($course['public_course_id'])) {
    $conn->prepare("DELETE FROM courses WHERE id = ?")->execute([(int)$course['public_course_id']]);
}
$conn->prepare("DELETE FROM professor_courses WHERE id = ? AND professor_id = ?")
     ->execute([$id, $professorId]);
$conn->commit();

header('Location: ' . $redir . '?sucesso=' . urlencode('Oferta de curso removida com sucesso.'));
exit;
