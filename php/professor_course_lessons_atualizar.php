<?php
require_once __DIR__ . '/valida_professor.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

$lessonId  = (int)($_POST['id'] ?? 0);
$titulo    = trim($_POST['titulo'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$videoLink = trim($_POST['video_link'] ?? '');
$content   = trim($_POST['content'] ?? '');
$duracao   = (int)($_POST['duracao'] ?? 0) ?: null;

if (!$lessonId || $titulo === '') {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']); exit;
}

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

// Verify lesson ownership via course
$stmt = $conn->prepare("
    SELECT pl.id, pl.attachment_path, pc.status AS course_status
    FROM professor_lessons pl
    JOIN professor_courses pc ON pc.id = pl.professor_course_id
    WHERE pl.id = ? AND pl.professor_id = ?
");
$stmt->execute([$lessonId, $professorId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    echo json_encode(['success' => false, 'error' => 'Aula não encontrada']); exit;
}
if ($lesson['course_status'] === 'pendente') {
    echo json_encode(['success' => false, 'error' => 'Não é possível editar aulas de cursos em análise']); exit;
}

// Handle file upload
$attachmentPath = $lesson['attachment_path'];
$attachmentName = null;
if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $allowedExt = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip'];
    $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) {
        echo json_encode(['success' => false, 'error' => 'Formato de arquivo inválido']); exit;
    }
    if ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Arquivo muito grande (máx. 10MB)']); exit;
    }
    $uploadDir = __DIR__ . '/../uploads/';
    $safeName  = preg_replace('/[^a-z0-9._-]/i', '_', $_FILES['attachment']['name']);
    $filename  = 'lesson_attach_' . uniqid() . '_' . $safeName;
    move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $filename);
    $attachmentPath = 'uploads/' . $filename;
    $attachmentName = $_FILES['attachment']['name'];
}

// Get current attachment_name if not replacing
if ($attachmentName === null) {
    $stmt = $conn->prepare("SELECT attachment_name FROM professor_lessons WHERE id=?");
    $stmt->execute([$lessonId]);
    $attachmentName = $stmt->fetchColumn() ?: null;
}

$stmt = $conn->prepare("
    UPDATE professor_lessons
    SET titulo=?, descricao=?, video_link=?, content=?, attachment_path=?, attachment_name=?, duracao=?, updated_at=CURRENT_TIMESTAMP
    WHERE id=?
");
$stmt->execute([$titulo, $descricao, $videoLink ?: null, $content ?: null, $attachmentPath, $attachmentName, $duracao, $lessonId]);

echo json_encode(['success' => true]);
