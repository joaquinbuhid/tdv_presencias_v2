<?php
/**
 * API del módulo Archivos (Facturas y Pagos).
 *   GET  ?accion=resumen
 *   GET  ?accion=listar&carpeta=facturas&q=texto&tipo=imagen|pdf|otro&mes=2026-11
 *   POST ?accion=subir      (carpeta, archivos[], csrf)
 *   POST ?accion=renombrar  (id, nombre, csrf)
 *   POST ?accion=eliminar   (id, csrf)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/archivos_helper.php';
requireBackofficeApi();

function responder($data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function carpetaValida(?string $c): string
{
    if (!$c || !array_key_exists($c, ARCHIVOS_CARPETAS)) responder(['error' => 'Carpeta inválida'], 400);
    return $c;
}

function formatearArchivo(array $r): array
{
    $ext  = $r['extension'];
    $tipo = in_array($ext, ARCHIVOS_IMAGENES, true) ? 'imagen' : ($ext === 'pdf' ? 'pdf' : 'otro');
    $base = 'api/ver_archivo.php?id=' . $r['id'];
    return [
        'id'         => (int)$r['id'],
        'carpeta'    => $r['carpeta'],
        'nombre'     => $r['nombre_original'],
        'extension'  => $ext,
        'tipo'       => $tipo,
        'tamanio'    => (int)$r['tamanio'],
        'miniatura'  => (int)$r['tiene_miniatura'] ? $base . '&thumb=1' : null,
        'url'        => $base,
        'descargar'  => $base . '&dl=1',
        'subido_por' => $r['subido_por'],
        'fecha'      => $r['creado_en'],
    ];
}

function crearMiniatura(string $origen, string $destino): bool
{
    if (!function_exists('imagecreatefromstring')) return false;
    $img = @imagecreatefromstring(file_get_contents($origen));
    if (!$img) return false;
    $w = imagesx($img); $h = imagesy($img);
    $escala = min(1, ARCHIVOS_THUMB / max($w, $h));
    $nw = max(1, (int)round($w * $escala)); $nh = max(1, (int)round($h * $escala));
    $th = imagecreatetruecolor($nw, $nh);
    imagefill($th, 0, 0, imagecolorallocate($th, 255, 255, 255));
    imagecopyresampled($th, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = imagejpeg($th, $destino, 82);
    imagedestroy($img); imagedestroy($th);
    return $ok;
}

function limpiarNombre(string $n): string
{
    return mb_substr(trim(preg_replace('/[\x00-\x1F\/\\\\]/u', '', $n)), 0, 255);
}

$accion = $_GET['accion'] ?? '';
$db = getDB();

try {
    switch ($accion) {

    case 'resumen':
        $out = [];
        foreach (ARCHIVOS_CARPETAS as $k => $nombre) $out[$k] = ['nombre' => $nombre, 'cantidad' => 0, 'bytes' => 0];
        $st = $db->query("SELECT carpeta, COUNT(*) n, COALESCE(SUM(tamanio),0) bytes
                          FROM drive_archivos WHERE eliminado_en IS NULL GROUP BY carpeta");
        foreach ($st as $r) if (isset($out[$r['carpeta']])) {
            $out[$r['carpeta']]['cantidad'] = (int)$r['n'];
            $out[$r['carpeta']]['bytes']    = (int)$r['bytes'];
        }
        responder($out);

    case 'listar':
        $carpeta = carpetaValida($_GET['carpeta'] ?? null);
        $sql = "SELECT * FROM drive_archivos WHERE carpeta = ? AND eliminado_en IS NULL";
        $p = [$carpeta];
        $q = trim($_GET['q'] ?? '');
        if ($q !== '') {
            $sql .= " AND (nombre_original LIKE ? OR descripcion LIKE ?)";
            $p[] = "%$q%"; $p[] = "%$q%";
        }
        $tipo = $_GET['tipo'] ?? '';
        $imgs = "'" . implode("','", ARCHIVOS_IMAGENES) . "'";
        if ($tipo === 'imagen')    $sql .= " AND extension IN ($imgs)";
        elseif ($tipo === 'pdf')   $sql .= " AND extension = 'pdf'";
        elseif ($tipo === 'otro')  $sql .= " AND extension NOT IN ($imgs,'pdf')";
        if (!empty($_GET['mes']) && preg_match('/^(\d{4})-(\d{2})$/', $_GET['mes'], $m)) {
            $desde = "{$m[1]}-{$m[2]}-01 00:00:00";
            $p[] = $desde;
            $p[] = date('Y-m-d H:i:s', strtotime("$desde +1 month"));
            $sql .= " AND creado_en >= ? AND creado_en < ?";
        }
        $sql .= " ORDER BY creado_en DESC, id DESC LIMIT 1000";
        $st = $db->prepare($sql); $st->execute($p);
        responder(array_map('formatearArchivo', $st->fetchAll()));

    case 'subir':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['error' => 'Metodo no permitido'], 405);
        if (!archivosCsrfOk($_POST['csrf'] ?? null)) responder(['error' => 'La sesión venció, recargá la página'], 403);
        $carpeta = carpetaValida($_POST['carpeta'] ?? null);
        if (empty($_FILES['archivos'])) responder(['error' => 'No se han recibido archivos para subir'], 400);
        if (!ARCHIVOS_DIR || !is_dir(ARCHIVOS_DIR)) responder(['error' => 'No existe la carpeta de archivos en el servidor'], 500);

        $sub   = date('Y') . '/' . date('m');
        $dir   = ARCHIVOS_DIR . "/$carpeta/$sub";
        $thumb = ARCHIVOS_DIR . '/_thumbs';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) responder(['error' => 'No se pudo crear la carpeta en el servidor'], 500);
        if (!is_dir($thumb)) @mkdir($thumb, 0750, true);

        $f = $_FILES['archivos'];
        $n = is_array($f['name']) ? count($f['name']) : 1;
        $campo = fn($k, $i) => is_array($f[$k]) ? $f[$k][$i] : $f[$k];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $subidos = []; $errores = [];

        for ($i = 0; $i < $n; $i++) {
            $nombre = $campo('name', $i);
            $tmp    = $campo('tmp_name', $i);
            $size   = (int)$campo('size', $i);

            if ($campo('error', $i) !== UPLOAD_ERR_OK) { $errores[] = "$nombre: error al subir"; continue; }
            if ($size > ARCHIVOS_MAX_MB * 1048576)     { $errores[] = "$nombre: supera " . ARCHIVOS_MAX_MB . " MB"; continue; }

            $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
            if (!isset(ARCHIVOS_TIPOS[$ext])) { $errores[] = "$nombre: tipo .$ext no permitido"; continue; }
            $mime = $finfo->file($tmp);
            if (!in_array($mime, ARCHIVOS_TIPOS[$ext], true)) { $errores[] = "$nombre: el contenido no coincide con .$ext"; continue; }

            $disco   = "$sub/" . bin2hex(random_bytes(16)) . ".$ext";
            $destino = ARCHIVOS_DIR . "/$carpeta/$disco";
            if (!move_uploaded_file($tmp, $destino)) { $errores[] = "$nombre: no se pudo guardar"; continue; }
            @chmod($destino, 0640);

            $st = $db->prepare("INSERT INTO drive_archivos
                (carpeta, nombre_original, nombre_disco, extension, mime, tamanio, subido_por, creado_en)
                VALUES (?,?,?,?,?,?,?,?)");
            $st->execute([$carpeta, limpiarNombre($nombre), $disco, $ext, $mime, $size,
                          $_SESSION['nombre_completo'] ?? null, date('Y-m-d H:i:s')]);
            $id = (int)$db->lastInsertId();

            if (str_starts_with($mime, 'image/') && crearMiniatura($destino, "$thumb/$id.jpg")) {
                $db->prepare("UPDATE drive_archivos SET tiene_miniatura = 1 WHERE id = ?")->execute([$id]);
            }
            $st = $db->prepare("SELECT * FROM drive_archivos WHERE id = ?"); $st->execute([$id]);
            $subidos[] = formatearArchivo($st->fetch());
        }
        responder(['subidos' => $subidos, 'errores' => $errores], $subidos ? 200 : 400);

    case 'renombrar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['error' => 'Metodo no permitido'], 405);
        if (!archivosCsrfOk($_POST['csrf'] ?? null)) responder(['error' => 'La sesión venció, recargá la página'], 403);
        $nombre = limpiarNombre($_POST['nombre'] ?? '');
        if ($nombre === '') responder(['error' => 'Nombre inválido'], 400);
        $st = $db->prepare("UPDATE drive_archivos SET nombre_original = ? WHERE id = ? AND eliminado_en IS NULL");
        $st->execute([$nombre, (int)($_POST['id'] ?? 0)]);
        responder(['ok' => true]);

    case 'eliminar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['error' => 'Metodo no permitido'], 405);
        if (!archivosCsrfOk($_POST['csrf'] ?? null)) responder(['error' => 'La sesión venció, recargá la página'], 403);
        // Borrado lógico: el archivo queda en disco y se recupera poniendo eliminado_en en NULL.
        $st = $db->prepare("UPDATE drive_archivos SET eliminado_en = ? WHERE id = ? AND eliminado_en IS NULL");
        $st->execute([date('Y-m-d H:i:s'), (int)($_POST['id'] ?? 0)]);
        responder(['ok' => $st->rowCount() > 0]);

    default:
        responder(['error' => 'Acción desconocida'], 400);
    }
} catch (Throwable $e) {
    error_log('[archivos] ' . $e->getMessage());
    responder(['error' => 'Error interno del servidor'], 500);
}
