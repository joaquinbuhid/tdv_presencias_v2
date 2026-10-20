<?php
// ?estado=abiertos|resueltos (vacío = todos), ?dispositivo=N
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireAdminRealApi();

try {
    $db = getDB();

    $where  = [];
    $params = [];

    $estado = $_GET['estado'] ?? '';
    if ($estado === 'abiertos')  $where[] = 'tk.fecha_hora_resuelto IS NULL';
    if ($estado === 'resueltos') $where[] = 'tk.fecha_hora_resuelto IS NOT NULL';

    $disp = (int)($_GET['dispositivo'] ?? 0);
    if ($disp) {
        $where[]  = 'tk.id_dispositivo = ?';
        $params[] = $disp;
    }

    $sql = "SELECT tk.id_ticket, tk.fecha_hora_creacion, tk.fecha_hora_resuelto,
                   tk.id_dispositivo, tk.pedido_por, tk.resuelto_por, tk.problema, tk.solucion,
                   d.nombre_disp, t.nombre AS tipo_nombre,
                   ep.nombre AS pedido_por_nombre,
                   er.nombre AS resuelto_por_nombre
            FROM tickets tk
            JOIN dispositivos d ON d.id_disp = tk.id_dispositivo
            JOIN tipos_dispositivo t ON t.id_tipo_disp = d.id_tipo_disp
            LEFT JOIN empleados ep ON ep.id_empleado = tk.pedido_por
            LEFT JOIN empleados er ON er.id_empleado = tk.resuelto_por"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY (tk.fecha_hora_resuelto IS NULL) DESC, tk.fecha_hora_creacion DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando tickets']);
}
