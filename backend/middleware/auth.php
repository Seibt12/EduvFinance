<?php
// ============================================================
// MIDDLEWARE — Funções usadas por TODOS os endpoints da API
//
// Cada endpoint começa chamando:
//   setCORSHeaders();   ← permite o browser fazer requisições
//   requireAuth();      ← bloqueia quem não está logado
//   requireAdmin();     ← bloqueia quem não é admin
// ============================================================


// ------------------------------------------------------------
// SESSÃO — mantém o usuário logado entre requisições
// ------------------------------------------------------------

function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 86400,  // sessão dura 24 horas
            'path'     => '/',
            'httponly' => true,   // JavaScript não pode ler o cookie (segurança)
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}


// ------------------------------------------------------------
// CORS — permite que o browser envie requisições ao backend
// (sem isso o browser bloqueia por segurança)
// ------------------------------------------------------------

function setCORSHeaders(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:8080';

    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Access-Control-Allow-Credentials: true');

    // Requisição de "verificação" do browser — responde OK e para
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}


// ------------------------------------------------------------
// RESPOSTA — todo endpoint devolve JSON neste formato:
//   { "success": true/false, "message": "...", ...dados }
// ------------------------------------------------------------

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}


// ------------------------------------------------------------
// LEITURA DO CORPO — pega os dados enviados pelo frontend
// (o frontend envia JSON no corpo da requisição POST)
// ------------------------------------------------------------

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw || trim($raw) === '') return [];

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['success' => false, 'message' => 'JSON inválido.'], 400);
    }

    return is_array($data) ? $data : [];
}


// ------------------------------------------------------------
// PROTEÇÃO DE ROTAS — coloque no topo do endpoint para proteger
// ------------------------------------------------------------

// Bloqueia quem não está logado (retorna HTTP 401)
function requireAuth(): void
{
    initSession();

    if (empty($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'Não autorizado. Faça login.'], 401);
    }
}

// Bloqueia quem não é admin (retorna HTTP 403)
function requireAdmin(): void
{
    requireAuth(); // primeiro verifica se está logado

    if (($_SESSION['user_tipo'] ?? '') !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Acesso negado. Apenas administradores.'], 403);
    }
}
