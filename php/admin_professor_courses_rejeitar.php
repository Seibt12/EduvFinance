<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_cursos.html');
    exit;
}

$id      = (int)($_POST['id'] ?? 0);
$comment = trim($_POST['review_comment'] ?? '');
$redir   = '../home/admin_cursos.html';

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

$stmt = $conn->prepare("SELECT public_course_id FROM professor_courses WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$oferta = $stmt->fetch();

if (!$oferta) {
    header('Location: ' . $redir . '?erro=' . urlencode('Oferta de curso não encontrada.'));
    exit;
}

$conn->beginTransaction();
removePublicCourse($conn, !empty($oferta['public_course_id']) ? (int)$oferta['public_course_id'] : null);
$conn->prepare("UPDATE professor_courses SET status='rejeitado', public_course_id=NULL, review_comment=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
     ->execute([$comment, $id]);
$conn->commit();

header('Location: ' . $redir . '?sucesso=' . urlencode('Curso rejeitado.'));
exit;
