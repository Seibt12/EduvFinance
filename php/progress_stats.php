<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

require_once __DIR__ . '/conexao.php';

$totalStudents = (int)$conn->query("SELECT COUNT(*) FROM users WHERE tipo = 'aluno'")->fetchColumn();
$totalLessons  = (int)$conn->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
$totalCourses  = (int)$conn->query("SELECT COUNT(*) FROM courses")->fetchColumn();

// Per-student progress based on lessons in their enrolled courses (not global total)
$stmt = $conn->query("
    SELECT
        u.nome,
        COUNT(DISTINCT CASE WHEN p.concluido = 1 THEN p.lesson_id END) AS concluidas,
        COUNT(DISTINCT cl.lesson_id) AS total_matriculadas
    FROM users u
    LEFT JOIN course_enrollments ce ON ce.user_id = u.id
    LEFT JOIN course_lessons cl ON cl.course_id = ce.course_id
    LEFT JOIN progress p ON p.user_id = u.id AND p.lesson_id = cl.lesson_id
    WHERE u.tipo = 'aluno'
    GROUP BY u.id, u.nome
    ORDER BY u.nome ASC
");
$progressoAlunos = $stmt->fetchAll();

$avgCompletion = 0;
if (count($progressoAlunos) > 0) {
    $soma = 0;
    $comMatricula = 0;
    foreach ($progressoAlunos as $a) {
        $total = (int)$a['total_matriculadas'];
        if ($total > 0) {
            $soma += round(((int)$a['concluidas'] / $total) * 100);
            $comMatricula++;
        }
    }
    $avgCompletion = $comMatricula > 0 ? round($soma / $comMatricula) : 0;
}

echo json_encode([
    'success'         => true,
    'totalStudents'   => $totalStudents,
    'totalLessons'    => $totalLessons,
    'totalCourses'    => $totalCourses,
    'avgCompletion'   => $avgCompletion,
    'progressoAlunos' => $progressoAlunos,
]);
