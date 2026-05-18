<?php
require_once __DIR__ . '/valida_professor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/professor.html');
    exit;
}

$id        = (int)($_POST['id'] ?? 0);
$nome      = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$nivel     = trim($_POST['nivel'] ?? '');
$idsAulas  = array_map('intval', (array)($_POST['lesson_ids'] ?? []));
$redir     = '../home/professor.html';

if ($id <= 0 || $nome === '' || $descricao === '' || $nivel === '') {
    header('Location: ' . $redir . '?erro=' . urlencode('Dados inválidos ou incompletos.'));
    exit;
}
if (!in_array($nivel, ['basico', 'intermediario', 'avancado'], true)) {
    header('Location: ' . $redir . '?erro=' . urlencode('Nível inválido.'));
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
    $conn->prepare("UPDATE professor_courses SET public_course_id = NULL WHERE id = ?")->execute([$id]);
}

$conn->prepare("UPDATE professor_courses SET nome=?, descricao=?, nivel=?, status='pendente', review_comment=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=? AND professor_id=?")
     ->execute([$nome, $descricao, $nivel, $id, $professorId]);

$conn->prepare("DELETE FROM professor_course_lessons WHERE professor_course_id = ?")->execute([$id]);

$stmtValidLesson = $conn->prepare("SELECT 1 FROM professor_lessons WHERE id = ? AND professor_id = ? LIMIT 1");
$stmtLink = $conn->prepare("INSERT INTO professor_course_lessons (professor_course_id, professor_lesson_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
foreach ($idsAulas as $aulaId) {
    if ($aulaId > 0) {
        $stmtValidLesson->execute([$aulaId, $professorId]);
        if ($stmtValidLesson->fetch()) {
            $stmtLink->execute([$id, $aulaId]);
        }
    }
}

$conn->commit();

header('Location: ' . $redir . '?sucesso=' . urlencode('Oferta de curso atualizada com sucesso.'));
exit;
