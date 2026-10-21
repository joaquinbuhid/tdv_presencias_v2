<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireAdminRealApi();

try {
    $db  = getDB();
    $hoy = date('Y-m-d');

    // Puede haber varias entradas/salidas en el día: primera entrada, última salida
    // (solo si es posterior a la última entrada) y el objetivo de la última marca.
    $stmt = $db->prepare(
        "SELECT x.id_empleado, x.nombre,
                TIME_FORMAT(x.primera_entrada, '%H:%i') AS hora_entrada_hoy,
                TIME_FORMAT(IF(x.ultima_salida > COALESCE(x.ultima_entrada, '00:00:00'), x.ultima_salida, NULL), '%H:%i') AS hora_salida_hoy,
                o.id_objetivo, o.nombre AS objetivo_nombre
         FROM (
             SELECT e.id_empleado, e.nombre,
                    (SELECT MIN(n.hora) FROM novedades n WHERE n.empleado_id = e.id_empleado AND n.fecha = ? AND n.tipo_novedad = 1) AS primera_entrada,
                    (SELECT MAX(n.hora) FROM novedades n WHERE n.empleado_id = e.id_empleado AND n.fecha = ? AND n.tipo_novedad = 1) AS ultima_entrada,
                    (SELECT MAX(n.hora) FROM novedades n WHERE n.empleado_id = e.id_empleado AND n.fecha = ? AND n.tipo_novedad = 2) AS ultima_salida,
                    (SELECT n.objetivo_id FROM novedades n
                     WHERE n.empleado_id = e.id_empleado AND n.fecha = ? AND n.tipo_novedad IN (1, 2)
                     ORDER BY n.hora DESC, n.id_novedad DESC LIMIT 1) AS objetivo_id
             FROM empleados e
             WHERE e.activo = 1 AND e.pendiente = 0 AND e.tipo = 2
         ) x
         LEFT JOIN objetivos o ON o.id_objetivo = x.objetivo_id
         ORDER BY x.nombre"
    );
    $stmt->execute([$hoy, $hoy, $hoy, $hoy]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        if ($r['hora_salida_hoy'])       $r['estado'] = 'completado';
        elseif ($r['hora_entrada_hoy'])  $r['estado'] = 'presente';
        else                             $r['estado'] = 'sin-registro';
    }
    unset($r);

    echo json_encode($rows);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando supervisores']);
}
