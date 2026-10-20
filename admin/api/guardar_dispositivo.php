<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireAdminRealApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

// Texto opcional: vacío se guarda como NULL.
function txt(array $data, string $campo): ?string {
    $v = trim((string)($data[$campo] ?? ''));
    return $v === '' ? null : $v;
}

$id         = (int)($data['id'] ?? 0);
$nombre     = txt($data, 'nombre_disp');
$tipo       = (int)($data['id_tipo_disp'] ?? 0);
$empleado   = (int)($data['id_empleado'] ?? 0) ?: null;

if (!$nombre) {
    http_response_code(400);
    echo json_encode(['error' => 'El nombre del dispositivo es requerido']);
    exit;
}
if (!$tipo) {
    http_response_code(400);
    echo json_encode(['error' => 'Elegí el tipo de dispositivo']);
    exit;
}

$valores = [
    $nombre,
    txt($data, 'usuario_disp'),
    txt($data, 'clave_disp'),
    $empleado,
    txt($data, 'observaciones_disp'),
    $tipo,
    txt($data, 'mother_disp'),
    txt($data, 'micro_disp'),
    txt($data, 'ram'),
    txt($data, 'fuente'),
];

try {
    $db = getDB();

    if ($id === 0) {
        $stmt = $db->prepare(
            "INSERT INTO dispositivos
                (nombre_disp, usuario_disp, clave_disp, id_empleado, observaciones_disp,
                 id_tipo_disp, mother_disp, micro_disp, ram, fuente)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute($valores);
        echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId(), 'accion' => 'creado']);
        exit;
    }

    $stmt = $db->prepare(
        "UPDATE dispositivos
         SET nombre_disp=?, usuario_disp=?, clave_disp=?, id_empleado=?, observaciones_disp=?,
             id_tipo_disp=?, mother_disp=?, micro_disp=?, ram=?, fuente=?
         WHERE id_disp=?"
    );
    $stmt->execute([...$valores, $id]);
    echo json_encode(['success' => true, 'id' => $id, 'accion' => 'actualizado']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error guardando dispositivo']);
}
