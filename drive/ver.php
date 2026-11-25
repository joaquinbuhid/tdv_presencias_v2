<?php
/**
 * Sirve archivos y miniaturas a través de PHP (la carpeta storage no es pública).
 *   ver.php?id=12          -> ver en el navegador
 *   ver.php?id=12&dl=1     -> descargar
 *   ver.php?id=12&thumb=1  -> miniatura
 */
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT * FROM drive_archivos WHERE id = ? AND eliminado_en IS NULL");
$st->execute([$id]);
$a = $st->fetch();
if (!$a || !array_key_exists($a['carpeta'], CARPETAS)) { http_response_code(404); exit('No encontrado'); }

if (!empty($_GET['thumb'])) {
    $ruta = STORAGE_DIR . "/_thumbs/$id.jpg";
    $mime = 'image/jpeg';
} else {
    $ruta = STORAGE_DIR . '/' . $a['carpeta'] . '/' . $a['nombre_disco'];
    $mime = $a['mime'];
}
$real = realpath($ruta);
if (!$real || !str_starts_with($real, realpath(STORAGE_DIR)) || !is_file($real)) { http_response_code(404); exit('No encontrado'); }

// Sólo imágenes y PDF se muestran en línea; el resto siempre se descarga.
$enLinea = empty($_GET['dl']) && (str_starts_with($mime, 'image/') || $mime === 'application/pdf');
$nombre = $a['nombre_original'];
$ascii = preg_replace('/[^\x20-\x7E]|"/', '_', $nombre);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
header(sprintf('Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
    $enLinea ? 'inline' : 'attachment', $ascii, rawurlencode($nombre)));
readfile($real);
