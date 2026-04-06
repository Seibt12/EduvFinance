<?php
// ============================================================
// MATRÍCULA — POST /backend/api/courses/enroll.php
//
// Recebe: { course_id, action: 'enroll' | 'unenroll' }
// Apenas alunos podem se matricular em cursos.
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

if (($_SESSION['user_tipo'] ?? '') !== 'aluno') {
    jsonResponse(['success' => false, 'message' => 'Apenas alunos podem se matricular em cursos.'], 403);
}

$corpo   = getJsonBody();
$cursoId = isset($corpo['course_id']) ? (int) $corpo['course_id'] : 0;
$acao    = trim($corpo['action'] ?? '');

if ($cursoId <= 0 || !in_array($acao, ['enroll', 'unenroll'], true)) {
    jsonResponse(['success' => false, 'message' => 'Dados inválidos.'], 400);
}

try {
    $conn      = getConnection();
    $idUsuario = (int) $_SESSION['user_id'];

    // Verifica se o curso existe
    $verificacao = $conn->prepare("SELECT id FROM courses WHERE id = ?");
    $verificacao->execute([$cursoId]);
    if (!$verificacao->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Curso não encontrado.'], 404);
    }

    if ($acao === 'enroll') {
        $stmt = $conn->prepare(
            "INSERT INTO course_enrollments (user_id, course_id) VALUES (?, ?) ON CONFLICT DO NOTHING"
        );
        $stmt->execute([$idUsuario, $cursoId]);
        jsonResponse(['success' => true, 'message' => 'Matrícula realizada com sucesso!']);
    } else {
        $stmt = $conn->prepare(
            "DELETE FROM course_enrollments WHERE user_id = ? AND course_id = ?"
        );
        $stmt->execute([$idUsuario, $cursoId]);
        jsonResponse(['success' => true, 'message' => 'Matrícula cancelada.']);
    }

} catch (PDOException $e) {
    error_log('[EduFinance][courses/enroll] DB error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao processar matrícula: ' . $e->getMessage()], 500);
}
