<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_cursos.html');
    exit;
}

$id        = (int)($_POST['id']        ?? 0);
$nome      = trim($_POST['nome']       ?? '');
$descricao = trim($_POST['descricao']  ?? '');
$nivel     = trim($_POST['nivel']      ?? '');
$idsAulas  = array_map('intval', (array)($_POST['lesson_ids'] ?? []));
$redir     = '../home/admin_cursos.html';

if ($id <= 0 || !$nome || !$descricao || !$nivel) {
    header('Location: ' . $redir . '?erro=' . urlencode('Dados inválidos ou incompletos.'));
    exit;
}
if (!in_array($nivel, ['basico', 'intermediario', 'avancado'], true)) {
    header('Location: ' . $redir . '?erro=' . urlencode('Nível inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    header('Location: ' . $redir . '?erro=' . urlencode('Curso não encontrado.'));
    exit;
}

$conn->beginTransaction();
$conn->prepare("UPDATE courses SET nome=?, descricao=?, nivel=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
     ->execute([$nome, $descricao, $nivel, $id]);
$conn->prepare("DELETE FROM course_lessons WHERE course_id=?")->execute([$id]);

if (!empty($idsAulas)) {
    $stmtVinculo = $conn->prepare("INSERT INTO course_lessons (course_id, lesson_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
    foreach ($idsAulas as $aulaId) {
        if ($aulaId > 0) $stmtVinculo->execute([$id, $aulaId]);
    }
}
$conn->commit();

header('Location: ' . $redir . '?sucesso=' . urlencode('Curso atualizado com sucesso.'));
exit;
