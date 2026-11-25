<?php
/**
 * Configuración del módulo Archivos (Facturas y Pagos).
 * Los archivos viven en /archivos/<carpeta>/AAAA/MM/ con nombre aleatorio;
 * el índice está en la tabla drive_archivos.
 */

// Si el hosting lo permite, mover la carpeta fuera de public_html y definir ARCHIVOS_DIR.
define('ARCHIVOS_DIR', getenv('ARCHIVOS_DIR') ?: realpath(__DIR__ . '/../../archivos'));

// Carpetas habilitadas (clave => nombre visible). Para sumar una: agregarla acá
// y crear archivos/<clave>/ con su .gitignore.
const ARCHIVOS_CARPETAS = [
    'facturas' => 'Facturas',
    'pagos'    => 'Pagos',
];

const ARCHIVOS_MAX_MB = 20;
const ARCHIVOS_THUMB  = 360;
const ARCHIVOS_IMAGENES = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

// Extensión => MIME reales aceptados (se verifica el contenido, no sólo la extensión)
const ARCHIVOS_TIPOS = [
    'pdf'  => ['application/pdf'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'webp' => ['image/webp'],
    'gif'  => ['image/gif'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'xls'  => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'csv'  => ['text/csv', 'text/plain'],
    'txt'  => ['text/plain'],
    'zip'  => ['application/zip'],
];

function archivosCsrf(): string
{
    if (empty($_SESSION['archivos_csrf'])) {
        $_SESSION['archivos_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['archivos_csrf'];
}

function archivosCsrfOk(?string $t): bool
{
    return is_string($t) && !empty($_SESSION['archivos_csrf']) && hash_equals($_SESSION['archivos_csrf'], $t);
}
