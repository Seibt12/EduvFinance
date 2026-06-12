<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login/cadastro.html');
    exit;
}

$nome  = trim($_POST['nome']  ?? '');
$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');
$idade = (int)($_POST['idade'] ?? 0);

$redir = '../login/cadastro.html';

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
if ($idade > 0 && ($idade < 10 || $idade > 120)) {
    header('Location: ' . $redir . '?erro=' . urlencode('Idade inválida.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    header('Location: ' . $redir . '?erro=' . urlencode('Este e-mail já está cadastrado.'));
    exit;
}

$senhaHash = password_hash($senha, PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO users (nome, email, senha, tipo, idade) VALUES (?, ?, ?, 'aluno', ?)");
$stmt->execute([$nome, $email, $senhaHash, $idade > 0 ? $idade : null]);

header('Location: ../login/index.html?sucesso=' . urlencode('Cadastro realizado! Faça login.'));
exit;
