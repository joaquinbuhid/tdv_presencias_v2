<?php
/**
 * Configuración de Retenciones: carpeta de adjuntos, tipos permitidos y CSRF.
 */

// Si el hosting lo permite, mover la carpeta fuera de public_html y definir RETENCIONES_DIR.
define('RETENCIONES_DIR', getenv('RETENCIONES_DIR') ?: realpath(__DIR__ . '/../../retenciones'));

const RETENCIONES_MAX_MB = 20;

const RETENCIONES_MESES = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

// Extensión => MIME reales aceptados (se verifica el contenido, no sólo la extensión)
const RETENCIONES_TIPOS = [
    'pdf'  => ['application/pdf'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'webp' => ['image/webp'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'xls'  => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'zip'  => ['application/zip'],
];

function retencionesCsrf(): string
{
    if (empty($_SESSION['retenciones_csrf'])) {
        $_SESSION['retenciones_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['retenciones_csrf'];
}

function retencionesCsrfOk(?string $t): bool
{
    return is_string($t) && !empty($_SESSION['retenciones_csrf']) && hash_equals($_SESSION['retenciones_csrf'], $t);
}
