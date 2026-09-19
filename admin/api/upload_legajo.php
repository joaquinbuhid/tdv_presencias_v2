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

$idEmpleado = isset($_POST['id_empleado']) ? (int)$_POST['id_empleado'] : 0;
if (!$idEmpleado) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de empleado requerido']);
    exit;
}

if (empty($_FILES['archivos'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No se han recibido archivos para subir']);
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
    http_response_code(400);
    echo json_encode(['error' => 'El empleado debe tener numero de legajo asignado para subir archivos']);
    exit;
}

// Generate folder name
$folderName = $nro_legajo . '+' . str_replace(' ', '+', $emp['nombre']);
$folderName = preg_replace('/[\<\>\:\"\/\\\|\?\*]/', '', $folderName);
$targetDir = __DIR__ . '/../../legajos/' . $folderName;

// Create folder if it doesn't exist
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear la carpeta del legajo en el servidor']);
        exit;
    }
}

const LEGAJO_MAX_BYTES = 10 * 1024 * 1024;

$allowedExtensions = array_fill_keys(
    ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'doc', 'docx', 'xls', 'xlsx'],
    true
);

function nombreSeguroLegajo(string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($name, PATHINFO_FILENAME));
    $base = trim($base, '._-') ?: 'archivo';
    return $base . '-' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
}

function procesarArchivoLegajo(string $originalName, string $tmpName, int $errorCode, int $size, string $targetDir, array $allowedExtensions, array &$errors): bool {
    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = "Error de subida ($errorCode) para: $originalName";
        return false;
    }
    if ($size <= 0 || $size > LEGAJO_MAX_BYTES) {
        $errors[] = "Archivo vacío o mayor a 10 MB: $originalName";
        return false;
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!isset($allowedExtensions[$ext])) {
        $errors[] = "Tipo de archivo no permitido: $originalName";
        return false;
    }

    $destPath = $targetDir . '/' . nombreSeguroLegajo($originalName);
    if (!move_uploaded_file($tmpName, $destPath)) {
        $errors[] = "Error al mover el archivo: $originalName";
        return false;
    }
    return true;
}

$uploadedFiles = $_FILES['archivos'];
$successCount = 0;
$errors = [];

if (is_array($uploadedFiles['name'])) {
    $fileCount = count($uploadedFiles['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        if (procesarArchivoLegajo(
            (string)$uploadedFiles['name'][$i],
            (string)$uploadedFiles['tmp_name'][$i],
            (int)$uploadedFiles['error'][$i],
            (int)$uploadedFiles['size'][$i],
            $targetDir,
            $allowedExtensions,
            $errors
        )) {
            $successCount++;
        }
    }
} else {
    if (procesarArchivoLegajo(
        (string)$uploadedFiles['name'],
        (string)$uploadedFiles['tmp_name'],
        (int)$uploadedFiles['error'],
        (int)$uploadedFiles['size'],
        $targetDir,
        $allowedExtensions,
        $errors
    )) {
        $successCount++;
    }
}

if ($successCount === 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'No se subio ningun archivo valido',
        'errors' => $errors
    ]);
    exit;
}

// Generate the URL requested by the user
$url_leg = 'tdvsrl.com/legajos/' . $nro_legajo . '+' . str_replace(' ', '+', $emp['nombre']);

// Update the database
$updateStmt = $db->prepare("UPDATE empleados SET url_leg = ? WHERE id_empleado = ?");
$updateStmt->execute([$url_leg, $idEmpleado]);

echo json_encode([
    'success' => true,
    'uploaded_count' => $successCount,
    'url_leg' => $url_leg,
    'errors' => $errors
]);
?>
