<?php
/**
 * professor_reviews_listar.php
 * GET — Teacher analytics: reviews only for courses owned by this professor.
 *
 * Security: teacher can only see reviews of their own approved public courses.
 * The ownership chain is: professor_courses.professor_id = session user
 *                         professor_courses.public_course_id = courses.id
 *
 * Response includes per-course stats and the 10 most recent reviews.
 */
require_once __DIR__ . '/valida_professor.php';
require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$idProf = (int)$_SESSION['user_id'];

// Per-course aggregates — only for professor's approved courses
$stmtStats = $conn->prepare("
    SELECT
        c.id                                       AS course_id,
        c.nome                                     AS course_nome,
        c.nivel,
        ROUND(AVG(cr.rating)::NUMERIC, 1)          AS media,
        COUNT(cr.id)                               AS total_reviews
    FROM professor_courses pc
    JOIN courses c         ON c.id  = pc.public_course_id
    LEFT JOIN course_reviews cr ON cr.course_id = c.id AND cr.is_hidden = FALSE
    WHERE pc.professor_id = ?
      AND pc.status       = 'aprovado'
      AND pc.public_course_id IS NOT NULL
    GROUP BY c.id, c.nome, c.nivel
    ORDER BY media DESC NULLS LAST, total_reviews DESC
");
$stmtStats->execute([$idProf]);
$cursoStats = $stmtStats->fetchAll();

// Global aggregate across all professor's courses
$totalReviews  = 0;
$somaMedias    = 0.0;
$cursosComMedia = 0;

$cursosOut = [];
foreach ($cursoStats as $row) {
    $totalReviews += (int)$row['total_reviews'];
    if ($row['media'] !== null) {
        $somaMedias += (float)$row['media'];
        $cursosComMedia++;
    }
    $cursosOut[] = [
        'course_id'     => (int)$row['course_id'],
        'course_nome'   => $row['course_nome'],
        'nivel'         => $row['nivel'],
        'media'         => $row['media'] !== null ? (float)$row['media'] : null,
        'total_reviews' => (int)$row['total_reviews'],
    ];
}

$mediaGeral = $cursosComMedia > 0 ? round($somaMedias / $cursosComMedia, 1) : null;

// 10 most recent reviews across all professor's courses
$stmtRecentes = $conn->prepare("
    SELECT
        cr.id,
        cr.rating,
        cr.comment,
        cr.created_at,
        c.nome  AS course_nome,
        u.nome  AS student_nome
    FROM course_reviews cr
    JOIN courses c ON c.id = cr.course_id
    JOIN professor_courses pc
         ON pc.public_course_id = c.id
        AND pc.professor_id     = ?
        AND pc.status           = 'aprovado'
    JOIN users u ON u.id = cr.student_id
    WHERE cr.is_hidden = FALSE
    ORDER BY cr.created_at DESC
    LIMIT 10
");
$stmtRecentes->execute([$idProf]);
$recentes = $stmtRecentes->fetchAll();

$recentesOut = [];
foreach ($recentes as $r) {
    $recentesOut[] = [
        'id'           => (int)$r['id'],
        'rating'       => (int)$r['rating'],
        'comment'      => $r['comment'],
        'created_at'   => $r['created_at'],
        'course_nome'  => $r['course_nome'],
        'student_nome' => $r['student_nome'],
    ];
}

echo json_encode([
    'success'      => true,
    'media_geral'  => $mediaGeral,
    'total_reviews'=> $totalReviews,
    'cursos'       => $cursosOut,
    'recentes'     => $recentesOut,
]);
