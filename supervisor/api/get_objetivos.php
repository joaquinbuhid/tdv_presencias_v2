<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (empty($_SESSION['supervisor_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    "SELECT id_objetivo, nombre
     FROM objetivos
     WHERE supervisor_id = ?
     ORDER BY nombre"
);
$stmt->execute([$_SESSION['supervisor_id']]);
echo json_encode($stmt->fetchAll());

