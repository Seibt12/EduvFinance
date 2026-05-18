<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: ../login/index.php');
    exit;
}
if (($_SESSION['user_tipo'] ?? '') !== 'professor') {
    header('Location: ../home/student.html');
    exit;
}
