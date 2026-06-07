<?php
ob_start();
require_once __DIR__ . '/valida_professor.php';
ob_end_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

$courseId  = (int)($_POST['course_id'] ?? 0);
$titulo    = trim($_POST['titulo'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$videoLink = trim($_POST['video_link'] ?? '');
$content   = trim($_POST['content'] ?? '');
$duracao   = (int)($_POST['duracao'] ?? 0) ?: null;

if (!$courseId || $titulo === '') {
    echo json_encode(['success' => false, 'error' => 'Título da aula e curso são obrigatórios']); exit;
}

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

// Verify ownership and editable state
$stmt = $conn->prepare("SELECT id, nivel, status FROM professor_courses WHERE id = ? AND professor_id = ?");
$stmt->execute([$courseId, $professorId]);
$course = $stmt->fetch();

if (!$course) {
    echo json_encode(['success' => false, 'error' => 'Curso não encontrado']); exit;
}
if ($course['status'] === 'pendente') {
    echo json_encode(['success' => false, 'error' => 'Não é possível adicionar aulas a cursos que estão em análise']); exit;
}
if ($course['status'] === 'aprovado') {
    echo json_encode(['success' => false, 'error' => 'Não é possível adicionar aulas a cursos aprovados']); exit;
}

// File upload
$attachmentPath = null;
$attachmentName = null;
if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $allowedExt = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip'];
    $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) {
        echo json_encode(['success' => false, 'error' => 'Formato de arquivo inválido. Use: PDF, DOC, DOCX, PPT, PPTX ou ZIP']); exit;
    }
    if ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Arquivo muito grande (máx. 10MB)']); exit;
    }
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $safeName  = preg_replace('/[^a-z0-9._-]/i', '_', $_FILES['attachment']['name']);
    $filename  = 'lesson_attach_' . uniqid() . '_' . $safeName;
    if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $filename)) {
        echo json_encode(['success' => false, 'error' => 'Falha ao salvar o arquivo anexado']); exit;
    }
    $attachmentPath = 'uploads/' . $filename;
    $attachmentName = $_FILES['attachment']['name'];
}

try {
    // Get next order index for this course
    $stmt = $conn->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 FROM professor_lessons WHERE professor_course_id = ?");
    $stmt->execute([$courseId]);
    $orderIndex = (int)$stmt->fetchColumn();

    $stmt = $conn->prepare("
        INSERT INTO professor_lessons
            (professor_course_id, professor_id, titulo, descricao, nivel, video_link, content,
             attachment_path, attachment_name, duracao, order_index, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')
        RETURNING id
    ");
    $stmt->execute([
        $courseId,
        $professorId,
        $titulo,
        $descricao,
        $course['nivel'],
        $videoLink ?: null,
        $content ?: null,
        $attachmentPath,
        $attachmentName,
        $duracao,
        $orderIndex,
    ]);
    $lessonId = (int)$stmt->fetchColumn();

    echo json_encode(['success' => true, 'lesson_id' => $lessonId, 'order_index' => $orderIndex, 'attachment_path' => $attachmentPath, 'attachment_name' => $attachmentName]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
