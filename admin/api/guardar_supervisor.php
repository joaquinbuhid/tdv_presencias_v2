<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (empty($_SESSION['es_admin'])) {
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
$id = isset($data['id']) ? (int)$data['id'] : 0;
$nombre = trim($data['nombre'] ?? '');
$apellido = trim($data['apellido'] ?? '');
$cuil = trim($data['cuil'] ?? $data['dni'] ?? '');
$telefono = trim($data['telefono'] ?? '');
$email = trim($data['email'] ?? $data['usuario'] ?? '');
$contrasena = $data['contrasena'] ?? '';

if (!$nombre || !$apellido || !$cuil || !$telefono || !$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Nombre, apellido, CUIL/DNI, telefono y email son requeridos']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email invalido']);
    exit;
}

if ($id === 0 && !$contrasena) {
    http_response_code(400);
    echo json_encode(['error' => 'La contrasena es requerida al crear un supervisor']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id_empleado FROM empleados WHERE CUIL = ? AND id_empleado != ?");
$stmt->execute([$cuil, $id]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => "El CUIL/DNI $cuil ya esta registrado"]);
    exit;
}

$stmt = $db->prepare("SELECT id_empleado FROM empleados WHERE email = ? AND id_empleado != ?");
$stmt->execute([$email, $id]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => "El email $email ya esta en uso"]);
    exit;
}

$nombreCompleto = trim($nombre . ' ' . $apellido);

if ($id === 0) {
    $hash = password_hash($contrasena, PASSWORD_BCRYPT);
    $stmt = $db->prepare(
        "INSERT INTO empleados
            (nombre, fecha_nac, est_civil, domicilio, CUIL, telefono, email, contrasena, activo, pendiente, tipo)
         VALUES (?, '1900-01-01', 'No informado', 'No informado', ?, ?, ?, ?, 1, 0, 2)"
    );
    $stmt->execute([$nombreCompleto, $cuil, $telefono, $email, $hash]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'accion' => 'creado']);
} else {
    if ($contrasena) {
        $hash = password_hash($contrasena, PASSWORD_BCRYPT);
        $stmt = $db->prepare(
            "UPDATE empleados SET nombre=?, CUIL=?, telefono=?, email=?, contrasena=?
             WHERE id_empleado=? AND tipo = 2"
        );
        $stmt->execute([$nombreCompleto, $cuil, $telefono, $email, $hash, $id]);
    } else {
        $stmt = $db->prepare(
            "UPDATE empleados SET nombre=?, CUIL=?, telefono=?, email=?
             WHERE id_empleado=? AND tipo = 2"
        );
        $stmt->execute([$nombreCompleto, $cuil, $telefono, $email, $id]);
    }
    echo json_encode(['success' => true, 'id' => $id, 'accion' => 'actualizado']);
}
