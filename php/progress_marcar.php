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

// Helper: append query param respecting whether redirect already has a '?'
function redirUrl(string $base, string $redir, string $key, string $val): string {
    $sep = (strpos($redir, '?') !== false) ? '&' : '?';
    return $base . $redir . $sep . $key . '=' . urlencode($val);
}

if ($aulaId <= 0) {
    header('Location: ' . redirUrl($redirBase, $redirect, 'erro', 'ID da aula inválido.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare("SELECT id, nivel FROM lessons WHERE id = ? LIMIT 1");
$stmt->execute([$aulaId]);
$aula = $stmt->fetch();

if (!$aula) {
    header('Location: ' . redirUrl($redirBase, $redirect, 'erro', 'Aula não encontrada.'));
    exit;
}

// Descobre o curso ao qual esta aula pertence (para escopo da progressão)
$stmtCourse = $conn->prepare("SELECT course_id FROM course_lessons WHERE lesson_id = ? LIMIT 1");
$stmtCourse->execute([$aulaId]);
$courseRow = $stmtCourse->fetch();
$courseId  = $courseRow ? (int)$courseRow['course_id'] : 0;

// Regra de progressão: verifica apenas dentro do mesmo curso
if ($concluido && $aula['nivel'] !== 'basico' && $courseId > 0) {
    $stmtTot = $conn->prepare("
        SELECT COUNT(*) FROM lessons l
        JOIN course_lessons cl ON cl.lesson_id = l.id
        WHERE cl.course_id = ? AND l.nivel = 'basico'
    ");
    $stmtTot->execute([$courseId]);
    $totalBasico = (int)$stmtTot->fetchColumn();

    $stmtDone = $conn->prepare("
        SELECT COUNT(*) FROM progress p
        JOIN lessons l ON l.id = p.lesson_id
        JOIN course_lessons cl ON cl.lesson_id = l.id
        WHERE p.user_id = ? AND cl.course_id = ? AND l.nivel = 'basico' AND p.concluido = 1
    ");
    $stmtDone->execute([$idUsuario, $courseId]);
    $doneBasico = (int)$stmtDone->fetchColumn();

    if ($totalBasico > 0 && $doneBasico < $totalBasico) {
        header('Location: ' . redirUrl($redirBase, $redirect, 'erro', 'Conclua todas as aulas básicas deste curso antes de avançar.'));
        exit;
    }

    if ($aula['nivel'] === 'avancado') {
        $stmtTot2 = $conn->prepare("
            SELECT COUNT(*) FROM lessons l
            JOIN course_lessons cl ON cl.lesson_id = l.id
            WHERE cl.course_id = ? AND l.nivel = 'intermediario'
        ");
        $stmtTot2->execute([$courseId]);
        $totalInter = (int)$stmtTot2->fetchColumn();

        $stmtDone2 = $conn->prepare("
            SELECT COUNT(*) FROM progress p
            JOIN lessons l ON l.id = p.lesson_id
            JOIN course_lessons cl ON cl.lesson_id = l.id
            WHERE p.user_id = ? AND cl.course_id = ? AND l.nivel = 'intermediario' AND p.concluido = 1
        ");
        $stmtDone2->execute([$idUsuario, $courseId]);
        $doneInter = (int)$stmtDone2->fetchColumn();

        if ($totalInter > 0 && $doneInter < $totalInter) {
            header('Location: ' . redirUrl($redirBase, $redirect, 'erro', 'Conclua todas as aulas intermediárias deste curso antes de avançar.'));
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
header('Location: ' . redirUrl($redirBase, $redirect, 'sucesso', $msg));
exit;
