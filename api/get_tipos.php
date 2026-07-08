<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';

if (!isset($_SESSION['empleado_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db   = getDB();
$stmt = $db->query("SELECT id_tipo, nombre, descripcion FROM tipo_novedad ORDER BY id_tipo");
echo json_encode($stmt->fetchAll());

