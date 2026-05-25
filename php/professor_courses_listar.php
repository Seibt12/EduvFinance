<?php
require_once __DIR__ . '/valida_professor.php';
header('Content-Type: application/json');

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT
        c.id, c.nome, c.subtitulo, c.descricao, c.nivel, c.categoria,
        c.thumbnail_path, c.status, c.review_comment,
        c.created_at, c.updated_at,
        COUNT(pl.id) AS total_lessons
    FROM professor_courses c
    LEFT JOIN professor_lessons pl ON pl.professor_course_id = c.id
    WHERE c.professor_id = ?
    GROUP BY c.id
    ORDER BY
        CASE c.status
            WHEN 'rejeitado' THEN 1
            WHEN 'draft'     THEN 2
            WHEN 'pendente'  THEN 3
            WHEN 'aprovado'  THEN 4
        END,
        c.updated_at DESC
");
$stmt->execute([$professorId]);
$courses = $stmt->fetchAll();

echo json_encode(['success' => true, 'courses' => $courses]);
