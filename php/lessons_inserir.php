<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_aulas.html');
    exit;
}

$titulo    = trim($_POST['titulo']    ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$nivel     = trim($_POST['nivel']     ?? '');
$redir     = '../home/admin_aulas.html';

if ($titulo === '' || $descricao === '' || $nivel === '') {
    header('Location: ' . $redir . '?erro=' . urlencode('Título, descrição e nível são obrigatórios.'));
    exit;
}
if (!in_array($nivel, ['basico', 'intermediario', 'avancado'], true)) {
    header('Location: ' . $redir . '?erro=' . urlencode('Nível inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';
$conn->prepare("INSERT INTO lessons (titulo, descricao, nivel) VALUES (?, ?, ?)")
     ->execute([$titulo, $descricao, $nivel]);

header('Location: ' . $redir . '?sucesso=' . urlencode('Aula criada com sucesso.'));
exit;
