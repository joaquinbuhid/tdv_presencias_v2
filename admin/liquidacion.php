<?php
session_start();
if (empty($_SESSION['es_admin'])) {
    header('Location: ../index.php');
    exit;
}
$adminNombre = $_SESSION['nombre_completo'] ?? 'Administrador';
require_once __DIR__ . '/auth.php';
$esAdminReal = esAdminReal();

require_once __DIR__ . '/api/liquidacion_helper.php';
require_once __DIR__ . '/../config/db.php';

$mostrar_resultados = false;
$error = '';
$result_data = [];
$title = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['csv_file']['tmp_name'];
            $file_name = $_FILES['csv_file']['name'];
            
            $rows = [];
            if (($handle = fopen($tmp_name, "r")) !== FALSE) {
                $headers = fgetcsv($handle, 1000, ",");
                if (count($headers) <= 1 && strpos($headers[0] ?? '', ';') !== false) {
                    rewind($handle);
                    $headers = fgetcsv($handle, 1000, ";");
                    $delimiter = ";";
                } else {
                    $delimiter = ",";
                }
                
                foreach ($headers as $k => $v) {
                    $v = str_replace("\xEF\xBB\xBF", '', $v);
                    $headers[$k] = trim(strtolower(str_replace('"', '', $v)));
                }
                
                while (($data_row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                    if (count($data_row) < 3) continue;
                    $row_assoc = [];
                    foreach ($headers as $idx => $col_name) {
                        if (isset($data_row[$idx])) {
                            $row_assoc[$col_name] = trim(str_replace('"', '', $data_row[$idx]));
                        }
                    }
                    
                    $rows[] = [
                        'fecha' => $row_assoc['fecha'] ?? '',
                        'hora' => $row_assoc['hora'] ?? '',
                        'tipo' => $row_assoc['tipo'] ?? '',
                        'vigilador_id' => $row_assoc['vigilador_id'] ?? '',
                        'nombre' => $row_assoc['nombre'] ?? '',
                        'apellido' => $row_assoc['apellido'] ?? '',
                        'observaciones' => $row_assoc['observaciones'] ?? '',
                    ];
                }
                fclose($handle);
            }
            
            if (empty($rows)) {
                $error = 'El archivo CSV esta vacio o tiene un formato incorrecto.';
            } else {
                try {
                    $db = getDB();
                    $stmt = $db->query("SELECT id_empleado, nombre, '' AS apellido FROM empleados");
                    $db_vigiladores = [];
                    while ($r = $stmt->fetch()) {
                        $db_vigiladores[(string)$r['id_empleado']] = $r;
                    }
                    
                    foreach ($rows as $k => $row) {
                        $vid = (string)$row['vigilador_id'];
                        if (empty($row['nombre']) && isset($db_vigiladores[$vid])) {
                            $rows[$k]['nombre'] = $db_vigiladores[$vid]['nombre'];
                            $rows[$k]['apellido'] = $db_vigiladores[$vid]['apellido'];
                        }
                    }
                    
                    $result_data = calcularLiquidacion($rows);
                    $_SESSION['last_liquidacion'] = $result_data;
                    $_SESSION['liquidacion_title'] = "Archivo CSV: " . $file_name;
                    $title = $_SESSION['liquidacion_title'];
                    $mostrar_resultados = true;
                } catch (Exception $e) {
                    $error = 'Error de base de datos al mapear empleados: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Por favor, seleccione un archivo CSV valido.';
        }
    } else if ($action === 'db') {
        $fecha_inicio = $_POST['fecha_inicio'] ?? '';
        $fecha_fin = $_POST['fecha_fin'] ?? '';
        
        if (empty($fecha_inicio) || empty($fecha_fin)) {
            $error = 'Por favor, complete ambas fechas.';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    SELECT n.fecha, n.hora, n.tipo_novedad AS tipo, n.observaciones, n.empleado_id AS vigilador_id, e.nombre, '' AS apellido
                    FROM novedades n
                    JOIN empleados e ON n.empleado_id = e.id_empleado
                    WHERE n.fecha BETWEEN ? AND ?
                    ORDER BY n.fecha ASC, n.hora ASC
                ");
                $stmt->execute([$fecha_inicio, $fecha_fin]);
                $rows = $stmt->fetchAll();
                
                if (empty($rows)) {
                    $error = 'No se encontraron novedades registradas en el rango de fechas seleccionado.';
                } else {
                    $result_data = calcularLiquidacion($rows);
                    $_SESSION['last_liquidacion'] = $result_data;
                    $_SESSION['liquidacion_title'] = "Base de Datos (" . date('d/m/Y', strtotime($fecha_inicio)) . " al " . date('d/m/Y', strtotime($fecha_fin)) . ")";
                    $title = $_SESSION['liquidacion_title'];
                    $mostrar_resultados = true;
                }
            } catch (Exception $e) {
                $error = 'Error al consultar la base de datos: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV — Computo de Horas</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-nav {
            background: var(--primary-dk);
            display: flex; align-items: center; justify-content: space-between;
            padding: .7rem 1.5rem; flex-wrap: wrap; gap: .5rem;
        }
        .admin-nav .brand { color:#fff;font-weight:700;font-size:1.1rem;display:flex;align-items:center;gap:.5rem; }
        .admin-nav .nav-links { display:flex;gap:.3rem;flex-wrap:wrap; }
        .admin-nav .nav-links a {
            color:rgba(255,255,255,.75);text-decoration:none;
            padding:.4rem .9rem;border-radius:6px;font-size:.88rem;transition:background .2s;
            position:relative;
        }
        .admin-nav .nav-links a.active,
        .admin-nav .nav-links a:hover { background:rgba(255,255,255,.15);color:#fff; }
        .admin-nav .nav-user { color:rgba(255,255,255,.7);font-size:.82rem;text-align:right; }
        .admin-nav .nav-user strong { display:block;color:#fff; }

        .section-title { font-size:1.2rem;font-weight:700;color:var(--primary);margin-bottom:1.2rem;display:flex;align-items:center;gap:.5rem; }
        
        .liq-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }
        
        .liq-card {
            background: var(--card);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            border-top: 4px solid var(--primary);
        }
        .liq-card.csv-card { border-top-color: var(--accent); }
        .liq-card.db-card { border-top-color: var(--primary); }
        
        .card-header-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: var(--text); }
        
        .preview-container {
            background: var(--card);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-box {
            background: var(--bg);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            border: 1px solid var(--border);
        }
        .stat-box .num { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .stat-box .lbl { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }
        .stat-box.anomaly-box .num { color: var(--danger); }
        
        .export-actions {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
            justify-content: flex-end;
        }
        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            cursor: pointer;
            transition: opacity 0.2s;
            border: none;
        }
        .btn-export:hover { opacity: 0.9; }
        .btn-pdf { background: #e74c3c; color: white; }
        .btn-excel { background: #27ae60; color: white; }
        
        .vigilador-accordion {
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 0.8rem;
            overflow: hidden;
        }
        
        .accordion-header {
            background: var(--bg);
            padding: 1rem 1.2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }
        .accordion-header:hover { background: var(--border); }
        .accordion-title { font-weight: 700; color: var(--text); }
        .accordion-title span { font-weight: 400; color: var(--text-muted); font-size: 0.85rem; margin-left: 0.5rem; }
        .accordion-hours { font-weight: 700; color: var(--accent); }
        
        .accordion-content {
            display: none;
            padding: 1.2rem;
            border-top: 1px solid var(--border);
            background: white;
        }
        .accordion-content.open { display: block; }
        
        .table-liq {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
        .table-liq th { background: var(--primary); color: white; padding: 0.5rem; text-align: left; }
        .table-liq td { padding: 0.5rem; border-bottom: 1px solid var(--border); }
        .table-liq tr:hover { background: var(--bg); }
        
        .badge-nextday {
            background: #f39c12;
            color: white;
            font-size: 0.7rem;
            padding: 0.1rem 0.3rem;
            border-radius: 3px;
            font-weight: 700;
            margin-left: 3px;
        }
        
        .anomalias-list {
            margin-top: 1rem;
            padding: 0.8rem 1rem;
            background: #fdf2f2;
            border-radius: 6px;
            border-left: 4px solid var(--danger);
        }
        .anomalias-list h4 { color: var(--danger); margin-top: 0; font-size: 0.85rem; }
        .anomalias-list ul { margin: 0; padding-left: 1.2rem; font-size: 0.8rem; color: #7f2d2d; }
        
        .alert-error {
            background: #fdf2f2;
            color: #9b2c2c;
            border-left: 4px solid var(--danger);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.2rem;
        }
        
        @media(max-width:768px) {
            .liq-grid { grid-template-columns: 1fr; }
            .summary-stats { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<nav class="admin-nav">
    <div class="brand">&#x1F6E1; TDV Seguridad</div>
    <div class="nav-links">
        <?php if ($esAdminReal): ?>
        <a href="dashboard.php">En vivo</a>
        <a href="usuarios.php">Usuarios</a>
        <?php endif; ?>
        <a href="postulantes.php">Postulantes</a>
        <a href="vigiladores.php">Empleados</a>
        <a href="supervisores.php">Supervisores</a>
        <?php if ($esAdminReal): ?>
        <a href="objetivos.php">Objetivos</a>
        <a href="reportes.php">Reportes</a>
        <?php endif; ?>
        <a href="liquidacion.php" class="active">Horas</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<div style="max-width:1000px;margin:0 auto;padding:1.5rem 1rem 3rem;">

    <div class="section-title">📊 Computo y Liquidacion de Horas</div>
    
    <?php if (!empty($error)): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="liq-grid">
        <div class="liq-card csv-card">
            <div class="card-header-title">📂 Opcion A: Cargar Planilla CSV</div>
            <form action="liquidacion.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="csv">
                <div class="form-group">
                    <label for="csv_file">Seleccione archivo CSV</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required style="width:100%">
                    <small style="color:var(--text-muted);display:block;margin-top:0.3rem">
                        Debe contener las columnas: <code>fecha</code>, <code>hora</code>, <code>tipo</code>, <code>vigilador_id</code>.
                    </small>
                </div>
                <button type="submit" class="btn btn-accent" style="margin-top:1rem;width:100%">Procesar Archivo CSV</button>
            </form>
        </div>

        <div class="liq-card db-card">
            <div class="card-header-title">🗄️ Opcion B: Procesar desde la Base de Datos</div>
            <form action="liquidacion.php" method="POST">
                <input type="hidden" name="action" value="db">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem">
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" required value="<?= $_POST['fecha_inicio'] ?? date('Y-m-01') ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin">Fecha Fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" required value="<?= $_POST['fecha_fin'] ?? date('Y-m-t') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:1rem;width:100%">Calcular desde Base de Datos</button>
            </form>
        </div>
    </div>

    <!-- Resultados -->
    <?php if ($mostrar_resultados): ?>
        <?php
        $total_vigiladores = count($result_data);
        $total_shifts = 0;
        $total_hours = 0.0;
        $total_anomalies = 0;
        foreach ($result_data as $v) {
            $total_shifts += count($v['shifts']);
            $total_hours += array_sum(array_column($v['shifts'], 'hours'));
            $total_anomalies += count($v['anomalies']);
        }
        ?>
        
        <div class="preview-container">
            <div class="card-header-title">🖥️ Vista Previa del Calculo: <i><?= htmlspecialchars($title) ?></i></div>
            
            <div class="summary-stats">
                <div class="stat-box">
                    <div class="num"><?= $total_vigiladores ?></div>
                    <div class="lbl">Empleados</div>
                </div>
                <div class="stat-box">
                    <div class="num"><?= $total_shifts ?></div>
                    <div class="lbl">Turnos Liquidados</div>
                </div>
                <div class="stat-box">
                    <div class="num"><?= formatDecimalHours($total_hours) ?> hs</div>
                    <div class="lbl">Total Horas (<?= number_format($total_hours, 1, ',', '') ?> hs)</div>
                </div>
                <div class="stat-box anomaly-box">
                    <div class="num"><?= $total_anomalies ?></div>
                    <div class="lbl">Anomalias Detectadas</div>
                </div>
            </div>
            
            <div class="export-actions">
                <a href="api/export_liquidacion.php?format=excel" class="btn-export btn-excel" target="_blank">
                    <span>🟢 Descargar EXCEL (CSV)</span>
                </a>
                <a href="api/export_liquidacion.php?format=pdf" class="btn-export btn-pdf" target="_blank">
                    <span>🔴 Descargar PDF</span>
                </a>
            </div>
            
            <div>
                <?php foreach ($result_data as $v): ?>
                    <?php 
                    $total_v_hours = array_sum(array_column($v['shifts'], 'hours'));
                    $v_anomalies = count($v['anomalies']);
                    ?>
                    <div class="vigilador-accordion">
                        <div class="accordion-header" onclick="toggleAccordion('content-<?= $v['vid'] ?>')">
                            <div class="accordion-title">
                                <?= htmlspecialchars($v['name']) ?>
                                <span>(ID: <?= $v['vid'] ?>)</span>
                                <?php if ($v_anomalies > 0): ?>
                                    <span style="color:var(--danger);font-weight:700">⚠️ <?= $v_anomalies ?> anomalia<?= $v_anomalies > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="accordion-hours">
                                <?= formatDecimalHours($total_v_hours) ?> hs
                            </div>
                        </div>
                        <div class="accordion-content" id="content-<?= $v['vid'] ?>">
                            <div class="card-header-title" style="font-size:0.85rem;margin-bottom:0.4rem">Detalle de Turnos</div>
                            <?php if (empty($v['shifts'])): ?>
                                <p style="font-size:0.8rem;color:var(--text-muted);margin:0.5rem 0">No se registraron turnos validos.</p>
                            <?php else: ?>
                                <table class="table-liq">
                                    <thead>
                                        <tr>
                                            <th>Entrada</th>
                                            <th>Salida</th>
                                            <th>Horas</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($v['shifts'] as $s): ?>
                                            <?php 
                                            $e_dt = new DateTime($s['entry']);
                                            $x_dt = new DateTime($s['exit']);
                                            $next_day = $x_dt->format('Y-m-d') !== $e_dt->format('Y-m-d');
                                            ?>
                                            <tr>
                                                <td><?= $e_dt->format('d/m/Y H:i:s') ?></td>
                                                <td>
                                                    <?= $x_dt->format('d/m/Y H:i:s') ?>
                                                    <?php if ($next_day): ?>
                                                        <span class="badge-nextday">+1 dia</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?= formatDecimalHours($s['hours']) ?></strong> (<?= number_format($s['hours'], 2, ',', '') ?> hs)</td>
                                                <td><?= htmlspecialchars($s['obs']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            
                            <?php if (!empty($v['anomalies'])): ?>
                                <div class="anomalias-list">
                                    <h4>Inconsistencias Detectadas (Marcas Huerfanas)</h4>
                                    <ul>
                                        <?php foreach ($v['anomalies'] as $a): ?>
                                            <li>
                                                <strong><?= htmlspecialchars($a['type']) ?></strong>: 
                                                <?= date('d/m/Y H:i:s', strtotime($a['dt'])) ?>
                                                <?= !empty($a['obs']) ? ' (Obs: ' . htmlspecialchars($a['obs']) . ')' : '' ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
        </div>
    <?php endif; ?>

</div>

<script>
function toggleAccordion(id) {
    const el = document.getElementById(id);
    el.classList.toggle('open');
}
</script>
</body>
</html>
