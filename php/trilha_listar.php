<?php
require_once __DIR__ . '/valida_sessao.php';
require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$idUsuario = (int)$_SESSION['user_id'];
$cursoId   = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;

if ($cursoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do curso é obrigatório.']);
    exit;
}

$stmtMatricula = $conn->prepare("
    SELECT ce.id FROM course_enrollments ce
    WHERE ce.user_id = ? AND ce.course_id = ?
    LIMIT 1
");
$stmtMatricula->execute([$idUsuario, $cursoId]);
if (!$stmtMatricula->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Você não está matriculado neste curso.']);
    exit;
}

$stmtCurso = $conn->prepare("SELECT id, nome, descricao, nivel FROM courses WHERE id = ?");
$stmtCurso->execute([$cursoId]);
$curso = $stmtCurso->fetch();

if (!$curso) {
    echo json_encode(['success' => false, 'message' => 'Curso não encontrado.']);
    exit;
}

// Order by order_index from course_lessons, fallback to nivel then titulo
$stmtAulas = $conn->prepare("
    SELECT
        l.id,
        l.titulo,
        l.descricao,
        l.nivel,
        cl.order_index,
        COALESCE(p.concluido, 0) AS concluido,
        ROW_NUMBER() OVER (
            ORDER BY cl.order_index ASC, l.titulo ASC
        ) AS posicao
    FROM lessons l
    JOIN course_lessons cl ON cl.lesson_id = l.id
    LEFT JOIN progress p ON p.lesson_id = l.id AND p.user_id = ?
    WHERE cl.course_id = ?
    ORDER BY cl.order_index ASC, l.titulo ASC
");
$stmtAulas->execute([$idUsuario, $cursoId]);
$aulas = $stmtAulas->fetchAll();

if (empty($aulas)) {
    echo json_encode([
        'success' => true,
        'course'  => $curso,
        'lessons' => [],
        'stats'   => ['total' => 0, 'concluidas' => 0, 'em_progresso' => 0],
    ]);
    exit;
}

$totalAulas      = count($aulas);
$aulasConcluidas = 0;

foreach ($aulas as $aula) {
    if ((int)$aula['concluido'] === 1) {
        $aulasConcluidas++;
    }
}

$aulasComStatus  = [];
$podeDesbloquear = true;

foreach ($aulas as $aula) {
    $status = 'futura';

    if ((int)$aula['concluido'] === 1) {
        $status = 'concluida';
    } elseif ($podeDesbloquear) {
        $status = 'em_progresso';
        $podeDesbloquear = false;
    }

    $aulasComStatus[] = array_merge($aula, ['status' => $status]);
}

$percentualProgresso = $totalAulas > 0 ? round(($aulasConcluidas / $totalAulas) * 100) : 0;

$proximaIdx = null;
foreach ($aulasComStatus as $i => $a) {
    if ($a['status'] === 'em_progresso') {
        $proximaIdx = (int)$a['posicao'];
        break;
    }
}

echo json_encode([
    'success' => true,
    'course'  => $curso,
    'lessons' => $aulasComStatus,
    'stats'   => [
        'total'          => $totalAulas,
        'concluidas'     => $aulasConcluidas,
        'percentual'     => $percentualProgresso,
        'proxima_posicao' => $proximaIdx,
    ],
]);
