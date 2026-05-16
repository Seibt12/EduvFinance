<?php
session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

require_once __DIR__ . '/conexao.php';

$totalAulas = (int)$conn->query("SELECT COUNT(*) FROM lessons")->fetchColumn();

$stmt = $conn->query("
    SELECT
        u.id, u.nome, u.email, u.created_at,
        COUNT(p.id) AS total_concluidas
    FROM users u
    LEFT JOIN progress p ON p.user_id = u.id AND p.concluido = 1
    WHERE u.tipo = 'aluno'
    GROUP BY u.id, u.nome, u.email, u.created_at
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

echo json_encode(['success' => true, 'users' => $users, 'totalAulas' => $totalAulas]);
