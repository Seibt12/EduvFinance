<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_aulas.html');
    exit;
}

$id    = (int)($_POST['id'] ?? 0);
$redir = '../home/admin_aulas.html';

if ($id <= 0) {
    header('Location: ' . $redir . '?erro=' . urlencode('ID inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/aprovacao_utils.php';

$stmt = $conn->prepare("SELECT * FROM professor_lessons WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$oferta = $stmt->fetch();

if (!$oferta) {
    header('Location: ' . $redir . '?erro=' . urlencode('Oferta de aula não encontrada.'));
    exit;
}

$conn->beginTransaction();
$publicId = syncPublicLesson($conn, $oferta);
$conn->prepare("UPDATE professor_lessons SET status='aprovado', public_lesson_id=?, review_comment=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=?")
     ->execute([$publicId, $id]);
linkApprovedLessonToCourses($conn, $id, $publicId);
$conn->commit();

header('Location: ' . $redir . '?sucesso=' . urlencode('Aula aprovada e publicada com sucesso.'));
exit;
