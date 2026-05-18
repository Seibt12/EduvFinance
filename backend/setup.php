<?php
/**
 * EduFinance — Setup
 * Inicializa o banco, migrações e usuário admin
 */
header('Content-Type: text/plain; charset=utf-8');

try {
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

    $sqlFile = __DIR__ . '/../database/edufinance.sql';
    if (file_exists($sqlFile)) {
        $commands = preg_split('/;/', file_get_contents($sqlFile));
        foreach ($commands as $command) {
            $command = trim($command);
            if ($command === '') continue;
            try {
                $conn->exec($command);
            } catch (Exception $e) {
                if (stripos($e->getMessage(), 'already exists') === false) {
                    // ignora erros benignos de objetos já criados
                }
            }
        }
        echo "✓ Schema principal (edufinance.sql)\n";
    }

    $migrationFile = __DIR__ . '/../database/migrations/001_professor_approval.sql';
    if (file_exists($migrationFile)) {
        $commands = preg_split('/;/', file_get_contents($migrationFile));
        foreach ($commands as $command) {
            $command = trim($command);
            if ($command === '') continue;
            try {
                $conn->exec($command);
            } catch (Exception $e) {
                // migração idempotente
            }
        }
        echo "✓ Migração professor/aprovação\n";
    }

    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    echo "✓ Pasta uploads\n";

    $adminEmail = 'admin@email.com';
    $adminPass  = '123';
    $adminName  = 'Administrador';

    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$adminEmail]);
    if (!$stmt->fetch()) {
        $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 10]);
        $conn->prepare('INSERT INTO users (nome, email, senha, tipo) VALUES (?, ?, ?, ?)')
            ->execute([$adminName, $adminEmail, $hash, 'admin']);
        echo "✓ Usuário admin criado: {$adminEmail}\n";
    } else {
        echo "✓ Usuário admin já existe\n";
    }

    echo "✓ Setup completado com sucesso!\n";
} catch (Exception $e) {
    echo "✗ Erro durante setup:\n  " . $e->getMessage() . "\n";
    exit(1);
}
