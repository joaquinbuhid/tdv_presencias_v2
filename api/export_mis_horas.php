<?php
// Exporta a Excel o PDF el último informe de "Mis horas" generado por el propio empleado.
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

session_start();
if (!isset($_SESSION['empleado_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../admin/api/liquidacion_helper.php';
require_once __DIR__ . '/../fpdf.php';

$informe = $_SESSION['mis_horas_informe'] ?? null;
// El informe guardado en sesión tiene que ser del mismo empleado logueado.
if (!$informe || (int)$informe['empleado_id'] !== (int)$_SESSION['empleado_id']) {
    die('No hay un informe para exportar. Generalo primero desde "Mis horas".');
}

$data   = $informe['data'];
$titulo = $informe['titulo'];
$format = $_GET['format'] ?? '';
$archivo = 'mis_horas_' . date('Ymd_His');

$totHoras = 0.0;
$totTurnos = 0;
$totAnom = 0;
foreach ($data as $r) {
    $totHoras  += array_sum(array_column($r['shifts'], 'hours'));
    $totTurnos += count($r['shifts']);
    $totAnom   += count($r['anomalies']);
}

function celda($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if ($format === 'excel') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $archivo . '.xls"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo "\xEF\xBB\xBF";
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 12px; }
    th, td { border: 1px solid #b0bec5; padding: 6px 10px; }
    th { background-color: #1a5276; color: #fff; font-weight: bold; text-align: left; }
    td { mso-number-format: "\@"; }
</style>
</head>
<body>
<table>
    <tr><td colspan="5"><b><?= celda($titulo) ?></b></td></tr>
    <tr><td colspan="5">Total: <?= formatDecimalHours($totHoras) ?> hs en <?= $totTurnos ?> turnos</td></tr>
    <tr><td colspan="5"></td></tr>
    <tr>
        <th>Objetivo</th>
        <th>Fecha y hora entrada</th>
        <th>Fecha y hora salida</th>
        <th>Horas</th>
        <th>Observaciones</th>
    </tr>
    <?php foreach ($data as $r): ?>
        <?php foreach ($r['shifts'] as $s): ?>
            <tr>
                <td><?= celda($r['name']) ?></td>
                <td><?= celda(date('d/m/Y H:i:s', strtotime($s['entry']))) ?></td>
                <td><?= celda(date('d/m/Y H:i:s', strtotime($s['exit']))) ?></td>
                <td><?= celda(formatDecimalHours($s['hours'])) ?></td>
                <td><?= celda($s['obs']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php foreach ($r['anomalies'] as $a):
            $dt = date('d/m/Y H:i:s', strtotime($a['dt']));
            $esEntrada = stripos($a['type'], 'entrada sin') !== false;
        ?>
            <tr>
                <td><?= celda($r['name']) ?></td>
                <td><?= celda($esEntrada ? $dt : '-') ?></td>
                <td><?= celda($esEntrada ? '-' : $dt) ?></td>
                <td>-</td>
                <td><?= celda('Marca sin par: ' . $a['type']) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="3"><b>Subtotal <?= celda($r['name']) ?></b></td>
            <td><b><?= formatDecimalHours(array_sum(array_column($r['shifts'], 'hours'))) ?></b></td>
            <td></td>
        </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
    <?php
    exit;
}

if ($format === 'pdf') {
    class MisHorasPDF extends FPDF {
        public $titulo = '';

        function Header() {
            $this->SetFont('Arial', 'B', 13);
            $this->SetTextColor(26, 82, 118);
            $this->Cell(0, 10, utf8ToLatin1('TDV SEGURIDAD - INFORME PERSONAL DE HORAS'), 0, 1, 'L');
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(127, 140, 141);
            $this->MultiCell(0, 4, utf8ToLatin1($this->titulo), 0, 'L');
            $this->Cell(0, 4, utf8ToLatin1('Fecha de generación: ' . date('d/m/Y H:i')), 0, 1, 'L');
            $this->Ln(3);
            $this->SetDrawColor(26, 82, 118);
            $this->SetLineWidth(0.5);
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            $this->Ln(4);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(127, 140, 141);
            $this->Cell(0, 10, utf8ToLatin1('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }

        function encabezadoTabla() {
            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor(255, 255, 255);
            $this->SetFillColor(26, 82, 118);
            $this->Cell(45, 6, 'Entrada', 1, 0, 'C', true);
            $this->Cell(45, 6, 'Salida', 1, 0, 'C', true);
            $this->Cell(25, 6, 'Horas', 1, 0, 'C', true);
            $this->Cell(75, 6, 'Observaciones', 1, 1, 'L', true);
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(44, 62, 80);
        }
    }

    $pdf = new MisHorasPDF();
    $pdf->titulo = str_replace('—', '-', $titulo); // Latin-1 no tiene guion largo
    $pdf->AliasNbPages();
    $pdf->SetMargins(10, 15, 10);
    $pdf->AddPage();

    // Resumen
    $pdf->SetFillColor(245, 247, 250);
    $pdf->SetDrawColor(220, 225, 230);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(63, 10, utf8ToLatin1('Total horas: ') . formatDecimalHours($totHoras), 1, 0, 'C', true);
    $pdf->Cell(63, 10, 'Turnos: ' . $totTurnos, 1, 0, 'C', true);
    $pdf->Cell(64, 10, 'Marcas sin par: ' . $totAnom, 1, 1, 'C', true);
    $pdf->Ln(5);

    foreach ($data as $r) {
        if ($pdf->GetY() > 240) $pdf->AddPage();
        $hs = array_sum(array_column($r['shifts'], 'hours'));

        $pdf->SetFillColor(230, 240, 250);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(21, 67, 96);
        $pdf->Cell(130, 7, '  ' . utf8ToLatin1('Objetivo: ' . $r['name']), 1, 0, 'L', true);
        $pdf->Cell(60, 7, 'Subtotal: ' . formatDecimalHours($hs) . ' hs  ', 1, 1, 'R', true);
        $pdf->encabezadoTabla();

        if (empty($r['shifts'])) {
            $pdf->Cell(190, 6, utf8ToLatin1('Sin turnos completos en el período.'), 1, 1, 'C');
        }
        foreach ($r['shifts'] as $s) {
            if ($pdf->GetY() > 265) {
                $pdf->AddPage();
                $pdf->encabezadoTabla();
            }
            $obs = (string)$s['obs'];
            while ($obs !== '' && $pdf->GetStringWidth(utf8ToLatin1($obs)) > 72) {
                $obs = mb_substr($obs, 0, -4) . '...';
            }
            $pdf->Cell(45, 6, date('d/m/Y H:i', strtotime($s['entry'])), 1, 0, 'C');
            $pdf->Cell(45, 6, date('d/m/Y H:i', strtotime($s['exit'])), 1, 0, 'C');
            $pdf->Cell(25, 6, formatDecimalHours($s['hours']), 1, 0, 'C');
            $pdf->Cell(75, 6, utf8ToLatin1($obs), 1, 1, 'L');
        }

        if (!empty($r['anomalies'])) {
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetTextColor(192, 57, 43);
            $pdf->Cell(190, 5, utf8ToLatin1('  Marcas sin par (no suman horas):'), 'LR', 1, 'L');
            $pdf->SetFont('Arial', 'I', 7.5);
            foreach ($r['anomalies'] as $a) {
                if ($pdf->GetY() > 270) $pdf->AddPage();
                $pdf->Cell(190, 4, utf8ToLatin1('    · ' . $a['type'] . ': ' . date('d/m/Y H:i', strtotime($a['dt']))), 'LR', 1, 'L');
            }
            $pdf->Cell(190, 1, '', 'B', 1, 'L');
        }
        $pdf->Ln(4);
    }

    if (ob_get_length()) ob_clean();
    $pdf->Output('D', $archivo . '.pdf');
    exit;
}

die('Formato no válido.');
