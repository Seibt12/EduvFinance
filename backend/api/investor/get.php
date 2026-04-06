<?php
// ============================================================
// PERFIL INVESTIDOR — GET /backend/api/investor/get.php
//
// Retorna o perfil de investidor do aluno logado.
// Se ainda não fez o quiz, retorna { profile: null }.
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

try {
    $idUsuario = (int) $_SESSION['user_id'];
    $conn      = getConnection();

    $stmt = $conn->prepare(
        "SELECT perfil, respostas, pontuacao, updated_at FROM investor_profile WHERE user_id = ?"
    );
    $stmt->execute([$idUsuario]);
    $row = $stmt->fetch();

    if (!$row) {
        // Aluno ainda não fez o quiz — retorna null (não é um erro)
        jsonResponse(['success' => true, 'profile' => null]);
    }

    jsonResponse([
        'success' => true,
        'profile' => [
            'perfil'     => $row['perfil'],
            'pontuacao'  => (int) $row['pontuacao'],
            'respostas'  => json_decode($row['respostas'], true),
            'updated_at' => $row['updated_at'],
        ],
    ]);

} catch (PDOException $e) {
    error_log('[EduFinance][investor/get] DB error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao carregar perfil: ' . $e->getMessage()], 500);
}
