<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_aulas.html');
    exit;
}

$id    = (int)($_POST['id'] ?? 0);
$redir = '../home/admin_aulas.html';

if ($id <= 0) {
    header('Location: ' . $redir . '?erro=' . urlencode('ID inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id FROM lessons WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    header('Location: ' . $redir . '?erro=' . urlencode('Aula não encontrada.'));
    exit;
}

$conn->prepare("DELETE FROM lessons WHERE id = ?")->execute([$id]);

header('Location: ' . $redir . '?sucesso=' . urlencode('Aula excluída com sucesso.'));
exit;
