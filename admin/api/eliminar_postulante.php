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
$id = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Postulante invalido']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM postulantes WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Postulante no encontrado']);
        exit;
    }

    echo json_encode(['success' => true, 'mensaje' => 'Postulante eliminado']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error eliminando postulante']);
}
