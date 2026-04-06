<?php
// ============================================================
// CONEXÃO COM O BANCO DE DADOS (PostgreSQL)
//
// As credenciais vêm do arquivo .env (via docker-compose).
// Fallbacks abaixo são usados se as variáveis não existirem.
//
// Para usar: $conn = getConnection();
// ============================================================

// Credenciais lidas do ambiente Docker (definidas no .env)
define('DB_HOST', getenv('DB_HOST') ?: 'postgres');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'educacao_financeira');
define('DB_USER', getenv('DB_USER') ?: 'edufinance');
define('DB_PASS', getenv('DB_PASS') ?: 'edufinance123');

/**
 * Abre e retorna uma conexão com o banco.
 * Se falhar, responde com erro JSON e para tudo.
 *
 * Uso em qualquer endpoint:
 *   $conn = getConnection();
 *   $stmt = $conn->prepare("SELECT ...");
 */
function getConnection(): PDO
{
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;

    try {
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // lança exceções em erros SQL
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // retorna arrays associativos
            PDO::ATTR_EMULATE_PREPARES   => false,                   // usa prepared statements reais
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao conectar com o banco de dados.']);
        exit;
    }
}
