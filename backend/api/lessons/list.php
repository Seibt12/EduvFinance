<?php
// Lista todas as aulas ordenadas por nível (básico → intermediário → avançado).

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

$conn = getConnection();

// FIELD() não existe no PostgreSQL — usa CASE WHEN para ordenar por nível
$stmt = $conn->query("
    SELECT id, titulo, descricao, nivel, created_at
    FROM lessons
    ORDER BY
        CASE nivel
            WHEN 'basico'        THEN 1
            WHEN 'intermediario' THEN 2
            WHEN 'avancado'      THEN 3
        END,
        id ASC
");

$aulas = [];
while ($row = $stmt->fetch()) {
    $aulas[] = [
        'id'         => (int)$row['id'],
        'titulo'     => $row['titulo'],
        'descricao'  => $row['descricao'],
        'nivel'      => $row['nivel'],
        'created_at' => $row['created_at'],
    ];
}

echo json_encode(['success' => true, 'lessons' => $aulas]);
