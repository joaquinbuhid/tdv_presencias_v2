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

$fechaNac = trim($data['fecha_nac'] ?? '') ?: '1900-01-01';
$estCivil = trim($data['est_civil'] ?? '') ?: 'No informado';
$domicilio = trim($data['domicilio'] ?? '') ?: 'No informado';
$nacionalidad = trim($data['nacionalidad'] ?? '') ?: null;
$empresaId = isset($data['empresa_id']) && $data['empresa_id'] !== '' ? (int)$data['empresa_id'] : null;
$nroLegajo = trim($data['nro_legajo'] ?? '') ?: null;
$nroCredencial = trim($data['nro_credencial'] ?? '') ?: null;
$fechaVencCred = trim($data['fecha_venc_cred'] ?? '') ?: null;
$urlLeg = trim($data['url_leg'] ?? '') ?: null;
$fechaAlta = trim($data['fecha_alta'] ?? '') ?: date('Y-m-d');
$tipo = isset($data['tipo']) && $data['tipo'] !== '' ? (int)$data['tipo'] : 1;
$activo = isset($data['activo']) ? (int)$data['activo'] : 1;
$pendiente = isset($data['pendiente']) ? (int)$data['pendiente'] : 0;

if ($id === 0 && !esAdminReal()) {
    http_response_code(403);
    echo json_encode(['error' => 'El oficinista solo puede editar empleados existentes']);
    exit;
}

if (!$nombre || !$dni || !$telefono || !$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos obligatorios (nombre, DNI, telefono, email)']);
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
$stmt->execute([$dni, $cuil, $id]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => "El DNI/CUIL ya esta registrado"]);
    exit;
}

$nombreCompleto = $nombre;

if ($id === 0) {
    $hash = password_hash($contrasena, PASSWORD_DEFAULT);
    $stmt = $db->prepare(
        "INSERT INTO empleados
            (nombre, fecha_nac, est_civil, empresa_id, domicilio, CUIL, DNI, telefono,
             nro_legajo, nro_credencial, fecha_venc_cred, activo, objetivo_id,
             hora_entrada, hora_salida, pendiente, email, contrasena, fecha_alta, tipo, url_leg, nacionalidad)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $nombreCompleto, $fechaNac, $estCivil, $empresaId, $domicilio, $cuil, $dni, $telefono,
        $nroLegajo, $nroCredencial, $fechaVencCred, $activo, $objId,
        $horaEntrada, $horaSalida, $pendiente, $email, $hash, $fechaAlta, $tipo, $urlLeg, $nacionalidad
    ]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'accion' => 'creado']);
} else {
    if ($contrasena) {
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $stmt = $db->prepare(
            "UPDATE empleados
             SET nombre=?, fecha_nac=?, est_civil=?, empresa_id=?, domicilio=?, CUIL=?, DNI=?, telefono=?,
                 nro_legajo=?, nro_credencial=?, fecha_venc_cred=?, activo=?, objetivo_id=?,
                 hora_entrada=?, hora_salida=?, pendiente=?, email=?, contrasena=?, fecha_alta=?, tipo=?, url_leg=?, nacionalidad=?
             WHERE id_empleado=? AND COALESCE(tipo, 1) = 1"
        );
        $stmt->execute([
            $nombreCompleto, $fechaNac, $estCivil, $empresaId, $domicilio, $cuil, $dni, $telefono,
            $nroLegajo, $nroCredencial, $fechaVencCred, $activo, $objId,
            $horaEntrada, $horaSalida, $pendiente, $email, $hash, $fechaAlta, $tipo, $urlLeg, $nacionalidad, $id
        ]);
    } else {
        $stmt = $db->prepare(
            "UPDATE empleados
             SET nombre=?, fecha_nac=?, est_civil=?, empresa_id=?, domicilio=?, CUIL=?, DNI=?, telefono=?,
                 nro_legajo=?, nro_credencial=?, fecha_venc_cred=?, activo=?, objetivo_id=?,
                 hora_entrada=?, hora_salida=?, pendiente=?, email=?, fecha_alta=?, tipo=?, url_leg=?, nacionalidad=?
             WHERE id_empleado=? AND COALESCE(tipo, 1) = 1"
        );
        $stmt->execute([
            $nombreCompleto, $fechaNac, $estCivil, $empresaId, $domicilio, $cuil, $dni, $telefono,
            $nroLegajo, $nroCredencial, $fechaVencCred, $activo, $objId,
            $horaEntrada, $horaSalida, $pendiente, $email, $fechaAlta, $tipo, $urlLeg, $nacionalidad, $id
        ]);
    }
    echo json_encode(['success' => true, 'id' => $id, 'accion' => 'actualizado']);
}
