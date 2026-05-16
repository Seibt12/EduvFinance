<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_aulas.html');
    exit;
}

$id        = (int)($_POST['id']        ?? 0);
$titulo    = trim($_POST['titulo']     ?? '');
$descricao = trim($_POST['descricao']  ?? '');
$nivel     = trim($_POST['nivel']      ?? '');
$redir     = '../home/admin_aulas.html';

if ($id <= 0 || $titulo === '' || $descricao === '' || $nivel === '') {
    header('Location: ' . $redir . '?erro=' . urlencode('Todos os campos são obrigatórios.'));
    exit;
}
if (!in_array($nivel, ['basico', 'intermediario', 'avancado'], true)) {
    header('Location: ' . $redir . '?erro=' . urlencode('Nível inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id FROM lessons WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    header('Location: ' . $redir . '?erro=' . urlencode('Aula não encontrada.'));
    exit;
}

$conn->prepare("UPDATE lessons SET titulo=?, descricao=?, nivel=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
     ->execute([$titulo, $descricao, $nivel, $id]);

header('Location: ' . $redir . '?sucesso=' . urlencode('Aula atualizada com sucesso.'));
exit;
