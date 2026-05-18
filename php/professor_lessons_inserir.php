<?php
require_once __DIR__ . '/valida_professor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/professor.html');
    exit;
}

$titulo      = trim($_POST['titulo'] ?? '');
$descricao   = trim($_POST['descricao'] ?? '');
$nivel       = trim($_POST['nivel'] ?? '');
$videoLink   = trim($_POST['video_link'] ?? '');
$redir       = '../home/professor.html';

if ($titulo === '' || $descricao === '' || $nivel === '') {
    header('Location: ' . $redir . '?erro=' . urlencode('Preencha todos os campos obrigatórios.'));
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

require_once __DIR__ . '/conexao.php';
$professorId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("INSERT INTO professor_lessons (professor_id, titulo, descricao, nivel, video_link, attachment_path, attachment_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$professorId, $titulo, $descricao, $nivel, $videoLink ?: null, $attachmentPath, $attachmentName]);

header('Location: ' . $redir . '?sucesso=' . urlencode('Oferta de aula criada com sucesso.'));
exit;
