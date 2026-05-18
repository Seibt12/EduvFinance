<?php
require_once __DIR__ . '/valida_professor.php';
header('Content-Type: application/json');

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT c.id, c.nome, c.descricao, c.nivel, c.status, c.review_comment, c.created_at, c.updated_at,
    COUNT(pcl.professor_lesson_id) AS total_lessons
    FROM professor_courses c
    LEFT JOIN professor_course_lessons pcl ON pcl.professor_course_id = c.id
    WHERE c.professor_id = ?
    GROUP BY c.id
    ORDER BY CASE c.nivel WHEN 'basico' THEN 1 WHEN 'intermediario' THEN 2 WHEN 'avancado' THEN 3 END, c.nome ASC");
$stmt->execute([$professorId]);
$courses = $stmt->fetchAll();

echo json_encode(['success' => true, 'courses' => $courses]);
