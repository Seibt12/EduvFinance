<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

require_once __DIR__ . '/conexao.php';

$idUsuario = (int)$_SESSION['user_id'];
$cursoId   = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;

if ($cursoId > 0) {
    $stmt = $conn->prepare("
        SELECT l.id, l.titulo, l.descricao, l.nivel, l.video_link, l.attachment_path, l.attachment_name,
               COALESCE(p.concluido, 0) AS concluido
        FROM lessons l
        JOIN course_lessons cl ON cl.lesson_id = l.id
        LEFT JOIN progress  p  ON p.lesson_id  = l.id AND p.user_id = ?
        WHERE cl.course_id = ?
        ORDER BY cl.order_index ASC, l.titulo ASC
    ");
    $stmt->execute([$idUsuario, $cursoId]);
} else {
    $stmt = $conn->query("
        SELECT id, titulo, descricao, nivel, created_at FROM lessons
        ORDER BY CASE nivel WHEN 'basico' THEN 1 WHEN 'intermediario' THEN 2 WHEN 'avancado' THEN 3 END, id ASC
    ");
}

$lessons = $stmt->fetchAll();
echo json_encode(['success' => true, 'lessons' => $lessons]);
