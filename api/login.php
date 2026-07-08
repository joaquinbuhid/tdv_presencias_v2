<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';

const TIPO_SUPERVISOR = 2;
const TIPO_ADMIN = 9;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$usuario = trim($data['usuario'] ?? '');
$clave = $data['contrasena'] ?? '';

if ($usuario === '' || $clave === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Email y contrasena son requeridos']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT e.id_empleado, e.nombre, e.contrasena, e.activo, e.pendiente, e.tipo,
                o.id_objetivo, o.nombre AS objetivo_nombre
         FROM empleados e
         LEFT JOIN objetivos o ON e.objetivo_id = o.id_objetivo
         WHERE e.email = ?"
    );
    $stmt->execute([$usuario]);
    $row = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error consultando usuarios']);
    exit;
}

if (!$row || !password_verify($clave, $row['contrasena'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Email o contrasena incorrectos']);
    exit;
}

if ((int)$row['pendiente'] === 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Tu cuenta esta pendiente de aprobacion. Contacta al administrador.']);
    exit;
}

if ((int)$row['activo'] !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Cuenta desactivada. Contacta al administrador.']);
    exit;
}

$tipo = (int)($row['tipo'] ?? 1);
$esAdmin = $tipo === TIPO_ADMIN;
$esSupervisor = $tipo === TIPO_SUPERVISOR;

if (!$esAdmin && !$esSupervisor && !$row['id_objetivo']) {
    http_response_code(403);
    echo json_encode(['error' => 'No tiene un objetivo asignado. Contacta al administrador.']);
    exit;
}

$_SESSION['empleado_id'] = (int)$row['id_empleado'];
$_SESSION['nombre_completo'] = $row['nombre'];
$_SESSION['es_admin'] = $esAdmin;
$_SESSION['objetivo_nombre'] = $row['objetivo_nombre'];

if ($esSupervisor) {
    $_SESSION['supervisor_id'] = (int)$row['id_empleado'];
    $_SESSION['es_supervisor'] = true;
}

session_write_close();

echo json_encode([
    'success' => true,
    'es_admin' => $esAdmin,
    'es_supervisor' => $esSupervisor,
    'nombre' => $_SESSION['nombre_completo'],
    'objetivo' => $row['objetivo_nombre'],
]);
