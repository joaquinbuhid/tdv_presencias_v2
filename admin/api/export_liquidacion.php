<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING);

session_start();
if (empty($_SESSION['es_admin'])) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/liquidacion_helper.php';
require_once __DIR__ . '/../../fpdf.php';

$data = $_SESSION['last_liquidacion'] ?? null;
$title = $_SESSION['liquidacion_title'] ?? 'Reporte';

if (!$data) {
    die("No hay datos de liquidacion para exportar. Por favor procese primero.");
}

$format = $_GET['format'] ?? '';

function excelCell($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function cleanObs($obs): string {
    $obs = trim((string)$obs);
    if ($obs === 'Entrada:  | Salida:' || $obs === 'Entrada: | Salida:' || $obs === 'Entrada: | Salida' || $obs === 'Entrada:  | Salida') {
        return '';
    }
    return $obs;
}

if ($format === 'excel' || $format === 'xls') {
    if (ob_get_length()) {
        ob_clean();
    }
    
    $filename = 'liquidacion_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    echo "\xEF\xBB\xBF";
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 12px; }
        th, td { border: 1px solid #b0bec5; padding: 6px 10px; }
        th { background-color: #1a5276; color: #ffffff; font-weight: bold; text-align: left; }
        td { mso-number-format: "\@"; }
    </style>
</head>
<body>
<table>
    <thead>
        <tr>
            <th>Nombre del empleado</th>
            <th>Fecha y hora entrada</th>
            <th>Fecha y hora salida</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data as $v): ?>
            <?php foreach ($v['shifts'] as $s): ?>
                <?php
                $e_dt = new DateTime($s['entry']);
                $x_dt = new DateTime($s['exit']);
                $obs = cleanObs($s['obs'] ?? '');
                ?>
                <tr>
                    <td><?= excelCell($v['name']) ?></td>
                    <td><?= excelCell($e_dt->format('d/m/Y H:i:s')) ?></td>
                    <td><?= excelCell($x_dt->format('d/m/Y H:i:s')) ?></td>
                    <td><?= excelCell($obs) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!empty($v['anomalies'])): ?>
                <?php foreach ($v['anomalies'] as $a): ?>
                    <?php
                    $a_dt = !empty($a['dt']) ? (new DateTime($a['dt']))->format('d/m/Y H:i:s') : '';
                    $is_entry = stripos($a['type'], 'entrada') !== false;
                    $is_exit = stripos($a['type'], 'salida') !== false;
                    $e_val = $is_entry ? $a_dt : '-';
                    $x_val = (!$is_entry && $is_exit) ? $a_dt : '-';
                    $obs_extra = !empty(trim($a['obs'] ?? '')) ? ' (' . trim($a['obs']) . ')' : '';
                    $obs_text = 'Anomalía: ' . $a['type'] . $obs_extra;
                    ?>
                    <tr>
                        <td><?= excelCell($v['name']) ?></td>
                        <td><?= excelCell($e_val) ?></td>
                        <td><?= excelCell($x_val) ?></td>
                        <td><?= excelCell($obs_text) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
    <?php
    exit;

} else if ($format === 'csv') {
    if (ob_get_length()) {
        ob_clean();
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="liquidacion_' . date('Ymd_His') . '.csv"');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ["Nombre del empleado", "Fecha y hora entrada", "Fecha y hora salida", "Observaciones"], ';');
    
    foreach ($data as $v) {
        foreach ($v['shifts'] as $s) {
            $e_dt = new DateTime($s['entry']);
            $x_dt = new DateTime($s['exit']);
            $obs = cleanObs($s['obs'] ?? '');
            
            fputcsv($output, [
                $v['name'],
                $e_dt->format('d/m/Y H:i:s'),
                $x_dt->format('d/m/Y H:i:s'),
                $obs
            ], ';');
        }
        if (!empty($v['anomalies'])) {
            foreach ($v['anomalies'] as $a) {
                $a_dt = !empty($a['dt']) ? (new DateTime($a['dt']))->format('d/m/Y H:i:s') : '';
                $is_entry = stripos($a['type'], 'entrada') !== false;
                $is_exit = stripos($a['type'], 'salida') !== false;
                $e_val = $is_entry ? $a_dt : '-';
                $x_val = (!$is_entry && $is_exit) ? $a_dt : '-';
                $obs_extra = !empty(trim($a['obs'] ?? '')) ? ' (' . trim($a['obs']) . ')' : '';
                $obs_text = 'Anomalía: ' . $a['type'] . $obs_extra;
                
                fputcsv($output, [
                    $v['name'],
                    $e_val,
                    $x_val,
                    $obs_text
                ], ';');
            }
        }
    }
    
    fclose($output);
    exit;

} else if ($format === 'pdf') {
    class LiquidacionPDF extends FPDF {
        private $reportTitle;
        
        function setReportTitle($title) {
            $this->reportTitle = $title;
        }
        
        function Header() {
            $this->SetFont('Arial', 'B', 13);
            $this->SetTextColor(26, 82, 118);
            $this->Cell(0, 10, utf8ToLatin1('TDV SEGURIDAD — REPORTE DE LIQUIDACION DE HORAS'), 0, 1, 'L');
            
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(127, 140, 141);
            $this->Cell(0, 4, utf8ToLatin1('Origen: ' . $this->reportTitle), 0, 1, 'L');
            $this->Cell(0, 4, utf8ToLatin1('Fecha generacion: ' . date('d-m-Y H:i:s')), 0, 1, 'L');
            
            $this->Ln(3);
            $this->SetDrawColor(26, 82, 118);
            $this->SetLineWidth(0.5);
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            $this->Ln(4);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.2);
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(127, 140, 141);
            $this->Cell(0, 10, utf8ToLatin1('Pagina ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    
    $pdf = new LiquidacionPDF();
    $pdf->setReportTitle($title);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(10, 15, 10);
    
    $total_vigiladores = count($data);
    $total_shifts = 0;
    $total_hours = 0.0;
    $total_anomalies = 0;
    
    foreach ($data as $v) {
        $total_shifts += count($v['shifts']);
        $total_hours += array_sum(array_column($v['shifts'], 'hours'));
        $total_anomalies += count($v['anomalies']);
    }
    
    $pdf->SetFillColor(245, 247, 250);
    $pdf->SetDrawColor(220, 225, 230);
    $pdf->Rect(10, $pdf->GetY(), 190, 20, 'DF');
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(47, 10, utf8ToLatin1('Empleados: ') . $total_vigiladores, 0, 0, 'C');
    $pdf->Cell(47, 10, utf8ToLatin1('Turnos Liquidados: ') . $total_shifts, 0, 0, 'C');
    $pdf->Cell(48, 10, utf8ToLatin1('Total Horas: ') . formatDecimalHours($total_hours), 0, 0, 'C');
    $pdf->Cell(48, 10, utf8ToLatin1('Anomalias: ') . $total_anomalies, 0, 1, 'C');
    
    $pdf->Ln(5);
    
    foreach ($data as $v) {
        if ($pdf->GetY() > 240) {
            $pdf->AddPage();
        }
        
        $total_v_hours = array_sum(array_column($v['shifts'], 'hours'));
        
        $pdf->SetFillColor(230, 240, 250);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(21, 67, 96);
        $pdf->Cell(130, 7, '  ' . utf8ToLatin1($v['name']) . ' (ID: ' . $v['vid'] . ')', 1, 0, 'L', true);
        $pdf->Cell(60, 7, 'Total: ' . formatDecimalHours($total_v_hours) . ' hs  ', 1, 1, 'R', true);
        
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor(26, 82, 118);
        
        $pdf->Cell(25, 6, 'F. Entrada', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'H. Entrada', 1, 0, 'C', true);
        $pdf->Cell(25, 6, 'F. Salida', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'H. Salida', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Cant. Horas', 1, 0, 'C', true);
        $pdf->Cell(70, 6, 'Observaciones', 1, 1, 'L', true);
        
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(44, 62, 80);
        
        if (empty($v['shifts'])) {
            $pdf->Cell(190, 6, utf8ToLatin1('Sin turnos liquidados validos.'), 1, 1, 'C');
        } else {
            foreach ($v['shifts'] as $s) {
                $e_dt = new DateTime($s['entry']);
                $x_dt = new DateTime($s['exit']);
                
                $next_day = '';
                if ($x_dt->format('Y-m-d') !== $e_dt->format('Y-m-d')) {
                    $days = (strtotime($x_dt->format('Y-m-d')) - strtotime($e_dt->format('Y-m-d'))) / 86400;
                    $next_day = " (+{$days}d)";
                }
                
                if ($pdf->GetY() > 265) {
                    $pdf->AddPage();
                    $pdf->SetFillColor(230, 240, 250);
                    $pdf->SetFont('Arial', 'B', 9);
                    $pdf->SetTextColor(21, 67, 96);
                    $pdf->Cell(190, 6, '  ' . utf8ToLatin1($v['name']) . ' (ID: ' . $v['vid'] . ') - Continuacion', 1, 1, 'L', true);
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->SetFillColor(26, 82, 118);
                    $pdf->Cell(25, 6, 'F. Entrada', 1, 0, 'C', true);
                    $pdf->Cell(20, 6, 'H. Entrada', 1, 0, 'C', true);
                    $pdf->Cell(25, 6, 'F. Salida', 1, 0, 'C', true);
                    $pdf->Cell(20, 6, 'H. Salida', 1, 0, 'C', true);
                    $pdf->Cell(30, 6, 'Cant. Horas', 1, 0, 'C', true);
                    $pdf->Cell(70, 6, 'Observaciones', 1, 1, 'L', true);
                    $pdf->SetFont('Arial', '', 8);
                    $pdf->SetTextColor(44, 62, 80);
                }
                
                $pdf->Cell(25, 6, $e_dt->format('Y-m-d'), 1, 0, 'C');
                $pdf->Cell(20, 6, $e_dt->format('H:i:s'), 1, 0, 'C');
                $pdf->Cell(25, 6, $x_dt->format('Y-m-d'), 1, 0, 'C');
                $pdf->Cell(20, 6, $x_dt->format('H:i:s') . $next_day, 1, 0, 'C');
                $pdf->Cell(30, 6, formatDecimalHours($s['hours']) . ' (' . number_format($s['hours'], 2, ',', '') . ' hs)', 1, 0, 'C');
                
                $obs_text = $s['obs'];
                if ($pdf->GetStringWidth($obs_text) > 68) {
                    while ($pdf->GetStringWidth($obs_text . '...') > 68 && strlen($obs_text) > 5) {
                        $obs_text = substr($obs_text, 0, -1);
                    }
                    $obs_text .= '...';
                }
                $pdf->Cell(70, 6, utf8ToLatin1($obs_text), 1, 1, 'L');
            }
        }
        
        if (!empty($v['anomalies'])) {
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetTextColor(192, 57, 43);
            $pdf->Cell(190, 5, utf8ToLatin1('  Marcas huerfanas / inconsistencias:'), 'LR', 1, 'L');
            $pdf->SetFont('Arial', 'I', 7.5);
            foreach ($v['anomalies'] as $a) {
                if ($pdf->GetY() > 270) {
                    $pdf->AddPage();
                }
                $obs_part = $a['obs'] ? ' [Obs: ' . $a['obs'] . ']' : '';
                $pdf->Cell(190, 4, utf8ToLatin1('    · ' . $a['type'] . ': ' . $a['dt'] . $obs_part), 'LR', 1, 'L');
            }
            $pdf->Cell(190, 1, '', 'B', 1, 'L');
        }
        
        $pdf->Ln(4);
    }
    
    if (ob_get_length()) {
        ob_clean();
    }
    
    $pdf->Output('D', 'liquidacion_' . date('Ymd_His') . '.pdf');
    exit;
} else {
    die("Formato no valido.");
}
