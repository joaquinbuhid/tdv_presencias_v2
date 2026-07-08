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
$dni = trim($data['dni'] ?? '');
$cuil = trim($data['cuil'] ?? $dni);
$telefono = trim($data['telefono'] ?? '');
$email = trim($data['email'] ?? $data['usuario'] ?? '');
$contrasena = $data['contrasena'] ?? '';
$objId = isset($data['objetivo_id']) && $data['objetivo_id'] !== '' ? (int)$data['objetivo_id'] : null;
$horaEntrada = trim($data['hora_entrada'] ?? '') ?: null;
$horaSalida = trim($data['hora_salida'] ?? '') ?: null;

if (!$nombre || !$dni || !$telefono || !$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos obligatorios']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email invalido']);
    exit;
}

if (!$id && !$contrasena) {
    http_response_code(400);
    echo json_encode(['error' => 'La contrasena es requerida para nuevos empleados']);
    exit;
}

if ($contrasena && strlen($contrasena) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'La contrasena debe tener al menos 6 caracteres']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id_empleado FROM empleados WHERE email = ? AND id_empleado != ?");
$stmt->execute([$email, $id]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => "El email $email ya esta en uso"]);
    exit;
}

$stmt = $db->prepare("SELECT id_empleado FROM empleados WHERE (DNI = ? OR CUIL = ?) AND id_empleado != ?");
$stmt->execute([$dni, $dni, $id]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => "El DNI $dni ya esta registrado"]);
    exit;
}

$nombreCompleto = $nombre;

if ($id === 0) {
    $hash = password_hash($contrasena, PASSWORD_DEFAULT);
    $stmt = $db->prepare(
        "INSERT INTO empleados
            (nombre, fecha_nac, est_civil, domicilio, CUIL, DNI, telefono, email, contrasena, objetivo_id, hora_entrada, hora_salida, activo, pendiente, tipo)
         VALUES (?, '1900-01-01', 'No informado', 'No informado', ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 1)"
    );
    $stmt->execute([$nombreCompleto, $cuil, $dni, $telefono, $email, $hash, $objId, $horaEntrada, $horaSalida]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'accion' => 'creado']);
} else {
    if ($contrasena) {
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $stmt = $db->prepare(
            "UPDATE empleados
             SET nombre=?, CUIL=?, DNI=?, telefono=?, email=?, contrasena=?, objetivo_id=?, hora_entrada=?, hora_salida=?
             WHERE id_empleado=? AND COALESCE(tipo, 1) = 1"
        );
        $stmt->execute([$nombreCompleto, $cuil, $dni, $telefono, $email, $hash, $objId, $horaEntrada, $horaSalida, $id]);
    } else {
        $stmt = $db->prepare(
            "UPDATE empleados
             SET nombre=?, CUIL=?, DNI=?, telefono=?, email=?, objetivo_id=?, hora_entrada=?, hora_salida=?
             WHERE id_empleado=? AND COALESCE(tipo, 1) = 1"
        );
        $stmt->execute([$nombreCompleto, $cuil, $dni, $telefono, $email, $objId, $horaEntrada, $horaSalida, $id]);
    }
    echo json_encode(['success' => true, 'id' => $id, 'accion' => 'actualizado']);
}
