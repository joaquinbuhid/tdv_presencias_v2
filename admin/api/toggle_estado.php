<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireAdminRealApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
$accion = $data['accion'] ?? '';

if (!$id || !in_array($accion, ['aprobar','activar','desactivar'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos invalidos']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id_empleado FROM empleados WHERE id_empleado = ? AND COALESCE(tipo, 1) = 1");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Empleado no encontrado']);
    exit;
}

if ($accion === 'aprobar' || $accion === 'activar') {
    $db->prepare("UPDATE empleados SET activo=1, pendiente=0 WHERE id_empleado=?")->execute([$id]);
    echo json_encode(['success' => true, 'mensaje' => 'Empleado activado']);
} else {
    $db->prepare("UPDATE empleados SET activo=0, pendiente=0 WHERE id_empleado=?")->execute([$id]);
    echo json_encode(['success' => true, 'mensaje' => 'Empleado desactivado']);
}
