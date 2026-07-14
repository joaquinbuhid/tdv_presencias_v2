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

$nombre = trim($data['nombre'] ?? '');
$fechaNac = trim($data['fecha_nac'] ?? '');
$fechaAlta = trim($data['fecha_alta'] ?? '') ?: date('Y-m-d');
$estCivil = trim($data['est_civil'] ?? '');
$empresaId = isset($data['empresa_id']) && $data['empresa_id'] !== '' ? (int)$data['empresa_id'] : null;
$domicilio = trim($data['domicilio'] ?? '');
$cuil = trim($data['cuil'] ?? '');
$dni = trim($data['dni'] ?? '');
$telefono = trim($data['telefono'] ?? '');
$legajo = trim($data['nro_legajo'] ?? '') ?: null;
$credencial = trim($data['nro_credencial'] ?? '') ?: null;
$fechaVencCred = trim($data['fecha_venc_cred'] ?? '') ?: null;
$activo = !empty($data['activo']) ? 1 : 0;
$objetivoId = isset($data['objetivo_id']) && $data['objetivo_id'] !== '' ? (int)$data['objetivo_id'] : null;
$horaEntrada = trim($data['hora_entrada'] ?? '') ?: null;
$horaSalida = trim($data['hora_salida'] ?? '') ?: null;
$pendiente = !empty($data['pendiente']) ? 1 : 0;
$email = trim($data['email'] ?? '');
$contrasena = $data['contrasena'] ?? '';
$tipo = isset($data['tipo']) && $data['tipo'] !== '' ? (int)$data['tipo'] : 1;
if (!in_array($tipo, [1, 2, 3, 4], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de usuario invalido']);
    exit;
}
if ($tipo !== 1) {
    $objetivoId = null;
}
$urlLeg = trim($data['url_leg'] ?? '') ?: null;
$nacionalidad = trim($data['nacionalidad'] ?? '') ?: null;

if (!$nombre || !$fechaNac || !$estCivil || !$domicilio || !$cuil || !$dni || !$telefono || !$email || !$contrasena) {
    http_response_code(400);
    echo json_encode(['error' => 'Complete todos los campos obligatorios']);
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

if ($horaEntrada && !preg_match('/^\d{2}:\d{2}$/', $horaEntrada)) {
    http_response_code(400);
    echo json_encode(['error' => 'Hora de entrada invalida']);
    exit;
}

if ($horaSalida && !preg_match('/^\d{2}:\d{2}$/', $horaSalida)) {
    http_response_code(400);
    echo json_encode(['error' => 'Hora de salida invalida']);
    exit;
}

foreach (['fecha de nacimiento' => $fechaNac, 'fecha de alta' => $fechaAlta, 'vencimiento de credencial' => $fechaVencCred] as $campo => $fecha) {
    if ($fecha && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        http_response_code(400);
        echo json_encode(['error' => "La $campo no es valida"]);
        exit;
    }
}

$db = getDB();

$stmt = $db->prepare('SELECT id_empleado FROM empleados WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Ya existe un usuario con ese email']);
    exit;
}

$stmt = $db->prepare('SELECT id_empleado FROM empleados WHERE CUIL = ?');
$stmt->execute([$cuil]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Ya existe un usuario con ese CUIL']);
    exit;
}

$stmt = $db->prepare('SELECT id_empleado FROM empleados WHERE DNI = ?');
$stmt->execute([$dni]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Ya existe un usuario con ese DNI']);
    exit;
}

$hash = password_hash($contrasena, PASSWORD_DEFAULT);

$stmt = $db->prepare(
    "INSERT INTO empleados
        (nombre, fecha_nac, est_civil, empresa_id, domicilio, CUIL, DNI, telefono,
         nro_legajo, nro_credencial, fecha_venc_cred, activo, objetivo_id, fecha_alta,
         hora_entrada, hora_salida, pendiente, email, contrasena, tipo, url_leg, nacionalidad)
     VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->execute([
    $nombre,
    $fechaNac,
    $estCivil,
    $empresaId,
    $domicilio,
    $cuil,
    $dni,
    $telefono,
    $legajo,
    $credencial,
    $fechaVencCred,
    $activo,
    $objetivoId,
    $fechaAlta,
    $horaEntrada,
    $horaSalida,
    $pendiente,
    $email,
    $hash,
    $tipo,
    $urlLeg,
    $nacionalidad,
]);

echo json_encode([
    'success' => true,
    'id' => (int)$db->lastInsertId(),
    'mensaje' => 'Usuario creado correctamente',
]);
