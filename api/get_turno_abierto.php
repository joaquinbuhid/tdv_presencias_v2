<?php
// Indica si el empleado logueado tiene una entrada sin salida, para sugerir la próxima marca.
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';
require_once __DIR__ . '/presencias_helper.php';

if (!isset($_SESSION['empleado_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db = getDB();
$empleadoId = (int)$_SESSION['empleado_id'];
$ultima = ultimaMarca($db, $empleadoId);
$abierta = entradaAbierta($db, $empleadoId, $ultima);
$vencida = $abierta ? null : entradaVencida($db, $empleadoId, $ultima);

echo json_encode([
    'abierta' => (bool)$abierta,
    'desde'   => $abierta ? describirEntrada($abierta) : null,
    // Entrada que quedó sin Salida y venció: "el 07/11 a las 06:52".
    'vencida' => $vencida ? describirEntrada($vencida) : null,
]);
