<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';
require_once __DIR__ . '/presencias_helper.php';

if (!isset($_SESSION['empleado_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$data         = json_decode(file_get_contents('php://input'), true);
$tipo_id      = isset($data['tipo_id'])      ? (int)$data['tipo_id']      : 0;
$observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : '';
$lat          = isset($data['lat'])          ? (float)$data['lat']        : null;
$lng          = isset($data['lng'])          ? (float)$data['lng']        : null;
$objetivo_id  = isset($data['objetivo_id'])  ? (int)$data['objetivo_id']  : 0;

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
    echo json_encode(['error' => 'Tipo de novedad no válido']);
    exit;
}

// --- Verificación: ubicación dentro del radio del objetivo ---
// El vigilador usa su objetivo asignado; el supervisor puede elegir uno de los que supervisa.
if ($objetivo_id && !empty($_SESSION['es_supervisor'])) {
    $stmt = $db->prepare(
        "SELECT id_objetivo, coord_lat, coord_long, rad_metros, nombre AS objetivo_nombre
         FROM objetivos WHERE id_objetivo = ? AND supervisor_id = ?"
    );
    $stmt->execute([$objetivo_id, $empleado_id]);
} else {
    $stmt = $db->prepare(
        "SELECT o.id_objetivo, o.coord_lat, o.coord_long, o.rad_metros, o.nombre AS objetivo_nombre
         FROM empleados v
         JOIN objetivos o ON v.objetivo_id = o.id_objetivo
         WHERE v.id_empleado = ?"
    );
    $stmt->execute([$empleado_id]);
}
$objetivos = $stmt->fetch();

if (!$objetivos) {
    http_response_code(400);
    echo json_encode(['error' => 'No tiene un objetivo asignado. Contacte al administrador']);
    exit;
}

$distancia = haversineMetros($lat, $lng, (float)$objetivos['coord_lat'], (float)$objetivos['coord_long']);
$radio     = (int)$objetivos['rad_metros'];

if ($distancia > $radio) {
    http_response_code(403);
    echo json_encode([
        'error'     => "Fuera del área permitida. Se encuentra a " . round($distancia) . " m del objetivos \"{$objetivos['objetivo_nombre']}\" (radio máximo: {$radio} m).",
        'distancia' => round($distancia),
        'radio'     => $radio,
    ]);
    exit;
}

// --- Validar el turno y registrar, en una transacción ---
// Bloquear la fila del empleado serializa dos envíos simultáneos (doble toque, reintento),
// así el segundo ve la marca del primero.
try {
    $db->beginTransaction();
    $db->prepare("SELECT id_empleado FROM empleados WHERE id_empleado = ? FOR UPDATE")->execute([$empleado_id]);

    // Entrada solo si no hay turno abierto; Salida solo si lo hay. Novedad/Incidente sin límite.
    if ($tipo['nombre'] === 'Entrada' || $tipo['nombre'] === 'Salida') {
        $abierta = entradaAbierta($db, $empleado_id);
        $error = null;
        if ($tipo['nombre'] === 'Entrada' && $abierta) {
            $error = 'Ya tenés una entrada abierta desde ' . describirEntrada($abierta) . '. Si terminaste el turno, marcá la Salida.';
        } elseif ($tipo['nombre'] === 'Salida' && !$abierta) {
            $error = 'No tenés una entrada abierta. Marcá primero la Entrada; si te olvidaste de marcarla, avisale a tu supervisor.';
        }
        if ($error) {
            $db->rollBack();
            http_response_code(409);
            echo json_encode(['error' => $error]);
            exit;
        }
    }

    $stmt = $db->prepare(
        "INSERT INTO novedades (fecha, hora, tipo_novedad, observaciones, empleado_id, ip_dispositivo, coord_lat, coord_long, objetivo_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$fecha, $hora, $tipo_id, $observaciones, $empleado_id, $ip, $lat, $lng, (int)$objetivos['id_objetivo']]);
    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo registrar. Intentá de nuevo.']);
    exit;
}

echo json_encode([
    'success'   => true,
    'mensaje'   => "{$tipo['nombre']} registrada correctamente.",
    'fecha'     => $fecha,
    'hora'      => substr($hora, 0, 5),
    'tipo'      => $tipo['nombre'],
    'distancia' => round($distancia),
]);

