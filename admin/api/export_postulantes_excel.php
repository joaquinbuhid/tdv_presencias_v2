<?php
require_once __DIR__ . '/../auth.php';
requireBackofficeApi();
require_once '../../config/db.php';

const POSTULANTES_UPLOAD_URL = 'https://postulaciones.tdvsrl.com/uploads/';

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

function archivoUrl(?string $path): string {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }
    $filename = basename(str_replace('\\', '/', $path));
    return POSTULANTES_UPLOAD_URL . rawurlencode($filename);
}

function excelCell($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
$edad_desde = param('edad_desde');
$edad_hasta = param('edad_hasta');

if ($desde && $hasta && $desde > $hasta) {
    http_response_code(400);
    echo 'La fecha desde no puede ser mayor que la fecha hasta';
    exit;
}
if ($edad_desde !== '' && (!ctype_digit($edad_desde) || (int)$edad_desde < 0)) {
    http_response_code(400);
    echo 'La edad desde debe ser un número entero válido y mayor o igual a 0';
    exit;
}
if ($edad_hasta !== '' && (!ctype_digit($edad_hasta) || (int)$edad_hasta < 0)) {
    http_response_code(400);
    echo 'La edad hasta debe ser un número entero válido y mayor o igual a 0';
    exit;
}
if ($edad_desde !== '' && $edad_hasta !== '' && (int)$edad_desde > (int)$edad_hasta) {
    http_response_code(400);
    echo 'La edad desde no puede ser mayor que la edad hasta';
    exit;
}

$where = [];
$params = [];

if ($busqueda !== '') {
    $where[] = "(nombre_completo LIKE ? OR dni LIKE ? OR telefono LIKE ? OR email LIKE ? OR localidad_residencia LIKE ? OR puesto_postula LIKE ?)";
    $like = '%' . $busqueda . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

foreach ([
    'experiencia_seguridad' => $experiencia,
    'curso_habilitante' => $curso,
    'credencial_vigente' => $credencial,
    'parte_track_seguridad' => $parteEmpresa,
] as $campo => $valor) {
    if ($valor === 'si' || $valor === 'no') {
        $where[] = "{$campo} = ?";
        $params[] = $valor;
    }
}

if (in_array($disponibilidad, ['Full Time', 'Turno Diurno', 'Turno Nocturno', 'Rotativos'], true)) {
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

if ($edad_desde !== '') {
    $where[] = "TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) >= ?";
    $params[] = (int)$edad_desde;
}

if ($edad_hasta !== '') {
    $where[] = "TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) <= ?";
    $params[] = (int)$edad_hasta;
}

try {
    $db = getDB();
    $sql = "SELECT id, nombre_completo, dni, fecha_nacimiento,
                   TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad,
                   telefono, email,
                   localidad_residencia, experiencia_seguridad, curso_habilitante,
                   credencial_vigente, disponibilidad_horaria, puesto_postula,
                   parte_track_seguridad, archivo_adjunto,
                   DATE_FORMAT(fecha_registro, '%d/%m/%Y %H:%i') AS fecha_registro_fmt
            FROM postulantes";

    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY fecha_registro DESC, id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Error consultando postulantes';
    exit;
}

$filename = 'postulantes_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 6px; mso-number-format:"\@"; }
        th { background: #000; color: #fff; font-weight: bold; }
    </style>
</head>
<body>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre completo</th>
            <th>DNI</th>
            <th>Fecha nacimiento</th>
            <th>Edad</th>
            <th>Telefono</th>
            <th>Email</th>
            <th>Localidad</th>
            <th>Experiencia seguridad</th>
            <th>Curso habilitante</th>
            <th>Credencial vigente</th>
            <th>Disponibilidad</th>
            <th>Puesto</th>
            <th>Fue parte de Track</th>
            <th>Archivo adjunto</th>
            <th>Fecha registro</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <?php $url = archivoUrl($row['archivo_adjunto'] ?? ''); ?>
        <tr>
            <td><?= excelCell($row['id'] ?? '') ?></td>
            <td><?= excelCell($row['nombre_completo'] ?? '') ?></td>
            <td><?= excelCell($row['dni'] ?? '') ?></td>
            <td><?= excelCell($row['fecha_nacimiento'] ?? '') ?></td>
            <td><?= excelCell($row['edad'] ?? '') ?></td>
            <td><?= excelCell($row['telefono'] ?? '') ?></td>
            <td><?= excelCell($row['email'] ?? '') ?></td>
            <td><?= excelCell($row['localidad_residencia'] ?? '') ?></td>
            <td><?= excelCell($row['experiencia_seguridad'] ?? '') ?></td>
            <td><?= excelCell($row['curso_habilitante'] ?? '') ?></td>
            <td><?= excelCell($row['credencial_vigente'] ?? '') ?></td>
            <td><?= excelCell($row['disponibilidad_horaria'] ?? '') ?></td>
            <td><?= excelCell($row['puesto_postula'] ?? '') ?></td>
            <td><?= excelCell($row['parte_track_seguridad'] ?? '') ?></td>
            <td><?php if ($url): ?><a href="<?= excelCell($url) ?>"><?= excelCell($url) ?></a><?php endif; ?></td>
            <td><?= excelCell($row['fecha_registro_fmt'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
