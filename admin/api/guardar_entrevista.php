<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireBackofficeApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON invalido']);
    exit;
}

$postulanteId = isset($data['postulante_id']) ? (int)$data['postulante_id'] : 0;
if ($postulanteId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Debe seleccionar un postulante']);
    exit;
}

function puntajeValido($v): ?int {
    if ($v === null || $v === '') return null;
    $n = (int)$v;
    return ($n >= 1 && $n <= 5) ? $n : null;
}

$relacionPesoAltura = puntajeValido($data['relacion_peso_altura'] ?? null);
$apariencia         = puntajeValido($data['apariencia_vestimenta'] ?? null);
$modulacion         = puntajeValido($data['modulacion_habla'] ?? null);
$valoracion         = puntajeValido($data['valoracion_personal'] ?? null);

if ($relacionPesoAltura === null || $apariencia === null || $modulacion === null || $valoracion === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Los puntajes deben ser numeros entre 1 y 5']);
    exit;
}

$estadosCiviles = ['Soltero/a', 'Casado/a', 'Divorciado/a', 'Viudo/a', 'Union convivencial', 'No informado'];
$estadoCivil = trim($data['estado_civil'] ?? '');
if ($estadoCivil !== '' && !in_array($estadoCivil, $estadosCiviles, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Estado civil invalido']);
    exit;
}

$tieneVehiculo = trim($data['tiene_vehiculo'] ?? '');
if ($tieneVehiculo !== '' && !in_array($tieneVehiculo, ['si', 'no'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valor de vehiculo invalido']);
    exit;
}
$vehiculo = trim($data['vehiculo'] ?? '');
if ($tieneVehiculo === 'si' && $vehiculo === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Indique que vehiculo tiene el postulante']);
    exit;
}
if ($tieneVehiculo !== 'si') {
    $vehiculo = '';
}

$fechaUltimoTrabajo = trim($data['fecha_ultimo_trabajo'] ?? '');
if ($fechaUltimoTrabajo !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $fechaUltimoTrabajo);
    if (!$dt || $dt->format('Y-m-d') !== $fechaUltimoTrabajo) {
        http_response_code(400);
        echo json_encode(['error' => 'Fecha de ultimo trabajo invalida']);
        exit;
    }
} else {
    $fechaUltimoTrabajo = null;
}

$peso = isset($data['peso']) && $data['peso'] !== '' ? (float)$data['peso'] : null;
$altura = isset($data['altura']) && $data['altura'] !== '' ? (float)$data['altura'] : null;
if ($peso !== null && ($peso <= 0 || $peso > 500)) {
    http_response_code(400);
    echo json_encode(['error' => 'Peso invalido']);
    exit;
}
if ($altura !== null && ($altura <= 0 || $altura > 300)) {
    http_response_code(400);
    echo json_encode(['error' => 'Altura invalida']);
    exit;
}

$hijos = isset($data['hijos']) && $data['hijos'] !== '' ? (int)$data['hijos'] : null;
if ($hijos !== null && $hijos < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Cantidad de hijos invalida']);
    exit;
}

$domicilio = trim($data['domicilio'] ?? '');
$valoracionTexto = trim($data['valoracion_texto'] ?? '');

// +1 punto si la fecha del ultimo trabajo en relacion de dependencia
// es mayor a 6 meses
$puntoUltimoTrabajo = 0;
if ($fechaUltimoTrabajo !== null && $fechaUltimoTrabajo < date('Y-m-d', strtotime('-6 months'))) {
    $puntoUltimoTrabajo = 1;
}

$puntajeSinValoracion = $relacionPesoAltura + $apariencia + $modulacion + $puntoUltimoTrabajo;
$puntajeTotal = $puntajeSinValoracion + $valoracion;

try {
    $db = getDB();

    $stmt = $db->prepare(
        "SELECT nombre_completo, dni, fecha_nacimiento, telefono, email,
                localidad_residencia, puesto_postula, disponibilidad_horaria,
                experiencia_seguridad, curso_habilitante, credencial_vigente,
                parte_track_seguridad, monotributista, genero
         FROM postulantes WHERE id = ?"
    );
    $stmt->execute([$postulanteId]);
    $postulante = $stmt->fetch();
    if (!$postulante) {
        http_response_code(404);
        echo json_encode(['error' => 'Postulante no encontrado']);
        exit;
    }

    $empleadoId = isset($_SESSION['empleado_id']) ? (int)$_SESSION['empleado_id'] : null;

    $stmt = $db->prepare(
        "INSERT INTO entrevistas
            (postulante_id, empleado_id, fecha_entrevista,
             nombre_completo, dni, fecha_nacimiento, telefono, email,
             localidad_residencia, puesto_postula, disponibilidad_horaria,
             experiencia_seguridad, curso_habilitante, credencial_vigente,
             parte_track_seguridad, monotributista, genero,
             peso, altura, relacion_peso_altura, apariencia_vestimenta,
             modulacion_habla, estado_civil, hijos, domicilio,
             tiene_vehiculo, vehiculo, fecha_ultimo_trabajo, punto_ultimo_trabajo,
             valoracion_personal, valoracion_texto,
             puntaje_sin_valoracion, puntaje_total)
         VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $postulanteId, $empleadoId,
        $postulante['nombre_completo'], $postulante['dni'], $postulante['fecha_nacimiento'],
        $postulante['telefono'], $postulante['email'], $postulante['localidad_residencia'],
        $postulante['puesto_postula'], $postulante['disponibilidad_horaria'],
        $postulante['experiencia_seguridad'], $postulante['curso_habilitante'],
        $postulante['credencial_vigente'], $postulante['parte_track_seguridad'],
        $postulante['monotributista'], $postulante['genero'],
        $peso, $altura, $relacionPesoAltura, $apariencia,
        $modulacion, $estadoCivil !== '' ? $estadoCivil : null, $hijos, $domicilio !== '' ? $domicilio : null,
        $tieneVehiculo !== '' ? $tieneVehiculo : null, $vehiculo !== '' ? $vehiculo : null,
        $fechaUltimoTrabajo, $puntoUltimoTrabajo,
        $valoracion, $valoracionTexto !== '' ? $valoracionTexto : null,
        $puntajeSinValoracion, $puntajeTotal,
    ]);

    echo json_encode([
        'success' => true,
        'id' => (int)$db->lastInsertId(),
        'puntaje_sin_valoracion' => $puntajeSinValoracion,
        'puntaje_total' => $puntajeTotal,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error guardando la entrevista']);
}
