<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';

function jsonResponse(array $payload, int $status = 200): void {
    http_response_code($status);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload);
    exit;
}

function oldTable(PDO $db, string $table): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function rows(PDO $db, string $sql): array {
    return $db->query($sql)->fetchAll();
}

function norm(?string $value, string $fallback = ''): string {
    $value = trim((string)$value);
    return $value !== '' ? $value : $fallback;
}

function nullableText($value): ?string {
    $value = trim((string)$value);
    return $value !== '' ? $value : null;
}

function generatedEmail(string $prefix, int $id): string {
    return strtolower($prefix) . $id . '@migrado.local';
}

function findOrCreateTipo(PDO $db, array &$map, array $old): int {
    $name = norm($old['nombre'] ?? '', 'Novedad');
    $stmt = $db->prepare("SELECT id_tipo FROM tipo_novedad WHERE nombre = ?");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        $stmt = $db->prepare("INSERT INTO tipo_novedad (nombre, descripcion) VALUES (?, ?)");
        $stmt->execute([$name, nullableText($old['descripcion'] ?? '')]);
        $id = (int)$db->lastInsertId();
    }
    $map[(int)$old['id_tipo']] = (int)$id;
    return (int)$id;
}

function findOrCreateObjetivo(PDO $db, array &$map, array $old): int {
    $oldId = (int)$old['id_objetivo'];
    $name = norm($old['nombre'] ?? '', 'Objetivo ' . $oldId);
    $stmt = $db->prepare("SELECT id_objetivo FROM objetivos WHERE nombre = ? LIMIT 1");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        $stmt = $db->prepare(
            "INSERT INTO objetivos (nombre, descripcion, coord_lat, coord_long, rad_metros)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $name,
            nullableText($old['descripcion'] ?? ''),
            $old['coord_lat'] ?? null,
            $old['coord_long'] ?? null,
            $old['radio_metros'] ?? 200,
        ]);
        $id = (int)$db->lastInsertId();
    }
    $map[$oldId] = (int)$id;
    return (int)$id;
}

function findOrCreateEmpleado(PDO $db, array &$map, array $old, int $tipo, string $prefix, ?int $objetivoId = null): int {
    $oldId = (int)($old['id_vigilador'] ?? $old['id_supervisor'] ?? 0);
    $nombre = trim(norm($old['nombre'] ?? '') . ' ' . norm($old['apellido'] ?? ''));
    $nombre = $nombre !== '' ? $nombre : ucfirst($prefix) . ' ' . $oldId;
    $dni = norm($old['dni'] ?? '', $prefix . '-' . $oldId);
    $cuil = norm($old['cuil'] ?? '', $dni);
    $telefono = norm($old['telefono'] ?? '', 'No informado');
    $email = norm($old['email'] ?? '', generatedEmail($prefix, $oldId));
    $hash = norm($old['contrasena'] ?? '', password_hash('migrado' . $oldId, PASSWORD_DEFAULT));

    $stmt = $db->prepare("SELECT id_empleado FROM empleados WHERE DNI = ? OR CUIL = ? OR email = ? LIMIT 1");
    $stmt->execute([$dni, $dni, $email]);
    $id = $stmt->fetchColumn();

    if (!$id) {
        $stmt = $db->prepare(
            "INSERT INTO empleados
                (nombre, fecha_nac, est_civil, domicilio, CUIL, DNI, telefono, email, contrasena,
                 activo, objetivo_id, hora_entrada, hora_salida, pendiente, tipo)
             VALUES
                (?, '1900-01-01', 'No informado', 'No informado', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $nombre,
            $cuil,
            $dni,
            $telefono,
            $email,
            $hash,
            (int)($old['activo'] ?? $old['estado'] ?? 1),
            $objetivoId,
            $old['hora_entrada'] ?? null,
            $old['hora_salida'] ?? null,
            (int)($old['pendiente'] ?? 0),
            $tipo,
        ]);
        $id = (int)$db->lastInsertId();
    } else {
        $update = $db->prepare(
            "UPDATE empleados
             SET DNI = COALESCE(DNI, ?), tipo = ?, objetivo_id = COALESCE(objetivo_id, ?), hora_entrada = COALESCE(hora_entrada, ?),
                 hora_salida = COALESCE(hora_salida, ?)
             WHERE id_empleado = ?"
        );
        $update->execute([$dni, $tipo, $objetivoId, $old['hora_entrada'] ?? null, $old['hora_salida'] ?? null, $id]);
    }

    $map[$oldId] = (int)$id;
    return (int)$id;
}

