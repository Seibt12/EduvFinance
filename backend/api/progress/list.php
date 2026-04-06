<?php
// Retorna todas as aulas com o status de conclusão e bloqueio para o aluno logado.
// Regra de progressão:
//   - Intermediário bloqueado até concluir todas as aulas básicas
//   - Avançado bloqueado até concluir básico E intermediário

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

$idUsuario = (int)$_SESSION['user_id'];
$conn      = getConnection();

// Busca todas as aulas com o status de conclusão do aluno (CASE WHEN no lugar de FIELD())
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
$stmt->execute([$idUsuario]);
$rows = $stmt->fetchAll();

// Conta totais e concluídas por nível
$totaisPorNivel    = ['basico' => 0, 'intermediario' => 0, 'avancado' => 0];
$concluidasPorNivel = ['basico' => 0, 'intermediario' => 0, 'avancado' => 0];

foreach ($rows as $row) {
    $nivel = $row['nivel'];
    $totaisPorNivel[$nivel]++;
    if ((int)$row['concluido'] === 1) {
        $concluidasPorNivel[$nivel]++;
    }
}

$basicoConcluido        = $totaisPorNivel['basico'] > 0        && $concluidasPorNivel['basico']        === $totaisPorNivel['basico'];
$intermediarioConcluido = $totaisPorNivel['intermediario'] > 0  && $concluidasPorNivel['intermediario'] === $totaisPorNivel['intermediario'];

$aulas = [];
foreach ($rows as $row) {
    $bloqueado = false;
    if ($row['nivel'] === 'intermediario' && !$basicoConcluido) {
        $bloqueado = true;
    }
    if ($row['nivel'] === 'avancado' && (!$basicoConcluido || !$intermediarioConcluido)) {
        $bloqueado = true;
    }

    $aulas[] = [
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
    'lessons' => $aulas,
    'resumo'  => [
        'total'      => $totalAulas,
        'concluidas' => (int)$totalConcluidas,
        'percentual' => $percentual,
        'por_nivel'  => [
            'basico'        => ['total' => $totaisPorNivel['basico'],        'concluidas' => $concluidasPorNivel['basico']],
            'intermediario' => ['total' => $totaisPorNivel['intermediario'], 'concluidas' => $concluidasPorNivel['intermediario']],
            'avancado'      => ['total' => $totaisPorNivel['avancado'],      'concluidas' => $concluidasPorNivel['avancado']],
        ],
    ],
]);
