<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['es_admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once '../../config/db.php';

function param(string $key): string {
    return trim($_GET[$key] ?? '');
}

function validarFecha(?string $fecha): ?string {
    if (!$fecha) {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt && $dt->format('Y-m-d') === $fecha ? $fecha : null;
}

$busqueda = param('q');
$experiencia = param('experiencia_seguridad');
$curso = param('curso_habilitante');
$credencial = param('credencial_vigente');
$disponibilidad = param('disponibilidad_horaria');
$parteEmpresa = param('parte_track_seguridad');
$puesto = param('puesto_postula');
$desde = validarFecha(param('desde'));
$hasta = validarFecha(param('hasta'));

$where = [];
$params = [];

if ($busqueda !== '') {
    $where[] = "(nombre_completo LIKE ? OR dni LIKE ? OR telefono LIKE ? OR email LIKE ? OR localidad_residencia LIKE ? OR puesto_postula LIKE ?)";
    $like = '%' . $busqueda . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$enumSiNo = [
    'experiencia_seguridad' => $experiencia,
    'curso_habilitante' => $curso,
    'credencial_vigente' => $credencial,
    'parte_track_seguridad' => $parteEmpresa,
];

foreach ($enumSiNo as $campo => $valor) {
    if ($valor === 'si' || $valor === 'no') {
        $where[] = "{$campo} = ?";
        $params[] = $valor;
    }
}

$disponibles = ['Full Time', 'Turno Diurno', 'Turno Nocturno', 'Rotativos'];
if (in_array($disponibilidad, $disponibles, true)) {
    $where[] = "disponibilidad_horaria = ?";
    $params[] = $disponibilidad;
}

if ($puesto !== '') {
    $where[] = "puesto_postula LIKE ?";
    $params[] = '%' . $puesto . '%';
}

if ($desde) {
    $where[] = "DATE(fecha_registro) >= ?";
    $params[] = $desde;
}

if ($hasta) {
    $where[] = "DATE(fecha_registro) <= ?";
    $params[] = $hasta;
}

if ($desde && $hasta && $desde > $hasta) {
    http_response_code(400);
    echo json_encode(['error' => 'La fecha desde no puede ser mayor que la fecha hasta']);
    exit;
}

try {
    $db = getDB();
    $sql = "SELECT id, nombre_completo, dni, fecha_nacimiento, telefono, email,
                   localidad_residencia, experiencia_seguridad, curso_habilitante,
                   credencial_vigente, disponibilidad_horaria, puesto_postula,
                   parte_track_seguridad, archivo_adjunto,
                   DATE_FORMAT(fecha_registro, '%d/%m/%Y %H:%i') AS fecha_registro_fmt,
                   fecha_registro
            FROM postulantes";

    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY fecha_registro DESC, id DESC LIMIT 500";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando postulantes']);
}
