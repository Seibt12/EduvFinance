<?php
// ============================================================
// EXCLUIR CURSO — POST /backend/api/courses/delete.php
//
// Recebe: { id }
// Apenas admin. ON DELETE CASCADE remove automaticamente:
//   course_lessons e course_enrollments relacionados.
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$body = getJsonBody();
$id   = isset($body['id']) ? (int) $body['id'] : 0;

if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => 'ID inválido.'], 400);
}

try {
    $conn = getConnection();

    $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Curso não encontrado.'], 404);
    }

    // ON DELETE CASCADE cuida de course_lessons e course_enrollments automaticamente
    $conn->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);

    jsonResponse(['success' => true, 'message' => 'Curso excluído com sucesso.']);

} catch (PDOException $e) {
    error_log('[EduFinance][courses/delete] DB error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao excluir curso: ' . $e->getMessage()], 500);
}
