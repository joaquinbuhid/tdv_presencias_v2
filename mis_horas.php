<?php
// Informe personal de horas: cada empleado ve SOLO sus propias marcas,
// entre dos fechas, en uno o varios objetivos.
session_start();
if (!isset($_SESSION['empleado_id'])) {
    header('Location: index.php');
    exit;
}
if (!empty($_SESSION['es_admin'])) {
    header('Location: admin/dashboard.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/admin/api/liquidacion_helper.php';

const MIS_HORAS_MAX_DIAS = 366;

$empleadoId = (int)$_SESSION['empleado_id'];
$nombre     = $_SESSION['nombre_completo'] ?? '';
$volverA    = !empty($_SESSION['es_supervisor']) ? 'supervisor/dashboard.php' : 'dashboard.php';

$error = '';
$objetivos = [];
$resultado = [];
$titulo = '';
$mostrar = false;

$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin    = $_POST['fecha_fin'] ?? date('Y-m-d');
$seleccion    = array_values(array_filter(array_map('intval', (array)($_POST['objetivos'] ?? [])), fn($id) => $id > 0));

try {
    $db = getDB();
    // Objetivos donde el empleado tiene marcas, más el que tiene asignado hoy.
    // Marcas viejas sin objetivo_id cuentan para el objetivo asignado al empleado.
    $stmt = $db->prepare("
        SELECT o.id_objetivo, o.nombre
        FROM objetivos o
        WHERE o.id_objetivo IN (
            SELECT DISTINCT COALESCE(n.objetivo_id, e.objetivo_id)
            FROM novedades n
            JOIN empleados e ON e.id_empleado = n.empleado_id
            WHERE n.empleado_id = ?
        )
        OR o.id_objetivo = (SELECT objetivo_id FROM empleados WHERE id_empleado = ?)
        ORDER BY o.nombre
    ");
    $stmt->execute([$empleadoId, $empleadoId]);
    $objetivos = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'No se pudieron cargar tus objetivos. Intentá de nuevo más tarde.';
}

$nombresObj = [];
foreach ($objetivos as $o) {
    $nombresObj[(int)$o['id_objetivo']] = $o['nombre'];
}
// Por defecto, todos los objetivos marcados.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $seleccion = array_keys($nombresObj);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $validaFecha = fn($f) => (bool)DateTime::createFromFormat('Y-m-d', $f);
    // Solo objetivos propios del empleado: descarta cualquier id ajeno.
    $seleccion = array_values(array_filter($seleccion, fn($id) => isset($nombresObj[$id])));

    if (!$validaFecha($fecha_inicio) || !$validaFecha($fecha_fin)) {
        $error = 'Completá ambas fechas.';
    } elseif ($fecha_inicio > $fecha_fin) {
        $error = 'La fecha "Desde" tiene que ser anterior o igual a la fecha "Hasta".';
    } elseif ((strtotime($fecha_fin) - strtotime($fecha_inicio)) / 86400 > MIS_HORAS_MAX_DIAS) {
        $error = 'El período no puede superar un año.';
    } elseif (empty($seleccion)) {
        $error = 'Elegí al menos un objetivo.';
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($seleccion), '?'));
            // Se agrupa por objetivo (usando la columna vigilador_id del helper como clave),
            // así las horas por objetivo coinciden con el informe de supervisor y administración.
            $stmt = $db->prepare("
                SELECT n.fecha, n.hora, n.tipo_novedad AS tipo, n.observaciones,
                       o.id_objetivo AS vigilador_id, o.nombre AS nombre, '' AS apellido
                FROM novedades n
                JOIN empleados e ON e.id_empleado = n.empleado_id
                JOIN objetivos o ON o.id_objetivo = COALESCE(n.objetivo_id, e.objetivo_id)
                WHERE n.empleado_id = ?
                  AND n.fecha BETWEEN ? AND ?
                  AND o.id_objetivo IN ($placeholders)
                ORDER BY n.fecha ASC, n.hora ASC
            ");
            $stmt->execute([$empleadoId, ...rangoConMargen($fecha_inicio, $fecha_fin), ...$seleccion]);
            $resultado = calcularLiquidacion($stmt->fetchAll(), $fecha_inicio, $fecha_fin);

            if (empty($resultado)) {
                $error = 'No hay marcas tuyas en ese período para los objetivos elegidos.';
            } else {
                $objTxt = count($seleccion) === count($nombresObj) && count($seleccion) > 1
                    ? 'Todos mis objetivos'
                    : implode(', ', array_map(fn($id) => $nombresObj[$id], $seleccion));
                $titulo = $nombre . ' — ' . $objTxt . ' (' . date('d/m/Y', strtotime($fecha_inicio))
                    . ' al ' . date('d/m/Y', strtotime($fecha_fin)) . ')';
                $_SESSION['mis_horas_informe'] = [
                    'empleado_id' => $empleadoId,
                    'titulo'      => $titulo,
                    'data'        => $resultado,
                ];
                $mostrar = true;
            }
        } catch (Exception $e) {
            $error = 'No se pudo generar el informe. Intentá de nuevo más tarde.';
        }
    }
}

