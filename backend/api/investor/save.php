<?php
// ============================================================
// SALVAR PERFIL INVESTIDOR — POST /backend/api/investor/save.php
//
// Recebe: { respostas: { q1: 'a', q2: 'b', q3: 'c', q4: 'a', q5: 'b' } }
// Calcula o perfil e salva (ou atualiza) no banco.
//
// Pontuação: a=1, b=2, c=3
//   5-8  pontos → conservador
//   9-12 pontos → moderado
//  13-15 pontos → agressivo
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

if (($_SESSION['user_tipo'] ?? '') !== 'aluno') {
    jsonResponse(['success' => false, 'message' => 'Apenas alunos podem realizar o teste.'], 403);
}

$corpo     = getJsonBody();
$respostas = $corpo['respostas'] ?? [];

// Valida que todas as 5 perguntas foram respondidas com a, b ou c
$perguntas     = ['q1', 'q2', 'q3', 'q4', 'q5'];
$opcoesValidas = ['a', 'b', 'c'];

foreach ($perguntas as $q) {
    if (!isset($respostas[$q]) || !in_array($respostas[$q], $opcoesValidas, true)) {
        jsonResponse(
            ['success' => false, 'message' => "Resposta inválida ou ausente para a pergunta {$q}."],
            400
        );
    }
}

// Calcula a pontuação total (a=1, b=2, c=3)
$pontos     = ['a' => 1, 'b' => 2, 'c' => 3];
$pontuacao  = 0;
foreach ($perguntas as $q) {
    $pontuacao += $pontos[$respostas[$q]];
}

// Determina o perfil com base na pontuação
if ($pontuacao <= 8) {
    $perfil = 'conservador';
} elseif ($pontuacao <= 12) {
    $perfil = 'moderado';
} else {
    $perfil = 'agressivo';
}

try {
    $idUsuario     = (int) $_SESSION['user_id'];
    $respostasJson = json_encode($respostas, JSON_UNESCAPED_UNICODE);
    $conn          = getConnection();

    // ON CONFLICT (user_id) → atualiza se já existe, cria se não existe
    $stmt = $conn->prepare("
        INSERT INTO investor_profile (user_id, perfil, respostas, pontuacao)
        VALUES (?, ?, ?, ?)
        ON CONFLICT (user_id)
        DO UPDATE SET
            perfil     = EXCLUDED.perfil,
            respostas  = EXCLUDED.respostas,
            pontuacao  = EXCLUDED.pontuacao,
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$idUsuario, $perfil, $respostasJson, $pontuacao]);

    jsonResponse([
        'success'   => true,
        'message'   => 'Perfil de investidor salvo com sucesso.',
        'perfil'    => $perfil,
        'pontuacao' => $pontuacao,
    ]);

} catch (PDOException $e) {
    error_log('[EduFinance][investor/save] DB error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao salvar perfil: ' . $e->getMessage()], 500);
}
