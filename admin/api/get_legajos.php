<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireBackofficeApi();

$db = getDB();
$stmt = $db->query(
    "SELECT id_empleado, nombre, nro_legajo, DNI AS dni, CUIL AS cuil, url_leg, tipo, activo, pendiente
     FROM empleados
     ORDER BY nombre ASC"
);
$empleados = $stmt->fetchAll();

$roles = [
    1 => 'Vigilador',
    2 => 'Supervisor',
    3 => 'Oficinista',
    4 => 'Administrador'
];

$res = [];
foreach ($empleados as $e) {
    $folderName = '';
    $filesCount = 0;
    $folderExists = false;
    $nro_legajo = trim($e['nro_legajo'] ?? '');
    
    if ($nro_legajo !== '') {
        $folderName = $nro_legajo . '+' . str_replace(' ', '+', $e['nombre']);
        $folderName = preg_replace('/[\<\>\:\"\/\\\|\?\*]/', '', $folderName);
        $path = __DIR__ . '/../../legajos/' . $folderName;
        if (is_dir($path)) {
            $folderExists = true;
            $files = array_diff(scandir($path), ['.', '..']);
            $filesCount = count($files);
        }
    }
    
    $res[] = [
        'id_empleado' => (int)$e['id_empleado'],
        'nombre' => $e['nombre'],
        'nro_legajo' => $e['nro_legajo'],
        'dni' => $e['dni'] ?: $e['cuil'],
        'url_leg' => $e['url_leg'],
        'tipo' => (int)$e['tipo'],
        'rol' => $roles[(int)$e['tipo']] ?? 'Vigilador',
        'activo' => (int)$e['activo'],
        'pendiente' => (int)$e['pendiente'],
        'folder_exists' => $folderExists,
        'folder_name' => $folderName,
        'files_count' => $filesCount
    ];
}

echo json_encode($res);
?>
