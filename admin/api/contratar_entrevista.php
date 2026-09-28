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
$contratado = (isset($data['contratado']) && (int)$data['contratado'] === 0) ? 0 : 1;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de entrevista invalido']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("UPDATE entrevistas SET contratado = ? WHERE id_entrevista = ?");
    $stmt->execute([$contratado, $id]);

    $check = $db->prepare("SELECT 1 FROM entrevistas WHERE id_entrevista = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Entrevista no encontrada']);
        exit;
    }

    echo json_encode(['success' => true, 'contratado' => $contratado]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error actualizando la entrevista']);
}
