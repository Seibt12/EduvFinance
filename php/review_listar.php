<?php
/**
 * review_listar.php
 * GET — Returns reviews and statistics for one or all courses.
 *
 * Modes:
 *   ?curso_id=X          Full reviews list + average for course X.
 *   ?medias=1            Lightweight map of course_id → { media, total }
 *                        used by the student course grid.
 *
 * Requires authentication for the "student reviewed?" flag.
 * Public stats (average, count) are included in both modes.
 */
require_once __DIR__ . '/valida_sessao.php';
require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$idUsuario = (int)$_SESSION['user_id'];

// ── Mode: lightweight averages for all courses ───────────────
if (isset($_GET['medias'])) {
    $stmt = $conn->prepare("
        SELECT
            cr.course_id,
            ROUND(AVG(cr.rating)::NUMERIC, 1) AS media,
            COUNT(*)                           AS total
        FROM course_reviews cr
        WHERE cr.is_hidden = FALSE
        GROUP BY cr.course_id
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $medias = [];
    foreach ($rows as $r) {
        $medias[(int)$r['course_id']] = [
            'media' => (float)$r['media'],
            'total' => (int)$r['total'],
        ];
    }
    echo json_encode(['success' => true, 'medias' => $medias]);
    exit;
}

// ── Mode: full reviews for one course ───────────────────────
$cursoId = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;

if ($cursoId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'ID do curso inválido.']);
    exit;
}

// Verify course exists
$stmtCurso = $conn->prepare("SELECT id FROM courses WHERE id = ? LIMIT 1");
$stmtCurso->execute([$cursoId]);
if (!$stmtCurso->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Curso não encontrado.']);
    exit;
}

// Aggregate stats
$stmtStats = $conn->prepare("
    SELECT
        ROUND(AVG(rating)::NUMERIC, 1) AS media,
        COUNT(*)                       AS total
    FROM course_reviews
    WHERE course_id = ? AND is_hidden = FALSE
");
$stmtStats->execute([$cursoId]);
$stats = $stmtStats->fetch();

// Reviews list (most recent first, max 50)
$stmtReviews = $conn->prepare("
    SELECT
        cr.id,
        cr.rating,
        cr.comment,
        cr.created_at,
        u.nome AS student_nome
    FROM course_reviews cr
    JOIN users u ON u.id = cr.student_id
    WHERE cr.course_id = ? AND cr.is_hidden = FALSE
    ORDER BY cr.created_at DESC
    LIMIT 50
");
$stmtReviews->execute([$cursoId]);
$reviews = $stmtReviews->fetchAll();

// Has the current student already reviewed this course?
$stmtMinha = $conn->prepare("
    SELECT rating, comment FROM course_reviews
    WHERE course_id = ? AND student_id = ?
    LIMIT 1
");
$stmtMinha->execute([$cursoId, $idUsuario]);
$minhaReview = $stmtMinha->fetch();

// Clean output — cast types for JSON
$reviewsOut = [];
foreach ($reviews as $r) {
    $reviewsOut[] = [
        'id'           => (int)$r['id'],
        'rating'       => (int)$r['rating'],
        'comment'      => $r['comment'],
        'created_at'   => $r['created_at'],
        'student_nome' => $r['student_nome'],
    ];
}

echo json_encode([
    'success'      => true,
    'media'        => $stats['media'] !== null ? (float)$stats['media'] : null,
    'total'        => (int)$stats['total'],
    'reviews'      => $reviewsOut,
    'minha_review' => $minhaReview
        ? ['rating' => (int)$minhaReview['rating'], 'comment' => $minhaReview['comment']]
        : null,
]);
