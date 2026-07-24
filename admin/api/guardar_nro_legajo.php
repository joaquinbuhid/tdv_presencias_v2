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
$nroLegajo = trim($data['nro_legajo'] ?? '');

if (!$idEmpleado || $nroLegajo === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos obligatorios (id_empleado, nro_legajo)']);
    exit;
}

$db = getDB();

// Check uniqueness of nro_legajo
$uniqStmt = $db->prepare("SELECT id_empleado, nombre FROM empleados WHERE nro_legajo = ? AND id_empleado != ?");
$uniqStmt->execute([$nroLegajo, $idEmpleado]);
$exist = $uniqStmt->fetch();
if ($exist) {
    http_response_code(409);
    echo json_encode(['error' => "El número de legajo '$nroLegajo' ya está asignado a: " . $exist['nombre']]);
    exit;
}

// Fetch current employee details
$stmt = $db->prepare("SELECT nombre, nro_legajo FROM empleados WHERE id_empleado = ?");
$stmt->execute([$idEmpleado]);
$emp = $stmt->fetch();

if (!$emp) {
    http_response_code(404);
    echo json_encode(['error' => 'Empleado no encontrado']);
    exit;
}

$oldLegajo = trim($emp['nro_legajo'] ?? '');
$newLegajo = $nroLegajo;

if ($oldLegajo !== '' && $oldLegajo !== $newLegajo) {
    // Rename physical folder if it exists
    $oldFolderName = $oldLegajo . '+' . str_replace(' ', '+', $emp['nombre']);
    $oldFolderName = preg_replace('/[\<\>\:\"\/\\\|\?\*]/', '', $oldFolderName);
    $oldPath = __DIR__ . '/../../legajos/' . $oldFolderName;
    
    $newFolderName = $newLegajo . '+' . str_replace(' ', '+', $emp['nombre']);
    $newFolderName = preg_replace('/[\<\>\:\"\/\\\|\?\*]/', '', $newFolderName);
    $newPath = __DIR__ . '/../../legajos/' . $newFolderName;
    
    if (is_dir($oldPath)) {
        if (!rename($oldPath, $newPath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al renombrar la carpeta del legajo en el servidor']);
            exit;
        }
    }
}

// Calculate the new URL Legajo
$url_leg = 'tdvsrl.com/legajos/' . $newLegajo . '_' . str_replace(' ', '_', $emp['nombre']);

// Update the database
$updateStmt = $db->prepare("UPDATE empleados SET nro_legajo = ?, url_leg = ? WHERE id_empleado = ?");
$updateStmt->execute([$newLegajo, $url_leg, $idEmpleado]);

echo json_encode([
    'success' => true,
    'nro_legajo' => $newLegajo,
    'url_leg' => $url_leg
]);
?>
