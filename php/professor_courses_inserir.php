<?php
require_once __DIR__ . '/valida_professor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/professor.html');
    exit;
}

$nome      = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$nivel     = trim($_POST['nivel'] ?? '');
$idsAulas  = array_map('intval', (array)($_POST['lesson_ids'] ?? []));
$redir     = '../home/professor.html';

if ($nome === '' || $descricao === '' || $nivel === '') {
    header('Location: ' . $redir . '?erro=' . urlencode('Preencha todos os campos obrigatórios.'));
    exit;
}
if (!in_array($nivel, ['basico', 'intermediario', 'avancado'], true)) {
    header('Location: ' . $redir . '?erro=' . urlencode('Nível inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

$conn->beginTransaction();
$stmt = $conn->prepare("INSERT INTO professor_courses (professor_id, nome, descricao, nivel) VALUES (?, ?, ?, ?) RETURNING id");
$stmt->execute([$professorId, $nome, $descricao, $nivel]);
$courseId = (int)$stmt->fetchColumn();

$stmtValidLesson = $conn->prepare("SELECT 1 FROM professor_lessons WHERE id = ? AND professor_id = ? LIMIT 1");
$stmtLink = $conn->prepare("INSERT INTO professor_course_lessons (professor_course_id, professor_lesson_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
foreach ($idsAulas as $aulaId) {
    if ($aulaId > 0) {
        $stmtValidLesson->execute([$aulaId, $professorId]);
        if ($stmtValidLesson->fetch()) {
            $stmtLink->execute([$courseId, $aulaId]);
        }
    }
}
$conn->commit();

header('Location: ' . $redir . '?sucesso=' . urlencode('Oferta de curso criada com sucesso.'));
exit;
