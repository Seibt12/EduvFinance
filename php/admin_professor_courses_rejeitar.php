<?php
require_once __DIR__ . '/valida_admin.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

$id      = (int)($_POST['id'] ?? 0);
$comment = trim($_POST['review_comment'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']); exit;
}
if ($comment === '') {
    echo json_encode(['success' => false, 'error' => 'Informe o motivo da rejeição para o professor saber o que corrigir']); exit;
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/aprovacao_utils.php';

$stmt = $conn->prepare("SELECT public_course_id, status FROM professor_courses WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$oferta = $stmt->fetch();

if (!$oferta) {
    echo json_encode(['success' => false, 'error' => 'Oferta de curso não encontrada']); exit;
}
if ($oferta['status'] !== 'pendente') {
    echo json_encode(['success' => false, 'error' => 'Apenas cursos com status pendente podem ser rejeitados']); exit;
}

$conn->beginTransaction();
removePublicCourse($conn, !empty($oferta['public_course_id']) ? (int)$oferta['public_course_id'] : null);
$conn->prepare("
    UPDATE professor_courses
    SET status='rejeitado', public_course_id=NULL, review_comment=?, updated_at=CURRENT_TIMESTAMP
    WHERE id=?
")->execute([$comment, $id]);
// Revert lessons to rejected too
$conn->prepare("
    UPDATE professor_lessons
    SET status='rejeitado', updated_at=CURRENT_TIMESTAMP
    WHERE professor_course_id=?
")->execute([$id]);
$conn->commit();

echo json_encode(['success' => true, 'message' => 'Curso rejeitado. O professor poderá corrigir e reenviar.']);
