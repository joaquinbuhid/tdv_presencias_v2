<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';

if (!isset($_SESSION['empleado_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    "SELECT n.id_novedad, n.fecha, n.hora, t.nombre AS tipo_nombre,
            n.observaciones, n.ip_dispositivo
     FROM novedades n
     JOIN tipo_novedad t ON n.tipo_novedad = t.id_tipo
     JOIN empleados e ON n.empleado_id = e.id_empleado
     WHERE n.empleado_id = :empleado_id
       AND (
         n.fecha = :hoy
         OR (
           n.fecha = :ayer
           AND e.hora_entrada > e.hora_salida
           AND n.hora >= ADDTIME(e.hora_entrada, '-03:00:00')
           AND :hora_actual < ADDTIME(e.hora_entrada, '-03:00:00')
         )
       )
     ORDER BY n.fecha ASC, n.hora ASC"
);
$stmt->execute([
    'empleado_id' => $_SESSION['empleado_id'],
    'hoy'         => date('Y-m-d'),
    'ayer'        => date('Y-m-d', strtotime('-1 day')),
    'hora_actual' => date('H:i:s'),
]);
echo json_encode($stmt->fetchAll());

