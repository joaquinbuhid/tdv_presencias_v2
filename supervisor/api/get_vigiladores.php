<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../api/presencias_helper.php';

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
            o.id_objetivo, o.nombre AS objetivo_nombre
        FROM empleados e
        LEFT JOIN objetivos o ON e.objetivo_id = o.id_objetivo
        WHERE e.activo = 1 AND e.pendiente = 0 AND COALESCE(e.tipo, 1) = 1
        $where
        ORDER BY o.nombre, e.nombre";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rows = aplicarEstadoPresencias($db, $stmt->fetchAll());

    echo json_encode($rows);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando presencias']);
}
