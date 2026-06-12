<?php
require_once __DIR__ . '/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login/index.html');
    exit;
}

$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
    header('Location: ../login/index.html?erro=' . urlencode('E-mail e senha são obrigatórios.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id, nome, email, senha, tipo FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($senha, $usuario['senha'])) {
    header('Location: ../login/index.html?erro=' . urlencode('E-mail ou senha incorretos.'));
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id']    = $usuario['id'];
$_SESSION['user_nome']  = $usuario['nome'];
$_SESSION['user_email'] = $usuario['email'];
$_SESSION['user_tipo']  = $usuario['tipo'];

if ($usuario['tipo'] === 'admin') {
    $destino = '../home/index.html';
} elseif ($usuario['tipo'] === 'professor') {
    $destino = '../home/professor.html';
} else {
    $destino = '../home/student.html';
}

header("Location: $destino");
exit;
