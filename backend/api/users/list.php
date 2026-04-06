<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAdmin();

$conn = getConnection();

$stmt = $conn->query("
    SELECT
        u.id,
        u.nome,
        u.email,
        u.tipo,
        u.created_at,
        COUNT(p.id)                             AS total_concluidas,
        (SELECT COUNT(*) FROM lessons)           AS total_aulas
    FROM users u
    LEFT JOIN progress p ON p.user_id = u.id AND p.concluido = 1
    WHERE u.tipo = 'aluno'
    GROUP BY u.id, u.nome, u.email, u.tipo, u.created_at
    ORDER BY u.created_at DESC
");

$users = [];
while ($row = $stmt->fetch()) {
    $total   = (int)$row['total_aulas'];
    $done    = (int)$row['total_concluidas'];
    $percent = $total > 0 ? round(($done / $total) * 100) : 0;

    $users[] = [
        'id'               => (int)$row['id'],
        'nome'             => $row['nome'],
        'email'            => $row['email'],
        'tipo'             => $row['tipo'],
        'created_at'       => $row['created_at'],
        'total_concluidas' => $done,
        'total_aulas'      => $total,
        'progresso'        => $percent,
    ];
}

echo json_encode(['success' => true, 'users' => $users]);
