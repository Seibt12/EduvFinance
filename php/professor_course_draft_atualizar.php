<?php
require_once __DIR__ . '/valida_professor.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

$id        = (int)($_POST['id'] ?? 0);
$nome      = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$nivel     = trim($_POST['nivel'] ?? '');
$subtitulo = trim($_POST['subtitulo'] ?? '');
$categoria = trim($_POST['categoria'] ?? '');

if (!$id || $nome === '' || $nivel === '') {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']); exit;
}
if (!in_array($nivel, ['basico', 'intermediario', 'avancado'], true)) {
    echo json_encode(['success' => false, 'error' => 'Nível inválido']); exit;
}

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id, thumbnail_path, status FROM professor_courses WHERE id = ? AND professor_id = ?");
$stmt->execute([$id, $professorId]);
$course = $stmt->fetch();

if (!$course) {
    echo json_encode(['success' => false, 'error' => 'Curso não encontrado']); exit;
}
if ($course['status'] === 'pendente') {
    echo json_encode(['success' => false, 'error' => 'Cursos em análise não podem ser editados. Aguarde a revisão do administrador.']); exit;
}

// Handle thumbnail
$thumbnailPath = $course['thumbnail_path'];
if (!empty($_FILES['thumbnail']['name']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) {
        echo json_encode(['success' => false, 'error' => 'Formato de imagem inválido']); exit;
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
    UPDATE professor_courses
    SET nome=?, subtitulo=?, descricao=?, nivel=?, categoria=?, thumbnail_path=?, updated_at=CURRENT_TIMESTAMP
    WHERE id=?
");
$stmt->execute([$nome, $subtitulo ?: null, $descricao, $nivel, $categoria ?: null, $thumbnailPath, $id]);

// If rejected and teacher edits, revert to draft
if ($course['status'] === 'rejeitado') {
    $conn->prepare("UPDATE professor_courses SET status='draft' WHERE id=?")->execute([$id]);
}

echo json_encode(['success' => true]);
