<?php
// Listas para los selects del módulo de inventario: tipos, empleados y dispositivos.
// Los oficinistas solo reciben los dispositivos (para pedir tickets).
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireBackofficeApi();

try {
    $db = getDB();
    $yo = (int)($_SESSION['empleado_id'] ?? 0);

    $stmt = $db->prepare(
        "SELECT d.id_disp, d.nombre_disp, t.nombre AS tipo_nombre, e.nombre AS empleado_nombre,
                (d.id_empleado = ?) AS es_mio
         FROM dispositivos d
         JOIN tipos_dispositivo t ON t.id_tipo_disp = d.id_tipo_disp
         LEFT JOIN empleados e ON e.id_empleado = d.id_empleado
         ORDER BY d.nombre_disp"
    );
    $stmt->execute([$yo]);
    $dispositivos = $stmt->fetchAll();

    if (!esAdminReal()) {
        echo json_encode(['dispositivos' => $dispositivos, 'yo' => $yo]);
        exit;
    }

    $tipos = $db->query("SELECT id_tipo_disp, nombre FROM tipos_dispositivo ORDER BY nombre")->fetchAll();

    $empleados = $db->query(
        "SELECT id_empleado, nombre, tipo
         FROM empleados
         WHERE activo = 1 AND pendiente = 0
         ORDER BY nombre"
    )->fetchAll();

    echo json_encode([
        'tipos'        => $tipos,
        'empleados'    => $empleados,
        'dispositivos' => $dispositivos,
        'yo'           => $yo,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando listas. ¿Se aplicó la migración de dispositivos?']);
}
