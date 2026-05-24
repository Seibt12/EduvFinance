<?php
/**
 * review_inserir.php
 * POST — Submit a course review.
 *
 * Business rules enforced server-side:
 *  - User must be authenticated and be an 'aluno'.
 *  - Student must be enrolled in the course.
 *  - All course lessons must be completed (100 % progress).
 *  - Student cannot review the same course twice.
 *  - Rating must be an integer between 1 and 5.
 *  - Comment is optional but sanitised before storage.
 */
require_once __DIR__ . '/valida_sessao.php';
require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// Only students may review courses
if ($_SESSION['user_tipo'] !== 'aluno') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Apenas alunos podem avaliar cursos.']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$cursoId   = isset($input['course_id'])  ? (int)$input['course_id']  : 0;
$rating    = isset($input['rating'])     ? (int)$input['rating']     : 0;
$comment   = isset($input['comment'])    ? trim((string)$input['comment']) : '';
$idUsuario = (int)$_SESSION['user_id'];

// ── Basic input validation ───────────────────────────────────
if ($cursoId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'ID do curso inválido.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A nota deve ser entre 1 e 5 estrelas.']);
    exit;
}

// Sanitise comment — strip tags, limit to 2000 chars
$comment = mb_substr(strip_tags($comment), 0, 2000);

// ── Verify course exists ─────────────────────────────────────
$stmtCurso = $conn->prepare("SELECT id FROM courses WHERE id = ? LIMIT 1");
$stmtCurso->execute([$cursoId]);
if (!$stmtCurso->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Curso não encontrado.']);
    exit;
}

// ── Verify enrollment ────────────────────────────────────────
$stmtEnroll = $conn->prepare("
    SELECT id FROM course_enrollments
    WHERE user_id = ? AND course_id = ?
    LIMIT 1
");
$stmtEnroll->execute([$idUsuario, $cursoId]);
if (!$stmtEnroll->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Você não está matriculado neste curso.']);
    exit;
}

// ── Verify course completion (all lessons must be concluido) ─
$stmtCompletion = $conn->prepare("
    SELECT
        COUNT(cl.lesson_id)                                        AS total,
        SUM(CASE WHEN COALESCE(p.concluido, 0) = 1 THEN 1 ELSE 0 END) AS concluidas
    FROM course_lessons cl
    LEFT JOIN progress p ON p.lesson_id = cl.lesson_id AND p.user_id = ?
    WHERE cl.course_id = ?
");
$stmtCompletion->execute([$idUsuario, $cursoId]);
$completion = $stmtCompletion->fetch();

$total     = (int)($completion['total']     ?? 0);
$concluidas = (int)($completion['concluidas'] ?? 0);

if ($total === 0 || $concluidas < $total) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Você precisa concluir todas as aulas do curso para avaliá-lo.',
    ]);
    exit;
}

// ── Prevent duplicate reviews ────────────────────────────────
$stmtDup = $conn->prepare("
    SELECT id FROM course_reviews
    WHERE course_id = ? AND student_id = ?
    LIMIT 1
");
$stmtDup->execute([$cursoId, $idUsuario]);
if ($stmtDup->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Você já avaliou este curso.']);
    exit;
}

// ── Insert review ────────────────────────────────────────────
$stmtInsert = $conn->prepare("
    INSERT INTO course_reviews (course_id, student_id, rating, comment)
    VALUES (?, ?, ?, ?)
");
$stmtInsert->execute([$cursoId, $idUsuario, $rating, $comment !== '' ? $comment : null]);

echo json_encode(['success' => true, 'message' => 'Avaliação enviada com sucesso!']);
