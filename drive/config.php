<?php
/**
 * TDV - Módulo Drive (Facturas y Pagos)
 * Usa la conexión del sistema (config/db.php -> getDB()).
 */
require_once __DIR__ . '/../config/db.php';

// Ruta de almacenamiento. Si el hosting lo permite, mover "storage" fuera de public_html
// y definir DRIVE_STORAGE con la ruta absoluta.
define('STORAGE_DIR', getenv('DRIVE_STORAGE') ?: __DIR__ . '/storage');

// Carpetas habilitadas (clave => nombre visible). Para sumar una: agregarla acá
// y crear storage/<clave>/ con su .gitignore.
const CARPETAS = [
    'facturas' => 'Facturas',
    'pagos'    => 'Pagos',
];

define('MAX_FILE_MB', 20);

// Extensiones permitidas => MIME reales aceptados (se verifica el contenido, no sólo la extensión)
const TIPOS_PERMITIDOS = [
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

define('THUMB_SIZE', 360);

function db(): PDO
{
    return getDB();
}