if (empty($_SESSION['es_admin']) || !esAdminReal()) {
    jsonResponse(['error' => 'No autorizado'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Metodo no permitido'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    jsonResponse(['error' => 'JSON invalido'], 400);
}

$host = norm($data['host'] ?? '');
$name = norm($data['name'] ?? '');
$user = norm($data['user'] ?? '');
$pass = (string)($data['pass'] ?? '');
$mode = norm($data['mode'] ?? 'preview');

if (!$host || !$name || !$user) {
    jsonResponse(['error' => 'Complete host, base y usuario de la base anterior'], 400);
}

try {
    $old = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $new = getDB();

    $counts = [
        'objetivos' => oldTable($old, 'objetivo') ? (int)$old->query("SELECT COUNT(*) FROM objetivo")->fetchColumn() : 0,
        'tipos' => oldTable($old, 'tipo_novedad') ? (int)$old->query("SELECT COUNT(*) FROM tipo_novedad")->fetchColumn() : 0,
        'vigiladores' => oldTable($old, 'vigiladores') ? (int)$old->query("SELECT COUNT(*) FROM vigiladores")->fetchColumn() : 0,
        'supervisores' => oldTable($old, 'supervisores') ? (int)$old->query("SELECT COUNT(*) FROM supervisores")->fetchColumn() : 0,
        'novedades' => oldTable($old, 'novedades') ? (int)$old->query("SELECT COUNT(*) FROM novedades")->fetchColumn() : 0,
    ];

    if ($mode !== 'run') {
        jsonResponse(['success' => true, 'preview' => true, 'counts' => $counts]);
    }

    $new->beginTransaction();
    $tipoMap = [];
    $objetivoMap = [];
    $empleadoMap = [];
    $supervisorMap = [];
    $done = ['objetivos' => 0, 'tipos' => 0, 'vigiladores' => 0, 'supervisores' => 0, 'novedades' => 0];

    if (oldTable($old, 'tipo_novedad')) {
        foreach (rows($old, "SELECT * FROM tipo_novedad ORDER BY id_tipo") as $r) {
            findOrCreateTipo($new, $tipoMap, $r);
            $done['tipos']++;
        }
    }

    if (oldTable($old, 'objetivo')) {
        foreach (rows($old, "SELECT * FROM objetivo ORDER BY id_objetivo") as $r) {
            findOrCreateObjetivo($new, $objetivoMap, $r);
            $done['objetivos']++;
        }
    }

    if (oldTable($old, 'supervisores')) {
        foreach (rows($old, "SELECT * FROM supervisores ORDER BY id_supervisor") as $r) {
            findOrCreateEmpleado($new, $supervisorMap, $r, 2, 'supervisor');
            $done['supervisores']++;
        }
    }

    if (oldTable($old, 'objetivo') && $supervisorMap) {
        foreach (rows($old, "SELECT id_objetivo, supervisor_id FROM objetivo WHERE supervisor_id IS NOT NULL") as $r) {
            $newObj = $objetivoMap[(int)$r['id_objetivo']] ?? null;
            $newSup = $supervisorMap[(int)$r['supervisor_id']] ?? null;
            if ($newObj && $newSup) {
                $stmt = $new->prepare("UPDATE objetivos SET supervisor_id = ? WHERE id_objetivo = ?");
                $stmt->execute([$newSup, $newObj]);
            }
        }
    }

    if (oldTable($old, 'vigiladores')) {
        foreach (rows($old, "SELECT * FROM vigiladores ORDER BY id_vigilador") as $r) {
            $oldObj = isset($r['objetivo_id']) ? (int)$r['objetivo_id'] : 0;
            $newObj = $oldObj && isset($objetivoMap[$oldObj]) ? $objetivoMap[$oldObj] : null;
            $tipo = !empty($r['es_admin']) ? 4 : 1;
            findOrCreateEmpleado($new, $empleadoMap, $r, $tipo, 'empleado', $newObj);
            $done['vigiladores']++;
        }
    }

    if (oldTable($old, 'novedades')) {
        $insert = $new->prepare(
            "INSERT INTO novedades (fecha, hora, tipo_novedad, observaciones, empleado_id, ip_dispositivo, coord_lat, coord_long)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $exists = $new->prepare(
            "SELECT id_novedad FROM novedades
             WHERE fecha = ? AND hora = ? AND tipo_novedad = ? AND empleado_id = ?
             LIMIT 1"
        );
        foreach (rows($old, "SELECT * FROM novedades ORDER BY id_novedad") as $r) {
            $newEmp = $empleadoMap[(int)$r['vigilador_id']] ?? null;
            $newTipo = $tipoMap[(int)$r['tipo']] ?? null;
            if (!$newEmp || !$newTipo) {
                continue;
            }
            $exists->execute([$r['fecha'], $r['hora'], $newTipo, $newEmp]);
            if (!$exists->fetchColumn()) {
                $insert->execute([
                    $r['fecha'],
                    $r['hora'],
                    $newTipo,
                    $r['observaciones'] ?? null,
                    $newEmp,
                    $r['ip_dispositivo'] ?? null,
                    $r['coord_lat'] ?? null,
                    $r['coord_long'] ?? null,
                ]);
            }
            $done['novedades']++;
        }
    }

    $new->commit();
    jsonResponse(['success' => true, 'preview' => false, 'counts' => $counts, 'migrated' => $done]);
} catch (Throwable $e) {
    if (isset($new) && $new instanceof PDO && $new->inTransaction()) {
        $new->rollBack();
    }
    jsonResponse(['error' => 'No se pudo migrar: ' . $e->getMessage()], 500);
}
