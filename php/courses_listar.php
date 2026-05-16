<?php
session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

require_once __DIR__ . '/conexao.php';

$idUsuario = (int)$_SESSION['user_id'];
$tipo = $_SESSION['user_tipo'];

if ($tipo === 'admin') {
    $stmt = $conn->query("
        SELECT c.id, c.nome, c.descricao, c.nivel, c.created_at,
               COUNT(DISTINCT cl.lesson_id) AS total_aulas,
               COUNT(DISTINCT ce.user_id)   AS total_matriculas
        FROM courses c
        LEFT JOIN course_lessons     cl ON cl.course_id = c.id
        LEFT JOIN course_enrollments ce ON ce.course_id = c.id
        GROUP BY c.id
        ORDER BY c.nivel ASC, c.nome ASC
    ");
    $courses = $stmt->fetchAll();
    echo json_encode(['success' => true, 'courses' => $courses]);
} else {
    $stmt = $conn->prepare("
        SELECT
            c.id, c.nome, c.descricao, c.nivel,
            COUNT(DISTINCT cl.lesson_id) AS total_aulas,
            CASE WHEN ce.id IS NOT NULL THEN 1 ELSE 0 END AS matriculado,
            COUNT(DISTINCT CASE WHEN p.concluido = 1 THEN p.lesson_id END) AS concluidas
        FROM courses c
        LEFT JOIN course_lessons     cl ON cl.course_id = c.id
        LEFT JOIN course_enrollments ce ON ce.course_id = c.id AND ce.user_id = ?
        LEFT JOIN progress           p  ON p.lesson_id  = cl.lesson_id AND p.user_id = ?
        GROUP BY c.id, ce.id
        ORDER BY c.nivel ASC, c.nome ASC
    ");
    $stmt->execute([$idUsuario, $idUsuario]);
    $courses = $stmt->fetchAll();
    echo json_encode(['success' => true, 'courses' => $courses]);
}
