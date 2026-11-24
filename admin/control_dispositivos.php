<?php
// Control de dispositivos de marcado: detecta un mismo teléfono usado por varios
// empleados y empleados que marcan desde varios teléfonos.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
requireAdminRealPage();

$fecha_inicio = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin    = $_GET['hasta'] ?? date('Y-m-d');
$valida = fn($f) => is_string($f) && (bool)DateTime::createFromFormat('Y-m-d', $f);
if (!$valida($fecha_inicio)) $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
if (!$valida($fecha_fin))    $fecha_fin = date('Y-m-d');
if ($fecha_inicio > $fecha_fin) [$fecha_inicio, $fecha_fin] = [$fecha_fin, $fecha_inicio];

/** Resume el User-Agent en algo legible: "Android 14 · Chrome". */
function describirUA(?string $ua): string {
    if (!$ua) return '';
    $tiene = function (string $t) use ($ua) { return strpos($ua, $t) !== false; };
    $so = '';
    if (preg_match('/Android ([\d.]+)/', $ua, $m))                  $so = 'Android ' . $m[1];
    elseif (preg_match('/(iPhone|iPad).*? OS ([\d_]+)/', $ua, $m))   $so = $m[1] . ' iOS ' . str_replace('_', '.', $m[2]);
    elseif ($tiene('Windows'))                                      $so = 'Windows';
    elseif ($tiene('Mac OS X'))                                     $so = 'Mac';
    elseif ($tiene('Linux'))                                        $so = 'Linux';
    $nav = '';
    if ($tiene('SamsungBrowser'))                     $nav = 'Samsung Internet';
    elseif ($tiene('Edg'))                            $nav = 'Edge';
    elseif ($tiene('OPR'))                            $nav = 'Opera';
    elseif ($tiene('Firefox') || $tiene('FxiOS'))     $nav = 'Firefox';
    elseif ($tiene('CriOS') || $tiene('Chrome'))      $nav = 'Chrome';
    elseif ($tiene('Safari'))                         $nav = 'Safari';
    return trim($so . ($so && $nav ? ' · ' : '') . $nav);
}

$error = '';
$compartidos = [];
$multiples = [];
$sinId = 0;
$conId = 0;

