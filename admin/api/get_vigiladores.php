<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (empty($_SESSION['es_admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db = getDB();
$stmt = $db->query(
    "SELECT e.id_empleado, e.nombre, '' AS apellido, e.CUIL AS dni, e.telefono, e.email,
            e.email AS usuario, e.activo, e.pendiente, e.objetivo_id,
            e.hora_entrada, e.hora_salida,
            o.nombre AS objetivo_nombre
     FROM empleados e
     LEFT JOIN objetivos o ON e.objetivo_id = o.id_objetivo
     WHERE COALESCE(e.tipo, 1) = 1
     ORDER BY e.pendiente DESC, e.nombre"
);

echo json_encode($stmt->fetchAll());
