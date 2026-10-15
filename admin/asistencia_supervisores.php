<?php
require_once __DIR__ . '/auth.php';
requireAdminRealPage();
require_once __DIR__ . '/../config/db.php';

$hoy    = date('Y-m-d');
$desde  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : $hoy;
$hasta  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : $hoy;
$supSel = (int)($_GET['supervisor'] ?? 0);
if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }

$db = getDB();

$supervisores = $db->query(
    "SELECT id_empleado, nombre FROM empleados WHERE tipo = 2 ORDER BY nombre"
)->fetchAll();

$params = [$desde, $hasta];
$whereSup = '';
if ($supSel > 0) {
    $whereSup = ' AND n.empleado_id = ?';
    $params[] = $supSel;
}
$stmt = $db->prepare(
    "SELECT n.fecha, n.hora, n.observaciones, e.nombre AS supervisor,
            t.nombre AS tipo, o.nombre AS objetivo
     FROM novedades n
     JOIN empleados e      ON n.empleado_id = e.id_empleado
     JOIN tipo_novedad t   ON n.tipo_novedad = t.id_tipo
     LEFT JOIN objetivos o ON n.objetivo_id = o.id_objetivo
     WHERE e.tipo = 2 AND n.fecha BETWEEN ? AND ? $whereSup
     ORDER BY n.fecha DESC, n.hora DESC"
);
$stmt->execute($params);
$registros = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Asistencia de supervisores</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <style>
        .data-table { width:100%;border-collapse:collapse;font-size:.88rem; }
        .data-table th { background:var(--primary);color:#fff;padding:.65rem .9rem;text-align:left;font-weight:600; }
        .data-table td { padding:.65rem .9rem;border-bottom:1px solid var(--bg);vertical-align:middle; }
        .data-table tr:hover td { background:#f7f9fc; }
        .pill { display:inline-block;padding:.18rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600;white-space:nowrap; }
        .pill-entrada { background:#eafaf1;color:#1e8449; }
        .pill-salida  { background:#ebf5fb;color:#1a5276; }
        .pill-otro    { background:#fef9e7;color:#9a7d0a; }
        .section-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:.8rem;flex-wrap:wrap;gap:.6rem; }
        .section-title  { font-size:1rem;font-weight:700;color:var(--primary); }
        .filtros { display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem; }
        .filtros .form-group { margin:0; }
        .filtros input, .filtros select { padding:.45rem .6rem; }
    </style>
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

<div style="max-width:1100px;margin:0 auto;padding:1.2rem 1rem 2rem;">

    <div class="card" style="overflow-x:auto;">
        <div class="section-header">
            <span class="section-title">&#x2705; Asistencia de supervisores</span>
            <span style="color:var(--text-muted);font-size:.85rem;"><?= count($registros) ?> registro<?= count($registros) === 1 ? '' : 's' ?></span>
        </div>

        <form method="get" class="filtros">
            <div class="form-group">
                <label for="desde">Desde</label>
                <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>">
            </div>
            <div class="form-group">
                <label for="hasta">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
            </div>
            <div class="form-group">
                <label for="supervisor">Supervisor</label>
                <select id="supervisor" name="supervisor">
                    <option value="0">Todos</option>
                    <?php foreach ($supervisores as $s): ?>
                        <option value="<?= (int)$s['id_empleado'] ?>"<?= (int)$s['id_empleado'] === $supSel ? ' selected' : '' ?>>
                            <?= htmlspecialchars($s['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </form>

        <?php if (!$registros): ?>
            <div style="padding:2rem;text-align:center;color:var(--text-muted);">
                No hay registros de supervisores en el período seleccionado.
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Hora</th><th>Supervisor</th><th>Tipo</th><th>Objetivo</th><th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($registros as $r):
                    $tipoLower = mb_strtolower($r['tipo']);
                    $pill = $tipoLower === 'entrada' ? 'pill-entrada' : ($tipoLower === 'salida' ? 'pill-salida' : 'pill-otro');
                ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                        <td><?= substr($r['hora'], 0, 5) ?></td>
                        <td><?= htmlspecialchars($r['supervisor']) ?></td>
                        <td><span class="pill <?= $pill ?>"><?= htmlspecialchars($r['tipo']) ?></span></td>
                        <td><?= $r['objetivo'] !== null ? htmlspecialchars($r['objetivo']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td><?= htmlspecialchars($r['observaciones'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