try {
    $db = getDB();
    $rango = [$fecha_inicio, $fecha_fin];

    // Detalle por dispositivo y empleado (base de ambos listados).
    $stmt = $db->prepare("
        SELECT n.dispositivo_id, n.empleado_id, e.nombre, e.DNI AS dni,
               COUNT(*) AS marcas,
               MIN(CONCAT(n.fecha, ' ', n.hora)) AS primera,
               MAX(CONCAT(n.fecha, ' ', n.hora)) AS ultima,
               MAX(n.modelo_disp) AS modelo,
               MAX(n.user_agent) AS ua
        FROM novedades n
        JOIN empleados e ON e.id_empleado = n.empleado_id
        WHERE n.fecha BETWEEN ? AND ?
          AND n.dispositivo_id IS NOT NULL
        GROUP BY n.dispositivo_id, n.empleado_id, e.nombre, e.DNI
        ORDER BY ultima DESC
    ");
    $stmt->execute($rango);
    $filas = $stmt->fetchAll();

    $porDisp = [];
    $porEmp = [];
    foreach ($filas as $f) {
        $porDisp[$f['dispositivo_id']][] = $f;
        $porEmp[$f['empleado_id']][] = $f;
    }
    $compartidos = array_filter($porDisp, fn($l) => count($l) > 1);
    $multiples   = array_filter($porEmp, fn($l) => count($l) > 1);
    uasort($compartidos, fn($a, $b) => count($b) <=> count($a));
    uasort($multiples, fn($a, $b) => count($b) <=> count($a));

    $stmt = $db->prepare("
        SELECT SUM(dispositivo_id IS NULL) AS sin_id, SUM(dispositivo_id IS NOT NULL) AS con_id
        FROM novedades WHERE fecha BETWEEN ? AND ?
    ");
    $stmt->execute($rango);
    $c = $stmt->fetch();
    $sinId = (int)($c['sin_id'] ?? 0);
    $conId = (int)($c['con_id'] ?? 0);
} catch (PDOException $e) {
    $error = $e->getCode() === '42S22'
        ? 'Falta aplicar la migración 2026-11-24_novedades_dispositivo.sql (Administración → Migraciones).'
        : 'No se pudo consultar la información de dispositivos.';
}

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$fh = fn($dt) => $dt ? date('d/m/Y H:i', strtotime($dt)) : '-';
$corto = fn($id) => strtoupper(substr(str_replace('-', '', (string)$id), 0, 8));
$equipo = function (array $f) use ($h) {
    $partes = array_filter([$f['modelo'] ?? '', describirUA($f['ua'] ?? null)]);
    return $partes ? $h(implode(' · ', $partes)) : '<span class="muted">Sin datos</span>';
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV — Control de dispositivos</title>
    <link rel="stylesheet" href="../css/style.css?v=3">
    <link rel="stylesheet" href="../css/admin.css?v=20261020">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <style>
        .section-title { font-size:1.2rem;font-weight:700;color:var(--primary);margin-bottom:1.2rem;display:flex;align-items:center;gap:.5rem; }
        .cd-card { background:var(--card);border-radius:10px;box-shadow:var(--shadow);padding:1.3rem;margin-bottom:1.3rem; }
        .cd-card h2 { font-size:1rem;margin:0 0 .3rem;color:var(--text); }
        .cd-card .ayuda { font-size:.85rem;color:var(--text-muted);margin:0 0 1rem; }
        .filtros { display:flex;flex-wrap:wrap;gap:.8rem;align-items:flex-end; }
        .filtros label { display:block;font-size:.85rem;font-weight:600;margin-bottom:.25rem; }
        .filtros input { padding:.5rem .6rem;border:1px solid var(--border);border-radius:6px; }
        .filtros button { padding:.55rem 1.2rem;width:auto; }
        .stats { display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.8rem;margin-bottom:1.3rem; }
        .stat { background:var(--card);border:1px solid var(--border);border-radius:8px;padding:.9rem;text-align:center; }
        .stat .num { font-size:1.5rem;font-weight:700;color:var(--primary); }
        .stat.alerta .num { color:var(--danger); }
        .stat .lbl { font-size:.78rem;color:var(--text-muted); }
        .grupo { border:1px solid var(--border);border-radius:8px;margin-bottom:.8rem;overflow:hidden; }
        .grupo-head { background:var(--bg);padding:.75rem 1rem;display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap;font-weight:600; }
        .grupo-head .muted { font-weight:400; }
        .disp-id { font-family:monospace;background:#eef2f7;padding:.1rem .4rem;border-radius:4px; }
        table.cd { width:100%;border-collapse:collapse;font-size:.85rem; }
        table.cd th { text-align:left;padding:.5rem .8rem;color:var(--text-muted);font-weight:600;border-bottom:1px solid var(--border); }
        table.cd td { padding:.5rem .8rem;border-bottom:1px solid var(--border); }
        table.cd tr:last-child td { border-bottom:none; }
        .muted { color:var(--text-muted); }
        .vacio { padding:1rem;text-align:center;color:var(--text-muted);font-size:.9rem; }
        .alert-error { background:#fdf2f2;color:#922;border-left:4px solid var(--danger);padding:.8rem 1rem;border-radius:6px;margin-bottom:1rem; }
        @media (max-width: 640px) { table.cd .opc { display:none; } }
    </style>
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

<div style="max-width:1000px;margin:0 auto;padding:1.5rem 1rem 3rem;">

    <div class="section-title">&#x1F4F1; Control de dispositivos de marcado</div>

    <?php if ($error): ?>
        <div class="alert-error"><?= $h($error) ?></div>
    <?php endif; ?>

    <div class="cd-card">
        <form class="filtros" method="GET">
            <div><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= $h($fecha_inicio) ?>"></div>
            <div><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= $h($fecha_fin) ?>"></div>
            <button type="submit" class="btn btn-primary">Consultar</button>
        </form>
    </div>

    <div class="stats">
        <div class="stat <?= $compartidos ? 'alerta' : '' ?>"><div class="num"><?= count($compartidos) ?></div><div class="lbl">Dispositivos compartidos</div></div>
        <div class="stat"><div class="num"><?= count($multiples) ?></div><div class="lbl">Empleados con varios dispositivos</div></div>
        <div class="stat"><div class="num"><?= $conId ?></div><div class="lbl">Marcas con dispositivo</div></div>
        <div class="stat"><div class="num"><?= $sinId ?></div><div class="lbl">Marcas sin dispositivo</div></div>
    </div>

    <div class="cd-card">
        <h2>&#x26A0; Mismo dispositivo, varios empleados</h2>
        <p class="ayuda">Puede indicar que alguien marca por otro. También pasa si comparten un teléfono de la empresa en el objetivo.</p>
        <?php if (!$compartidos): ?>
            <div class="vacio">No hay dispositivos usados por más de un empleado en el período.</div>
        <?php endif; ?>
        <?php foreach ($compartidos as $dispId => $lista): ?>
            <div class="grupo">
                <div class="grupo-head">
                    <span>Dispositivo <span class="disp-id" title="<?= $h($dispId) ?>"><?= $corto($dispId) ?></span> &nbsp;<span class="muted"><?= $equipo($lista[0]) ?></span></span>
                    <span><?= count($lista) ?> empleados</span>
                </div>
                <table class="cd">
                    <tr><th>Empleado</th><th class="opc">DNI</th><th>Marcas</th><th class="opc">Primera</th><th>Última</th></tr>
                    <?php foreach ($lista as $f): ?>
                        <tr>
                            <td><?= $h($f['nombre']) ?></td>
                            <td class="opc"><?= $h($f['dni'] ?: '-') ?></td>
                            <td><?= (int)$f['marcas'] ?></td>
                            <td class="opc"><?= $fh($f['primera']) ?></td>
                            <td><?= $fh($f['ultima']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="cd-card">
        <h2>&#x1F504; Empleados que marcaron desde varios dispositivos</h2>
        <p class="ayuda">Puede ser un cambio de teléfono, otro navegador o datos del navegador borrados. Revisar si se repite seguido.</p>
        <?php if (!$multiples): ?>
            <div class="vacio">Ningún empleado usó más de un dispositivo en el período.</div>
        <?php endif; ?>
        <?php foreach ($multiples as $lista): ?>
            <div class="grupo">
                <div class="grupo-head">
                    <span>&#x1F464; <?= $h($lista[0]['nombre']) ?> <span class="muted">DNI <?= $h($lista[0]['dni'] ?: '-') ?></span></span>
                    <span><?= count($lista) ?> dispositivos</span>
                </div>
                <table class="cd">
                    <tr><th>Dispositivo</th><th class="opc">Equipo</th><th>Marcas</th><th class="opc">Primera</th><th>Última</th></tr>
                    <?php foreach ($lista as $f): ?>
                        <tr>
                            <td><span class="disp-id" title="<?= $h($f['dispositivo_id']) ?>"><?= $corto($f['dispositivo_id']) ?></span></td>
                            <td class="opc"><?= $equipo($f) ?></td>
                            <td><?= (int)$f['marcas'] ?></td>
                            <td class="opc"><?= $fh($f['primera']) ?></td>
                            <td><?= $fh($f['ultima']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($sinId > 0): ?>
        <p class="muted" style="font-size:.85rem">Las marcas sin dispositivo son anteriores a esta función o vienen de un teléfono que todavía tiene la versión vieja de la página en caché.</p>
    <?php endif; ?>
</div>
</body>
</html>
