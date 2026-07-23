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

// Handle file uploads
$uploadedFiles = $_FILES['archivos'];
$successCount = 0;
$errors = [];

// PHP $_FILES structure check (multi-upload support)
if (is_array($uploadedFiles['name'])) {
    $fileCount = count($uploadedFiles['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        if ($uploadedFiles['error'][$i] === UPLOAD_ERR_OK) {
            $fileName = basename($uploadedFiles['name'][$i]);
            // Clean file name to prevent directory traversal or invalid characters
            $fileName = preg_replace('/[\<\>\:\"\/\\\|\?\*]/', '', $fileName);
            $destPath = $targetDir . '/' . $fileName;
            
            if (move_uploaded_file($uploadedFiles['tmp_name'][$i], $destPath)) {
                $successCount++;
            } else {
                $errors[] = "Error al mover el archivo: " . $uploadedFiles['name'][$i];
            }
        } else {
            $errors[] = "Error de subida (" . $uploadedFiles['error'][$i] . ") para: " . $uploadedFiles['name'][$i];
        }
    }
} else {
    // Single file upload
    if ($uploadedFiles['error'] === UPLOAD_ERR_OK) {
        $fileName = basename($uploadedFiles['name']);
        $fileName = preg_replace('/[\<\>\:\"\/\\\|\?\*]/', '', $fileName);
        $destPath = $targetDir . '/' . $fileName;
        
        if (move_uploaded_file($uploadedFiles['tmp_name'], $destPath)) {
            $successCount++;
        } else {
            $errors[] = "Error al mover el archivo: " . $uploadedFiles['name'];
        }
    } else {
        $errors[] = "Error de subida (" . $uploadedFiles['error'] . ") para: " . $uploadedFiles['name'];
    }
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
