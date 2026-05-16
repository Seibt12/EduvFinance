<?php
require_once __DIR__ . '/valida_sessao.php';
require_once __DIR__ . '/conexao.php';

$idUsuario = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT l.id, l.titulo, l.nivel, COALESCE(p.concluido, 0) AS concluido
    FROM lessons l
    LEFT JOIN progress p ON p.lesson_id = l.id AND p.user_id = ?
    ORDER BY CASE l.nivel WHEN 'basico' THEN 1 WHEN 'intermediario' THEN 2 WHEN 'avancado' THEN 3 END, l.titulo ASC
");
$stmt->execute([$idUsuario]);
$progresso = $stmt->fetchAll();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'lessons' => $progresso]);
