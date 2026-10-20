<?php
// Sin ?id devuelve el listado (sin claves). Con ?id=N devuelve el dispositivo completo, clave incluida.
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireAdminRealApi();

try {
    $db = getDB();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id) {
        $stmt = $db->prepare("SELECT * FROM dispositivos WHERE id_disp = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Dispositivo no encontrado']);
            exit;
        }
        echo json_encode($row);
        exit;
    }

    $stmt = $db->query(
        "SELECT d.id_disp, d.nombre_disp, d.usuario_disp, d.id_empleado, d.id_tipo_disp,
                d.observaciones_disp, d.mother_disp, d.micro_disp, d.ram, d.fuente,
                (d.clave_disp IS NOT NULL AND d.clave_disp <> '') AS tiene_clave,
                t.nombre AS tipo_nombre,
                e.nombre AS empleado_nombre,
                (SELECT COUNT(*) FROM tickets tk WHERE tk.id_dispositivo = d.id_disp AND tk.fecha_hora_resuelto IS NULL) AS tickets_abiertos,
                (SELECT COUNT(*) FROM tickets tk WHERE tk.id_dispositivo = d.id_disp) AS tickets_total
         FROM dispositivos d
         JOIN tipos_dispositivo t ON t.id_tipo_disp = d.id_tipo_disp
         LEFT JOIN empleados e ON e.id_empleado = d.id_empleado
         ORDER BY d.nombre_disp"
    );
    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando dispositivos']);
}
