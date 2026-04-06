<?php
// Atualiza nome, email e (opcionalmente) senha de um usuário.
// Admin pode editar qualquer aluno; aluno só pode editar a si mesmo.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$dados = getJsonBody();
$id    = isset($dados['id'])    ? (int)$dados['id']        : 0;
$nome  = trim($dados['nome']    ?? '');
$email = trim($dados['email']   ?? '');
$senha = trim($dados['senha']   ?? '');

if ($_SESSION['user_tipo'] !== 'admin' && $id !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

if ($id <= 0 || $nome === '' || $email === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID, nome e email são obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email inválido.']);
    exit;
}

$conn = getConnection();

$stmt = $conn->prepare("SELECT id, email, tipo FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$existente = $stmt->fetch();

if (!$existente) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
    exit;
}

// Protege o administrador padrão contra edições
if ($existente['tipo'] === 'admin' && $existente['email'] === 'admin@email.com') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'O administrador padrão não pode ser editado.']);
    exit;
}

// Verifica e-mail duplicado
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
$stmt->execute([$email, $id]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Este e-mail já está em uso.']);
    exit;
}

if ($senha !== '') {
    if (strlen($senha) < 3) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A senha deve ter pelo menos 3 caracteres.']);
        exit;
    }
    $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET nome = ?, email = ?, senha = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$nome, $email, $senhaHash, $id]);
} else {
    $stmt = $conn->prepare("UPDATE users SET nome = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$nome, $email, $id]);
}

// Atualiza a sessão se o próprio usuário editou seus dados
if ($id === (int)$_SESSION['user_id']) {
    $_SESSION['user_nome']  = $nome;
    $_SESSION['user_email'] = $email;
}

echo json_encode(['success' => true, 'message' => 'Perfil atualizado com sucesso.']);
