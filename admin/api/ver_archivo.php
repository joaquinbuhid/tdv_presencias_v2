<?php
/**
 * Sirve archivos y miniaturas del módulo Archivos (la carpeta /archivos no es pública).
 *   ?id=12          ver en el navegador
 *   ?id=12&dl=1     descargar
 *   ?id=12&thumb=1  miniatura
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/archivos_helper.php';

if (empty($_SESSION['es_admin'])) { http_response_code(401); exit('No autorizado'); }

$id = (int)($_GET['id'] ?? 0);
$st = getDB()->prepare("SELECT * FROM drive_archivos WHERE id = ? AND eliminado_en IS NULL");
$st->execute([$id]);
$a = $st->fetch();
if (!$a || !array_key_exists($a['carpeta'], ARCHIVOS_CARPETAS)) { http_response_code(404); exit('No encontrado'); }

if (!empty($_GET['thumb'])) {
    $ruta = ARCHIVOS_DIR . "/_thumbs/$id.jpg";
    $mime = 'image/jpeg';
} else {
    $ruta = ARCHIVOS_DIR . '/' . $a['carpeta'] . '/' . $a['nombre_disco'];
    $mime = $a['mime'];
}
$real = realpath($ruta);
if (!$real || !str_starts_with($real, ARCHIVOS_DIR . DIRECTORY_SEPARATOR) || !is_file($real)) {
    http_response_code(404); exit('No encontrado');
}

// Sólo imágenes y PDF se muestran en el navegador; el resto siempre se descarga.
$enLinea = empty($_GET['dl']) && (str_starts_with($mime, 'image/') || $mime === 'application/pdf');
$nombre  = $a['nombre_original'];
$ascii   = preg_replace('/[^\x20-\x7E]|"/', '_', $nombre);

session_write_close();
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
header(sprintf('Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
    $enLinea ? 'inline' : 'attachment', $ascii, rawurlencode($nombre)));
readfile($real);
