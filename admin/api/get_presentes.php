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
    "SELECT
        e.id_empleado, e.nombre, '' AS apellido,
        o.id_objetivo, o.nombre AS objetivo_nombre,
        e.hora_entrada AS turno_entrada,
        e.hora_salida AS turno_salida,
        MAX(CASE WHEN tn.nombre = 'Entrada' THEN n.hora END) AS hora_entrada_hoy,
        MAX(CASE WHEN tn.nombre = 'Salida' THEN n.hora END) AS hora_salida_hoy,
        MAX(n.hora) AS ultima_actividad,
        COUNT(n.id_novedad) AS total_novedades
     FROM empleados e
     LEFT JOIN objetivos o ON e.objetivo_id = o.id_objetivo
     LEFT JOIN novedades n ON e.id_empleado = n.empleado_id AND n.fecha = CURDATE()
     LEFT JOIN tipo_novedad tn ON n.tipo_novedad = tn.id_tipo
     WHERE e.activo = 1 AND e.pendiente = 0 AND COALESCE(e.tipo, 1) = 1
     GROUP BY e.id_empleado, e.nombre, o.id_objetivo, o.nombre, e.hora_entrada, e.hora_salida
     ORDER BY o.nombre, e.nombre"
    );

    $rows = $stmt->fetchAll();
    $ahora = date('H:i');

    foreach ($rows as &$r) {
        $tEntrada = $r['turno_entrada'] ? substr($r['turno_entrada'], 0, 5) : null;
        $tSalida = $r['turno_salida'] ? substr($r['turno_salida'], 0, 5) : null;
        $sinHorario = !$tEntrada && !$tSalida;

        if (!$r['id_objetivo']) {
            $r['estado'] = 'sin-objetivo';
        } elseif ($r['hora_entrada_hoy'] && $r['hora_salida_hoy']) {
            $r['estado'] = 'completado';
        } elseif ($r['hora_entrada_hoy'] || $r['hora_salida_hoy']) {
            $r['estado'] = 'incompleto';
        } else {
            $r['estado'] = $sinHorario ? 'sin-registro' : (($tSalida && $ahora > $tSalida) ? 'ausente' : 'por-iniciar');
        }

        foreach (['hora_entrada_hoy','hora_salida_hoy','ultima_actividad','turno_entrada','turno_salida'] as $campo) {
            if ($r[$campo]) $r[$campo] = substr($r[$campo], 0, 5);
        }
    }

    echo json_encode($rows);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando presencias']);
}
