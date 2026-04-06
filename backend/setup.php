<?php
// ============================================================
// SETUP — Cria o administrador padrão
//
// Chamado automaticamente pelo Docker entrypoint (CLI).
// Também pode ser acessado via browser para diagnóstico.
// ============================================================

require_once __DIR__ . '/config/database.php';

$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    header('Content-Type: text/html; charset=utf-8');
}

// Tenta conectar (em CLI, retry com backoff caso o DB ainda não esteja 100%)
$conn = null;
$attempts = 0;
while ($conn === null && $attempts < 5) {
    try {
        $conn = getConnection();
    } catch (Throwable $e) {
        $attempts++;
        if ($attempts >= 5) {
            $msg = 'Não foi possível conectar ao banco: ' . $e->getMessage();
            if ($isCLI) { echo "❌ $msg\n"; exit(1); }
            echo "<p style='color:red'>$msg</p>";
            exit;
        }
        sleep(2);
    }
}

$adminEmail = 'admin@email.com';
$adminNome  = 'Administrador';
$adminSenha = password_hash('123', PASSWORD_BCRYPT);
$adminTipo  = 'admin';

// Verifica se já existe
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$adminEmail]);

if ($stmt->fetch()) {
    if ($isCLI) {
        echo "ℹ️  Admin já existe — nada a fazer.\n";
    } else {
        echo htmlAdmin('⚠️ Admin já existe', '#d29922',
            'O usuário <code>admin@email.com</code> já está cadastrado.');
    }
    exit;
}

// Cria o admin
$stmt = $conn->prepare("INSERT INTO users (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
$stmt->execute([$adminNome, $adminEmail, $adminSenha, $adminTipo]);

if ($isCLI) {
    echo "✅ Admin criado: admin@email.com / 123\n";
} else {
    echo htmlAdmin('✅ Admin criado!', '#3fb950',
        'Email: <code>admin@email.com</code><br>Senha: <code>123</code>',
        '<a href="../frontend/login.html">→ Ir para o Login</a>'
    );
}

// ── Helper de HTML ──────────────────────────────────────────
function htmlAdmin(string $title, string $color, string $body, string $extra = ''): string
{
    return <<<HTML
    <!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
    <style>
        body{font-family:sans-serif;background:#0d1117;color:#e6edf3;
             display:flex;justify-content:center;align-items:center;height:100vh;margin:0}
        .box{background:#161b22;border:1px solid #30363d;border-radius:12px;
             padding:40px;max-width:480px;text-align:center}
        h2{color:{$color}} code{background:#21262d;padding:4px 8px;border-radius:4px}
        hr{border-color:#30363d;margin:20px 0} a{color:#58a6ff}
        small{color:#6e7681;font-size:13px}
    </style></head><body>
    <div class="box">
        <h2>{$title}</h2><p>{$body}</p>
        <hr>{$extra}
        <p><small>Este arquivo pode ser removido após o setup.</small></p>
    </div></body></html>
    HTML;
}
