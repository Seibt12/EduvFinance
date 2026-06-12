<?php
require_once __DIR__ . '/valida_admin.php';
header('Content-Type: application/json');
http_response_code(410);
echo json_encode(['success' => false, 'message' => 'Criação direta de aulas foi removida. Aulas são criadas por professores via Course Builder e aprovadas pelo administrador.']);
