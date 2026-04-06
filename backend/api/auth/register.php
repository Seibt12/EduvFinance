<?php
// ============================================================
// CADASTRO — POST /backend/api/auth/register.php
//
// Recebe: { nome, email, senha }
// Retorna: { success, message }
//
// Para adicionar um campo novo (ex: idade):
//   1. Adicione $idade = (int)($data['idade'] ?? 0);
//   2. Valide: if ($idade < 1) jsonResponse([...], 400);
//   3. Insira na query: INSERT INTO users (nome, email, senha, tipo, idade)
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();

// PASSO 1 — Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

// PASSO 2 — Lê os dados do formulário de cadastro
$data  = getJsonBody();
$nome  = trim($data['nome']  ?? '');
$email = trim($data['email'] ?? '');
$senha = trim($data['senha'] ?? '');
$idade = (int)($data['idade'] ?? 0);  // EXEMPLO: campo "idade" — 0 significa "não informado"

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

// EXEMPLO: validação do campo "idade"
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

// EXEMPLO: inclua o campo "idade" na query (NULL se não informado)
$stmt = $conn->prepare("INSERT INTO users (nome, email, senha, tipo, idade) VALUES (?, ?, ?, 'aluno', ?)");
$stmt->execute([$nome, $email, $senhaHash, $idade > 0 ? $idade : null]);

jsonResponse(['success' => true, 'message' => 'Cadastro realizado com sucesso!']);
