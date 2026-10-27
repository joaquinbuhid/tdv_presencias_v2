<?php
require_once __DIR__ . '/../auth.php';
requireBackofficeApi();
require_once '../../config/db.php';

function excelCell($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function siNo(?string $v): string {
    return $v === 'si' ? 'SI' : ($v === 'no' ? 'NO' : '');
}

function fechaDMY(?string $fecha): string {
    if (!$fecha) {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt ? $dt->format('d/m/Y') : $fecha;
}

// ?contratadas=0 (entrevistas pendientes), 1 (contratados), vacío = todas.
$contratadas = $_GET['contratadas'] ?? '';

try {
    $db = getDB();
    $sql = "SELECT e.*, emp.nombre AS entrevistador,
                   DATE_FORMAT(e.fecha_entrevista, '%d/%m/%Y %H:%i') AS fecha_fmt,
                   TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) AS edad
            FROM entrevistas e
            LEFT JOIN empleados emp ON emp.id_empleado = e.empleado_id";
    $params = [];
    if ($contratadas === '0' || $contratadas === '1') {
        $sql .= " WHERE e.contratado = ?";
        $params[] = (int)$contratadas;
    }
    $sql .= " ORDER BY e.fecha_entrevista DESC, e.id_entrevista DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Error consultando entrevistas';
    exit;
}

$sufijo = $contratadas === '1' ? 'contratados_' : '';
$filename = 'entrevistas_' . $sufijo . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$columnas = [
    'ID'                          => fn($r) => $r['id_entrevista'],
    'Fecha entrevista'            => fn($r) => $r['fecha_fmt'],
    'Entrevistador'               => fn($r) => $r['entrevistador'],
    'Contratado'                  => fn($r) => (int)$r['contratado'] === 1 ? 'SI' : 'NO',
    'Nombre completo'             => fn($r) => $r['nombre_completo'],
    'DNI'                         => fn($r) => $r['dni'],
    'Fecha nacimiento'            => fn($r) => fechaDMY($r['fecha_nacimiento']),
    'Edad'                        => fn($r) => $r['edad'],
    'Género'                      => fn($r) => $r['genero'],
    'Teléfono'                    => fn($r) => $r['telefono'],
    'Email'                       => fn($r) => $r['email'],
    'Localidad'                   => fn($r) => $r['localidad_residencia'],
    'Domicilio'                   => fn($r) => $r['domicilio'],
    'Puesto'                      => fn($r) => $r['puesto_postula'],
    'Disponibilidad'              => fn($r) => $r['disponibilidad_horaria'],
    'Experiencia seguridad'       => fn($r) => siNo($r['experiencia_seguridad']),
    'Curso habilitante'           => fn($r) => siNo($r['curso_habilitante']),
    'Credencial vigente'          => fn($r) => siNo($r['credencial_vigente']),
    'Fue parte de Track'          => fn($r) => siNo($r['parte_track_seguridad']),
    'Monotributista'              => fn($r) => siNo($r['monotributista']),
    'Peso (kg)'                   => fn($r) => $r['peso'],
    'Altura (cm)'                 => fn($r) => $r['altura'],
    'Relación peso/altura'        => fn($r) => $r['relacion_peso_altura'],
    'Apariencia/vestimenta'       => fn($r) => $r['apariencia_vestimenta'],
    'Modulación y habla'          => fn($r) => $r['modulacion_habla'],
    'Estado civil'                => fn($r) => $r['estado_civil'],
    'Hijos'                       => fn($r) => $r['hijos'],
    'Vehículo'                    => fn($r) => $r['tiene_vehiculo'] === 'si' ? ($r['vehiculo'] ?: 'SI') : siNo($r['tiene_vehiculo']),
    'Familiares en la empresa'    => fn($r) => ($r['tiene_familiares'] ?? null) === 'si' ? ($r['familiares'] ?: 'SI') : siNo($r['tiene_familiares'] ?? null),
    'Último trabajo en rel. dep.' => fn($r) => fechaDMY($r['fecha_ultimo_trabajo']),
    'Punto +6 meses'              => fn($r) => (int)$r['punto_ultimo_trabajo'] === 1 ? 'SI' : 'NO',
    'Valoración personal'         => fn($r) => $r['valoracion_personal'],
    'Comentario'                  => fn($r) => $r['valoracion_texto'],
    'Puntaje sin valoración'      => fn($r) => $r['puntaje_sin_valoracion'],
    'Puntaje total'               => fn($r) => $r['puntaje_total'],
];

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
            <?php foreach (array_keys($columnas) as $titulo): ?>
            <th><?= excelCell($titulo) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <?php foreach ($columnas as $valor): ?>
            <td><?= excelCell($valor($row) ?? '') ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
