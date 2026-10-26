<?php
// PHP helper functions for processing shifts and liquidaciones

/**
 * Days of margin to fetch around the requested period, so shifts that cross
 * the period boundary (e.g. 19:00 -> 07:00) keep both of their marks.
 */
const LIQUIDACION_MARGEN_DIAS = 1;

/**
 * Returns [desde, hasta] widened by LIQUIDACION_MARGEN_DIAS, for the novedades query.
 */
function rangoConMargen(string $fecha_inicio, string $fecha_fin): array {
    $m = LIQUIDACION_MARGEN_DIAS;
    return [
        date('Y-m-d', strtotime("$fecha_inicio -$m day")),
        date('Y-m-d', strtotime("$fecha_fin +$m day")),
    ];
}

/**
 * Calculates shifts from raw novelty/check-in rows
 * Each row must have: fecha, hora, tipo, vigilador_id, nombre, apellido, observaciones
 *
 * If $desde/$hasta are given, rows are expected to include the margin from rangoConMargen():
 * a shift belongs to the period of its entry date, and anomalies outside the period are dropped.
 */
function calcularLiquidacion(array $rows, ?string $desde = null, ?string $hasta = null): array {
    $by_vigilador = [];
    foreach ($rows as $r) {
        $vid = (string)$r['vigilador_id'];
        if (!isset($by_vigilador[$vid])) {
            $by_vigilador[$vid] = [];
        }
        $by_vigilador[$vid][] = $r;
    }

    $report = [];
    foreach ($by_vigilador as $vid => $v_rows) {
        // Sort chronologically
        usort($v_rows, function($a, $b) {
            $tA = $a['fecha'] . ' ' . $a['hora'];
            $tB = $b['fecha'] . ' ' . $b['hora'];
            return strcmp($tA, $tB);
        });

        $nombre = $v_rows[0]['nombre'] ?? '';
        $apellido = $v_rows[0]['apellido'] ?? '';
        $name = trim("$nombre $apellido");
        if (empty($name)) {
            $name = "Vigilador ID $vid";
        }

        $vigilador_report = [
            'vid' => $vid,
            'name' => $name,
            'shifts' => [],
            'anomalies' => []
        ];

        $active_entry = null;

        foreach ($v_rows as $r) {
            $tipo = (string)$r['tipo'];
            $dt_str = $r['fecha'] . ' ' . $r['hora'];
            
            // Try different datetime formats if seconds are missing or weird
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $dt_str);
            if (!$dt) {
                $dt = DateTime::createFromFormat('Y-m-d H:i', $r['fecha'] . ' ' . substr($r['hora'], 0, 5));
            }
            if (!$dt) {
                // If it still fails, skip this row
                continue;
            }
            
            $obs = $r['observaciones'] ?? '';

            if ($tipo === '1') { // Entrada
                if ($active_entry !== null) {
                    $diff = $dt->getTimestamp() - $active_entry['dt']->getTimestamp();
                    if ($diff < 300) {
                        // Ignore duplicates within 5 minutes
                        continue;
                    } else {
                        // Unpaired entry
                        $vigilador_report['anomalies'][] = [
                            'type' => 'Entrada sin salida',
                            'dt' => $active_entry['dt']->format('Y-m-d H:i:s'),
                            'obs' => $active_entry['obs']
                        ];
                    }
                }
                $active_entry = ['dt' => $dt, 'obs' => $obs, 'row' => $r];
            } else if ($tipo === '2') { // Salida
                if ($active_entry !== null) {
                    $diff_seconds = $dt->getTimestamp() - $active_entry['dt']->getTimestamp();
                    $diff_hours = $diff_seconds / 3600.0;

                    if ($diff_hours > 18.0) {
                        // Exceeds 18 hours, count as separate anomalies
                        $vigilador_report['anomalies'][] = [
                            'type' => 'Entrada sin salida (excede 18hs)',
                            'dt' => $active_entry['dt']->format('Y-m-d H:i:s'),
                            'obs' => $active_entry['obs']
                        ];
                        $vigilador_report['anomalies'][] = [
                            'type' => 'Salida sin entrada (excede 18hs)',
                            'dt' => $dt->format('Y-m-d H:i:s'),
                            'obs' => $obs
                        ];
                    } else {
                        // Valid pair
                        $obs_parts = [];
                        if (!empty(trim((string)($active_entry['obs'] ?? '')))) {
                            $obs_parts[] = 'Entrada: ' . trim((string)$active_entry['obs']);
                        }
                        if (!empty(trim((string)($obs ?? '')))) {
                            $obs_parts[] = 'Salida: ' . trim((string)$obs);
                        }
                        $shift_obs = implode(' | ', $obs_parts);

                        $vigilador_report['shifts'][] = [
                            'entry' => $active_entry['dt']->format('Y-m-d H:i:s'),
                            'exit' => $dt->format('Y-m-d H:i:s'),
                            'hours' => $diff_hours,
                            'obs' => $shift_obs
                        ];
                    }
                    $active_entry = null;
                } else {
                    // Exit without entry
                    $vigilador_report['anomalies'][] = [
                        'type' => 'Salida sin entrada',
                        'dt' => $dt->format('Y-m-d H:i:s'),
                        'obs' => $obs
                    ];
                }
            }
        }

        if ($active_entry !== null) {
            $vigilador_report['anomalies'][] = [
                'type' => 'Entrada sin salida (fin de registros)',
                'dt' => $active_entry['dt']->format('Y-m-d H:i:s'),
                'obs' => $active_entry['obs']
            ];
        }

        if ($desde !== null && $hasta !== null) {
            $enPeriodo = function (string $dt) use ($desde, $hasta): bool {
                $fecha = substr($dt, 0, 10);
                return $fecha >= $desde && $fecha <= $hasta;
            };
            $vigilador_report['shifts'] = array_values(array_filter(
                $vigilador_report['shifts'], fn($s) => $enPeriodo($s['entry'])
            ));
            $vigilador_report['anomalies'] = array_values(array_filter(
                $vigilador_report['anomalies'], fn($a) => $enPeriodo($a['dt'])
            ));
            if (!$vigilador_report['shifts'] && !$vigilador_report['anomalies']) {
                continue;
            }
        }

        $report[] = $vigilador_report;
    }

    // Sort report alphabetically by vigilador name
    usort($report, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return $report;
}

/**
 * Format decimal hours to HH:MM format
 */
function formatDecimalHours($hours_decimal) {
    $total_minutes = (int)round($hours_decimal * 60);
    $h = floor($total_minutes / 60);
    $m = $total_minutes % 60;
    return sprintf("%02d:%02d", $h, $m);
}

/**
 * Safe UTF-8 to Latin1 (ISO-8859-1) conversion to prevent PHP 8.2+ deprecation warnings.
 */
function utf8ToLatin1($string) {
    if ($string === null || $string === '') {
        return '';
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');
    }
    if (function_exists('iconv')) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $string);
    }
    return @utf8_decode($string);
}
