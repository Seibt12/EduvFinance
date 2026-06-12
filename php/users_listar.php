<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

require_once __DIR__ . '/conexao.php';

$tipo = trim($_GET['tipo'] ?? 'aluno');
if (!in_array($tipo, ['aluno', 'professor'], true)) {
    $tipo = 'aluno';
}

if ($tipo === 'aluno') {
    $stmt = $conn->prepare("
        SELECT
            u.id, u.nome, u.email, u.created_at,
            (SELECT COUNT(*) FROM progress p WHERE p.user_id = u.id AND p.concluido = 1) AS total_concluidas,
            (SELECT COUNT(DISTINCT cl.lesson_id)
             FROM course_enrollments ce
             JOIN course_lessons cl ON cl.course_id = ce.course_id
             WHERE ce.user_id = u.id) AS total_matriculadas
        FROM users u
        WHERE u.tipo = ?
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$tipo]);
} else {
    $stmt = $conn->prepare("
        SELECT id, nome, email, created_at
        FROM users
        WHERE tipo = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$tipo]);
}
$users = $stmt->fetchAll();

echo json_encode(['success' => true, 'users' => $users]);
