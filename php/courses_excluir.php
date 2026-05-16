<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_cursos.html');
    exit;
}

$id    = (int)($_POST['id'] ?? 0);
$redir = '../home/admin_cursos.html';

if ($id <= 0) {
    header('Location: ' . $redir . '?erro=' . urlencode('ID inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    header('Location: ' . $redir . '?erro=' . urlencode('Curso não encontrado.'));
    exit;
}

$conn->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);

header('Location: ' . $redir . '?sucesso=' . urlencode('Curso excluído com sucesso.'));
exit;
