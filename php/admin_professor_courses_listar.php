<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$status = trim($_GET['status'] ?? 'pendente');
if (!in_array($status, ['draft', 'pendente', 'aprovado', 'rejeitado', 'todos'], true)) {
    $status = 'pendente';
}

require_once __DIR__ . '/conexao.php';

$sql = "
    SELECT
        pc.id, pc.nome, pc.subtitulo, pc.descricao, pc.nivel, pc.categoria,
        pc.thumbnail_path, pc.status, pc.review_comment,
        pc.created_at, pc.updated_at,
        u.nome AS professor_nome, u.email AS professor_email,
        COUNT(pl.id) AS total_aulas
    FROM professor_courses pc
    JOIN users u ON u.id = pc.professor_id
    LEFT JOIN professor_lessons pl ON pl.professor_course_id = pc.id
";

if ($status !== 'todos') {
    $sql .= " WHERE pc.status = :status";
}
$sql .= " GROUP BY pc.id, u.nome, u.email ORDER BY pc.created_at DESC";

$stmt = $conn->prepare($sql);
if ($status !== 'todos') {
    $stmt->execute(['status' => $status]);
} else {
    $stmt->execute();
}

echo json_encode(['success' => true, 'courses' => $stmt->fetchAll()]);
