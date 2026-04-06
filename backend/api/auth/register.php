<?php
// ============================================================
// CADASTRO — POST /backend/api/auth/register.php
//
// Recebe: { nome, email, senha }
// Retorna: { success, message }
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();

// PASSO 1 — Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

// PASSO 2 — Lê os dados do formulário de cadastro
$dados = getJsonBody();
$nome  = trim($dados['nome']  ?? '');
$email = trim($dados['email'] ?? '');
$senha = trim($dados['senha'] ?? '');
$idade = (int)($dados['idade'] ?? 0);

// PASSO 3 — Validações básicas
if ($nome === '' || $email === '' || $senha === '') {
    jsonResponse(['success' => false, 'message' => 'Nome, email e senha são obrigatórios.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Email inválido.'], 400);
}

if (strlen($senha) < 3) {
    jsonResponse(['success' => false, 'message' => 'A senha deve ter pelo menos 3 caracteres.'], 400);
}

// Validação da idade (campo opcional)
if ($idade > 0 && ($idade < 10 || $idade > 120)) {
    jsonResponse(['success' => false, 'message' => 'Idade inválida.'], 400);
}

// PASSO 4 — Garante que o email não está em uso
$conn = getConnection();
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Este e-mail já está cadastrado.'], 409);
}

// PASSO 5 — Salva o novo aluno no banco
// password_hash() criptografa a senha — nunca salvamos a senha em texto puro!
$senhaHash = password_hash($senha, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO users (nome, email, senha, tipo, idade) VALUES (?, ?, ?, 'aluno', ?)");
$stmt->execute([$nome, $email, $senhaHash, $idade > 0 ? $idade : null]);

jsonResponse(['success' => true, 'message' => 'Cadastro realizado com sucesso!']);
