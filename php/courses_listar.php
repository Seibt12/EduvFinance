<?php
require_once __DIR__ . '/session.php';
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
    // Buscar perfil do aluno
    $stmtPerfil = $conn->prepare("SELECT perfil FROM investor_profile WHERE user_id = ? LIMIT 1");
    $stmtPerfil->execute([$idUsuario]);
    $perfilUsuario = $stmtPerfil->fetchColumn() ?: null;

    // Filtros opcionais
    $filtroNivel  = trim($_GET['nivel']  ?? '');
    $filtroPerfil = trim($_GET['perfil'] ?? '');

    $niveisValidos = ['basico', 'intermediario', 'avancado'];
    $perfisValidos = ['todos', 'conservador', 'moderado', 'agressivo'];
    if (!in_array($filtroNivel,  $niveisValidos, true)) $filtroNivel  = '';
    if (!in_array($filtroPerfil, $perfisValidos, true)) $filtroPerfil = '';

    $where  = [];
    $params = [$idUsuario, $idUsuario];

    if ($filtroNivel !== '') {
        $where[]  = 'c.nivel = ?';
        $params[] = $filtroNivel;
    }
    if ($filtroPerfil !== '') {
        $where[]  = 'c.perfil_recomendado = ?';
        $params[] = $filtroPerfil;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $conn->prepare("
        SELECT
            c.id, c.nome, c.descricao, c.nivel, c.perfil_recomendado,
            COUNT(DISTINCT cl.lesson_id) AS total_aulas,
            CASE WHEN ce.id IS NOT NULL THEN 1 ELSE 0 END AS matriculado,
            COUNT(DISTINCT CASE WHEN p.concluido = 1 THEN p.lesson_id END) AS concluidas
        FROM courses c
        LEFT JOIN course_lessons     cl ON cl.course_id = c.id
        LEFT JOIN course_enrollments ce ON ce.course_id = c.id AND ce.user_id = ?
        LEFT JOIN progress           p  ON p.lesson_id  = cl.lesson_id AND p.user_id = ?
        $whereClause
        GROUP BY c.id, ce.id
        ORDER BY c.nivel ASC, c.nome ASC
    ");
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
    echo json_encode(['success' => true, 'courses' => $courses, 'perfil_usuario' => $perfilUsuario]);
}
