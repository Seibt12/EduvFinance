<?php
session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

require_once __DIR__ . '/conexao.php';

$totalStudents = (int)$conn->query("SELECT COUNT(*) FROM users WHERE tipo = 'aluno'")->fetchColumn();
$totalLessons  = (int)$conn->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
$totalCourses  = (int)$conn->query("SELECT COUNT(*) FROM courses")->fetchColumn();

$stmt = $conn->query("
    SELECT u.nome, COUNT(p.id) AS concluidas
    FROM users u
    LEFT JOIN progress p ON p.user_id = u.id AND p.concluido = 1
    WHERE u.tipo = 'aluno'
    GROUP BY u.id, u.nome
    ORDER BY u.nome ASC
");
$progressoAlunos = $stmt->fetchAll();

$avgCompletion = 0;
if (count($progressoAlunos) > 0 && $totalLessons > 0) {
    $soma = 0;
    foreach ($progressoAlunos as $a) {
        $soma += round(((int)$a['concluidas'] / $totalLessons) * 100);
    }
    $avgCompletion = round($soma / count($progressoAlunos));
}

echo json_encode([
    'success'        => true,
    'totalStudents'  => $totalStudents,
    'totalLessons'   => $totalLessons,
    'totalCourses'   => $totalCourses,
    'avgCompletion'  => $avgCompletion,
    'progressoAlunos' => $progressoAlunos,
]);
