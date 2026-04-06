<?php
// ============================================================
// CRIAR CURSO — POST /backend/api/courses/create.php
//
// Recebe: { nome, descricao, nivel, lesson_ids: [1, 2, 3] }
// Apenas admin. Usa transação para garantir consistência.
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$body      = getJsonBody();
$nome      = trim($body['nome']      ?? '');
$descricao = trim($body['descricao'] ?? '');
$nivel     = trim($body['nivel']     ?? '');
$lessonIds = array_map('intval', (array) ($body['lesson_ids'] ?? []));

if (!$nome || !$descricao || !$nivel) {
    jsonResponse(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.'], 400);
}

if (!in_array($nivel, ['basico', 'intermediario', 'avancado'], true)) {
    jsonResponse(['success' => false, 'message' => 'Nível inválido.'], 400);
}

try {
    $conn = getConnection();
    $conn->beginTransaction();

    // Cria o curso e pega o ID gerado
    $stmt = $conn->prepare(
        "INSERT INTO courses (nome, descricao, nivel) VALUES (?, ?, ?) RETURNING id"
    );
    $stmt->execute([$nome, $descricao, $nivel]);
    $courseId = (int) $stmt->fetchColumn();

    // Vincula as aulas ao curso (se houver)
    if (!empty($lessonIds)) {
        $stmtLink = $conn->prepare(
            "INSERT INTO course_lessons (course_id, lesson_id) VALUES (?, ?) ON CONFLICT DO NOTHING"
        );
        foreach ($lessonIds as $lid) {
            if ($lid > 0) $stmtLink->execute([$courseId, $lid]);
        }
    }

    $conn->commit();
    jsonResponse(['success' => true, 'message' => 'Curso criado com sucesso.', 'id' => $courseId]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('[EduFinance][courses/create] DB error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao criar curso: ' . $e->getMessage()], 500);
}
