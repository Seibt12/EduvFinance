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

$corpo     = getJsonBody();
$id        = isset($corpo['id'])       ? (int) $corpo['id']        : 0;
$nome      = trim($corpo['nome']      ?? '');
$descricao = trim($corpo['descricao'] ?? '');
$nivel     = trim($corpo['nivel']     ?? '');
$idsAulas  = array_map('intval', (array) ($corpo['lesson_ids'] ?? []));

if ($id <= 0 || !$nome || !$descricao || !$nivel) {
    jsonResponse(['success' => false, 'message' => 'Dados inválidos ou incompletos.'], 400);
}

if (!in_array($nivel, ['basico', 'intermediario', 'avancado'], true)) {
    jsonResponse(['success' => false, 'message' => 'Nível inválido.'], 400);
}

try {
    $conn = getConnection();

    // Verifica se o curso existe
    $verificacao = $conn->prepare("SELECT id FROM courses WHERE id = ?");
    $verificacao->execute([$id]);
    if (!$verificacao->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Curso não encontrado.'], 404);
    }

    $conn->beginTransaction();

    // Atualiza os dados do curso
    $conn->prepare("UPDATE courses SET nome = ?, descricao = ?, nivel = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
         ->execute([$nome, $descricao, $nivel, $id]);

    // Remove as aulas antigas e insere as novas
    $conn->prepare("DELETE FROM course_lessons WHERE course_id = ?")->execute([$id]);

    if (!empty($idsAulas)) {
        $stmtVinculo = $conn->prepare(
            "INSERT INTO course_lessons (course_id, lesson_id) VALUES (?, ?) ON CONFLICT DO NOTHING"
        );
        foreach ($idsAulas as $aulaId) {
            if ($aulaId > 0) $stmtVinculo->execute([$id, $aulaId]);
        }
    }

    $conn->commit();
    jsonResponse(['success' => true, 'message' => 'Curso atualizado com sucesso.']);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('[EduFinance][courses/update] DB error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao atualizar curso: ' . $e->getMessage()], 500);
}
