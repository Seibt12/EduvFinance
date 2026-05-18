<?php
require_once __DIR__ . '/valida_professor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/professor.html');
    exit;
}

$id         = (int)($_POST['id'] ?? 0);
$titulo     = trim($_POST['titulo'] ?? '');
$descricao  = trim($_POST['descricao'] ?? '');
$nivel      = trim($_POST['nivel'] ?? '');
$videoLink  = trim($_POST['video_link'] ?? '');
$redir      = '../home/professor.html';

if ($id <= 0 || $titulo === '' || $descricao === '' || $nivel === '') {
    header('Location: ' . $redir . '?erro=' . urlencode('Dados inválidos ou incompletos.'));
    exit;
}
if (!in_array($nivel, ['basico', 'intermediario', 'avancado'], true)) {
    header('Location: ' . $redir . '?erro=' . urlencode('Nível inválido.'));
    exit;
}
if ($videoLink !== '' && !filter_var($videoLink, FILTER_VALIDATE_URL)) {
    header('Location: ' . $redir . '?erro=' . urlencode('Link de vídeo inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT public_lesson_id, attachment_path FROM professor_lessons WHERE id = ? AND professor_id = ? LIMIT 1");
$stmt->execute([$id, $professorId]);
$lesson = $stmt->fetch();
if (!$lesson) {
    header('Location: ' . $redir . '?erro=' . urlencode('Oferta de aula não encontrada.'));
    exit;
}

$attachmentPath = null;
$attachmentName = null;
if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $allowedExt = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip'];
    $originalName = basename($_FILES['attachment']['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        header('Location: ' . $redir . '?erro=' . urlencode('Tipo de arquivo não permitido.'));
        exit;
    }
    if ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
        header('Location: ' . $redir . '?erro=' . urlencode('Arquivo muito grande. Máximo 10MB.'));
        exit;
    }

    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $originalName);
    $filename = uniqid('lesson_attach_', true) . '_' . $safeName;
    $destination = $uploadsDir . '/' . $filename;
    if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $destination)) {
        header('Location: ' . $redir . '?erro=' . urlencode('Falha ao enviar o arquivo.'));
        exit;
    }
    $attachmentPath = 'uploads/' . $filename;
    $attachmentName = $originalName;
}

$conn->beginTransaction();
if (!empty($lesson['public_lesson_id'])) {
    $conn->prepare("DELETE FROM lessons WHERE id = ?")->execute([(int)$lesson['public_lesson_id']]);
    $conn->prepare("UPDATE professor_lessons SET public_lesson_id = NULL WHERE id = ?")->execute([$id]);
}

$updateFields = "titulo=?, descricao=?, nivel=?, video_link=?, updated_at=CURRENT_TIMESTAMP, status='pendente', review_comment=NULL";
$params = [$titulo, $descricao, $nivel, $videoLink ?: null];
if ($attachmentPath !== null) {
    $updateFields .= ", attachment_path=?, attachment_name=?";
    $params[] = $attachmentPath;
    $params[] = $attachmentName;
}
$params[] = $id;
$params[] = $professorId;
$conn->prepare("UPDATE professor_lessons SET $updateFields WHERE id = ? AND professor_id = ?")
     ->execute($params);

$conn->commit();

header('Location: ' . $redir . '?sucesso=' . urlencode('Oferta de aula atualizada com sucesso.'));
exit;
