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

// El formulario de postulaciones guarda el género como código.
function generoLabel(?string $g): string {
    if ($g === '1') return 'Masculino';
    if ($g === '2') return 'Femenino';
    return $g ?? '';
}

// Puntajes 1 a 5 de la entrevista.
function puntaje($v): string {
    return $v === null || $v === '' ? '' : $v . ' / 5';
}

function fechaDMY(?string $fecha): string {
    if (!$fecha) {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt ? $dt->format('d/m/Y') : $fecha;
}

// Estado: pendientes (entrevistados), convocados o contratados.
// Compatibilidad: ?contratadas=1 equivale a contratados, ?contratadas=0 a pendientes.
$estado = $_GET['estado'] ?? '';
if ($estado === '' && isset($_GET['contratadas'])) {
    $estado = $_GET['contratadas'] === '1' ? 'contratados' : ($_GET['contratadas'] === '0' ? 'pendientes' : '');
}
$filtrosEstado = [
    'pendientes'  => 'e.contratado = 0 AND e.convocado = 0',
    'convocados'  => 'e.contratado = 0 AND e.convocado = 1',
    'contratados' => 'e.contratado = 1',
];
// Vacío = todas.

// ?ids=1,2,3 exporta solo las entrevistas seleccionadas.
$ids = [];
if (!empty($_GET['ids'])) {
    $ids = array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string)$_GET['ids'])),
        fn($id) => $id > 0
    )));
}

try {
    $db = getDB();
    $sql = "SELECT e.*, emp.nombre AS entrevistador,
                   DATE_FORMAT(e.fecha_entrevista, '%d/%m/%Y %H:%i') AS fecha_fmt,
                   TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) AS edad
            FROM entrevistas e
            LEFT JOIN empleados emp ON emp.id_empleado = e.empleado_id";
    $where = [];
    $params = [];
    if (isset($filtrosEstado[$estado])) {
        $where[] = $filtrosEstado[$estado];
    }
    if ($ids) {
        $where[] = "e.id_entrevista IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";
        $params = array_merge($params, $ids);
    }
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    // Ordenado por puntaje total (mayor primero); empates por fecha más reciente.
    $sql .= " ORDER BY e.puntaje_total DESC, e.fecha_entrevista DESC, e.id_entrevista DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Error consultando entrevistas';
    exit;
}

$sufijo = (in_array($estado, ['convocados', 'contratados'], true) ? $estado . '_' : '') . ($ids ? 'seleccion_' : '');
$filename = 'entrevistas_' . $sufijo . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$columnas = [
    'ID'                          => fn($r) => $r['id_entrevista'],
    'Fecha entrevista'            => fn($r) => $r['fecha_fmt'],
    'Entrevistador'               => fn($r) => $r['entrevistador'],
    'Convocado'                   => fn($r) => (int)($r['convocado'] ?? 0) === 1 ? 'SI' : 'NO',
    'Contratado'                  => fn($r) => (int)$r['contratado'] === 1 ? 'SI' : 'NO',
    'Nombre completo'             => fn($r) => $r['nombre_completo'],
    'DNI'                         => fn($r) => $r['dni'],
    'Fecha nacimiento'            => fn($r) => fechaDMY($r['fecha_nacimiento']),
    'Edad'                        => fn($r) => $r['edad'],
    'Género'                      => fn($r) => generoLabel($r['genero']),
    'Teléfono'                    => fn($r) => $r['telefono'],
    'Email'                       => fn($r) => $r['email'],
    'Localidad'                   => fn($r) => $r['localidad_residencia'],
    'Domicilio'                   => fn($r) => $r['domicilio'],
    'Puesto'                      => fn($r) => $r['puesto_postula'],
    'Disponibilidad'              => fn($r) => $r['disponibilidad_horaria'],
    'Experiencia seguridad'       => fn($r) => siNo($r['experiencia_seguridad']),
    'Curso habilitante (declarado)'      => fn($r) => siNo($r['curso_habilitante']),
    'Curso vigilador provincial (verificado entrev.)' => fn($r) => siNo($r['curso_vigilador_verificado'] ?? null),
    'Título secundario'           => fn($r) => siNo($r['titulo_secundario'] ?? null),
    'Credencial vigente'          => fn($r) => siNo($r['credencial_vigente']),
    'Fue parte de Track'          => fn($r) => siNo($r['parte_track_seguridad']),
    'Monotributista'              => fn($r) => siNo($r['monotributista']),
    'Peso (kg)'                   => fn($r) => $r['peso'],
    'Altura (cm)'                 => fn($r) => $r['altura'],
    'Relación peso/altura'        => fn($r) => puntaje($r['relacion_peso_altura']),
    'Apariencia/vestimenta'       => fn($r) => puntaje($r['apariencia_vestimenta']),
    'Modulación y habla'          => fn($r) => puntaje($r['modulacion_habla']),
    'Estado civil'                => fn($r) => $r['estado_civil'],
    'Hijos'                       => fn($r) => $r['hijos'],
    'Vehículo'                    => fn($r) => $r['tiene_vehiculo'] === 'si' ? ($r['vehiculo'] ?: 'SI') : siNo($r['tiene_vehiculo']),
    'Familiares en la empresa'    => fn($r) => ($r['tiene_familiares'] ?? null) === 'si' ? ($r['familiares'] ?: 'SI') : siNo($r['tiene_familiares'] ?? null),
    'Último trabajo en rel. dep.' => fn($r) => fechaDMY($r['fecha_ultimo_trabajo']),
    'Punto +6 meses'              => fn($r) => (int)$r['punto_ultimo_trabajo'] === 1 ? 'SI' : 'NO',
    'Valoración personal'         => fn($r) => puntaje($r['valoracion_personal']),
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
