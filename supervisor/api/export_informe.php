<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING);

session_start();
if (empty($_SESSION['supervisor_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../admin/api/liquidacion_helper.php';
require_once __DIR__ . '/../../fpdf.php';

$data = $_SESSION['last_informe'] ?? null;
$title = $_SESSION['informe_title'] ?? 'Reporte';

if (!$data) {
    die("No hay datos de informe para exportar. Por favor realice la consulta primero.");
}

$format = $_GET['format'] ?? '';

if ($format === 'excel') {
    if (ob_get_length()) {
        ob_clean();
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="informe_' . date('Ymd_His') . '.csv"');
    
    // Output UTF-8 BOM
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Header Info
    fputcsv($output, ["TDV Seguridad - Reporte de Asistencia por Objetivo"], ';');
    fputcsv($output, ["Origen: " . $title], ';');
    fputcsv($output, ["Generado: " . date('d-m-Y H:i:s')], ';');
    fputcsv($output, [], ';');
    
    // 1. Shifts Table
    fputcsv($output, ["DETALLE DE TURNOS"], ';');
    fputcsv($output, ["Vigilador", "ID", "Fecha Entrada", "Hora Entrada", "Fecha Salida", "Hora Salida", "Cant. Horas", "Horas (HH:MM)", "Observaciones"], ';');
    
    foreach ($data as $v) {
        foreach ($v['shifts'] as $s) {
            $e_dt = new DateTime($s['entry']);
            $x_dt = new DateTime($s['exit']);
            
            $next_day = '';
            if ($x_dt->format('Y-m-d') !== $e_dt->format('Y-m-d')) {
                $days = (strtotime($x_dt->format('Y-m-d')) - strtotime($e_dt->format('Y-m-d'))) / 86400;
                $next_day = " (+{$days}d)";
            }
            
            fputcsv($output, [
                $v['name'],
                $v['vid'],
                $e_dt->format('Y-m-d'),
                $e_dt->format('H:i:s'),
                $x_dt->format('Y-m-d'),
                $x_dt->format('H:i:s') . $next_day,
                number_format($s['hours'], 2, ',', ''),
                formatDecimalHours($s['hours']),
                $s['obs']
            ], ';');
        }
    }
    
    fputcsv($output, [], ';');
    
    // 2. Summary Table
    fputcsv($output, ["RESUMEN POR VIGILADOR"], ';');
    fputcsv($output, ["Vigilador", "ID", "Total Horas (Decimal)", "Total Horas (HH:MM)"], ';');
    
    $grand_total = 0.0;
    foreach ($data as $v) {
        $total_v = array_sum(array_column($v['shifts'], 'hours'));
        $grand_total += $total_v;
        fputcsv($output, [
            $v['name'],
            $v['vid'],
            number_format($total_v, 2, ',', ''),
            formatDecimalHours($total_v)
        ], ';');
    }
    fputcsv($output, ["TOTAL GENERAL", "", number_format($grand_total, 2, ',', ''), formatDecimalHours($grand_total)], ';');
    
    fputcsv($output, [], ';');
    
    // 3. Anomalies Table
    fputcsv($output, ["MARCAS HUÉRFANAS / ANOMALÍAS"], ';');
    fputcsv($output, ["Vigilador", "ID", "Tipo Anomalía", "Fecha/Hora Marca", "Observaciones"], ';');
    
    $has_anomalies = false;
    foreach ($data as $v) {
        foreach ($v['anomalies'] as $a) {
            $has_anomalies = true;
            fputcsv($output, [
                $v['name'],
                $v['vid'],
                $a['type'],
                $a['dt'],
                $a['obs']
            ], ';');
        }
    }
    if (!$has_anomalies) {
        fputcsv($output, ["No se detectaron anomalías."], ';');
    }
    
    fclose($output);
    exit;

} else if ($format === 'pdf') {
    class InformePDF extends FPDF {
        private $reportTitle;
        
        function setReportTitle($title) {
            $this->reportTitle = $title;
        }
        
        function Header() {
            $this->SetFont('Arial', 'B', 13);
            $this->SetTextColor(26, 82, 118); // Dark blue
            $this->Cell(0, 10, utf8ToLatin1('TDV SEGURIDAD — REPORTE DE ASISTENCIA POR OBJETIVO'), 0, 1, 'L');
            
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(127, 140, 141);
            $this->Cell(0, 4, utf8ToLatin1($this->reportTitle), 0, 1, 'L');
            $this->Cell(0, 4, utf8ToLatin1('Fecha generación: ' . date('d-m-Y H:i:s')), 0, 1, 'L');
            
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
            $this->Cell(0, 10, utf8ToLatin1('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    
    $pdf = new InformePDF();
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
    
    // Draw Summary Box
    $pdf->SetFillColor(245, 247, 250);
    $pdf->SetDrawColor(220, 225, 230);
    $pdf->Rect(10, $pdf->GetY(), 190, 20, 'DF');
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(47, 10, utf8ToLatin1('Vigiladores: ') . $total_vigiladores, 0, 0, 'C');
    $pdf->Cell(47, 10, utf8ToLatin1('Turnos Cerrados: ') . $total_shifts, 0, 0, 'C');
    $pdf->Cell(48, 10, utf8ToLatin1('Total Horas: ') . formatDecimalHours($total_hours), 0, 0, 'C');
    $pdf->Cell(48, 10, utf8ToLatin1('Anomalías: ') . $total_anomalies, 0, 1, 'C');
    
    $pdf->Ln(5);
    
    foreach ($data as $v) {
        if ($pdf->GetY() > 240) {
            $pdf->AddPage();
        }
        
        $total_v_hours = array_sum(array_column($v['shifts'], 'hours'));
        
        // Vigilador Name Strip
        $pdf->SetFillColor(230, 240, 250);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(21, 67, 96);
        $pdf->Cell(130, 7, '  ' . utf8ToLatin1($v['name']) . ' (ID: ' . $v['vid'] . ')', 1, 0, 'L', true);
        $pdf->Cell(60, 7, 'Total: ' . formatDecimalHours($total_v_hours) . ' hs  ', 1, 1, 'R', true);
        
        // Shifts Table Header
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
            $pdf->Cell(190, 6, utf8ToLatin1('Sin turnos cerrados válidos.'), 1, 1, 'C');
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
                    // Re-draw name and table header on new page
                    $pdf->SetFillColor(230, 240, 250);
                    $pdf->SetFont('Arial', 'B', 9);
                    $pdf->SetTextColor(21, 67, 96);
                    $pdf->Cell(190, 6, '  ' . utf8ToLatin1($v['name']) . ' (ID: ' . $v['vid'] . ') - Continuación', 1, 1, 'L', true);
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
            $pdf->SetTextColor(192, 57, 43); // Red color for anomalies
            $pdf->Cell(190, 5, utf8ToLatin1('  Marcas huérfanas / inconsistencias:'), 'LR', 1, 'L');
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
    
    $pdf->Output('D', 'informe_' . date('Ymd_His') . '.pdf');
    exit;
} else {
    die("Formato no válido.");
}
