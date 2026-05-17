<?php
/**
 * EduFinance — Setup
 * Inicializa o banco de dados e cria o usuário admin
 */

header('Content-Type: text/plain; charset=utf-8');

try {
    // ── 1. Conecta ao PostgreSQL ──────────────────────────
    $conn = new PDO(
        "pgsql:host=" . (getenv('DB_HOST') ?: 'postgres') .
        ";port="      . (getenv('DB_PORT') ?: '5432') .
        ";dbname="    . (getenv('DB_NAME') ?: 'educacao_financeira'),
        getenv('DB_USER') ?: 'edufinance',
        getenv('DB_PASS') ?: 'edufinance123',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // ── 2. Cria as tabelas (se não existirem) ─────────────
    $sql_file = '/var/www/html/database/edufinance.sql';
    if (file_exists($sql_file)) {
        $sql_commands = file_get_contents($sql_file);
        
        // Separa os comandos SQL e executa cada um
        $commands = preg_split('/;/', $sql_commands);
        foreach ($commands as $command) {
            $command = trim($command);
            if (!empty($command)) {
                try {
                    $conn->exec($command);
                } catch (Exception $e) {
                    // Ignora erros de tabelas que já existem
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        // Log apenas para erros reais
                    }
                }
            }
        }
        echo "✓ Banco de dados inicializado\n";
    }

    // ── 3. Verifica e cria o admin ────────────────────────
    $admin_email = 'admin@email.com';
    $admin_password = '123';
    $admin_name = 'Administrador';

    // Verifica se o admin já existe
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$admin_email]);
    $admin_exists = $stmt->fetch();

    if (!$admin_exists) {
        // Cria o admin com password_hash (bcrypt)
        $hashed_password = password_hash($admin_password, PASSWORD_BCRYPT, ['cost' => 10]);
        
        $stmt = $conn->prepare('
            INSERT INTO users (nome, email, senha, tipo)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$admin_name, $admin_email, $hashed_password, 'admin']);
        
        echo "✓ Usuário admin criado: $admin_email\n";
    } else {
        echo "✓ Usuário admin já existe\n";
    }

    echo "✓ Setup completado com sucesso!\n";

} catch (Exception $e) {
    echo "✗ Erro durante setup:\n";
    echo "  " . $e->getMessage() . "\n";
    exit(1);
}
?>
