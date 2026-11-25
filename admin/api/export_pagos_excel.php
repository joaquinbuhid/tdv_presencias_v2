<?php
/**
 * Exporta una lista de Pagos a vigiladores a Excel (.xlsx sin formato), con 3 hojas:
 *   1. Lista      Nombre completo | Monto | Observaciones  + TOTAL
 *   2. Por banco  bloques por banco (Nombre completo | Monto) con subtotal, y TOTAL GENERAL
 *   3. Resumen    Banco | Monto + TOTAL
 */
require_once __DIR__ . '/../auth.php';
requireBackofficeApi();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/pagos_helper.php';

$db = getDB();
$st = $db->prepare("SELECT * FROM pagos_listas WHERE id = ? AND eliminado_en IS NULL");
$st->execute([(int)($_GET['id'] ?? 0)]);
$lista = $st->fetch();
if (!$lista) { http_response_code(404); exit('Lista no encontrada'); }

$st = $db->prepare("SELECT nombre, monto, observaciones, banco FROM pagos_items WHERE id_lista = ? ORDER BY id");
$st->execute([$lista['id']]);
$items = $st->fetchAll();
$m = fn($v) => $v === null ? null : (float)$v;
$total = array_sum(array_map(fn($i) => (float)$i['monto'], $items));

// Hoja 1: lista
$hoja1 = [['Nombre completo', 'Monto', 'Observaciones']];
foreach ($items as $i) $hoja1[] = [$i['nombre'], $m($i['monto']), $i['observaciones']];
$hoja1[] = [];
$hoja1[] = ['TOTAL', $total, null];

// Agrupar por banco ("Sin banco asignado" primero, después alfabético)
$grupos = [];
foreach ($items as $i) $grupos[$i['banco'] ?? ''][] = $i;
uksort($grupos, fn($a, $b) => $a === '' ? -1 : ($b === '' ? 1 : strcasecmp($a, $b)));

// Hoja 2: por banco
$hoja2 = [];
foreach ($grupos as $banco => $lst) {
    $hoja2[] = [$banco !== '' ? $banco : 'Sin banco asignado'];
    $hoja2[] = ['Nombre completo', 'Monto'];
    foreach ($lst as $i) $hoja2[] = [$i['nombre'], $m($i['monto'])];
    $hoja2[] = ['Subtotal', array_sum(array_map(fn($i) => (float)$i['monto'], $lst))];
    $hoja2[] = [];
}
$hoja2[] = ['TOTAL GENERAL', $total];

// Hoja 3: resumen
$hoja3 = [['Banco', 'Monto']];
foreach ($grupos as $banco => $lst) {
    $hoja3[] = [$banco !== '' ? $banco : 'Sin banco asignado', array_sum(array_map(fn($i) => (float)$i['monto'], $lst))];
}
$hoja3[] = [];
$hoja3[] = ['TOTAL', $total];

$archivo = 'Pagos ' . preg_replace('/[\\\\\/:*?"<>|]+/', '-', $lista['nombre']) . '.xlsx';
pagosDescargarXlsx($archivo, [
    'Lista'     => ['filas' => $hoja1, 'anchos' => [38, 16, 45]],
    'Por banco' => ['filas' => $hoja2, 'anchos' => [38, 16]],
    'Resumen'   => ['filas' => $hoja3, 'anchos' => [30, 18]],
]);
