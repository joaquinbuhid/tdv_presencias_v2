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
    // Búsqueda opcional por nombre o DNI.
    $q = trim($_GET['q'] ?? '');
    $where = $filtrosEstado[$estado];
    $params = [];
    if ($q !== '') {
        $where .= " AND (e.nombre_completo LIKE ? OR e.dni LIKE ?)";
        $like = '%' . $q . '%';
        $params = [$like, $like];
    }

    // Paginación: con ?page=N responde {items, total, page, per_page, pages}.
    // Sin ?page responde la lista como antes (compatibilidad).
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 0;
    $perPage = isset($_GET['per_page']) ? min(200, max(1, (int)$_GET['per_page'])) : 50;

    $sql = "SELECT e.*, emp.nombre AS entrevistador,
                   DATE_FORMAT(e.fecha_entrevista, '%d/%m/%Y %H:%i') AS fecha_fmt
            FROM entrevistas e
            LEFT JOIN empleados emp ON emp.id_empleado = e.empleado_id
            WHERE {$where}
            ORDER BY e.puntaje_total DESC, e.fecha_entrevista DESC, e.id_entrevista DESC";

    if ($page > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM entrevistas e WHERE {$where}");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $db->prepare($sql . " LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        echo json_encode([
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ]);
        exit;
    }

    $stmt = $db->prepare($sql . " LIMIT 300");
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando entrevistas']);
}
