<?php
/**
 * Exporta Retenciones a Excel, sin formato: Fecha | Período | Ingreso | N° de factura/s
 * Respeta los filtros de la pantalla: ?periodo=Noviembre&q=texto
 * Genera un .xlsx real (el monto queda como número); si el servidor no tiene ZipArchive,
 * cae al .xls HTML que usan los otros exports del sistema.
 */
require_once __DIR__ . '/../auth.php';
requireBackofficeApi();
require_once __DIR__ . '/../../config/db.php';

$periodo = trim($_GET['periodo'] ?? '');
$q       = trim($_GET['q'] ?? '');

$db  = getDB();
$sql = "SELECT * FROM retenciones_ingresos WHERE eliminado_en IS NULL";
$p   = [];
if ($periodo !== '') { $sql .= " AND periodo = ?"; $p[] = $periodo; }
if ($q !== '') {
    // Mismo criterio que el buscador: ingresos con alguna factura cuyo número u objetivo coincida
    $sql .= " AND EXISTS (SELECT 1 FROM retenciones_facturas f WHERE f.id_ingreso = retenciones_ingresos.id
              AND f.eliminado_en IS NULL AND (f.numero LIKE ? OR f.objetivo LIKE ?))";
    $p[] = "%$q%"; $p[] = "%$q%";
}
$sql .= " ORDER BY fecha DESC, id DESC";
$st = $db->prepare($sql); $st->execute($p);
$ingresos = $st->fetchAll();

$facturas = [];
if ($ingresos) {
    $ids = array_column($ingresos, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $db->prepare("SELECT id_ingreso, numero FROM retenciones_facturas
                         WHERE eliminado_en IS NULL AND id_ingreso IN ($ph) ORDER BY id");
    $st->execute($ids);
    foreach ($st as $f) $facturas[$f['id_ingreso']][] = $f['numero'];
}

$encabezado = ['Fecha', 'Período', 'Ingreso', 'N° de factura/s'];
$filas = [];
foreach ($ingresos as $i) {
    [$y, $m, $d] = explode('-', substr($i['fecha'], 0, 10));
    $filas[] = [
        "$d/$m/$y",
        $i['periodo'],
        (float)$i['monto'],
        implode(', ', $facturas[$i['id']] ?? []),
    ];
}

$base = 'retenciones_' . date('Ymd_His');

function x(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// ---------- .xlsx mínimo (sin estilos) ----------
if (class_exists('ZipArchive')) {
    $celda = function ($v, string $ref): string {
        if (is_float($v) || is_int($v)) return "<c r=\"$ref\"><v>" . rtrim(rtrim(sprintf('%.2F', $v), '0'), '.') . '</v></c>';
        return "<c r=\"$ref\" t=\"inlineStr\"><is><t xml:space=\"preserve\">" . x((string)$v) . '</t></is></c>';
    };
    $rows = '';
    foreach (array_merge([$encabezado], $filas) as $n => $fila) {
        $r = $n + 1; $rows .= "<row r=\"$r\">";
        foreach (array_values($fila) as $c => $v) $rows .= $celda($v, chr(65 + $c) . $r);
        $rows .= '</row>';
    }
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<cols><col min="1" max="1" width="12" customWidth="1"/><col min="2" max="2" width="14" customWidth="1"/>'
        . '<col min="3" max="3" width="16" customWidth="1"/><col min="4" max="4" width="50" customWidth="1"/></cols>'
        . "<sheetData>$rows</sheetData></worksheet>";

    $tmp = tempnam(sys_get_temp_dir(), 'ret');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Retenciones" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $base . '.xlsx"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($tmp);
    unlink($tmp);
    exit;
}

// ---------- Respaldo: .xls HTML como el resto de los exports ----------
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $base . '.xls"');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo "\xEF\xBB\xBF";
echo '<html><head><meta charset="UTF-8"></head><body><table border="1"><tr>';
foreach ($encabezado as $h) echo '<th>' . x($h) . '</th>';
echo '</tr>';
foreach ($filas as $f) {
    echo '<tr><td>' . x($f[0]) . '</td><td>' . x($f[1]) . '</td>'
       . '<td style="mso-number-format:\'0\.00\'">' . number_format($f[2], 2, ',', '') . '</td><td>' . x($f[3]) . '</td></tr>';
}
echo '</table></body></html>';
