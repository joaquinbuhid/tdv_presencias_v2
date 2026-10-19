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
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

$data        = json_decode(file_get_contents('php://input'), true);
$vigiladorId = isset($data['empleado_id']) ? (int)$data['empleado_id'] : 0;
$objetivoId  = isset($data['objetivo_id']) ? (int)$data['objetivo_id'] : 0;

if (!$vigiladorId || !$objetivoId) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

$db    = getDB();
$supId = (int)$_SESSION['supervisor_id'];

// El vigilador debe estar hoy en un objetivo de este supervisor
$chk = $db->prepare(
    "SELECT v.objetivo_id FROM empleados v
     JOIN objetivos o ON v.objetivo_id = o.id_objetivo
     WHERE v.id_empleado = ? AND o.supervisor_id = ? AND COALESCE(v.tipo, 1) = 1"
);
$chk->execute([$vigiladorId, $supId]);
$actual = $chk->fetch();
if (!$actual) {
    http_response_code(403);
    echo json_encode(['error' => 'Vigilador no pertenece a sus objetivos']);
    exit;
}

// El objetivo destino tambien debe ser de este supervisor
$chk = $db->prepare("SELECT id_objetivo FROM objetivos WHERE id_objetivo = ? AND supervisor_id = ?");
$chk->execute([$objetivoId, $supId]);
if (!$chk->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo puede asignar objetivos que usted supervisa']);
    exit;
}

$objAnt = (int)$actual['objetivo_id'];
if ($objAnt === $objetivoId) {
    echo json_encode(['success' => true, 'mensaje' => 'El vigilador ya esta en ese objetivo']);
    exit;
}

try {
    $db->beginTransaction();
    $db->prepare("UPDATE empleados SET objetivo_id = ? WHERE id_empleado = ?")
       ->execute([$objetivoId, $vigiladorId]);
    $db->prepare(
        "INSERT INTO movimientos_objetivos (objetivo_ant_id, objetivo_nuevo_id, fecha, empleado_id, id_usuario)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([$objAnt, $objetivoId, date('Y-m-d'), $vigiladorId, $supId]);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error al cambiar el objetivo']);
    exit;
}

echo json_encode(['success' => true, 'mensaje' => 'Objetivo actualizado correctamente']);
