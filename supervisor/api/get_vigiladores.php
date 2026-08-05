<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (empty($_SESSION['supervisor_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$supId = (int)$_SESSION['supervisor_id'];
$objetivoId = isset($_GET['objetivo_id']) ? (int)$_GET['objetivo_id'] : 0;
$db = getDB();

if ($objetivoId) {
    $chk = $db->prepare("SELECT id_objetivo FROM objetivos WHERE id_objetivo = ? AND supervisor_id = ?");
    $chk->execute([$objetivoId, $supId]);
    if (!$chk->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Objetivo no autorizado']);
        exit;
    }
    $where = "AND e.objetivo_id = " . $objetivoId;
} else {
    $where = "AND o.supervisor_id = " . $supId;
}

$sql = "SELECT
            e.id_empleado, e.nombre, '' AS apellido,
            e.hora_entrada AS turno_entrada,
            e.hora_salida AS turno_salida,
            o.id_objetivo, o.nombre AS objetivo_nombre,
            MAX(CASE WHEN tn.nombre = 'Entrada' THEN n.hora END) AS hora_entrada_hoy,
            MAX(CASE WHEN tn.nombre = 'Salida' THEN n.hora END) AS hora_salida_hoy
        FROM empleados e
        LEFT JOIN objetivos o ON e.objetivo_id = o.id_objetivo
        LEFT JOIN novedades n ON e.id_empleado = n.empleado_id
          AND (
            n.fecha = :hoy
            OR (
              n.fecha = :ayer
              AND e.hora_entrada > e.hora_salida
              AND n.hora >= ADDTIME(e.hora_entrada, '-03:00:00')
              AND :hora_actual < ADDTIME(e.hora_entrada, '-03:00:00')
            )
          )
        LEFT JOIN tipo_novedad tn ON n.tipo_novedad = tn.id_tipo
        WHERE e.activo = 1 AND e.pendiente = 0 AND COALESCE(e.tipo, 1) = 1
        $where
        GROUP BY e.id_empleado, e.nombre, e.hora_entrada, e.hora_salida, o.id_objetivo, o.nombre
        ORDER BY o.nombre, e.nombre";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'hoy'         => date('Y-m-d'),
        'ayer'        => date('Y-m-d', strtotime('-1 day')),
        'hora_actual' => date('H:i:s'),
    ]);
    $rows = $stmt->fetchAll();
    $ahora = date('H:i');

    foreach ($rows as &$r) {
        $te = $r['turno_entrada'] ? substr($r['turno_entrada'], 0, 5) : null;
        $ts = $r['turno_salida'] ? substr($r['turno_salida'], 0, 5) : null;
        if ($te === '00:00') $te = null;
        if ($ts === '00:00') $ts = null;
        $sinHorario = !$te && !$ts;

        if ($r['hora_entrada_hoy'] && $r['hora_salida_hoy']) {
            $r['estado'] = 'completado';
        } elseif ($r['hora_entrada_hoy'] || $r['hora_salida_hoy']) {
            $r['estado'] = 'incompleto';
        } else {
            $r['estado'] = $sinHorario ? 'sin-registro' : (($ts && $ahora > $ts) ? 'ausente' : 'por-iniciar');
        }

        foreach (['hora_entrada_hoy','hora_salida_hoy','turno_entrada','turno_salida'] as $c) {
            if ($r[$c]) {
                $r[$c] = substr($r[$c], 0, 5);
                if (($c === 'turno_entrada' || $c === 'turno_salida') && $r[$c] === '00:00') {
                    $r[$c] = null;
                }
            }
        }
    }

    echo json_encode($rows);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando presencias']);
}
