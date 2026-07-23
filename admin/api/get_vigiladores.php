<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireBackofficeApi();

$db = getDB();
$stmt = $db->query(
    "SELECT e.id_empleado, e.nombre, e.fecha_nac, e.est_civil, e.empresa_id, e.domicilio, e.CUIL AS cuil, e.DNI AS dni_real,
            COALESCE(NULLIF(e.DNI, ''), e.CUIL) AS dni, e.telefono, e.email, e.email AS usuario, e.activo, e.pendiente,
            e.objetivo_id, e.hora_entrada, e.hora_salida, e.tipo, e.url_leg, e.nacionalidad, e.fecha_alta, e.nro_legajo,
            e.nro_credencial, e.fecha_venc_cred,
            o.nombre AS objetivo_nombre, emp.nombre AS empresa_nombre
     FROM empleados e
     LEFT JOIN objetivos o ON e.objetivo_id = o.id_objetivo
     LEFT JOIN empresas emp ON e.empresa_id = emp.id_empresa
     WHERE COALESCE(e.tipo, 1) = 1
     ORDER BY e.pendiente DESC, e.nombre"
);

echo json_encode($stmt->fetchAll());
?>
