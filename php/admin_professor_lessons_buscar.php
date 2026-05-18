<?php
session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("
    SELECT pl.*, u.nome AS professor_nome, u.email AS professor_email
    FROM professor_lessons pl
    JOIN users u ON u.id = pl.professor_id
    WHERE pl.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    echo json_encode(['success' => false, 'message' => 'Oferta não encontrada.']);
    exit;
}

echo json_encode(['success' => true, 'lesson' => $lesson]);
