<?php
// ============================================================
// CHECK — GET /backend/api/auth/check.php
//
// Verifica se o usuário está logado (sessão válida no servidor).
// Chamado automaticamente por requireSession() em TODA página
// protegida, antes de carregar qualquer conteúdo.
//
// Retorna: { success: true, user: { id, nome, email, tipo } }
//       ou: { success: false, message: "..." }  com HTTP 401
// ============================================================

require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
initSession();

// Sessão inválida ou expirada — usuário precisa fazer login
if (empty($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Sessão inválida ou expirada.'], 401);
}

// Sessão válida — devolve os dados do usuário logado
jsonResponse([
    'success' => true,
    'user'    => [
        'id'    => (int) $_SESSION['user_id'],
        'nome'  => $_SESSION['user_nome']  ?? '',
        'email' => $_SESSION['user_email'] ?? '',  // salvo em login.php
        'tipo'  => $_SESSION['user_tipo']  ?? 'aluno',
    ],
]);
