<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
requireBackofficeApi();

require_once '../../config/db.php';

try {
    $db = getDB();
    $contratadas = (isset($_GET['contratadas']) && $_GET['contratadas'] === '1') ? 1 : 0;
    $stmt = $db->prepare(
        "SELECT e.*, emp.nombre AS entrevistador,
                DATE_FORMAT(e.fecha_entrevista, '%d/%m/%Y %H:%i') AS fecha_fmt
         FROM entrevistas e
         LEFT JOIN empleados emp ON emp.id_empleado = e.empleado_id
         WHERE e.contratado = ?
         ORDER BY e.puntaje_total DESC, e.fecha_entrevista DESC, e.id_entrevista DESC
         LIMIT 300"
    );
    $stmt->execute([$contratadas]);
    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando entrevistas']);
}
