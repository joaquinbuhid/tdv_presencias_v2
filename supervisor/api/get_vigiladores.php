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
        LEFT JOIN novedades n ON e.id_empleado = n.empleado_id AND n.fecha = CURDATE()
        LEFT JOIN tipo_novedad tn ON n.tipo_novedad = tn.id_tipo
        WHERE e.activo = 1 AND e.pendiente = 0 AND COALESCE(e.tipo, 1) = 1
        $where
        GROUP BY e.id_empleado, e.nombre, e.hora_entrada, e.hora_salida, o.id_objetivo, o.nombre
        ORDER BY o.nombre, e.nombre";

try {
    $stmt = $db->query($sql);
    $rows = $stmt->fetchAll();
    $ahora = date('H:i');

    foreach ($rows as &$r) {
        $ts = $r['turno_salida'] ? substr($r['turno_salida'], 0, 5) : null;

        if ($r['hora_entrada_hoy'] && $r['hora_salida_hoy']) {
            $r['estado'] = 'completado';
        } elseif ($r['hora_entrada_hoy']) {
            $r['estado'] = ($ts && $ahora > $ts) ? 'sin-salida' : 'presente';
        } else {
            $r['estado'] = ($ts && $ahora > $ts) ? 'ausente' : 'por-iniciar';
        }

        foreach (['hora_entrada_hoy','hora_salida_hoy','turno_entrada','turno_salida'] as $c) {
            if ($r[$c]) $r[$c] = substr($r[$c], 0, 5);
        }
    }

    echo json_encode($rows);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando presencias']);
}
