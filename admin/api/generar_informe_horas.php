<?php
require_once __DIR__ . '/../auth.php';
require_once '../../config/db.php';
if (empty($_SESSION['es_admin'])) {
    http_response_code(401);
    echo 'No autorizado';
    exit;
}

$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');

function validarFecha(string $fecha): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt && $dt->format('Y-m-d') === $fecha;
}

if (!validarFecha($desde) || !validarFecha($hasta)) {
    http_response_code(400);
    echo 'Fechas invalidas';
    exit;
}

$dtDesde = new DateTime($desde);
$dtHasta = new DateTime($hasta);

if ($dtHasta < $dtDesde) {
    http_response_code(400);
    echo 'La fecha hasta debe ser igual o mayor que la fecha desde';
    exit;
}

function fechaAR(string $fecha): string {
    return (new DateTime($fecha))->format('d/m/Y');
}

function horaCorta(?string $hora): string {
    return $hora ? substr($hora, 0, 5) : '-';
}

function calcularHoras(?string $entrada, ?string $salida): string {
    if (!$entrada || !$salida) {
        return 'Incompleto';
    }

    $ini = strtotime('2000-01-01 ' . $entrada);
    $fin = strtotime('2000-01-01 ' . $salida);
    if ($fin < $ini) {
        $fin += 86400;
    }

    $minutos = (int)round(($fin - $ini) / 60);
    $hs = intdiv($minutos, 60);
    $mins = $minutos % 60;
    return sprintf('%02d:%02d hs', $hs, $mins);
}

function pdfText(string $text): string {
    $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
    if ($encoded === false) {
        $encoded = preg_replace('/[^\x20-\x7E]/', '?', $text);
    }
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
}

function pdfLine(string $text, int $x, int $y, int $size = 10): string {
    return "BT /F1 {$size} Tf {$x} {$y} Td (" . pdfText($text) . ") Tj ET\n";
}

function buildPdf(array $lines): string {
    $pageWidth = 595;
    $pageHeight = 842;
    $top = 800;
    $bottom = 48;
    $lineHeight = 15;
    $pages = [];
    $content = '';
    $y = $top;

    foreach ($lines as $line) {
        $size = $line['size'] ?? 10;
        $indent = $line['indent'] ?? 0;
        $text = $line['text'] ?? '';

        if ($y < $bottom) {
            $pages[] = $content;
            $content = '';
            $y = $top;
        }

        $content .= pdfLine($text, 42 + $indent, $y, $size);
        $y -= $lineHeight;
    }

    if ($content !== '') {
        $pages[] = $content;
    }

    $objects = [];
    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[] = '';
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

    $pageObjNums = [];
    foreach ($pages as $pageContent) {
        $contentObjNum = count($objects) + 2;
        $pageObjNum = count($objects) + 1;
        $pageObjNums[] = $pageObjNum;
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentObjNum} 0 R >>";
        $objects[] = "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}endstream";
    }

    $kids = implode(' ', array_map(fn($n) => "{$n} 0 R", $pageObjNums));
    $objects[1] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageObjNums) . " >>";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $num = $i + 1;
        $pdf .= "{$num} 0 obj\n{$object}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $count = count($objects) + 1;
    $pdf .= "xref\n0 {$count}\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i < $count; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

$db = getDB();
$stmt = $db->prepare(
    "SELECT
        n.fecha,
        e.nombre AS vigilador,
        MIN(CASE WHEN LOWER(tn.nombre) = 'entrada' THEN n.hora END) AS entrada,
        MAX(CASE WHEN LOWER(tn.nombre) = 'salida' THEN n.hora END) AS salida
     FROM novedades n
     JOIN empleados e ON e.id_empleado = n.empleado_id
     JOIN tipo_novedad tn ON tn.id_tipo = n.tipo_novedad
     WHERE n.fecha BETWEEN ? AND ?
       AND LOWER(tn.nombre) IN ('entrada', 'salida')
     GROUP BY n.fecha, e.id_empleado, e.nombre
     ORDER BY n.fecha, e.nombre"
);
$stmt->execute([$desde, $hasta]);

$porFecha = [];
foreach ($stmt->fetchAll() as $row) {
    $porFecha[$row['fecha']][] = $row;
}

$lines = [];
$lines[] = ['text' => 'Informe de horas por vigilador', 'size' => 16];
$lines[] = ['text' => 'Periodo: ' . fechaAR($desde) . ' al ' . fechaAR($hasta), 'size' => 11];
$lines[] = ['text' => 'Generado: ' . date('d/m/Y H:i') . ' hs', 'size' => 9];
$lines[] = ['text' => '', 'size' => 10];

$periodo = new DatePeriod($dtDesde, new DateInterval('P1D'), (clone $dtHasta)->modify('+1 day'));
foreach ($periodo as $dia) {
    $fecha = $dia->format('Y-m-d');
    $lines[] = ['text' => 'Fecha ' . fechaAR($fecha), 'size' => 13];

    if (empty($porFecha[$fecha])) {
        $lines[] = ['text' => 'Sin registros.', 'indent' => 16, 'size' => 10];
        $lines[] = ['text' => '', 'size' => 10];
        continue;
    }

    foreach ($porFecha[$fecha] as $r) {
        $entrada = horaCorta($r['entrada'] ?? null);
        $salida = horaCorta($r['salida'] ?? null);
        $horas = calcularHoras($r['entrada'] ?? null, $r['salida'] ?? null);
        $lines[] = [
            'text' => $r['vigilador'] . ' - ' . $entrada . ' - ' . $salida . ' - ' . $horas,
            'indent' => 16,
            'size' => 10,
        ];
    }
    $lines[] = ['text' => '', 'size' => 10];
}

$filename = 'informe_horas_' . $desde . '_' . $hasta . '.pdf';
$pdf = buildPdf($lines);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
