<?php
/**
 * Utilidades de Pagos a vigiladores: CSRF, autor, parseo de montos y un generador de .xlsx simple.
 */

function pagosCsrf(): string
{
    if (empty($_SESSION['pagos_csrf'])) {
        $_SESSION['pagos_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['pagos_csrf'];
}

function pagosCsrfOk(?string $t): bool
{
    return is_string($t) && !empty($_SESSION['pagos_csrf']) && hash_equals($_SESSION['pagos_csrf'], $t);
}

/** Quién está haciendo la acción: [id_empleado, nombre]. */
function pagosAutor(): array
{
    $id = isset($_SESSION['empleado_id']) ? (int)$_SESSION['empleado_id'] : 0;
    return [$id ?: null, $_SESSION['nombre_completo'] ?? null];
}

/**
 * Acepta "125000", "125000.50", "125.000", "125.000,50", "$ 125.000".
 * Devuelve null si está vacío y false si no es un monto válido.
 */
function pagosParsearMonto(string $v): float|null|false
{
    $v = str_replace([' ', '$', "\u{00A0}"], '', trim($v));
    if ($v === '') return null;
    if (str_contains($v, ',')) {                         // formato argentino: 125.000,50
        $v = str_replace(['.', ','], ['', '.'], $v);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) { // 125.000 / 1.250.000 (puntos de miles)
        $v = str_replace('.', '', $v);
    }
    if (!preg_match('/^\d{1,12}(\.\d{1,2})?$/', $v)) return false;
    return (float)$v;
}

/**
 * Descarga un .xlsx sin estilos. $hojas = ['Nombre hoja' => ['filas' => [[...], ...], 'anchos' => [30, 14, ...]]]
 * Valores float/int se guardan como número; null como celda vacía; el resto como texto.
 */
function pagosDescargarXlsx(string $archivo, array $hojas): never
{
    $x = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $col = function (int $i): string { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; };

    $tmp = tempnam(sys_get_temp_dir(), 'pag');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);

    $sheetsXml = ''; $relsXml = ''; $overrides = ''; $n = 0;
    foreach ($hojas as $nombre => $hoja) {
        $n++;
        $cols = '';
        foreach (($hoja['anchos'] ?? []) as $i => $w) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        $rows = '';
        foreach ($hoja['filas'] as $r => $fila) {
            $rn = $r + 1; $rows .= "<row r=\"$rn\">";
            foreach (array_values($fila) as $c => $v) {
                $ref = $col($c) . $rn;
                if ($v === null || $v === '') continue;
                if (is_int($v) || is_float($v)) {
                    $rows .= "<c r=\"$ref\"><v>" . rtrim(rtrim(sprintf('%.2F', $v), '0'), '.') . '</v></c>';
                } else {
                    $rows .= "<c r=\"$ref\" t=\"inlineStr\"><is><t xml:space=\"preserve\">" . $x((string)$v) . '</t></is></c>';
                }
            }
            $rows .= '</row>';
        }
        $zip->addFromString("xl/worksheets/sheet$n.xml",
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . ($cols ? "<cols>$cols</cols>" : '') . "<sheetData>$rows</sheetData></worksheet>");
        $nombreHoja = $x(mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', '', $nombre), 0, 31));
        $sheetsXml .= "<sheet name=\"$nombreHoja\" sheetId=\"$n\" r:id=\"rId$n\"/>";
        $relsXml   .= "<Relationship Id=\"rId$n\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet$n.xml\"/>";
        $overrides .= "<Override PartName=\"/xl/worksheets/sheet$n.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
    }

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . $overrides . '</Types>');
    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . "<sheets>$sheetsXml</sheets></workbook>");
    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relsXml . '</Relationships>');
    $zip->close();

    $ascii = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $archivo);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($archivo));
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($tmp);
    unlink($tmp);
    exit;
}
