<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_cursos.html');
    exit;
}

$nome      = trim($_POST['nome']      ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$nivel     = trim($_POST['nivel']     ?? '');
$idsAulas  = array_map('intval', (array)($_POST['lesson_ids'] ?? []));
$redir     = '../home/admin_cursos.html';

if (!$nome || !$descricao || !$nivel) {
    header('Location: ' . $redir . '?erro=' . urlencode('Preencha todos os campos obrigatórios.'));
    exit;
}
if (!in_array($nivel, ['basico', 'intermediario', 'avancado'], true)) {
    header('Location: ' . $redir . '?erro=' . urlencode('Nível inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$conn->beginTransaction();
$stmt = $conn->prepare("INSERT INTO courses (nome, descricao, nivel) VALUES (?, ?, ?) RETURNING id");
$stmt->execute([$nome, $descricao, $nivel]);
$cursoId = (int)$stmt->fetchColumn();

if (!empty($idsAulas)) {
    $stmtVinculo = $conn->prepare("INSERT INTO course_lessons (course_id, lesson_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
    foreach ($idsAulas as $aulaId) {
        if ($aulaId > 0) $stmtVinculo->execute([$cursoId, $aulaId]);
    }
}
$conn->commit();

header('Location: ' . $redir . '?sucesso=' . urlencode('Curso criado com sucesso.'));
exit;
