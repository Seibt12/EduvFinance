<?php
require_once __DIR__ . '/valida_professor.php';
header('Content-Type: application/json');

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id, titulo, descricao, nivel, video_link, attachment_path, attachment_name, status, review_comment, created_at, updated_at
    FROM professor_lessons
    WHERE professor_id = ?
    ORDER BY CASE nivel WHEN 'basico' THEN 1 WHEN 'intermediario' THEN 2 WHEN 'avancado' THEN 3 END, titulo ASC");
$stmt->execute([$professorId]);
$lessons = $stmt->fetchAll();

echo json_encode(['success' => true, 'lessons' => $lessons]);
