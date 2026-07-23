<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireBackofficeApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$idEmpleado = isset($data['id_empleado']) ? (int)$data['id_empleado'] : 0;
$fileName = trim($data['file_name'] ?? '');

if (!$idEmpleado || !$fileName) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos obligatorios (id_empleado, file_name)']);
    exit;
}

// Clean file name to prevent directory traversal
$fileName = basename($fileName);
$fileName = preg_replace('/[\<\>\:\"\/\\\|\?\*]/', '', $fileName);

$db = getDB();
$stmt = $db->prepare("SELECT nombre, nro_legajo FROM empleados WHERE id_empleado = ?");
$stmt->execute([$idEmpleado]);
$emp = $stmt->fetch();

if (!$emp) {
    http_response_code(404);
    echo json_encode(['error' => 'Empleado no encontrado']);
    exit;
}

$nro_legajo = trim($emp['nro_legajo'] ?? '');
if ($nro_legajo === '') {
    http_response_code(400);
    echo json_encode(['error' => 'El empleado no tiene legajo configurado']);
    exit;
}

$folderName = $nro_legajo . '+' . str_replace(' ', '+', $emp['nombre']);
$folderName = preg_replace('/[\<\>\:\"\/\\\|\?\*]/', '', $folderName);
$filePath = __DIR__ . '/../../legajos/' . $folderName . '/' . $fileName;

if (!is_file($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'El archivo no existe']);
    exit;
}

if (unlink($filePath)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo eliminar el archivo del disco']);
}
?>
