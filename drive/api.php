<?php
/**
 * API JSON del Drive.
 *   GET  api.php?accion=listar&carpeta=facturas&q=texto&tipo=imagen|pdf|otro&mes=2026-11
 *   GET  api.php?accion=resumen
 *   POST api.php?accion=subir      (multipart: carpeta, archivos[], csrf)
 *   POST api.php?accion=renombrar  (id, nombre, csrf)
 *   POST api.php?accion=eliminar   (id, csrf)
 */
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

function responder($data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function carpeta_valida(?string $c): string
{
    if (!$c || !array_key_exists($c, CARPETAS)) responder(['error' => 'Carpeta inválida'], 400);
    return $c;
}

function formatear(array $r): array
{
    $ext = $r['extension'];
    $tipo = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? 'imagen' : ($ext === 'pdf' ? 'pdf' : 'otro');
    return [
        'id'        => (int)$r['id'],
        'carpeta'   => $r['carpeta'],
        'nombre'    => $r['nombre_original'],
        'extension' => $ext,
        'tipo'      => $tipo,
        'tamanio'   => (int)$r['tamanio'],
        'miniatura' => (bool)$r['tiene_miniatura'] ? 'ver.php?id=' . $r['id'] . '&thumb=1' : null,
        'url'       => 'ver.php?id=' . $r['id'],
        'descargar' => 'ver.php?id=' . $r['id'] . '&dl=1',
        'subido_por'=> $r['subido_por'],
        'fecha'     => $r['creado_en'],
    ];
}

function crear_miniatura(string $origen, string $destino, string $mime): bool
{
    if (!function_exists('imagecreatefromstring')) return false;
    $img = @imagecreatefromstring(file_get_contents($origen));
    if (!$img) return false;
    $w = imagesx($img); $h = imagesy($img);
    $escala = min(1, THUMB_SIZE / max($w, $h));
    $nw = max(1, (int)round($w * $escala)); $nh = max(1, (int)round($h * $escala));
    $th = imagecreatetruecolor($nw, $nh);
    imagefill($th, 0, 0, imagecolorallocate($th, 255, 255, 255)); // fondo blanco para PNG transparentes
    imagecopyresampled($th, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = imagejpeg($th, $destino, 82);
    imagedestroy($img); imagedestroy($th);
    return $ok;
}

$accion = $_GET['accion'] ?? '';

try {
    switch ($accion) {

    case 'resumen':
        $st = db()->query("SELECT carpeta, COUNT(*) n, COALESCE(SUM(tamanio),0) bytes
                           FROM drive_archivos WHERE eliminado_en IS NULL GROUP BY carpeta");
        $out = [];
        foreach (CARPETAS as $k => $nombre) $out[$k] = ['nombre' => $nombre, 'cantidad' => 0, 'bytes' => 0];
        foreach ($st as $r) if (isset($out[$r['carpeta']])) {
            $out[$r['carpeta']]['cantidad'] = (int)$r['n'];
            $out[$r['carpeta']]['bytes'] = (int)$r['bytes'];
        }
        responder($out);

    case 'listar':
        $carpeta = carpeta_valida($_GET['carpeta'] ?? null);
        $sql = "SELECT * FROM drive_archivos WHERE carpeta = ? AND eliminado_en IS NULL";
        $p = [$carpeta];
        if (!empty($_GET['q'])) {
            $sql .= " AND (nombre_original LIKE ? OR descripcion LIKE ?)";
            $p[] = '%' . $_GET['q'] . '%'; $p[] = '%' . $_GET['q'] . '%';
        }
        $tipo = $_GET['tipo'] ?? '';
        if ($tipo === 'imagen') $sql .= " AND extension IN ('jpg','jpeg','png','webp','gif')";
        elseif ($tipo === 'pdf') $sql .= " AND extension = 'pdf'";
        elseif ($tipo === 'otro') $sql .= " AND extension NOT IN ('jpg','jpeg','png','webp','gif','pdf')";
        if (!empty($_GET['mes']) && preg_match('/^(\d{4})-(\d{2})$/', $_GET['mes'], $m)) {
            $desde = "{$m[1]}-{$m[2]}-01 00:00:00";
            $hasta = date('Y-m-d H:i:s', strtotime("$desde +1 month"));
            $sql .= " AND creado_en >= ? AND creado_en < ?";
            $p[] = $desde; $p[] = $hasta;
        }
        $sql .= " ORDER BY creado_en DESC, id DESC LIMIT 1000";
        $st = db()->prepare($sql); $st->execute($p);
        responder(array_map('formatear', $st->fetchAll()));

    case 'subir':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['error' => 'Método no permitido'], 405);
        if (!csrf_ok($_POST['csrf'] ?? null)) responder(['error' => 'Sesión vencida, recargá la página'], 403);
        $carpeta = carpeta_valida($_POST['carpeta'] ?? null);
        if (empty($_FILES['archivos'])) responder(['error' => 'No se recibieron archivos'], 400);

        $f = $_FILES['archivos'];
        $n = is_array($f['name']) ? count($f['name']) : 1;
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $subidos = []; $errores = [];

        $sub = date('Y') . '/' . date('m');
        $dir = STORAGE_DIR . "/$carpeta/$sub";
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) responder(['error' => 'No se pudo crear la carpeta en el servidor'], 500);
        $thumbDir = STORAGE_DIR . '/_thumbs';
        if (!is_dir($thumbDir)) mkdir($thumbDir, 0750, true);

        for ($i = 0; $i < $n; $i++) {
            $nombre = is_array($f['name']) ? $f['name'][$i] : $f['name'];
            $tmp    = is_array($f['tmp_name']) ? $f['tmp_name'][$i] : $f['tmp_name'];
            $err    = is_array($f['error']) ? $f['error'][$i] : $f['error'];
            $size   = is_array($f['size']) ? $f['size'][$i] : $f['size'];

            if ($err !== UPLOAD_ERR_OK) { $errores[] = "$nombre: error de subida ($err)"; continue; }
            if ($size > MAX_FILE_MB * 1024 * 1024) { $errores[] = "$nombre: supera " . MAX_FILE_MB . " MB"; continue; }

            $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
            if (!isset(TIPOS_PERMITIDOS[$ext])) { $errores[] = "$nombre: tipo .$ext no permitido"; continue; }
            $mime = $finfo->file($tmp);
            if (!in_array($mime, TIPOS_PERMITIDOS[$ext], true)) { $errores[] = "$nombre: el contenido no coincide con .$ext"; continue; }

            $nombreDisco = "$sub/" . bin2hex(random_bytes(16)) . ".$ext";
            $destino = STORAGE_DIR . "/$carpeta/$nombreDisco";
            $movido = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $destino) : rename($tmp, $destino);
            if (!$movido) { $errores[] = "$nombre: no se pudo guardar"; continue; }
            @chmod($destino, 0640);

            $limpio = mb_substr(preg_replace('/[\x00-\x1F\/\\\\]/u', '', $nombre), 0, 255);
            $st = db()->prepare("INSERT INTO drive_archivos
                (carpeta, nombre_original, nombre_disco, extension, mime, tamanio, subido_por, creado_en)
                VALUES (?,?,?,?,?,?,?,?)");
            $st->execute([$carpeta, $limpio, $nombreDisco, $ext, $mime, $size, usuario_actual(), date('Y-m-d H:i:s')]);
            $id = (int)db()->lastInsertId();

            if (str_starts_with($mime, 'image/') && crear_miniatura($destino, "$thumbDir/$id.jpg", $mime)) {
                db()->prepare("UPDATE drive_archivos SET tiene_miniatura = 1 WHERE id = ?")->execute([$id]);
            }
            $st = db()->prepare("SELECT * FROM drive_archivos WHERE id = ?"); $st->execute([$id]);
            $subidos[] = formatear($st->fetch());
        }
        responder(['subidos' => $subidos, 'errores' => $errores], $subidos ? 200 : 400);

    case 'renombrar':
        if (!csrf_ok($_POST['csrf'] ?? null)) responder(['error' => 'Sesión vencida'], 403);
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '' || mb_strlen($nombre) > 255) responder(['error' => 'Nombre inválido'], 400);
        $nombre = preg_replace('/[\x00-\x1F\/\\\\]/u', '', $nombre);
        $st = db()->prepare("UPDATE drive_archivos SET nombre_original = ? WHERE id = ? AND eliminado_en IS NULL");
        $st->execute([$nombre, (int)($_POST['id'] ?? 0)]);
        responder(['ok' => $st->rowCount() > 0]);

    case 'eliminar':
        if (!csrf_ok($_POST['csrf'] ?? null)) responder(['error' => 'Sesión vencida'], 403);
        // Borrado lógico: el archivo queda en disco y se puede recuperar desde la base.
        $st = db()->prepare("UPDATE drive_archivos SET eliminado_en = ? WHERE id = ? AND eliminado_en IS NULL");
        $st->execute([date('Y-m-d H:i:s'), (int)($_POST['id'] ?? 0)]);
        responder(['ok' => $st->rowCount() > 0]);

    default:
        responder(['error' => 'Acción desconocida'], 400);
    }
} catch (Throwable $e) {
    error_log('[drive] ' . $e->getMessage());
    responder(['error' => 'Error interno del servidor'], 500);
}
