<?php
header('Content-Type: application/json');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$nombre = trim($data['nombre'] ?? '');
$apellido = trim($data['apellido'] ?? '');
$cuil = trim($data['cuil'] ?? $data['dni'] ?? '');
$telefono = trim($data['telefono'] ?? '');
$email = trim($data['email'] ?? $data['usuario'] ?? '');
$contrasena = $data['contrasena'] ?? '';
$fechaNac = trim($data['fecha_nac'] ?? '') ?: '1900-01-01';
$estCivil = trim($data['est_civil'] ?? '') ?: 'No informado';
$domicilio = trim($data['domicilio'] ?? '') ?: 'No informado';
$nacionalidad = trim($data['nacionalidad'] ?? '') ?: null;

if (!$nombre || !$apellido || !$cuil || !$telefono || !$email || !$contrasena) {
    http_response_code(400);
    echo json_encode(['error' => 'Complete nombre, apellido, CUIL/DNI, telefono, email y contrasena']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email invalido']);
    exit;
}

if (strlen($contrasena) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'La contrasena debe tener al menos 6 caracteres']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT id_empleado FROM empleados WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => "El email $email ya esta registrado"]);
    exit;
}

$stmt = $db->prepare("SELECT id_empleado FROM empleados WHERE CUIL = ?");
$stmt->execute([$cuil]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => "El CUIL/DNI $cuil ya esta registrado"]);
    exit;
}

$hash = password_hash($contrasena, PASSWORD_DEFAULT);
$nombreCompleto = trim($nombre . ' ' . $apellido);

$stmt = $db->prepare(
    "INSERT INTO empleados
        (nombre, fecha_nac, est_civil, domicilio, CUIL, telefono, email, contrasena, activo, pendiente, tipo, nacionalidad)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1, 1, ?)"
);
$stmt->execute([$nombreCompleto, $fechaNac, $estCivil, $domicilio, $cuil, $telefono, $email, $hash, $nacionalidad]);

echo json_encode(['success' => true]);
