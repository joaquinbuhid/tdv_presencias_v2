<?php
// Listas para los selects del módulo de inventario: tipos, empleados y dispositivos.
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireAdminRealApi();

try {
    $db = getDB();

    $tipos = $db->query("SELECT id_tipo_disp, nombre FROM tipos_dispositivo ORDER BY nombre")->fetchAll();

    $empleados = $db->query(
        "SELECT id_empleado, nombre, tipo
         FROM empleados
         WHERE activo = 1 AND pendiente = 0
         ORDER BY nombre"
    )->fetchAll();

    $dispositivos = $db->query(
        "SELECT d.id_disp, d.nombre_disp, t.nombre AS tipo_nombre, e.nombre AS empleado_nombre
         FROM dispositivos d
         JOIN tipos_dispositivo t ON t.id_tipo_disp = d.id_tipo_disp
         LEFT JOIN empleados e ON e.id_empleado = d.id_empleado
         ORDER BY d.nombre_disp"
    )->fetchAll();

    echo json_encode([
        'tipos'        => $tipos,
        'empleados'    => $empleados,
        'dispositivos' => $dispositivos,
        'yo'           => (int)($_SESSION['empleado_id'] ?? 0),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando listas. ¿Se aplicó la migración de dispositivos?']);
}
