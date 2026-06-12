<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("
    SELECT pc.*, u.nome AS professor_nome, u.email AS professor_email
    FROM professor_courses pc
    JOIN users u ON u.id = pc.professor_id
    WHERE pc.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$course = $stmt->fetch();

if (!$course) {
    echo json_encode(['success' => false, 'message' => 'Oferta não encontrada.']);
    exit;
}

// Fetch lessons using new schema (professor_course_id)
$stmtLessons = $conn->prepare("
    SELECT id, titulo, descricao, video_link, attachment_path, attachment_name, order_index, duracao, content
    FROM professor_lessons
    WHERE professor_course_id = ?
    ORDER BY order_index ASC, id ASC
");
$stmtLessons->execute([$id]);
$lessons = $stmtLessons->fetchAll();

// Legacy fallback: try junction table for pre-migration data
if (empty($lessons)) {
    $stmtLegacy = $conn->prepare("
        SELECT pl.id, pl.titulo, pl.descricao, pl.video_link, pl.attachment_path, pl.attachment_name
        FROM professor_course_lessons pcl
        JOIN professor_lessons pl ON pl.id = pcl.professor_lesson_id
        WHERE pcl.professor_course_id = ?
        ORDER BY pl.titulo ASC
    ");
    $stmtLegacy->execute([$id]);
    $lessons = $stmtLegacy->fetchAll();
}

$course['lessons'] = $lessons;

echo json_encode(['success' => true, 'course' => $course]);
