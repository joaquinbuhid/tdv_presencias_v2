<?php
/**
 * API de Retenciones.
 *   GET  ?accion=listar
 *   POST ?accion=crear_ingreso     (fecha, periodo, monto, csrf)
 *   POST ?accion=agregar_factura   (id_ingreso, numero, objetivo, observaciones, adjunto?, csrf)
 *   POST ?accion=eliminar_factura  (id, csrf)            sólo administradores
 *   POST ?accion=eliminar_ingreso  (id, csrf)            sólo administradores
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/retenciones_helper.php';
requireBackofficeApi();

function responder($data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function texto(string $campo, int $max): string
{
    $v = trim(preg_replace('/[\x00-\x09\x0B-\x1F]/u', '', (string)($_POST[$campo] ?? '')));
    return mb_substr($v, 0, $max);
}

function exigirPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['error' => 'Método no permitido'], 405);
    if (!retencionesCsrfOk($_POST['csrf'] ?? null)) responder(['error' => 'La sesión venció, recargá la página'], 403);
}

function exigirAdmin(): void
{
    if (!esAdminReal()) responder(['error' => 'Sólo un administrador puede eliminar'], 403);
}

/** Acepta "1000000.00", "1000000,50" o "1.000.000,50". */
function parsearMonto(string $v): ?float
{
    $v = str_replace([' ', '$'], '', $v);
    if ($v === '') return null;
    if (str_contains($v, ',')) $v = str_replace(['.', ','], ['', '.'], $v);
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $v)) return null;
    return (float)$v;
}

/** Quién está haciendo la acción: [id_empleado, nombre]. */
function autor(): array
{
    $id = isset($_SESSION['empleado_id']) ? (int)$_SESSION['empleado_id'] : null;
    return [$id ?: null, $_SESSION['nombre_completo'] ?? null];
}

function formatearFactura(array $f): array
{
    return [
        'id'            => (int)$f['id'],
        'id_ingreso'    => (int)$f['id_ingreso'],
        'numero'        => $f['numero'],
        'objetivo'      => $f['objetivo'],
        'observaciones' => $f['observaciones'],
        'adjunto'       => $f['adjunto_disco'] ? [
            'nombre'    => $f['adjunto_nombre'],
            'tamanio'   => (int)$f['adjunto_tamanio'],
            'imagen'    => str_starts_with((string)$f['adjunto_mime'], 'image/'),
            'pdf'       => $f['adjunto_mime'] === 'application/pdf',
            'url'       => 'api/ver_retencion.php?id=' . $f['id'],
            'descargar' => 'api/ver_retencion.php?id=' . $f['id'] . '&dl=1',
        ] : null,
        'creado_por'    => $f['creado_por'],
        'creado_en'     => $f['creado_en'],
    ];
}

$accion = $_GET['accion'] ?? '';
$db = getDB();

