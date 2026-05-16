<?php
require_once __DIR__ . '/valida_sessao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/aluno_cursos.php');
    exit;
}

$aulaId    = (int)($_POST['lesson_id'] ?? 0);
$concluido = (int)($_POST['concluido'] ?? 1);
$redirect  = $_POST['redirect'] ?? 'aluno_cursos.php';
$idUsuario = (int)$_SESSION['user_id'];

// Sanitize redirect to prevent open redirect
$redirect  = preg_replace('/[^a-zA-Z0-9_.?=&\/]/', '', $redirect);
$redirBase = '../home/';

if ($aulaId <= 0) {
    header('Location: ' . $redirBase . $redirect . '?erro=' . urlencode('ID da aula inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id, nivel FROM lessons WHERE id = ? LIMIT 1");
$stmt->execute([$aulaId]);
$aula = $stmt->fetch();

if (!$aula) {
    header('Location: ' . $redirBase . $redirect . '?erro=' . urlencode('Aula não encontrada.'));
    exit;
}

// Regra de progressão
if ($concluido && $aula['nivel'] !== 'basico') {
    $totalBasico = (int)$conn->query("SELECT COUNT(*) FROM lessons WHERE nivel = 'basico'")->fetchColumn();

    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM progress p
        JOIN lessons l ON l.id = p.lesson_id
        WHERE p.user_id = ? AND l.nivel = 'basico' AND p.concluido = 1
    ");
    $stmt->execute([$idUsuario]);
    $doneBasico = (int)$stmt->fetchColumn();

    if ($totalBasico === 0 || $doneBasico < $totalBasico) {
        header('Location: ' . $redirBase . $redirect . '?erro=' . urlencode('Conclua todas as aulas básicas antes de avançar.'));
        exit;
    }

    if ($aula['nivel'] === 'avancado') {
        $totalInter = (int)$conn->query("SELECT COUNT(*) FROM lessons WHERE nivel = 'intermediario'")->fetchColumn();
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM progress p
            JOIN lessons l ON l.id = p.lesson_id
            WHERE p.user_id = ? AND l.nivel = 'intermediario' AND p.concluido = 1
        ");
        $stmt->execute([$idUsuario]);
        $doneInter = (int)$stmt->fetchColumn();

        if ($totalInter === 0 || $doneInter < $totalInter) {
            header('Location: ' . $redirBase . $redirect . '?erro=' . urlencode('Conclua todas as aulas intermediárias antes de avançar.'));
            exit;
        }
    }
}

$conn->prepare("
    INSERT INTO progress (user_id, lesson_id, concluido)
    VALUES (?, ?, ?)
    ON CONFLICT (user_id, lesson_id)
    DO UPDATE SET concluido = EXCLUDED.concluido, updated_at = CURRENT_TIMESTAMP
")->execute([$idUsuario, $aulaId, $concluido]);

$msg = $concluido ? 'Aula marcada como concluída!' : 'Aula desmarcada.';
header('Location: ' . $redirBase . $redirect . '?sucesso=' . urlencode($msg));
exit;
