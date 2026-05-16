<?php
require_once __DIR__ . '/valida_sessao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/aluno_simulador.html');
    exit;
}
if ($_SESSION['user_tipo'] !== 'aluno') {
    header('Location: ../home/aluno_simulador.html?erro=' . urlencode('Apenas alunos podem realizar o teste.'));
    exit;
}

$perguntas     = ['q1', 'q2', 'q3', 'q4', 'q5'];
$opcoesValidas = ['a', 'b', 'c'];
$respostas     = [];

foreach ($perguntas as $q) {
    $val = $_POST[$q] ?? '';
    if (!in_array($val, $opcoesValidas, true)) {
        header('Location: ../home/aluno_simulador.html?erro=' . urlencode("Resposta inválida para a pergunta {$q}."));
        exit;
    }
    $respostas[$q] = $val;
}

$pontos    = ['a' => 1, 'b' => 2, 'c' => 3];
$pontuacao = 0;
foreach ($perguntas as $q) {
    $pontuacao += $pontos[$respostas[$q]];
}

if ($pontuacao <= 8) {
    $perfil = 'conservador';
} elseif ($pontuacao <= 12) {
    $perfil = 'moderado';
} else {
    $perfil = 'agressivo';
}

require_once __DIR__ . '/conexao.php';

$idUsuario     = (int)$_SESSION['user_id'];
$respostasJson = json_encode($respostas, JSON_UNESCAPED_UNICODE);

$conn->prepare("
    INSERT INTO investor_profile (user_id, perfil, respostas, pontuacao)
    VALUES (?, ?, ?, ?)
    ON CONFLICT (user_id)
    DO UPDATE SET perfil=EXCLUDED.perfil, respostas=EXCLUDED.respostas, pontuacao=EXCLUDED.pontuacao, updated_at=CURRENT_TIMESTAMP
")->execute([$idUsuario, $perfil, $respostasJson, $pontuacao]);

header('Location: ../home/aluno_simulador.html?sucesso=' . urlencode('Perfil ' . ucfirst($perfil) . ' salvo com sucesso!'));
exit;
