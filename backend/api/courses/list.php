<?php
// ============================================================
// CURSOS — GET /backend/api/courses/list.php
//
// Admin vê: todos os cursos + total de aulas + total de matrículas
// Aluno vê: todos os cursos + se está matriculado + progresso
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

try {
    $conn    = getConnection();
    $userId  = (int) $_SESSION['user_id'];
    $isAdmin = ($_SESSION['user_tipo'] ?? '') === 'admin';

    if ($isAdmin) {
        // Admin vê todos os cursos com contagem de aulas e matrículas
        $stmt = $conn->query("
            SELECT
                c.id,
                c.nome,
                c.descricao,
                c.nivel,
                c.created_at,
                COUNT(DISTINCT cl.lesson_id) AS total_aulas,
                COUNT(DISTINCT ce.user_id)   AS total_matriculas
            FROM courses c
            LEFT JOIN course_lessons     cl ON cl.course_id = c.id
            LEFT JOIN course_enrollments ce ON ce.course_id = c.id
            GROUP BY c.id
            ORDER BY c.nivel ASC, c.nome ASC
        ");

        $courses = [];
        while ($row = $stmt->fetch()) {
            $courses[] = [
                'id'               => (int) $row['id'],
                'nome'             => $row['nome'],
                'descricao'        => $row['descricao'],
                'nivel'            => $row['nivel'],
                'created_at'       => $row['created_at'],
                'total_aulas'      => (int) $row['total_aulas'],
                'total_matriculas' => (int) $row['total_matriculas'],
            ];
        }
    } else {
        // Aluno vê os cursos com status de matrícula e progresso
        $stmt = $conn->prepare("
            SELECT
                c.id,
                c.nome,
                c.descricao,
                c.nivel,
                COUNT(DISTINCT cl.lesson_id)                                         AS total_aulas,
                CASE WHEN ce.id IS NOT NULL THEN 1 ELSE 0 END                        AS matriculado,
                COUNT(DISTINCT CASE WHEN p.concluido = 1 THEN p.lesson_id END)       AS concluidas
            FROM courses c
            LEFT JOIN course_lessons     cl ON cl.course_id = c.id
            LEFT JOIN course_enrollments ce ON ce.course_id = c.id AND ce.user_id = ?
            LEFT JOIN progress           p  ON p.lesson_id  = cl.lesson_id AND p.user_id = ?
            GROUP BY c.id, ce.id
            ORDER BY c.nivel ASC, c.nome ASC
        ");
        $stmt->execute([$userId, $userId]);

        $courses = [];
        while ($row = $stmt->fetch()) {
            $total     = (int) $row['total_aulas'];
            $concluidas = (int) $row['concluidas'];
            $courses[] = [
                'id'          => (int) $row['id'],
                'nome'        => $row['nome'],
                'descricao'   => $row['descricao'],
                'nivel'       => $row['nivel'],
                'total_aulas' => $total,
                'matriculado' => (bool) $row['matriculado'],
                'concluidas'  => $concluidas,
                'percentual'  => $total > 0 ? round(($concluidas / $total) * 100) : 0,
            ];
        }
    }

    jsonResponse(['success' => true, 'courses' => $courses]);

} catch (PDOException $e) {
    // Erro de banco de dados — retorna JSON em vez de HTML de erro
    error_log('[EduFinance][courses/list] DB error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao consultar cursos: ' . $e->getMessage()], 500);
}
