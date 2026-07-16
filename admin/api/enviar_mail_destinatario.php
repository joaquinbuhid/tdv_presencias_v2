<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['es_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

// Get JSON POST input
$data = json_decode(file_get_contents('php://input'), true);

$id = isset($data['id']) ? (int)$data['id'] : 0;
$type = $data['type'] ?? '';
$subject = $data['subject'] ?? '';
$body = $data['body'] ?? '';

if ($id <= 0 || !in_array($type, ['postulantes', 'empleados']) || empty($subject) || empty($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros incompletos o inválidos']);
    exit;
}

try {
    $db = getDB();
    $row = null;

    if ($type === 'postulantes') {
        // Query to match get_postulantes structure
        $stmt = $db->prepare("
            SELECT id, nombre_completo, dni, email, telefono
            FROM postulantes
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
    } else {
        // Query for employees
        $stmt = $db->prepare("
            SELECT id_empleado AS id, nombre, dni, email, telefono
            FROM empleados
            WHERE id_empleado = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
    }

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Destinatario no encontrado']);
        exit;
    }

    $nombre = ($type === 'postulantes') ? ($row['nombre_completo'] ?? '') : ($row['nombre'] ?? '');
    $email = trim($row['email'] ?? '');
    $dni = $row['dni'] ?? '';
    $telefono = $row['telefono'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'El destinatario no tiene un correo electrónico válido registrado']);
        exit;
    }

    // Replace placeholders
    $replacements = [
        '{nombre}' => $nombre,
        '{email}' => $email,
        '{dni}' => $dni,
        '{telefono}' => $telefono
    ];

    $final_subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
    $final_body = str_replace(array_keys($replacements), array_values($replacements), $body);

    // Standard HTML Email Headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: TDV SRL - TRACK SEGURIDAD <noresponder@tdvsrl.com>" . "\r\n";
    $headers .= "Reply-To: noresponder@tdvsrl.com" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Suppress warnings to guarantee clean JSON response
    $sent = @mail($email, $final_subject, $final_body, $headers);

    if ($sent) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'El servidor SMTP del hosting rechazó el envío del correo']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno de base de datos o servidor: ' . $e->getMessage()]);
}