$totHoras = 0.0;
$totTurnos = 0;
$totAnom = 0;
foreach ($resultado as $r) {
    $totHoras  += array_sum(array_column($r['shifts'], 'hours'));
    $totTurnos += count($r['shifts']);
    $totAnom   += count($r['anomalies']);
}
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV — Mis horas</title>
    <link rel="stylesheet" href="css/style.css?v=2">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <style>
        input[type="date"] {
            width: 100%; padding: .85rem 1rem; border: 2px solid var(--border);
            border-radius: 10px; font-size: 1.05rem; color: var(--text); background: #fff;
        }
        .fechas { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }
        .obj-list { display: flex; flex-direction: column; gap: .45rem; }
        .obj-item {
            display: flex; align-items: center; gap: .7rem; margin: 0;
            padding: .75rem .9rem; border: 2px solid var(--border); border-radius: 10px;
            font-weight: 500; cursor: pointer;
        }
        .obj-item input { width: 1.25rem; height: 1.25rem; flex-shrink: 0; }
        .obj-todos { font-size: .95rem; color: var(--accent); background: none; border: none;
                     cursor: pointer; text-decoration: underline; padding: 0; margin-bottom: .5rem; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; margin-bottom: 1.2rem; }
        .stat { background: var(--bg); border: 1px solid var(--border); border-radius: 10px;
                padding: .8rem .4rem; text-align: center; }
        .stat .num { font-size: 1.45rem; font-weight: 700; color: var(--primary); }
        .stat .lbl { font-size: .8rem; color: var(--text-muted); }
        .stat.warn .num { color: var(--danger); }
        .exports { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; margin-bottom: 1.2rem; }
        .exports a { text-decoration: none; font-size: 1rem; padding: .8rem; }
        .btn-xls { background: #27ae60; color: #fff; }
        .btn-pdf { background: #e74c3c; color: #fff; }
        .obj-bloque { border: 1px solid var(--border); border-radius: 10px; margin-bottom: .8rem; overflow: hidden; }
        .obj-head { display: flex; justify-content: space-between; align-items: center; gap: .5rem;
                    background: var(--bg); padding: .9rem 1rem; cursor: pointer; font-weight: 700; }
        .obj-head .hs { color: var(--accent); white-space: nowrap; }
        .obj-body { display: none; padding: .4rem .8rem .9rem; }
        .obj-body.open { display: block; }
        .turno { display: flex; justify-content: space-between; gap: .5rem; padding: .65rem .2rem;
                 border-bottom: 1px solid var(--border); font-size: .95rem; }
        .turno:last-child { border-bottom: none; }
        .turno .obs { font-size: .82rem; color: var(--text-muted); margin-top: .15rem; }
        .turno .hs { font-weight: 700; white-space: nowrap; }
        .nextday { background: #f39c12; color: #fff; font-size: .7rem; padding: .05rem .3rem;
                   border-radius: 3px; font-weight: 700; }
        .anom { margin-top: .7rem; padding: .7rem .9rem; background: #fdf2f2; border-left: 4px solid var(--danger);
                border-radius: 6px; font-size: .88rem; color: #7f2d2d; }
        .anom ul { margin: .3rem 0 0; padding-left: 1.1rem; }
        .anom-nota { font-size: .82rem; color: var(--text-muted); margin-top: .4rem; }
    </style>
</head>
<body>

<header class="app-header">
    <div class="brand"><span>&#x1F6E1;</span> TDV Seguridad</div>
    <div class="header-user">
        <strong><?= $h($nombre) ?></strong>
        <a href="<?= $volverA ?>" style="color:rgba(255,255,255,.75);font-size:.95rem;text-decoration:none;">&larr; Volver al panel</a>
    </div>
</header>

<main class="app-content">

    <div class="card">
        <div class="card-title">&#x1F4CA; Mis horas trabajadas</div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger show" role="alert"><span>&#9888;</span><span><?= $h($error) ?></span></div>
        <?php endif; ?>

        <?php if (empty($objetivos)): ?>
            <p style="color:var(--text-muted)">Todavía no tenés objetivos ni marcas registradas.</p>
        <?php else: ?>
        <form method="POST" action="mis_horas.php">
            <div class="fechas form-group">
                <div>
                    <label for="fecha_inicio">Desde</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" required value="<?= $h($fecha_inicio) ?>">
                </div>
                <div>
                    <label for="fecha_fin">Hasta</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" required value="<?= $h($fecha_fin) ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Objetivos</label>
                <?php if (count($objetivos) > 1): ?>
                    <button type="button" class="obj-todos" id="btnTodos">Marcar / desmarcar todos</button>
                <?php endif; ?>
                <div class="obj-list">
                    <?php foreach ($objetivos as $o): $id = (int)$o['id_objetivo']; ?>
                        <label class="obj-item">
                            <input type="checkbox" name="objetivos[]" value="<?= $id ?>" <?= in_array($id, $seleccion, true) ? 'checked' : '' ?>>
                            <?= $h($o['nombre']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Generar informe</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($mostrar): ?>
    <div class="card">
        <div class="card-title">&#x1F4C4; Resultado</div>
        <p style="font-size:.92rem;color:var(--text-muted);margin:-.3rem 0 1rem"><?= $h($titulo) ?></p>

        <div class="stats">
            <div class="stat"><div class="num"><?= formatDecimalHours($totHoras) ?></div><div class="lbl">Horas</div></div>
            <div class="stat"><div class="num"><?= $totTurnos ?></div><div class="lbl">Turnos</div></div>
            <div class="stat <?= $totAnom ? 'warn' : '' ?>"><div class="num"><?= $totAnom ?></div><div class="lbl">Marcas sin par</div></div>
        </div>

        <div class="exports">
            <a class="btn btn-xls" href="api/export_mis_horas.php?format=excel">&#x1F4E5; Excel</a>
            <a class="btn btn-pdf" href="api/export_mis_horas.php?format=pdf">&#x1F4E5; PDF</a>
        </div>

        <?php foreach ($resultado as $i => $r): $hs = array_sum(array_column($r['shifts'], 'hours')); ?>
            <div class="obj-bloque">
                <div class="obj-head" onclick="document.getElementById('obj-<?= $i ?>').classList.toggle('open')">
                    <span>&#x1F4CD; <?= $h($r['name']) ?></span>
                    <span class="hs"><?= formatDecimalHours($hs) ?> hs</span>
                </div>
                <div class="obj-body <?= count($resultado) === 1 ? 'open' : '' ?>" id="obj-<?= $i ?>">
                    <?php if (empty($r['shifts'])): ?>
                        <p style="font-size:.9rem;color:var(--text-muted);margin:.6rem 0">Sin turnos completos en el período.</p>
                    <?php endif; ?>
                    <?php foreach ($r['shifts'] as $s):
                        $e = new DateTime($s['entry']);
                        $x = new DateTime($s['exit']);
                        $otroDia = $e->format('Y-m-d') !== $x->format('Y-m-d');
                    ?>
                        <div class="turno">
                            <div>
                                <?= $e->format('d/m') ?> &nbsp;<?= $e->format('H:i') ?> &rarr; <?= $x->format('H:i') ?>
                                <?php if ($otroDia): ?><span class="nextday">+1 día</span><?php endif; ?>
                                <?php if (trim($s['obs']) !== ''): ?><div class="obs"><?= $h($s['obs']) ?></div><?php endif; ?>
                            </div>
                            <div class="hs"><?= formatDecimalHours($s['hours']) ?> hs</div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($r['anomalies'])): ?>
                        <div class="anom">
                            <strong>Marcas sin par (no suman horas)</strong>
                            <ul>
                                <?php foreach ($r['anomalies'] as $a): ?>
                                    <li><?= $h($a['type']) ?>: <?= date('d/m/Y H:i', strtotime($a['dt'])) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="anom-nota">Si te olvidaste de marcar, avisale a tu supervisor.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

<script>
(function () {
    const btn = document.getElementById('btnTodos');
    if (!btn) return;
    btn.addEventListener('click', function () {
        const cajas = document.querySelectorAll('input[name="objetivos[]"]');
        const todas = Array.from(cajas).every(c => c.checked);
        cajas.forEach(c => { c.checked = !todas; });
    });
})();
</script>
</body>
</html>
