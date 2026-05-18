<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_aulas.html');
    exit;
}

$id      = (int)($_POST['id'] ?? 0);
$comment = trim($_POST['review_comment'] ?? '');
$redir   = '../home/admin_aulas.html';

if ($id <= 0) {
    header('Location: ' . $redir . '?erro=' . urlencode('ID inválido.'));
    exit;
}
if ($comment === '') {
    header('Location: ' . $redir . '?erro=' . urlencode('Informe o motivo da rejeição.'));
    exit;
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/aprovacao_utils.php';

$stmt = $conn->prepare("SELECT public_lesson_id FROM professor_lessons WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$oferta = $stmt->fetch();

if (!$oferta) {
    header('Location: ' . $redir . '?erro=' . urlencode('Oferta de aula não encontrada.'));
    exit;
}

$conn->beginTransaction();
removePublicLesson($conn, !empty($oferta['public_lesson_id']) ? (int)$oferta['public_lesson_id'] : null);
$conn->prepare("UPDATE professor_lessons SET status='rejeitado', public_lesson_id=NULL, review_comment=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
     ->execute([$comment, $id]);
$conn->commit();

header('Location: ' . $redir . '?sucesso=' . urlencode('Aula rejeitada.'));
exit;
