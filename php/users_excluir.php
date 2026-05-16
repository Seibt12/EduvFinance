<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_alunos.html');
    exit;
}

$id    = (int)($_POST['id'] ?? 0);
$redir = '../home/admin_alunos.html';

if ($id <= 0) {
    header('Location: ' . $redir . '?erro=' . urlencode('ID inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id, email, tipo FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header('Location: ' . $redir . '?erro=' . urlencode('Usuário não encontrado.'));
    exit;
}
if ($usuario['tipo'] === 'admin' && $usuario['email'] === 'admin@email.com') {
    header('Location: ' . $redir . '?erro=' . urlencode('O administrador padrão não pode ser excluído.'));
    exit;
}
if ($id === (int)$_SESSION['user_id']) {
    header('Location: ' . $redir . '?erro=' . urlencode('Você não pode excluir a própria conta.'));
    exit;
}

$conn->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

header('Location: ' . $redir . '?sucesso=' . urlencode('Aluno excluído com sucesso.'));
exit;
