<?php
/**
 * trilha_progresso.php
 * Retorna apenas o progresso do usuário em um curso (para refresh rápido)
 */
require_once __DIR__ . '/valida_sessao.php';
require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$idUsuario = (int)$_SESSION['user_id'];
$cursoId   = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;

if ($cursoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do curso é obrigatório.']);
    exit;
}

// Verificar matrícula
$stmtMatricula = $conn->prepare("
    SELECT ce.id FROM course_enrollments ce
    WHERE ce.user_id = ? AND ce.course_id = ?
    LIMIT 1
");
$stmtMatricula->execute([$idUsuario, $cursoId]);
$matricula = $stmtMatricula->fetch();

if (!$matricula) {
    echo json_encode(['success' => false, 'message' => 'Você não está matriculado neste curso.']);
    exit;
}

// Obter progresso do usuário neste curso
$stmtProgresso = $conn->prepare("
    SELECT 
        l.id,
        COALESCE(p.concluido, 0) AS concluido
    FROM lessons l
    JOIN course_lessons cl ON cl.lesson_id = l.id
    LEFT JOIN progress p ON p.lesson_id = l.id AND p.user_id = ?
    WHERE cl.course_id = ?
    ORDER BY CASE l.nivel WHEN 'basico' THEN 1 
                           WHEN 'intermediario' THEN 2 
                           WHEN 'avancado' THEN 3 END, 
             l.titulo ASC
");
$stmtProgresso->execute([$idUsuario, $cursoId]);
$progresso = $stmtProgresso->fetchAll();

$totalAulas = count($progresso);
$aulasConcluidas = 0;

foreach ($progresso as $item) {
    if ((int)$item['concluido'] === 1) {
        $aulasConcluidas++;
    }
}

$percentualProgresso = $totalAulas > 0 ? round(($aulasConcluidas / $totalAulas) * 100) : 0;

echo json_encode([
    'success' => true,
    'stats' => [
        'total' => $totalAulas,
        'concluidas' => $aulasConcluidas,
        'percentual' => $percentualProgresso
    ],
    'progresso' => $progresso
]);
