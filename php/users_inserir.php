<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_professores.html');
    exit;
}

$nome  = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');
$tipo  = trim($_POST['tipo'] ?? 'professor');
$redir = '../home/admin_professores.html';

if ($nome === '' || $email === '' || $senha === '') {
    header('Location: ' . $redir . '?erro=' . urlencode('Nome, e-mail e senha são obrigatórios.'));
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $redir . '?erro=' . urlencode('E-mail inválido.'));
    exit;
}
if (strlen($senha) < 8) {
    header('Location: ' . $redir . '?erro=' . urlencode('A senha deve ter pelo menos 8 caracteres.'));
    exit;
}
if (!in_array($tipo, ['professor', 'aluno'], true)) {
    $tipo = 'professor';
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    header('Location: ' . $redir . '?erro=' . urlencode('Este e-mail já está em uso.'));
    exit;
}

$hash = password_hash($senha, PASSWORD_BCRYPT);
$conn->prepare("INSERT INTO users (nome, email, senha, tipo) VALUES (?, ?, ?, ?)")
     ->execute([$nome, $email, $hash, $tipo]);

header('Location: ' . $redir . '?sucesso=' . urlencode('Professor criado com sucesso.'));
exit;
