<?php
// Lista todos os alunos com o progresso de cada um.
// Apenas administradores têm acesso a este endpoint.

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

$alunos = [];
while ($row = $stmt->fetch()) {
    $totalAulas  = (int)$row['total_aulas'];
    $concluidas  = (int)$row['total_concluidas'];
    $percentual  = $totalAulas > 0 ? round(($concluidas / $totalAulas) * 100) : 0;

    $alunos[] = [
        'id'               => (int)$row['id'],
        'nome'             => $row['nome'],
        'email'            => $row['email'],
        'tipo'             => $row['tipo'],
        'created_at'       => $row['created_at'],
        'total_concluidas' => $concluidas,
        'total_aulas'      => $totalAulas,
        'progresso'        => $percentual,
    ];
}

echo json_encode(['success' => true, 'users' => $alunos]);
