<?php
// ============================================================
// LOGIN — POST /backend/api/auth/login.php
//
// Recebe: { email, senha }
// Retorna: { success, user: { id, nome, email, tipo } }
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
initSession();

// PASSO 1 — Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

// PASSO 2 — Lê os dados enviados pelo frontend
$dados = getJsonBody();
$email = trim($dados['email'] ?? '');
$senha = trim($dados['senha'] ?? '');

if ($email === '' || $senha === '') {
    jsonResponse(['success' => false, 'message' => 'Email e senha são obrigatórios.'], 400);
}

// PASSO 3 — Busca o usuário no banco pelo email
$conn = getConnection();
$stmt = $conn->prepare("SELECT id, nome, email, senha, tipo FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$usuario = $stmt->fetch();

// PASSO 4 — Verifica se o usuário existe e se a senha está correta
if (!$usuario || !password_verify($senha, $usuario['senha'])) {
    jsonResponse(['success' => false, 'message' => 'Email ou senha incorretos.'], 401);
}

// PASSO 5 — Cria a sessão (fica salva no servidor por 24h)
session_regenerate_id(true); // gera um novo ID de sessão por segurança

$_SESSION['user_id']    = $usuario['id'];
$_SESSION['user_nome']  = $usuario['nome'];
$_SESSION['user_email'] = $usuario['email'];  // necessário para check.php devolver o email
$_SESSION['user_tipo']  = $usuario['tipo'];

// PASSO 6 — Devolve os dados do usuário para o frontend
jsonResponse([
    'success' => true,
    'message' => 'Login realizado com sucesso.',
    'user'    => [
        'id'    => (int) $usuario['id'],
        'nome'  => $usuario['nome'],
        'email' => $usuario['email'],
        'tipo'  => $usuario['tipo'],  // 'admin' ou 'aluno'
    ],
]);
