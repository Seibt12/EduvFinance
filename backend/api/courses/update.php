<?php
// ============================================================
// ATUALIZAR CURSO — POST /backend/api/courses/update.php
//
// Recebe: { id, nome, descricao, nivel, lesson_ids: [1, 2, 3] }
// Apenas admin. Re-vincula as aulas (apaga antigas e insere novas).
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$body      = getJsonBody();
$id        = isset($body['id']) ? (int) $body['id'] : 0;
$nome      = trim($body['nome']      ?? '');
$descricao = trim($body['descricao'] ?? '');
$nivel     = trim($body['nivel']     ?? '');
$lessonIds = array_map('intval', (array) ($body['lesson_ids'] ?? []));

if ($id <= 0 || !$nome || !$descricao || !$nivel) {
    jsonResponse(['success' => false, 'message' => 'Dados inválidos ou incompletos.'], 400);
}

if (!in_array($nivel, ['basico', 'intermediario', 'avancado'], true)) {
    jsonResponse(['success' => false, 'message' => 'Nível inválido.'], 400);
}

try {
    $conn = getConnection();

    // Verifica se o curso existe
    $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Curso não encontrado.'], 404);
    }

    $conn->beginTransaction();

    // Atualiza os dados do curso
    $conn->prepare("UPDATE courses SET nome = ?, descricao = ?, nivel = ? WHERE id = ?")
         ->execute([$nome, $descricao, $nivel, $id]);

    // Remove as aulas antigas e insere as novas
    $conn->prepare("DELETE FROM course_lessons WHERE course_id = ?")->execute([$id]);

    if (!empty($lessonIds)) {
        $stmtLink = $conn->prepare(
            "INSERT INTO course_lessons (course_id, lesson_id) VALUES (?, ?) ON CONFLICT DO NOTHING"
        );
        foreach ($lessonIds as $lid) {
            if ($lid > 0) $stmtLink->execute([$id, $lid]);
        }
    }

    $conn->commit();
    jsonResponse(['success' => true, 'message' => 'Curso atualizado com sucesso.']);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('[EduFinance][courses/update] DB error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao atualizar curso: ' . $e->getMessage()], 500);
}
