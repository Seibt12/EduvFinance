<?php
require_once __DIR__ . '/valida_sessao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../home/aluno_cursos.html');
    exit;
}

$aulaId    = (int)($_POST['lesson_id'] ?? 0);
$cursoId   = (int)($_POST['course_id'] ?? 0);
$concluido = (int)($_POST['concluido'] ?? 1);
$redirect  = $_POST['redirect'] ?? 'aluno_cursos.html';
$idUsuario = (int)$_SESSION['user_id'];

$redirect  = preg_replace('/[^a-zA-Z0-9_.?=&\/]/', '', $redirect);
$redirBase = '../home/';

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

// Resolve which course to use for progression rules
if ($cursoId <= 0) {
    // Fallback: pick the first course this lesson belongs to (legacy path)
    $stmtCourse = $conn->prepare("SELECT course_id FROM course_lessons WHERE lesson_id = ? LIMIT 1");
    $stmtCourse->execute([$aulaId]);
    $courseRow = $stmtCourse->fetch();
    $cursoId   = $courseRow ? (int)$courseRow['course_id'] : 0;
}

// Verify the lesson actually belongs to the resolved course
if ($cursoId > 0) {
    $stmtVerify = $conn->prepare("SELECT 1 FROM course_lessons WHERE course_id = ? AND lesson_id = ? LIMIT 1");
    $stmtVerify->execute([$cursoId, $aulaId]);
    if (!$stmtVerify->fetch()) {
        $cursoId = 0;
    }
}

// Progression rules: only when marking as complete and within a known course
if ($concluido && $aula['nivel'] !== 'basico' && $cursoId > 0) {
    $stmtTot = $conn->prepare("
        SELECT COUNT(*) FROM lessons l
        JOIN course_lessons cl ON cl.lesson_id = l.id
        WHERE cl.course_id = ? AND l.nivel = 'basico'
    ");
    $stmtTot->execute([$cursoId]);
    $totalBasico = (int)$stmtTot->fetchColumn();

    $stmtDone = $conn->prepare("
        SELECT COUNT(*) FROM progress p
        JOIN lessons l ON l.id = p.lesson_id
        JOIN course_lessons cl ON cl.lesson_id = l.id
        WHERE p.user_id = ? AND cl.course_id = ? AND l.nivel = 'basico' AND p.concluido = 1
    ");
    $stmtDone->execute([$idUsuario, $cursoId]);
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
        $stmtTot2->execute([$cursoId]);
        $totalInter = (int)$stmtTot2->fetchColumn();

        $stmtDone2 = $conn->prepare("
            SELECT COUNT(*) FROM progress p
            JOIN lessons l ON l.id = p.lesson_id
            JOIN course_lessons cl ON cl.lesson_id = l.id
            WHERE p.user_id = ? AND cl.course_id = ? AND l.nivel = 'intermediario' AND p.concluido = 1
        ");
        $stmtDone2->execute([$idUsuario, $cursoId]);
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
