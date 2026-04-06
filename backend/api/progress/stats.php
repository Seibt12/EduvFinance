<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAdmin();

$conn = getConnection();

// Totais
$totalStudents = (int)$conn->query("SELECT COUNT(*) FROM users WHERE tipo = 'aluno'")->fetchColumn();
$totalLessons  = (int)$conn->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
$totalCourses  = (int)$conn->query("SELECT COUNT(*) FROM courses")->fetchColumn();

// Progresso por aluno (para o gráfico)
$stmt = $conn->query("
    SELECT
        u.id,
        u.nome,
        COUNT(p.id) AS concluidas
    FROM users u
    LEFT JOIN progress p ON p.user_id = u.id AND p.concluido = 1
    WHERE u.tipo = 'aluno'
    GROUP BY u.id, u.nome
    ORDER BY u.nome ASC
");

$studentsChart = [];
while ($row = $stmt->fetch()) {
    $done    = (int)$row['concluidas'];
    $percent = $totalLessons > 0 ? round(($done / $totalLessons) * 100) : 0;

    $studentsChart[] = [
        'nome'       => $row['nome'],
        'concluidas' => $done,
        'percentual' => $percent,
    ];
}

// Progresso por nível
$niveis  = ['basico', 'intermediario', 'avancado'];
$byLevel = [];

foreach ($niveis as $nivel) {
    $stmtTotal = $conn->prepare("SELECT COUNT(*) FROM lessons WHERE nivel = ?");
    $stmtTotal->execute([$nivel]);
    $totalNivel = (int)$stmtTotal->fetchColumn();

    $stmtAtivos = $conn->prepare("
        SELECT COUNT(DISTINCT p.user_id)
        FROM progress p
        JOIN lessons l ON l.id = p.lesson_id
        WHERE l.nivel = ? AND p.concluido = 1
    ");
    $stmtAtivos->execute([$nivel]);
    $alunosAtivos = (int)$stmtAtivos->fetchColumn();

    $byLevel[$nivel] = [
        'total_aulas'   => $totalNivel,
        'alunos_ativos' => $alunosAtivos,
    ];
}

// Média geral
$avgPercent = 0;
if (count($studentsChart) > 0) {
    $sum        = array_sum(array_column($studentsChart, 'percentual'));
    $avgPercent = round($sum / count($studentsChart));
}

echo json_encode([
    'success'       => true,
    'totalStudents' => $totalStudents,
    'totalLessons'  => $totalLessons,
    'totalCourses'  => $totalCourses,
    'avgCompletion' => $avgPercent,
    'studentsChart' => $studentsChart,
    'byLevel'       => $byLevel,
]);
