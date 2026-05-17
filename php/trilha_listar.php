<?php
/**
 * trilha_listar.php
 * Retorna as aulas de um curso em sequência (trilha de aprendizagem)
 * com status de progresso do usuário
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

// Verificar se usuário está matriculado no curso
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

// Obter dados do curso
$stmtCurso = $conn->prepare("
    SELECT id, nome, descricao, nivel FROM courses WHERE id = ?
");
$stmtCurso->execute([$cursoId]);
$curso = $stmtCurso->fetch();

if (!$curso) {
    echo json_encode(['success' => false, 'message' => 'Curso não encontrado.']);
    exit;
}

// Obter todas as aulas do curso em ordem (sequência)
// Ordenação: por nível (basico → intermediário → avançado) depois por título
$stmtAulas = $conn->prepare("
    SELECT 
        l.id,
        l.titulo,
        l.descricao,
        l.nivel,
        COALESCE(p.concluido, 0) AS concluido,
        ROW_NUMBER() OVER (
            ORDER BY 
                CASE l.nivel WHEN 'basico' THEN 1 
                             WHEN 'intermediario' THEN 2 
                             WHEN 'avancado' THEN 3 END, 
                l.titulo ASC
        ) AS posicao
    FROM lessons l
    JOIN course_lessons cl ON cl.lesson_id = l.id
    LEFT JOIN progress p ON p.lesson_id = l.id AND p.user_id = ?
    WHERE cl.course_id = ?
    ORDER BY posicao ASC
");
$stmtAulas->execute([$idUsuario, $cursoId]);
$aulas = $stmtAulas->fetchAll();

if (empty($aulas)) {
    echo json_encode(['success' => true, 'course' => $curso, 'lessons' => [], 'stats' => ['total' => 0, 'concluidas' => 0, 'em_progresso' => 0]]);
    exit;
}

// Calcular estatísticas
$totalAulas = count($aulas);
$aulasConcluidas = 0;
$aulasEmProgresso = 0;

foreach ($aulas as $aula) {
    if ((int)$aula['concluido'] === 1) {
        $aulasConcluidas++;
    }
}

// Determinar status de cada aula (bloqueada, disponível, em progresso, concluída)
$aulasComStatus = [];
$podeDesbloquear = true; // Começa true - primeira aula sempre disponível

foreach ($aulas as $idx => $aula) {
    $status = 'futura';
    
    if ((int)$aula['concluido'] === 1) {
        $status = 'concluida';
    } elseif ($podeDesbloquear && $status === 'futura') {
        $status = 'disponivel';
        $podeDesbloquear = false; // Apenas uma aula disponível por vez
    }
    
    // Se está disponível e não concluída, marcar como "em_progresso"
    if ($status === 'disponivel' && (int)$aula['concluido'] === 0) {
        $status = 'em_progresso';
    }
    
    $aulasComStatus[] = array_merge($aula, ['status' => $status]);
}

$percentualProgresso = $totalAulas > 0 ? round(($aulasConcluidas / $totalAulas) * 100) : 0;

echo json_encode([
    'success' => true,
    'course' => $curso,
    'lessons' => $aulasComStatus,
    'stats' => [
        'total' => $totalAulas,
        'concluidas' => $aulasConcluidas,
        'percentual' => $percentualProgresso,
        'proxima_posicao' => $podeDesbloquear ? null : array_search('em_progresso', array_column($aulasComStatus, 'status')) + 1
    ]
]);
