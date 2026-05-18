<?php
require_once __DIR__ . '/valida_admin.php';

header('Location: ../home/index.html?erro=' . urlencode('Funcionalidade de cursos removida para o administrador.'));
exit;
