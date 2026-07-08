<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (empty($_SESSION['supervisor_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$data         = json_decode(file_get_contents('php://input'), true);
$vigiladorId  = isset($data['empleado_id']) ? (int)$data['empleado_id'] : 0;
$horaEntrada  = trim($data['hora_entrada'] ?? '');
$horaSalida   = trim($data['hora_salida']  ?? '');

if (!$vigiladorId || !$horaEntrada || !$horaSalida) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

// Validar formato HH:MM
$re = '/^\d{2}:\d{2}$/';
if (!preg_match($re, $horaEntrada) || !preg_match($re, $horaSalida)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de hora inválido']);
    exit;
}

$db  = getDB();
$supId = (int)$_SESSION['supervisor_id'];

// Verificar que el vigilador pertenece a un objetivos de este supervisor
$chk = $db->prepare(
    "SELECT v.id_empleado FROM empleados v
     JOIN objetivos o ON v.objetivo_id = o.id_objetivo
     WHERE v.id_empleado = ? AND o.supervisor_id = ?"
);
$chk->execute([$vigiladorId, $supId]);
if (!$chk->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Vigilador no pertenece a sus objetivos']);
    exit;
}

$upd = $db->prepare(
    "UPDATE empleados SET hora_entrada = ?, hora_salida = ? WHERE id_empleado = ?"
);
$upd->execute([$horaEntrada, $horaSalida, $vigiladorId]);

echo json_encode(['success' => true, 'mensaje' => 'Turno actualizado correctamente']);

