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

$db = getDB();

// No se borra un dispositivo con historial de tickets.
$stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE id_dispositivo = ?");
$stmt->execute([$id]);
$tickets = (int)$stmt->fetchColumn();

if ($tickets > 0) {
    http_response_code(409);
    echo json_encode(['error' => "No se puede eliminar: el dispositivo tiene $tickets ticket(s). Eliminalos primero."]);
    exit;
}

$stmt = $db->prepare("DELETE FROM dispositivos WHERE id_disp = ?");
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Dispositivo no encontrado']);
    exit;
}

echo json_encode(['success' => true, 'mensaje' => 'Dispositivo eliminado correctamente']);
