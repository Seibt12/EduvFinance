<?php
// Retorna estatísticas gerais para o dashboard do admin:
// total de alunos, aulas, cursos e taxa de conclusão média.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAdmin();

$conn = getConnection();

// Totais gerais
$totalAlunos  = (int)$conn->query("SELECT COUNT(*) FROM users WHERE tipo = 'aluno'")->fetchColumn();
$totalAulas   = (int)$conn->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
$totalCursos  = (int)$conn->query("SELECT COUNT(*) FROM courses")->fetchColumn();

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

$progressoAlunos = [];
while ($row = $stmt->fetch()) {
    $concluidas = (int)$row['concluidas'];
    $percentual = $totalAulas > 0 ? round(($concluidas / $totalAulas) * 100) : 0;

    $progressoAlunos[] = [
        'nome'       => $row['nome'],
        'concluidas' => $concluidas,
        'percentual' => $percentual,
    ];
}

// Progresso por nível
$niveis   = ['basico', 'intermediario', 'avancado'];
$porNivel = [];

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

    $porNivel[$nivel] = [
        'total_aulas'   => $totalNivel,
        'alunos_ativos' => $alunosAtivos,
    ];
}

// Média geral de conclusão
$percentualMedio = 0;
if (count($progressoAlunos) > 0) {
    $soma            = array_sum(array_column($progressoAlunos, 'percentual'));
    $percentualMedio = round($soma / count($progressoAlunos));
}

echo json_encode([
    'success'       => true,
    'totalStudents' => $totalAlunos,
    'totalLessons'  => $totalAulas,
    'totalCourses'  => $totalCursos,
    'avgCompletion' => $percentualMedio,
    'studentsChart' => $progressoAlunos,
    'byLevel'       => $porNivel,
]);
