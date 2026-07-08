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
    echo json_encode(['error' => 'MÃ©todo no permitido']);
    exit;
}

$data         = json_decode(file_get_contents('php://input'), true);
$tipo_id      = isset($data['tipo_id'])      ? (int)$data['tipo_id']      : 0;
$observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : '';
$lat          = isset($data['lat'])          ? (float)$data['lat']        : null;
$lng          = isset($data['lng'])          ? (float)$data['lng']        : null;

if (!$tipo_id || $lat === null || $lng === null || $lat == 0 || $lng == 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos incompletos: se requiere tipo, latitud y longitud']);
    exit;
}

$ip          = getClientIP();
$empleado_id = (int)$_SESSION['empleado_id'];
$fecha       = date('Y-m-d');
$hora        = date('H:i:s');

$db = getDB();

// Verificar que el tipo de novedad existe
$stmt = $db->prepare("SELECT id_tipo, nombre FROM tipo_novedad WHERE id_tipo = ?");
$stmt->execute([$tipo_id]);
$tipo = $stmt->fetch();
if (!$tipo) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de novedad no vÃ¡lido']);
    exit;
}

// --- VerificaciÃ³n: el vigilador no haya registrado este tipo hoy ---
$stmt = $db->prepare(
    "SELECT id_novedad FROM novedades
     WHERE empleado_id = ? AND tipo_novedad = ? AND fecha = ?"
);
$stmt->execute([$empleado_id, $tipo_id, $fecha]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode([
        'error' => "Ya registrÃ³ \"{$tipo['nombre']}\" el dÃ­a de hoy."
    ]);
    exit;
}

// --- VerificaciÃ³n 2: ubicaciÃ³n dentro del radio del objetivos ---
$stmt = $db->prepare(
    "SELECT o.coord_lat, o.coord_long, o.rad_metros, o.nombre AS objetivo_nombre
     FROM empleados v
     JOIN objetivos o ON v.objetivo_id = o.id_objetivo
     WHERE v.id_empleado = ?"
);
$stmt->execute([$empleado_id]);
$objetivos = $stmt->fetch();

if (!$objetivos) {
    http_response_code(400);
    echo json_encode(['error' => 'No tiene un objetivos asignado. Contacte al administrador']);
    exit;
}

$distancia = haversineMetros($lat, $lng, (float)$objetivos['coord_lat'], (float)$objetivos['coord_long']);
$radio     = (int)$objetivos['rad_metros'];

if ($distancia > $radio) {
    http_response_code(403);
    echo json_encode([
        'error'     => "Fuera del Ã¡rea permitida. Se encuentra a " . round($distancia) . " m del objetivos \"{$objetivos['objetivo_nombre']}\" (radio mÃ¡ximo: {$radio} m).",
        'distancia' => round($distancia),
        'radio'     => $radio,
    ]);
    exit;
}

// --- Registrar la novedad ---
$stmt = $db->prepare(
    "INSERT INTO novedades (fecha, hora, tipo_novedad, observaciones, empleado_id, ip_dispositivo, coord_lat, coord_long)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->execute([$fecha, $hora, $tipo_id, $observaciones, $empleado_id, $ip, $lat, $lng]);

echo json_encode([
    'success'   => true,
    'mensaje'   => "{$tipo['nombre']} registrada correctamente.",
    'fecha'     => $fecha,
    'hora'      => substr($hora, 0, 5),
    'tipo'      => $tipo['nombre'],
    'distancia' => round($distancia),
]);

