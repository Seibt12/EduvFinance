<?php
session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['autenticado' => false]);
    exit;
}

echo json_encode([
    'autenticado' => true,
    'id'    => $_SESSION['user_id'],
    'nome'  => $_SESSION['user_nome'],
    'email' => $_SESSION['user_email'] ?? '',
    'tipo'  => $_SESSION['user_tipo'],
]);
