<?php
require_once __DIR__ . '/valida_professor.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

$nome      = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$nivel     = trim($_POST['nivel'] ?? '');
$subtitulo = trim($_POST['subtitulo'] ?? '');
$categoria = trim($_POST['categoria'] ?? '');

if ($nome === '' || $nivel === '') {
    echo json_encode(['success' => false, 'error' => 'Nome e nível são obrigatórios']);
    exit;
}
if (!in_array($nivel, ['basico', 'intermediario', 'avancado'], true)) {
    echo json_encode(['success' => false, 'error' => 'Nível inválido']); exit;
}

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

// Handle thumbnail upload
$thumbnailPath = null;
if (!empty($_FILES['thumbnail']['name']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) {
        echo json_encode(['success' => false, 'error' => 'Formato de imagem inválido (use JPG, PNG ou WebP)']); exit;
    }
    if ($_FILES['thumbnail']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Imagem muito grande (máx. 5MB)']); exit;
    }
    $uploadDir = __DIR__ . '/../uploads/thumbnails/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $filename = 'thumb_' . uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $filename);
    $thumbnailPath = 'uploads/thumbnails/' . $filename;
}

$stmt = $conn->prepare("
    INSERT INTO professor_courses
        (professor_id, nome, subtitulo, descricao, nivel, categoria, thumbnail_path, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')
    RETURNING id
");
$stmt->execute([
    $professorId,
    $nome,
    $subtitulo ?: null,
    $descricao,
    $nivel,
    $categoria ?: null,
    $thumbnailPath,
]);
$courseId = (int)$stmt->fetchColumn();

echo json_encode(['success' => true, 'course_id' => $courseId]);
