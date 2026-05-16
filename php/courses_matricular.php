<?php
require_once __DIR__ . '/valida_sessao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/aluno_cursos.html');
    exit;
}
if ($_SESSION['user_tipo'] !== 'aluno') {
    header('Location: ../home/aluno_cursos.html?erro=' . urlencode('Apenas alunos podem se matricular.'));
    exit;
}

$cursoId   = (int)($_POST['course_id'] ?? 0);
$acao      = trim($_POST['action']     ?? '');
$idUsuario = (int)$_SESSION['user_id'];
$redir     = '../home/aluno_cursos.html';

if ($cursoId <= 0 || !in_array($acao, ['enroll', 'unenroll'], true)) {
    header('Location: ' . $redir . '?erro=' . urlencode('Dados inválidos.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? LIMIT 1");
$stmt->execute([$cursoId]);
if (!$stmt->fetch()) {
    header('Location: ' . $redir . '?erro=' . urlencode('Curso não encontrado.'));
    exit;
}

if ($acao === 'enroll') {
    $conn->prepare("INSERT INTO course_enrollments (user_id, course_id) VALUES (?, ?) ON CONFLICT DO NOTHING")
         ->execute([$idUsuario, $cursoId]);
    header('Location: ' . $redir . '?sucesso=' . urlencode('Matrícula realizada com sucesso!'));
} else {
    $conn->prepare("DELETE FROM course_enrollments WHERE user_id = ? AND course_id = ?")
         ->execute([$idUsuario, $cursoId]);
    header('Location: ' . $redir . '?sucesso=' . urlencode('Matrícula cancelada.'));
}
exit;
