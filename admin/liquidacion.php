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
$objetivos = [];
$selected_objetivo_id = 0;

try {
    $db = getDB();
    // Load all objectives in the database
    $stmtObj = $db->query("SELECT id_objetivo, nombre FROM objetivos ORDER BY nombre");
    $objetivos = $stmtObj->fetchAll();
} catch (Exception $e) {
    $error = 'Error al cargar los objetivos: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_objetivo_id = isset($_POST['objetivo_id']) ? (int)$_POST['objetivo_id'] : 0;
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';

    if (empty($fecha_inicio) || empty($fecha_fin)) {
        $error = 'Por favor, complete ambas fechas.';
    } elseif ($fecha_inicio > $fecha_fin) {
        $error = 'La fecha de inicio debe ser menor o igual que la fecha de fin.';
    } else {
        try {
            $where_obj = '';
            $params = [$fecha_inicio, $fecha_fin];
            $objNombre = 'Todos los objetivos';
            
            if ($selected_objetivo_id > 0) {
                $where_obj = "AND o.id_objetivo = ?";
                $params[] = $selected_objetivo_id;
                
                foreach ($objetivos as $obj) {
                    if ((int)$obj['id_objetivo'] === $selected_objetivo_id) {
                        $objNombre = $obj['nombre'];
                        break;
                    }
                }
            }

            $stmt = $db->prepare("
                SELECT n.fecha, n.hora, n.tipo_novedad AS tipo, n.observaciones, n.empleado_id AS vigilador_id, e.nombre AS nombre, '' AS apellido
                FROM novedades n
                JOIN empleados e ON n.empleado_id = e.id_empleado
                JOIN objetivos o ON e.objetivo_id = o.id_objetivo
                WHERE n.fecha BETWEEN ? AND ? $where_obj
                ORDER BY n.fecha ASC, n.hora ASC
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            if (empty($rows)) {
                $error = 'No se encontraron novedades registradas en el rango de fechas.';
            } else {
                $result_data = calcularLiquidacion($rows);
                $_SESSION['last_liquidacion'] = $result_data;
                $_SESSION['liquidacion_title'] = "Objetivo: " . $objNombre . " (" . date('d/m/Y', strtotime($fecha_inicio)) . " al " . date('d/m/Y', strtotime($fecha_fin)) . ")";
                $title = $_SESSION['liquidacion_title'];
                $mostrar_resultados = true;
            }
        } catch (Exception $e) {
            $error = 'Error al consultar la base de datos: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV — Computo de Horas por Objetivo</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
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
            grid-template-columns: 1fr;
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
            .summary-stats { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<nav class="admin-nav">
    <div class="brand">&#x1F6E1; TDV Seguridad</div>
    <div class="nav-links">
        <?php if ($esAdminReal): ?>
        <a href="dashboard.php" class="active">&#x1F7E2; En vivo</a>
        <a href="usuarios.php">&#x2795; Usuarios</a>
        <a href="postulantes.php">Postulantes</a>
        <a href="vigiladores.php">&#x1F464; Empleados</a>
        <a href="legajos.php">&#x1F4C1; Legajos</a>
        <a href="supervisores.php">&#x1F4BC; Supervisores</a>
        <a href="objetivos.php">&#x1F3AF; Objetivos</a>
        <a href="reportes.php" style="position:relative;">&#x26A0; Reportes<span id="navBadgeRep" class="nav-badge" style="display:none;">!</span></a>
        <a href="liquidacion.php">Horas</a>
        <a href="enviar_mails.php">Mails</a>        <?php endif; ?>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<div style="max-width:1000px;margin:0 auto;padding:1.5rem 1rem 3rem;">

    <div class="section-title">📊 Informe y Cómputo de Horas por Objetivo</div>
    
    <?php if (!empty($error)): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="liq-grid">
        <div class="liq-card">
            <div class="card-header-title">🔍 Consulta de Asistencias</div>
            <form action="liquidacion.php" method="POST">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="objetivo_id">Seleccione el Objetivo</label>
                    <select id="objetivo_id" name="objetivo_id" required style="width: 100%;">
                        <option value="0">— Todos los objetivos —</option>
                        <?php foreach ($objetivos as $obj): ?>
                            <option value="<?= $obj['id_objetivo'] ?>" <?= $selected_objetivo_id === (int)$obj['id_objetivo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($obj['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
                <button type="submit" class="btn btn-primary" style="margin-top:1.2rem;width:100%">Generar Informe</button>
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
            <div class="card-header-title">🖥️ Vista Previa: <i><?= htmlspecialchars($title) ?></i></div>
            
            <div class="summary-stats">
                <div class="stat-box">
                    <div class="num"><?= $total_vigiladores ?></div>
                    <div class="lbl">Empleados</div>
                </div>
                <div class="stat-box">
                    <div class="num"><?= $total_shifts ?></div>
                    <div class="lbl">Turnos Cerrados</div>
                </div>
                <div class="stat-box">
                    <div class="num"><?= formatDecimalHours($total_hours) ?> hs</div>
                    <div class="lbl">Total Horas (<?= number_format($total_hours, 1, ',', '') ?> hs)</div>
                </div>
                <div class="stat-box anomaly-box">
                    <div class="num"><?= $total_anomalies ?></div>
                    <div class="lbl">Anomalías Detectadas</div>
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
                                    <span style="color:var(--danger);font-weight:700">⚠️ <?= $v_anomalies ?> anomalía<?= $v_anomalies > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="accordion-hours">
                                <?= formatDecimalHours($total_v_hours) ?> hs
                            </div>
                        </div>
                        <div class="accordion-content" id="content-<?= $v['vid'] ?>">
                            <div class="card-header-title" style="font-size:0.85rem;margin-bottom:0.4rem">Detalle de Turnos</div>
                            <?php if (empty($v['shifts'])): ?>
                                <p style="font-size:0.8rem;color:var(--text-muted);margin:0.5rem 0">No se registraron turnos válidos.</p>
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
                                                        <span class="badge-nextday">+1 día</span>
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
                                    <h4>Inconsistencias Detectadas (Marcas Huérfanas)</h4>
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
