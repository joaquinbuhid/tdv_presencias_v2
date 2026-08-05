<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireAdminRealApi();

try {
    $db = getDB();
    $stmt = $db->prepare(
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
     LEFT JOIN novedades n ON e.id_empleado = n.empleado_id
       AND (
         n.fecha = :hoy
         OR (
           n.fecha = :ayer
           AND NOT EXISTS (
             SELECT 1 FROM novedades n2
             JOIN tipo_novedad tn2 ON n2.tipo_novedad = tn2.id_tipo
             WHERE n2.empleado_id = e.id_empleado
               AND n2.fecha = :hoy
               AND tn2.nombre = 'Entrada'
           )
           AND (
             (e.hora_entrada > e.hora_salida AND n.hora >= ADDTIME(e.hora_entrada, '-03:00:00') AND :hora_actual < ADDTIME(e.hora_entrada, '-03:00:00'))
             OR
             (
               (e.hora_entrada IS NULL OR e.hora_entrada = '00:00:00' OR e.hora_entrada <= e.hora_salida)
               AND n.hora >= '12:00:00'
               AND NOT EXISTS (
                 SELECT 1 FROM novedades n3
                 JOIN tipo_novedad tn3 ON n3.tipo_novedad = tn3.id_tipo
                 WHERE n3.empleado_id = e.id_empleado
                   AND n3.fecha = :ayer
                   AND tn3.nombre = 'Salida'
               )
             )
           )
         )
       )
     LEFT JOIN tipo_novedad tn ON n.tipo_novedad = tn.id_tipo
     WHERE e.activo = 1 AND e.pendiente = 0 AND COALESCE(e.tipo, 1) = 1
     GROUP BY e.id_empleado, e.nombre, o.id_objetivo, o.nombre, e.hora_entrada, e.hora_salida
     ORDER BY o.nombre, e.nombre"
    );
    $stmt->execute([
        'hoy'         => date('Y-m-d'),
        'ayer'        => date('Y-m-d', strtotime('-1 day')),
        'hora_actual' => date('H:i:s'),
    ]);

    $rows = $stmt->fetchAll();
    $ahora = date('H:i');

    foreach ($rows as &$r) {
        $tEntrada = $r['turno_entrada'] ? substr($r['turno_entrada'], 0, 5) : null;
        $tSalida = $r['turno_salida'] ? substr($r['turno_salida'], 0, 5) : null;
        if ($tEntrada === '00:00') $tEntrada = null;
        if ($tSalida === '00:00') $tSalida = null;
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
            if ($r[$campo]) {
                $r[$campo] = substr($r[$campo], 0, 5);
                if (($campo === 'turno_entrada' || $campo === 'turno_salida') && $r[$campo] === '00:00') {
                    $r[$campo] = null;
                }
            }
        }
    }

    echo json_encode($rows);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando presencias']);
}
