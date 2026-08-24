<?php

function timeOrNull(?string $value): ?string {
    if (!$value) {
        return null;
    }
    $time = substr($value, 0, 5);
    return $time === '00:00' ? null : $time;
}

function dtFor(string $date, string $time): DateTime {
    return new DateTime($date . ' ' . $time . ':00');
}

function buildTurnoWindow(array $empleado, DateTime $now): array {
    $entrada = timeOrNull($empleado['turno_entrada'] ?? null);
    $salida = timeOrNull($empleado['turno_salida'] ?? null);
    $today = $now->format('Y-m-d');
    $yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');
    $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');

    if (!$entrada || !$salida) {
        $start = new DateTime($today . ' 00:00:00');
        $end = new DateTime($today . ' 23:59:59');
        return [
            'sin_horario' => true,
            'start' => $start,
            'end' => $end,
            'lookup_start' => $start,
            'lookup_end' => $end,
        ];
    }

    $cruzaMedianoche = $entrada > $salida;
    if ($cruzaMedianoche) {
        $cutoff = dtFor($today, $entrada)->modify('-3 hours');
        if ($now < $cutoff) {
            $start = dtFor($yesterday, $entrada);
            $end = dtFor($today, $salida);
        } else {
            $start = dtFor($today, $entrada);
            $end = dtFor($tomorrow, $salida);
        }
    } else {
        $start = dtFor($today, $entrada);
        $end = dtFor($today, $salida);
    }

    return [
        'sin_horario' => false,
        'start' => $start,
        'end' => $end,
        'lookup_start' => (clone $start)->modify('-3 hours'),
        'lookup_end' => (clone $end)->modify('+3 hours'),
    ];
}

function buildTurnoWindows(array $empleado, DateTime $now): array {
    $entrada = timeOrNull($empleado['turno_entrada'] ?? null);
    $salida = timeOrNull($empleado['turno_salida'] ?? null);
    $today = $now->format('Y-m-d');
    $yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');
    $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');

    if (!$entrada || !$salida) {
        return ['current' => buildTurnoWindow($empleado, $now)];
    }

    if ($entrada <= $salida) {
        return ['current' => buildTurnoWindow($empleado, $now)];
    }

    $previousStart = dtFor($yesterday, $entrada);
    $previousEnd = dtFor($today, $salida);
    $currentStart = dtFor($today, $entrada);
    $currentEnd = dtFor($tomorrow, $salida);

    return [
        'previous' => [
            'sin_horario' => false,
            'start' => $previousStart,
            'end' => $previousEnd,
            'lookup_start' => (clone $previousStart)->modify('-3 hours'),
            'lookup_end' => (clone $previousEnd)->modify('+3 hours'),
        ],
        'current' => [
            'sin_horario' => false,
            'start' => $currentStart,
            'end' => $currentEnd,
            'lookup_start' => (clone $currentStart)->modify('-3 hours'),
            'lookup_end' => (clone $currentEnd)->modify('+3 hours'),
        ],
    ];
}

function resumirNovedadesTurno(array $novedades, array $window): array {
    $entrada = null;
    $salida = null;
    $ultimaActividad = null;

    foreach ($novedades as $nov) {
        $fechaHora = new DateTime($nov['fecha'] . ' ' . $nov['hora']);
        if ($fechaHora < $window['lookup_start'] || $fechaHora > $window['lookup_end']) {
            continue;
        }

        $hora = substr($nov['hora'], 0, 5);
        $ultimaActividad = $hora;
        if ($nov['tipo_nombre'] === 'Entrada') {
            $entrada = $hora;
        } elseif ($nov['tipo_nombre'] === 'Salida') {
            $salida = $hora;
        }
    }

    return [
        'entrada' => $entrada,
        'salida' => $salida,
        'ultima_actividad' => $ultimaActividad,
        'tiene_registro' => (bool)($entrada || $salida),
    ];
}

function elegirTurnoPresencias(array $empleado, array $novedadesEmpleado, DateTime $now): array {
    $windows = buildTurnoWindows($empleado, $now);

    if (!isset($windows['previous'])) {
        return [
            'window' => $windows['current'],
            'summary' => resumirNovedadesTurno($novedadesEmpleado, $windows['current']),
        ];
    }

    $previousSummary = resumirNovedadesTurno($novedadesEmpleado, $windows['previous']);
    $currentSummary = resumirNovedadesTurno($novedadesEmpleado, $windows['current']);

    if ($currentSummary['entrada']) {
        return [
            'window' => $windows['current'],
            'summary' => $currentSummary,
        ];
    }

    if ($previousSummary['tiene_registro']) {
        return [
            'window' => $windows['previous'],
            'summary' => $previousSummary,
        ];
    }

    return [
        'window' => $windows['previous'],
        'summary' => $previousSummary,
    ];
}

