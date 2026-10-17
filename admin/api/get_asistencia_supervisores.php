<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireAdminRealApi();

try {
    $db  = getDB();
    $hoy = date('Y-m-d');

    $stmt = $db->prepare(
        "SELECT e.id_empleado, e.nombre,
                TIME_FORMAT(ent.hora, '%H:%i') AS hora_entrada_hoy,
                TIME_FORMAT(sal.hora, '%H:%i') AS hora_salida_hoy,
                o.id_objetivo, o.nombre AS objetivo_nombre
         FROM empleados e
         LEFT JOIN novedades ent ON ent.empleado_id = e.id_empleado AND ent.fecha = ? AND ent.tipo_novedad = 1
         LEFT JOIN novedades sal ON sal.empleado_id = e.id_empleado AND sal.fecha = ? AND sal.tipo_novedad = 2
         LEFT JOIN objetivos o   ON o.id_objetivo = COALESCE(ent.objetivo_id, sal.objetivo_id)
         WHERE e.activo = 1 AND e.pendiente = 0 AND e.tipo = 2
         ORDER BY e.nombre"
    );
    $stmt->execute([$hoy, $hoy]);
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
