<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['es_admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

echo json_encode(['success' => true, 'mensaje' => 'Los reportes ahora se registran como novedades.']);
