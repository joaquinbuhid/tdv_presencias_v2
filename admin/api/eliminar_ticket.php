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
$id   = isset($data['id']) ? (int)$data['id'] : 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$stmt = getDB()->prepare("DELETE FROM tickets WHERE id_ticket = ?");
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Ticket no encontrado']);
    exit;
}

echo json_encode(['success' => true, 'mensaje' => 'Ticket eliminado correctamente']);
