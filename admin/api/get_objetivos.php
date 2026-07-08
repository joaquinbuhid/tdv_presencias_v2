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
$full = isset($_GET['full']) && $_GET['full'] == '1';

if ($full) {
    $stmt = $db->query(
        "SELECT o.*,
                COUNT(v.id_empleado) AS vigiladores_asignados,
                s.id_empleado AS id_supervisor,
                s.nombre AS supervisor_nombre,
                s.telefono AS supervisor_telefono
         FROM objetivos o
         LEFT JOIN empleados v ON v.objetivo_id = o.id_objetivo AND v.activo = 1 AND v.pendiente = 0 AND COALESCE(v.tipo, 1) = 1
         LEFT JOIN empleados s ON s.id_empleado = o.supervisor_id AND s.tipo = 2
         GROUP BY o.id_objetivo
         ORDER BY o.nombre"
    );
} else {
    $stmt = $db->query("SELECT id_objetivo, nombre FROM objetivos ORDER BY nombre");
}

echo json_encode($stmt->fetchAll());
