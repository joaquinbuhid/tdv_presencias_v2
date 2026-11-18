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

$id = isset($data['id']) ? (int)$data['id'] : 0;
// convocado=0 devuelve la entrevista a la lista de entrevistas.
$convocado = (isset($data['convocado']) && (int)$data['convocado'] === 0) ? 0 : 1;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de entrevista invalido']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT contratado FROM entrevistas WHERE id_entrevista = ?");
    $stmt->execute([$id]);
    $entrevista = $stmt->fetch();
    if (!$entrevista) {
        http_response_code(404);
        echo json_encode(['error' => 'Entrevista no encontrada']);
        exit;
    }
    if ((int)$entrevista['contratado'] === 1) {
        http_response_code(409);
        echo json_encode(['error' => 'La entrevista ya fue contratada; primero devolvela desde Contratados']);
        exit;
    }

    $stmt = $db->prepare("UPDATE entrevistas SET convocado = ? WHERE id_entrevista = ?");
    $stmt->execute([$convocado, $id]);

    echo json_encode(['success' => true, 'convocado' => $convocado]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error actualizando la entrevista']);
}
