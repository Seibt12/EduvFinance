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

$stmt = $conn->prepare("SELECT public_lesson_id, attachment_path FROM professor_lessons WHERE id = ? AND professor_id = ? LIMIT 1");
$stmt->execute([$id, $professorId]);
$lesson = $stmt->fetch();
if (!$lesson) {
    header('Location: ' . $redir . '?erro=' . urlencode('Oferta de aula não encontrada.'));
    exit;
}

$conn->beginTransaction();
if (!empty($lesson['public_lesson_id'])) {
    $conn->prepare("DELETE FROM lessons WHERE id = ?")->execute([(int)$lesson['public_lesson_id']]);
}
$conn->prepare("DELETE FROM professor_lessons WHERE id = ? AND professor_id = ?")
     ->execute([$id, $professorId]);
$conn->commit();

if (!empty($lesson['attachment_path'])) {
    $filePath = __DIR__ . '/../' . $lesson['attachment_path'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

header('Location: ' . $redir . '?sucesso=' . urlencode('Oferta de aula removida com sucesso.'));
exit;
