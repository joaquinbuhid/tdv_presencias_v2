<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
require_once '../../api/presencias_helper.php';
requireAdminRealApi();

try {
    $db = getDB();
    $stmt = $db->prepare(
    "SELECT
        e.id_empleado, e.nombre, '' AS apellido,
        o.id_objetivo, o.nombre AS objetivo_nombre,
        e.hora_entrada AS turno_entrada,
        e.hora_salida AS turno_salida,
        0 AS total_novedades
     FROM empleados e
     LEFT JOIN objetivos o ON e.objetivo_id = o.id_objetivo
     WHERE e.activo = 1 AND e.pendiente = 0 AND COALESCE(e.tipo, 1) = 1
     ORDER BY o.nombre, e.nombre"
    );
    $stmt->execute();

    $rows = aplicarEstadoPresencias($db, $stmt->fetchAll());

    echo json_encode($rows);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando presencias']);
}
