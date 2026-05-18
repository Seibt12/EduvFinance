<?php
session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
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

$totalAulas = (int)$conn->query("SELECT COUNT(*) FROM lessons")->fetchColumn();

if ($tipo === 'aluno') {
    $stmt = $conn->prepare("
        SELECT
            u.id, u.nome, u.email, u.created_at,
            COUNT(p.id) AS total_concluidas
        FROM users u
        LEFT JOIN progress p ON p.user_id = u.id AND p.concluido = 1
        WHERE u.tipo = ?
        GROUP BY u.id, u.nome, u.email, u.created_at
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

echo json_encode(['success' => true, 'users' => $users, 'totalAulas' => $totalAulas]);
