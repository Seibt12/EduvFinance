<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_alunos.html');
    exit;
}

$id    = (int)($_POST['id']    ?? 0);
$nome  = trim($_POST['nome']   ?? '');
$email = trim($_POST['email']  ?? '');
$senha = trim($_POST['senha']  ?? '');

$redir = '../home/admin_alunos.html';

if ($id <= 0 || $nome === '' || $email === '') {
    header('Location: ' . $redir . '?erro=' . urlencode('ID, nome e e-mail são obrigatórios.'));
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $redir . '?erro=' . urlencode('E-mail inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id, email, tipo FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$existente = $stmt->fetch();

if (!$existente) {
    header('Location: ' . $redir . '?erro=' . urlencode('Usuário não encontrado.'));
    exit;
}
if ($existente['tipo'] === 'admin') {
    header('Location: ' . $redir . '?erro=' . urlencode('Contas de administrador não podem ser editadas por aqui.'));
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
$stmt->execute([$email, $id]);
if ($stmt->fetch()) {
    header('Location: ' . $redir . '?erro=' . urlencode('Este e-mail já está em uso.'));
    exit;
}

if ($senha !== '') {
    if (strlen($senha) < 8) {
        header('Location: ' . $redir . '?erro=' . urlencode('A senha deve ter pelo menos 8 caracteres.'));
        exit;
    }
    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $conn->prepare("UPDATE users SET nome=?, email=?, senha=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
         ->execute([$nome, $email, $hash, $id]);
} else {
    $conn->prepare("UPDATE users SET nome=?, email=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
         ->execute([$nome, $email, $id]);
}

header('Location: ' . $redir . '?sucesso=' . urlencode('Aluno atualizado com sucesso.'));
exit;
