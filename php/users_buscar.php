<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once __DIR__ . '/conexao.php';

$tipo = trim($_GET['tipo'] ?? 'aluno');
if (!in_array($tipo, ['aluno', 'professor'], true)) {
    $tipo = 'aluno';
}

$stmt = $conn->prepare("SELECT id, nome, email FROM users WHERE id = ? AND tipo = ? LIMIT 1");
$stmt->execute([$id, $tipo]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
    exit;
}

echo json_encode(['success' => true, 'user' => $user]);
