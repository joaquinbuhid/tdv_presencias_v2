<?php
/**
 * Exporta una lista de Descuentos a Excel (.xlsx sin formato): Vigilador | Total.
 * Total = Adelantos + Desc. Ropa/médico + Otros. Sólo vigiladores con descuento (total > 0).
 * Sólo administradores.
 */
require_once __DIR__ . '/../auth.php';
requireBackofficeApi();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/pagos_helper.php';   // pagosDescargarXlsx

if (!esAdminReal()) { http_response_code(403); exit('Sólo un administrador puede exportar los descuentos'); }

$db = getDB();
$st = $db->prepare("SELECT * FROM descuentos_listas WHERE id = ? AND eliminado_en IS NULL");
$st->execute([(int)($_GET['id'] ?? 0)]);
$lista = $st->fetch();
if (!$lista) { http_response_code(404); exit('Lista no encontrada'); }

$st = $db->prepare("SELECT nombre, COALESCE(adelantos,0) + COALESCE(ropa_medico,0) + COALESCE(otros,0) AS total
                    FROM descuentos_items WHERE id_lista = ? ORDER BY id");
$st->execute([$lista['id']]);

$filas = [['Vigilador', 'Total']];
foreach ($st as $r) {
    $total = round((float)$r['total'], 2);
    if ($total > 0) $filas[] = [$r['nombre'], $total];
}

$archivo = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $lista['nombre']) . '.xlsx';
if (!str_starts_with(mb_strtolower($archivo), 'descuentos')) $archivo = 'Descuentos ' . $archivo;
pagosDescargarXlsx($archivo, ['Descuentos' => ['filas' => $filas, 'anchos' => [40, 16]]]);
