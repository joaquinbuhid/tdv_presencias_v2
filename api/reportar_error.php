<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';

if (!isset($_SESSION['empleado_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$accion = trim($data['accion'] ?? '');
$mensajeError = trim($data['mensaje_error'] ?? '');
$descripcion = trim($data['descripcion'] ?? '');

if (!$descripcion) {
    http_response_code(400);
    echo json_encode(['error' => 'La descripcion del problema es requerida']);
    exit;
}

$db = getDB();
$stmt = $db->query("SELECT id_tipo FROM tipo_novedad WHERE nombre IN ('Incidente','Novedad') ORDER BY FIELD(nombre,'Incidente','Novedad') LIMIT 1");
$tipo = $stmt->fetch();

if (!$tipo) {
    http_response_code(400);
    echo json_encode(['error' => 'No existe un tipo de novedad para registrar reportes']);
    exit;
}

$observaciones = trim("Reporte de problema\nAccion: " . ($accion ?: 'No informada') . "\nMensaje: " . ($mensajeError ?: 'No informado') . "\nDescripcion: " . $descripcion);
$stmt = $db->prepare(
    "INSERT INTO novedades (fecha, hora, tipo_novedad, observaciones, empleado_id, ip_dispositivo)
     VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->execute([date('Y-m-d'), date('H:i:s'), (int)$tipo['id_tipo'], $observaciones, (int)$_SESSION['empleado_id'], getClientIP()]);

echo json_encode(['success' => true, 'mensaje' => 'Reporte registrado como novedad.']);
