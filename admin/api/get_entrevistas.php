<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
requireBackofficeApi();

require_once '../../config/db.php';

try {
    $db = getDB();
    // Estado: pendientes (entrevistados), convocados o contratados.
    // Compatibilidad: ?contratadas=1 equivale a contratados, ?contratadas=0 a pendientes.
    $estado = $_GET['estado'] ?? '';
    if ($estado === '' && isset($_GET['contratadas'])) {
        $estado = $_GET['contratadas'] === '1' ? 'contratados' : ($_GET['contratadas'] === '0' ? 'pendientes' : '');
    }
    $filtrosEstado = [
        'pendientes'  => 'e.contratado = 0 AND e.convocado = 0',
        'convocados'  => 'e.contratado = 0 AND e.convocado = 1',
        'contratados' => 'e.contratado = 1',
    ];
    if (!isset($filtrosEstado[$estado])) {
        $estado = 'pendientes';
    }
    $stmt = $db->prepare(
        "SELECT e.*, emp.nombre AS entrevistador,
                DATE_FORMAT(e.fecha_entrevista, '%d/%m/%Y %H:%i') AS fecha_fmt
         FROM entrevistas e
         LEFT JOIN empleados emp ON emp.id_empleado = e.empleado_id
         WHERE " . $filtrosEstado[$estado] . "
         ORDER BY e.puntaje_total DESC, e.fecha_entrevista DESC, e.id_entrevista DESC
         LIMIT 300"
    );
    $stmt->execute();
    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando entrevistas']);
}