try {
    switch ($accion) {

    case 'listar':
        $ingresos = $db->query("SELECT * FROM retenciones_ingresos WHERE eliminado_en IS NULL
                                ORDER BY fecha DESC, id DESC LIMIT 2000")->fetchAll();
        $porId = [];
        foreach ($ingresos as $i) {
            $porId[(int)$i['id']] = [
                'id'         => (int)$i['id'],
                'fecha'      => $i['fecha'],
                'periodo'    => $i['periodo'],
                'monto'      => (float)$i['monto'],
                'creado_por' => $i['creado_por'],
                'creado_en'  => $i['creado_en'],
                'facturas'   => [],
            ];
        }
        if ($porId) {
            $ph = implode(',', array_fill(0, count($porId), '?'));
            $st = $db->prepare("SELECT * FROM retenciones_facturas
                                WHERE eliminado_en IS NULL AND id_ingreso IN ($ph) ORDER BY id");
            $st->execute(array_keys($porId));
            foreach ($st as $f) $porId[(int)$f['id_ingreso']]['facturas'][] = formatearFactura($f);
        }
        responder(['ingresos' => array_values($porId), 'es_admin' => esAdminReal()]);

    case 'crear_ingreso':
        exigirPost();
        $fecha = texto('fecha', 10);
        $dt = DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$dt || $dt->format('Y-m-d') !== $fecha) responder(['error' => 'La fecha no es válida'], 400);
        $periodo = texto('periodo', 20);
        if (!in_array($periodo, RETENCIONES_MESES, true)) responder(['error' => 'Elegí el período (mes)'], 400);
        $monto = parsearMonto(texto('monto', 30));
        if ($monto === null || $monto <= 0) responder(['error' => 'El monto del ingreso no es válido'], 400);

        [$autorId, $autorNombre] = autor();
        $st = $db->prepare("INSERT INTO retenciones_ingresos (fecha, periodo, monto, creado_por_id, creado_por, creado_en)
                            VALUES (?,?,?,?,?,?)");
        $st->execute([$fecha, $periodo, number_format($monto, 2, '.', ''), $autorId, $autorNombre, date('Y-m-d H:i:s')]);
        responder(['ok' => true, 'id' => (int)$db->lastInsertId()]);

    case 'agregar_factura':
        exigirPost();
        $idIngreso = (int)($_POST['id_ingreso'] ?? 0);
        $st = $db->prepare("SELECT id FROM retenciones_ingresos WHERE id = ? AND eliminado_en IS NULL");
        $st->execute([$idIngreso]);
        if (!$st->fetch()) responder(['error' => 'El ingreso no existe o fue eliminado'], 404);

        $numero = texto('numero', 50);
        $objetivo = texto('objetivo', 150);
        $obs = trim(mb_substr((string)($_POST['observaciones'] ?? ''), 0, 2000));
        if ($numero === '')   responder(['error' => 'Falta el número de factura'], 400);
        if ($objetivo === '') responder(['error' => 'Falta el objetivo'], 400);

        $st = $db->prepare("SELECT 1 FROM retenciones_facturas WHERE id_ingreso = ? AND numero = ? AND eliminado_en IS NULL");
        $st->execute([$idIngreso, $numero]);
        if ($st->fetch()) responder(['error' => "La factura $numero ya está cargada en este ingreso"], 400);

        // Adjunto opcional
        $adj = ['nombre' => null, 'disco' => null, 'mime' => null, 'tamanio' => null];
        $f = $_FILES['adjunto'] ?? null;
        if ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($f['error'] !== UPLOAD_ERR_OK) responder(['error' => 'No se pudo subir el adjunto'], 400);
            if ($f['size'] > RETENCIONES_MAX_MB * 1048576) responder(['error' => 'El adjunto supera ' . RETENCIONES_MAX_MB . ' MB'], 400);
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!isset(RETENCIONES_TIPOS[$ext])) responder(['error' => "El tipo .$ext no está permitido"], 400);
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            if (!in_array($mime, RETENCIONES_TIPOS[$ext], true)) responder(['error' => "El contenido del adjunto no coincide con .$ext"], 400);
            if (!RETENCIONES_DIR || !is_dir(RETENCIONES_DIR)) responder(['error' => 'No existe la carpeta de retenciones en el servidor'], 500);

            $sub = date('Y') . '/' . date('m');
            $dir = RETENCIONES_DIR . "/$sub";
            if (!is_dir($dir) && !@mkdir($dir, 0750, true)) responder(['error' => 'No se pudo crear la carpeta en el servidor'], 500);
            $disco = "$sub/" . bin2hex(random_bytes(16)) . ".$ext";
            if (!move_uploaded_file($f['tmp_name'], RETENCIONES_DIR . "/$disco")) responder(['error' => 'No se pudo guardar el adjunto'], 500);
            @chmod(RETENCIONES_DIR . "/$disco", 0640);
            $adj = [
                'nombre'  => mb_substr(preg_replace('/[\x00-\x1F\/\\\\]/u', '', $f['name']), 0, 255),
                'disco'   => $disco,
                'mime'    => $mime,
                'tamanio' => (int)$f['size'],
            ];
        }

        $st = $db->prepare("INSERT INTO retenciones_facturas
            (id_ingreso, numero, objetivo, observaciones, adjunto_nombre, adjunto_disco, adjunto_mime, adjunto_tamanio, creado_por_id, creado_por, creado_en)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        [$autorId, $autorNombre] = autor();
        $st->execute([$idIngreso, $numero, $objetivo, $obs !== '' ? $obs : null,
                      $adj['nombre'], $adj['disco'], $adj['mime'], $adj['tamanio'],
                      $autorId, $autorNombre, date('Y-m-d H:i:s')]);
        $st = $db->prepare("SELECT * FROM retenciones_facturas WHERE id = ?");
        $st->execute([(int)$db->lastInsertId()]);
        responder(['ok' => true, 'factura' => formatearFactura($st->fetch())]);

    case 'eliminar_factura':
        exigirPost(); exigirAdmin();
        $st = $db->prepare("UPDATE retenciones_facturas SET eliminado_en = ? WHERE id = ? AND eliminado_en IS NULL");
        $st->execute([date('Y-m-d H:i:s'), (int)($_POST['id'] ?? 0)]);
        responder(['ok' => $st->rowCount() > 0]);

    case 'eliminar_ingreso':
        exigirPost(); exigirAdmin();
        $ahora = date('Y-m-d H:i:s'); $id = (int)($_POST['id'] ?? 0);
        $db->beginTransaction();
        $db->prepare("UPDATE retenciones_facturas SET eliminado_en = ? WHERE id_ingreso = ? AND eliminado_en IS NULL")->execute([$ahora, $id]);
        $st = $db->prepare("UPDATE retenciones_ingresos SET eliminado_en = ? WHERE id = ? AND eliminado_en IS NULL");
        $st->execute([$ahora, $id]);
        $db->commit();
        responder(['ok' => $st->rowCount() > 0]);

    default:
        responder(['error' => 'Acción desconocida'], 400);
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[retenciones] ' . $e->getMessage());
    responder(['error' => 'Error interno del servidor'], 500);
}
