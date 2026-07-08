<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (empty($_SESSION['es_admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->query(
    "SELECT s.id_empleado AS id_supervisor, s.nombre, '' AS apellido, COALESCE(NULLIF(s.DNI, ''), s.CUIL) AS dni, s.telefono, s.email,
            s.email AS usuario, s.activo AS estado,
            COUNT(o.id_objetivo) AS objetivos_count
     FROM empleados s
     LEFT JOIN objetivos o ON o.supervisor_id = s.id_empleado
     WHERE s.tipo = 2
     GROUP BY s.id_empleado, s.nombre, s.DNI, s.CUIL, s.telefono, s.email, s.activo
     ORDER BY s.nombre"
    );

    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando supervisores']);
}
