<?php
require_once __DIR__ . '/valida_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/admin_alunos.html');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: ../home/admin_alunos.html?erro=' . urlencode('ID inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id, email, tipo FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header('Location: ../home/admin_alunos.html?erro=' . urlencode('Usuário não encontrado.'));
    exit;
}

$redir = match($usuario['tipo']) {
    'professor' => '../home/admin_professores.html',
    default     => '../home/admin_alunos.html',
};
$label = match($usuario['tipo']) {
    'professor' => 'Professor',
    default     => 'Aluno',
};
if ($usuario['tipo'] === 'admin') {
    header('Location: ' . $redir . '?erro=' . urlencode('Contas de administrador não podem ser excluídas.'));
    exit;
}
if ($id === (int)$_SESSION['user_id']) {
    header('Location: ' . $redir . '?erro=' . urlencode('Você não pode excluir a própria conta.'));
    exit;
}

try {
    $conn->beginTransaction();

    // A professor's PUBLIC courses/lessons are not linked back by a foreign key,
    // so the users-row cascade would leave them (and their reviews, enrollments
    // and student progress) orphaned in the catalog. Purge them explicitly first.
    if ($usuario['tipo'] === 'professor') {
        require_once __DIR__ . '/aprovacao_utils.php';
        removeProfessorPublicArtifacts($conn, $id);
    }

    // Deleting the user cascades professor_courses + professor_lessons (and, for
    // students, their own progress/enrollments/reviews via user_id FKs).
    $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    header('Location: ' . $redir . '?erro=' . urlencode('Erro ao excluir. Tente novamente.'));
    exit;
}

header('Location: ' . $redir . '?sucesso=' . urlencode($label . ' excluído com sucesso.'));
exit;
