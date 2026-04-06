<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

$userId = (int)$_SESSION['user_id'];
$conn   = getConnection();

// Todas as aulas + status do aluno (CASE WHEN no lugar de FIELD())
$stmt = $conn->prepare("
    SELECT
        l.id,
        l.titulo,
        l.descricao,
        l.nivel,
        COALESCE(p.concluido, 0) AS concluido
    FROM lessons l
    LEFT JOIN progress p ON p.lesson_id = l.id AND p.user_id = ?
    ORDER BY
        CASE l.nivel
            WHEN 'basico'        THEN 1
            WHEN 'intermediario' THEN 2
            WHEN 'avancado'      THEN 3
        END,
        l.id ASC
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();

// Conta totais e concluídas por nível
$totals    = ['basico' => 0, 'intermediario' => 0, 'avancado' => 0];
$completed = ['basico' => 0, 'intermediario' => 0, 'avancado' => 0];

foreach ($rows as $row) {
    $nivel = $row['nivel'];
    $totals[$nivel]++;
    if ((int)$row['concluido'] === 1) {
        $completed[$nivel]++;
    }
}

$basicoDone        = $totals['basico'] > 0        && $completed['basico']        === $totals['basico'];
$intermediarioDone = $totals['intermediario'] > 0  && $completed['intermediario'] === $totals['intermediario'];

$lessons = [];
foreach ($rows as $row) {
    $bloqueado = false;
    if ($row['nivel'] === 'intermediario' && !$basicoDone) {
        $bloqueado = true;
    }
    if ($row['nivel'] === 'avancado' && (!$basicoDone || !$intermediarioDone)) {
        $bloqueado = true;
    }

    $lessons[] = [
        'id'        => (int)$row['id'],
        'titulo'    => $row['titulo'],
        'descricao' => $row['descricao'],
        'nivel'     => $row['nivel'],
        'concluido' => (bool)(int)$row['concluido'],
        'bloqueado' => $bloqueado,
    ];
}

$totalAulas      = count($rows);
$totalConcluidas = array_sum(array_column($rows, 'concluido'));
$percentual      = $totalAulas > 0 ? round(($totalConcluidas / $totalAulas) * 100) : 0;

echo json_encode([
    'success' => true,
    'lessons' => $lessons,
    'resumo'  => [
        'total'      => $totalAulas,
        'concluidas' => (int)$totalConcluidas,
        'percentual' => $percentual,
        'por_nivel'  => [
            'basico'        => ['total' => $totals['basico'],        'concluidas' => $completed['basico']],
            'intermediario' => ['total' => $totals['intermediario'], 'concluidas' => $completed['intermediario']],
            'avancado'      => ['total' => $totals['avancado'],      'concluidas' => $completed['avancado']],
        ],
    ],
]);
