<?php
// Marca ou desmarca uma aula como concluída.
// Valida a regra de progressão no backend antes de salvar.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$dados     = getJsonBody();
$aulaId    = isset($dados['lesson_id']) ? (int)$dados['lesson_id'] : 0;
$concluido = isset($dados['concluido']) ? (bool)$dados['concluido'] : true;
$idUsuario = (int)$_SESSION['user_id'];

if ($aulaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da aula inválido.']);
    exit;
}

$conn = getConnection();

// Busca a aula para verificar o nível
$stmt = $conn->prepare("SELECT id, nivel FROM lessons WHERE id = ? LIMIT 1");
$stmt->execute([$aulaId]);
$aula = $stmt->fetch();

if (!$aula) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aula não encontrada.']);
    exit;
}

// ============================================================
// REGRA DE PROGRESSÃO — verificada no BACKEND
// ============================================================
if ($concluido && $aula['nivel'] !== 'basico') {

    $stmt = $conn->query("SELECT COUNT(*) FROM lessons WHERE nivel = 'basico'");
    $totalBasico = (int)$stmt->fetchColumn();

    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM progress p
        JOIN lessons l ON l.id = p.lesson_id
        WHERE p.user_id = ? AND l.nivel = 'basico' AND p.concluido = 1
    ");
    $stmt->execute([$idUsuario]);
    $doneBasico = (int)$stmt->fetchColumn();

    if ($totalBasico === 0 || $doneBasico < $totalBasico) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Você precisa concluir todas as aulas básicas antes de avançar.',
        ]);
        exit;
    }

    if ($aula['nivel'] === 'avancado') {
        $stmt = $conn->query("SELECT COUNT(*) FROM lessons WHERE nivel = 'intermediario'");
        $totalInter = (int)$stmt->fetchColumn();

        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM progress p
            JOIN lessons l ON l.id = p.lesson_id
            WHERE p.user_id = ? AND l.nivel = 'intermediario' AND p.concluido = 1
        ");
        $stmt->execute([$idUsuario]);
        $doneInter = (int)$stmt->fetchColumn();

        if ($totalInter === 0 || $doneInter < $totalInter) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Você precisa concluir todas as aulas intermediárias antes de avançar.',
            ]);
            exit;
        }
    }
}

// INSERT ... ON CONFLICT — equivalente PostgreSQL de "ON DUPLICATE KEY UPDATE"
$valor = $concluido ? 1 : 0;
$stmt  = $conn->prepare("
    INSERT INTO progress (user_id, lesson_id, concluido)
    VALUES (?, ?, ?)
    ON CONFLICT (user_id, lesson_id)
    DO UPDATE SET concluido = EXCLUDED.concluido,
                  updated_at = CURRENT_TIMESTAMP
");
$stmt->execute([$idUsuario, $aulaId, $valor]);

$msg = $concluido ? 'Aula marcada como concluída!' : 'Aula desmarcada.';
echo json_encode(['success' => true, 'message' => $msg]);
