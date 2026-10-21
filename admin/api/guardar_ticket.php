<?php
// Crea o edita un ticket. Si viene "solucion" se marca como resuelto;
// si viene vacía en un ticket resuelto, se reabre.
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
requireAdminRealApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

// Acepta "YYYY-MM-DDTHH:MM" (input datetime-local) o "YYYY-MM-DD HH:MM[:SS]".
function fechaHora(?string $v): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    $v = str_replace('T', ' ', $v);
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $v);
        if ($dt) return $dt->format('Y-m-d H:i:s');
    }
    return null;
}

$id          = (int)($data['id'] ?? 0);
$dispositivo = (int)($data['id_dispositivo'] ?? 0);
$pedidoPor   = (int)($data['pedido_por'] ?? 0) ?: null;
$problema    = trim((string)($data['problema'] ?? ''));
$creacion    = fechaHora($data['fecha_hora_creacion'] ?? null) ?? date('Y-m-d H:i:s');
$solucion    = trim((string)($data['solucion'] ?? ''));

if (!$dispositivo) {
    http_response_code(400);
    echo json_encode(['error' => 'Elegí el dispositivo']);
    exit;
}
if ($problema === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Describí el problema']);
    exit;
}

if ($solucion !== '') {
    $resueltoPor = (int)($data['resuelto_por'] ?? 0) ?: ((int)($_SESSION['empleado_id'] ?? 0) ?: null);
    $resuelto    = fechaHora($data['fecha_hora_resuelto'] ?? null) ?? date('Y-m-d H:i:s');
    if ($resuelto < $creacion) {
        http_response_code(400);
        echo json_encode(['error' => 'La fecha de resolución no puede ser anterior a la de creación']);
        exit;
    }
} else {
    $solucion = null;
    $resueltoPor = null;
    $resuelto = null;
}

$valores = [$creacion, $resuelto, $dispositivo, $resueltoPor, $pedidoPor, $problema, $solucion];

try {
    $db = getDB();

    if ($id === 0) {
        $stmt = $db->prepare(
            "INSERT INTO tickets
                (fecha_hora_creacion, fecha_hora_resuelto, id_dispositivo, resuelto_por, pedido_por, problema, solucion)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute($valores);
        echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId(), 'accion' => 'creado']);
        exit;
    }

    $stmt = $db->prepare(
        "UPDATE tickets
         SET fecha_hora_creacion=?, fecha_hora_resuelto=?, id_dispositivo=?, resuelto_por=?,
             pedido_por=?, problema=?, solucion=?
         WHERE id_ticket=?"
    );
    $stmt->execute([...$valores, $id]);
    echo json_encode(['success' => true, 'id' => $id, 'accion' => 'actualizado']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error guardando ticket']);
}
