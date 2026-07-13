<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';

function jsonResponse(array $payload, int $status = 200): void {
    http_response_code($status);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload);
    exit;
}

if (empty($_SESSION['es_admin']) || !esAdminReal()) {
    jsonResponse(['error' => 'No autorizado'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Metodo no permitido'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    jsonResponse(['error' => 'JSON invalido'], 400);
}

$id = isset($data['id']) ? (int)$data['id'] : 0;
$nombre = trim($data['nombre'] ?? '');
$descripcion = trim($data['descripcion'] ?? '');
$coordLat = array_key_exists('coord_lat', $data) ? (float)$data['coord_lat'] : null;
$coordLong = array_key_exists('coord_long', $data) ? (float)$data['coord_long'] : null;
$radio = isset($data['rad_metros']) ? (int)$data['rad_metros'] : 200;
$supervisorId = isset($data['supervisor_id']) && $data['supervisor_id'] !== ''
    ? (int)$data['supervisor_id']
    : null;

if (!$nombre) {
    jsonResponse(['error' => 'El nombre es requerido'], 400);
}
if ($coordLat === null || $coordLong === null) {
    jsonResponse(['error' => 'Las coordenadas son requeridas'], 400);
}
if ($coordLat < -90 || $coordLat > 90) {
    jsonResponse(['error' => 'Latitud invalida (debe estar entre -90 y 90)'], 400);
}
if ($coordLong < -180 || $coordLong > 180) {
    jsonResponse(['error' => 'Longitud invalida (debe estar entre -180 y 180)'], 400);
}
if ($radio < 1) {
    jsonResponse(['error' => 'El radio debe ser mayor a 0'], 400);
}

try {
    $db = getDB();

    if ($id === 0) {
        $stmt = $db->prepare(
            "INSERT INTO objetivos (nombre, descripcion, coord_lat, coord_long, rad_metros, supervisor_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$nombre, $descripcion ?: null, $coordLat, $coordLong, $radio, $supervisorId]);
        jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId(), 'accion' => 'creado']);
    }

    $stmt = $db->prepare(
        "UPDATE objetivos
         SET nombre=?, descripcion=?, coord_lat=?, coord_long=?, rad_metros=?, supervisor_id=?
         WHERE id_objetivo=?"
    );
    $stmt->execute([$nombre, $descripcion ?: null, $coordLat, $coordLong, $radio, $supervisorId, $id]);
    jsonResponse(['success' => true, 'id' => $id, 'accion' => 'actualizado']);
} catch (PDOException $e) {
    jsonResponse(['error' => 'Error guardando objetivo'], 500);
}
