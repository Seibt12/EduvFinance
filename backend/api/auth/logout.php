<?php
// ============================================================
// LOGOUT — POST /backend/api/auth/logout.php
//
// Destrói a sessão do servidor e invalida o cookie.
// O frontend também limpa o localStorage após chamar este endpoint.
//
// Retorna: { success: true, message: "..." }
// ============================================================

require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
initSession();

// Limpa todas as variáveis de sessão
$_SESSION = [];

// Invalida o cookie de sessão no browser do usuário
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 3600,         // expira no passado = deleta o cookie
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destrói a sessão no servidor
session_destroy();

jsonResponse(['success' => true, 'message' => 'Logout realizado com sucesso.']);
