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

$courseId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($courseId <= 0) {
    jsonResponse(['success' => false, 'message' => 'ID do curso inválido.'], 400);
}

try {
    $conn    = getConnection();
    $userId  = (int) $_SESSION['user_id'];
    $isAdmin = ($_SESSION['user_tipo'] ?? '') === 'admin';

    // Busca os dados do curso
    $stmt = $conn->prepare("SELECT id, nome, descricao, nivel, created_at FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();

    if (!$course) {
        jsonResponse(['success' => false, 'message' => 'Curso não encontrado.'], 404);
    }

    // Busca as aulas do curso (com progresso para aluno, sem para admin)
    if ($isAdmin) {
        $stmtLessons = $conn->prepare("
            SELECT l.id, l.titulo, l.descricao, l.nivel
            FROM lessons l
            JOIN course_lessons cl ON cl.lesson_id = l.id
            WHERE cl.course_id = ?
            ORDER BY l.nivel ASC, l.titulo ASC
        ");
        $stmtLessons->execute([$courseId]);
    } else {
        $stmtLessons = $conn->prepare("
            SELECT
                l.id, l.titulo, l.descricao, l.nivel,
                COALESCE(p.concluido, 0) AS concluido
            FROM lessons l
            JOIN course_lessons cl ON cl.lesson_id = l.id
            LEFT JOIN progress  p  ON p.lesson_id  = l.id AND p.user_id = ?
            WHERE cl.course_id = ?
            ORDER BY l.nivel ASC, l.titulo ASC
        ");
        $stmtLessons->execute([$userId, $courseId]);
    }

    $lessons = [];
    while ($row = $stmtLessons->fetch()) {
        $lesson = [
            'id'       => (int) $row['id'],
            'titulo'   => $row['titulo'],
            'descricao' => $row['descricao'],
            'nivel'    => $row['nivel'],
        ];
        if (!$isAdmin) {
            $lesson['concluido'] = (bool) $row['concluido'];
        }
        $lessons[] = $lesson;
    }

    // Verifica matrícula do aluno neste curso
    $matriculado = false;
    if (!$isAdmin) {
        $stmtEnroll = $conn->prepare(
            "SELECT id FROM course_enrollments WHERE user_id = ? AND course_id = ?"
        );
        $stmtEnroll->execute([$userId, $courseId]);
        $matriculado = (bool) $stmtEnroll->fetch();
    }

    $total      = count($lessons);
    $concluidas = !$isAdmin
        ? count(array_filter($lessons, fn($l) => $l['concluido']))
        : 0;

    jsonResponse([
        'success' => true,
        'course'  => [
            'id'          => (int) $course['id'],
            'nome'        => $course['nome'],
            'descricao'   => $course['descricao'],
            'nivel'       => $course['nivel'],
            'created_at'  => $course['created_at'],
            'lesson_ids'  => array_column($lessons, 'id'),
            'total_aulas' => $total,
            'matriculado' => $matriculado,
            'concluidas'  => $concluidas,
            'percentual'  => $total > 0 ? round(($concluidas / $total) * 100) : 0,
        ],
        'lessons' => $lessons,
    ]);

} catch (PDOException $e) {
    error_log('[EduFinance][courses/get] DB error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao carregar curso: ' . $e->getMessage()], 500);
}
