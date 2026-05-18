<?php
session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$status = trim($_GET['status'] ?? 'pendente');
if (!in_array($status, ['pendente', 'aprovado', 'rejeitado', 'todos'], true)) {
    $status = 'pendente';
}

require_once __DIR__ . '/conexao.php';

$sql = "SELECT pl.id, pl.titulo, pl.descricao, pl.nivel, pl.status, pl.review_comment,
               pl.video_link, pl.attachment_path, pl.attachment_name, pl.created_at, pl.updated_at,
               u.nome AS professor_nome, u.email AS professor_email
        FROM professor_lessons pl
        JOIN users u ON u.id = pl.professor_id";
if ($status !== 'todos') {
    $sql .= " WHERE pl.status = :status";
}
$sql .= " ORDER BY pl.created_at DESC";

$stmt = $conn->prepare($sql);
if ($status !== 'todos') {
    $stmt->execute(['status' => $status]);
} else {
    $stmt->execute();
}

echo json_encode(['success' => true, 'lessons' => $stmt->fetchAll()]);
