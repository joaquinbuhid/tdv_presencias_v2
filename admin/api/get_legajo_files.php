<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireBackofficeApi();

$idEmpleado = isset($_GET['id_empleado']) ? (int)$_GET['id_empleado'] : 0;
if (!$idEmpleado) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de empleado requerido']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT nombre, nro_legajo, url_leg FROM empleados WHERE id_empleado = ?");
$stmt->execute([$idEmpleado]);
$emp = $stmt->fetch();

if (!$emp) {
    http_response_code(404);
    echo json_encode(['error' => 'Empleado no encontrado']);
    exit;
}

$nro_legajo = trim($emp['nro_legajo'] ?? '');
if ($nro_legajo === '') {
    echo json_encode([
        'url_leg' => $emp['url_leg'],
        'files' => []
    ]);
    exit;
}

$folderName = $nro_legajo . '+' . str_replace(' ', '+', $emp['nombre']);
$folderName = preg_replace('/[\<\>\:\"\/\\\|\?\*]/', '', $folderName);
$path = __DIR__ . '/../../legajos/' . $folderName;

$filesList = [];
if (is_dir($path)) {
    $files = array_diff(scandir($path), ['.', '..', '.gitkeep']);
    foreach ($files as $file) {
        $filePath = $path . '/' . $file;
        if (is_file($filePath)) {
            $filesList[] = [
                'name' => $file,
                'size' => filesize($filePath),
                'date' => date('Y-m-d H:i:s', filemtime($filePath)),
                'url' => '../legajos/' . rawurlencode($folderName) . '/' . rawurlencode($file)
            ];
        }
    }
}

echo json_encode([
    'url_leg' => $emp['url_leg'],
    'folder_name' => $folderName,
    'files' => $filesList
]);
?>
