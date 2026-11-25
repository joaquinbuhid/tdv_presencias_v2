<?php
/**
 * Acceso al Drive: mismo login y roles que el backoffice (admin/auth.php).
 * Pueden entrar administradores y oficinistas (sesión con es_admin).
 */
require_once __DIR__ . '/../admin/auth.php';

$__script = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($__script === 'api.php') {
    header('Content-Type: application/json; charset=utf-8');
    requireBackofficeApi();
} elseif ($__script === 'ver.php') {
    if (empty($_SESSION['es_admin'])) { http_response_code(401); exit('No autorizado'); }
} else {
    // requireBackofficePage() redirige a ../index.php, que desde drive/ es el login del sistema.
    requireBackofficePage();
}

function usuario_actual(): string
{
    return $_SESSION['nombre_completo'] ?? 'sistema';
}

// Token CSRF para las acciones que modifican datos
if (empty($_SESSION['drive_csrf'])) {
    $_SESSION['drive_csrf'] = bin2hex(random_bytes(16));
}
function csrf_token(): string { return $_SESSION['drive_csrf']; }
function csrf_ok(?string $t): bool { return is_string($t) && hash_equals($_SESSION['drive_csrf'], $t); }