function novedadesPorEmpleado(PDO $db, array $empleados, DateTime $now): array {
    if (!$empleados) {
        return [];
    }

    $ids = array_map(fn($e) => (int)$e['id_empleado'], $empleados);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $desde = (clone $now)->modify('-1 day')->format('Y-m-d');
    $hasta = (clone $now)->modify('+1 day')->format('Y-m-d');

    $stmt = $db->prepare(
        "SELECT n.empleado_id, n.fecha, n.hora, tn.nombre AS tipo_nombre
         FROM novedades n
         JOIN tipo_novedad tn ON n.tipo_novedad = tn.id_tipo
         WHERE n.empleado_id IN ($placeholders)
           AND n.fecha BETWEEN ? AND ?
           AND tn.nombre IN ('Entrada', 'Salida')
         ORDER BY n.fecha ASC, n.hora ASC"
    );
    $stmt->execute([...$ids, $desde, $hasta]);

    $porEmpleado = [];
    foreach ($stmt->fetchAll() as $row) {
        $porEmpleado[(int)$row['empleado_id']][] = $row;
    }
    return $porEmpleado;
}

function aplicarEstadoPresencias(PDO $db, array $empleados): array {
    $now = new DateTime();
    $novedades = novedadesPorEmpleado($db, $empleados, $now);

    foreach ($empleados as &$empleado) {
        $novedadesEmpleado = $novedades[(int)$empleado['id_empleado']] ?? [];

        // Si no tiene horario predefinido y entró ayer por la tarde/noche sin salida ayer,
        // le asignamos un turno dinámico para que la lógica de ventana de medianoche aplique.
        $entrada = timeOrNull($empleado['turno_entrada'] ?? null);
        $salida = timeOrNull($empleado['turno_salida'] ?? null);
        if (!$entrada || !$salida) {
            $entradaAyer = null;
            $ayerStr = (clone $now)->modify('-1 day')->format('Y-m-d');
            foreach ($novedadesEmpleado as $nov) {
                if ($nov['fecha'] === $ayerStr && $nov['tipo_nombre'] === 'Entrada' && $nov['hora'] >= '12:00:00') {
                    $entradaAyer = $nov['hora'];
                }
            }
            if ($entradaAyer) {
                $empleado['turno_entrada'] = substr($entradaAyer, 0, 5);
                $dtEntrada = new DateTime($ayerStr . ' ' . $entradaAyer);
                $dtSalida = (clone $dtEntrada)->modify('+12 hours');
                $empleado['turno_salida'] = $dtSalida->format('H:i');
            }
        }

        $turno = elegirTurnoPresencias($empleado, $novedadesEmpleado, $now);
        $window = $turno['window'];
        $entradaHoy = $turno['summary']['entrada'];
        $salidaHoy = $turno['summary']['salida'];
        $ultimaActividad = $turno['summary']['ultima_actividad'];

        $empleado['hora_entrada_hoy'] = $entradaHoy;
        $empleado['hora_salida_hoy'] = $salidaHoy;
        $empleado['ultima_actividad'] = $ultimaActividad;

        $tEntrada = timeOrNull($empleado['turno_entrada'] ?? null);
        $tSalida = timeOrNull($empleado['turno_salida'] ?? null);
        $sinHorario = $window['sin_horario'];

        if (array_key_exists('id_objetivo', $empleado) && !$empleado['id_objetivo']) {
            $empleado['estado'] = 'sin-objetivos';
        } elseif ($entradaHoy && $salidaHoy) {
            $empleado['estado'] = 'completado';
        } elseif ($entradaHoy && !$salidaHoy) {
            $empleado['estado'] = $now <= $window['end'] ? 'presente' : 'incompleto';
        } elseif (!$entradaHoy && $salidaHoy) {
            $empleado['estado'] = 'incompleto';
        } else {
            if ($sinHorario) {
                $empleado['estado'] = 'sin-registro';
            } elseif ($now > $window['end']) {
                $empleado['estado'] = 'ausente';
            } else {
                $empleado['estado'] = 'por-iniciar';
            }
        }

        $empleado['turno_entrada'] = $tEntrada;
        $empleado['turno_salida'] = $tSalida;
        if (isset($empleado['total_novedades'])) {
            $empleado['total_novedades'] = count($novedades[(int)$empleado['id_empleado']] ?? []);
        }
    }

    return $empleados;
}
