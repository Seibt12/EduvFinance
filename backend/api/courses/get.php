<?php
// ============================================================
// DETALHE DO CURSO — GET /backend/api/courses/get.php?id=X
//
// Retorna: dados do curso + aulas vinculadas (com progresso para aluno)
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$cursoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($cursoId <= 0) {
    jsonResponse(['success' => false, 'message' => 'ID do curso inválido.'], 400);
}

try {
    $conn      = getConnection();
    $idUsuario = (int) $_SESSION['user_id'];
    $ehAdmin   = ($_SESSION['user_tipo'] ?? '') === 'admin';

    // Busca os dados do curso
    $stmt = $conn->prepare("SELECT id, nome, descricao, nivel, created_at FROM courses WHERE id = ?");
    $stmt->execute([$cursoId]);
    $curso = $stmt->fetch();

    if (!$curso) {
        jsonResponse(['success' => false, 'message' => 'Curso não encontrado.'], 404);
    }

    // Busca as aulas do curso (com progresso para aluno, sem para admin)
    if ($ehAdmin) {
        $stmtAulas = $conn->prepare("
            SELECT l.id, l.titulo, l.descricao, l.nivel
            FROM lessons l
            JOIN course_lessons cl ON cl.lesson_id = l.id
            WHERE cl.course_id = ?
            ORDER BY l.nivel ASC, l.titulo ASC
        ");
        $stmtAulas->execute([$cursoId]);
    } else {
        $stmtAulas = $conn->prepare("
            SELECT
                l.id, l.titulo, l.descricao, l.nivel,
                COALESCE(p.concluido, 0) AS concluido
            FROM lessons l
            JOIN course_lessons cl ON cl.lesson_id = l.id
            LEFT JOIN progress  p  ON p.lesson_id  = l.id AND p.user_id = ?
            WHERE cl.course_id = ?
            ORDER BY l.nivel ASC, l.titulo ASC
        ");
        $stmtAulas->execute([$idUsuario, $cursoId]);
    }

    $aulas = [];
    while ($row = $stmtAulas->fetch()) {
        $aula = [
            'id'        => (int) $row['id'],
            'titulo'    => $row['titulo'],
            'descricao' => $row['descricao'],
            'nivel'     => $row['nivel'],
        ];
        if (!$ehAdmin) {
            $aula['concluido'] = (bool) $row['concluido'];
        }
        $aulas[] = $aula;
    }

    // Verifica matrícula do aluno neste curso
    $matriculado = false;
    if (!$ehAdmin) {
        $stmtMatricula = $conn->prepare(
            "SELECT id FROM course_enrollments WHERE user_id = ? AND course_id = ?"
        );
        $stmtMatricula->execute([$idUsuario, $cursoId]);
        $matriculado = (bool) $stmtMatricula->fetch();
    }

    $totalAulas = count($aulas);
    $concluidas = !$ehAdmin
        ? count(array_filter($aulas, fn($a) => $a['concluido']))
        : 0;

    jsonResponse([
        'success' => true,
        'course'  => [
            'id'          => (int) $curso['id'],
            'nome'        => $curso['nome'],
            'descricao'   => $curso['descricao'],
            'nivel'       => $curso['nivel'],
            'created_at'  => $curso['created_at'],
            'lesson_ids'  => array_column($aulas, 'id'),
            'total_aulas' => $totalAulas,
            'matriculado' => $matriculado,
            'concluidas'  => $concluidas,
            'percentual'  => $totalAulas > 0 ? round(($concluidas / $totalAulas) * 100) : 0,
        ],
        'lessons' => $aulas,
    ]);

} catch (PDOException $e) {
    error_log('[EduFinance][courses/get] DB error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao carregar curso: ' . $e->getMessage()], 500);
}
