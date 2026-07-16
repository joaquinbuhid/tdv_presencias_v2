<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
requireBackofficeApi();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$ids = isset($data['ids']) && is_array($data['ids']) ? array_filter(array_map('intval', $data['ids'])) : [];

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'No se seleccionaron postulantes validos']);
    exit;
}

try {
    $db = getDB();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("DELETE FROM postulantes WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    echo json_encode(['success' => true, 'mensaje' => $stmt->rowCount() . ' postulantes eliminados']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error eliminando postulantes en lote']);
}
