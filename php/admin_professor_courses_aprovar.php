<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_cursos.html');
    exit;
}

$id    = (int)($_POST['id'] ?? 0);
$redir = '../home/admin_cursos.html';

if ($id <= 0) {
    header('Location: ' . $redir . '?erro=' . urlencode('ID inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/aprovacao_utils.php';

$stmt = $conn->prepare("SELECT * FROM professor_courses WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$oferta = $stmt->fetch();

if (!$oferta) {
    header('Location: ' . $redir . '?erro=' . urlencode('Oferta de curso não encontrada.'));
    exit;
}

$conn->beginTransaction();
$publicId = syncPublicCourse($conn, $oferta);
$conn->prepare("UPDATE professor_courses SET status='aprovado', public_course_id=?, review_comment=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=?")
     ->execute([$publicId, $id]);
syncCourseLessonsFromProfessor($conn, $id, $publicId);
$conn->commit();

header('Location: ' . $redir . '?sucesso=' . urlencode('Curso aprovado e publicado com sucesso.'));
exit;
