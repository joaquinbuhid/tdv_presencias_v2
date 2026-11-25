<?php
/**
 * Sirve el adjunto de una factura de Retenciones (la carpeta /retenciones no es pública).
 *   ?id=12        ver en el navegador (imágenes y PDF)
 *   ?id=12&dl=1   descargar
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/retenciones_helper.php';

if (empty($_SESSION['es_admin'])) { http_response_code(401); exit('No autorizado'); }

$st = getDB()->prepare("SELECT f.* FROM retenciones_facturas f
                        JOIN retenciones_ingresos i ON i.id = f.id_ingreso
                        WHERE f.id = ? AND f.eliminado_en IS NULL AND i.eliminado_en IS NULL");
$st->execute([(int)($_GET['id'] ?? 0)]);
$f = $st->fetch();
if (!$f || !$f['adjunto_disco']) { http_response_code(404); exit('No encontrado'); }

$real = realpath(RETENCIONES_DIR . '/' . $f['adjunto_disco']);
if (!$real || !str_starts_with($real, RETENCIONES_DIR . DIRECTORY_SEPARATOR) || !is_file($real)) {
    http_response_code(404); exit('No encontrado');
}

$mime    = $f['adjunto_mime'];
$enLinea = empty($_GET['dl']) && (str_starts_with($mime, 'image/') || $mime === 'application/pdf');
$nombre  = $f['adjunto_nombre'];
$ascii   = preg_replace('/[^\x20-\x7E]|"/', '_', $nombre);

session_write_close();
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
header(sprintf('Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
    $enLinea ? 'inline' : 'attachment', $ascii, rawurlencode($nombre)));
readfile($real);
