<?php
session_start();
if (empty($_SESSION['supervisor_id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';

$supId     = (int)$_SESSION['supervisor_id'];
$supNombre = $_SESSION['nombre_completo'] ?? 'Supervisor';

$db   = getDB();
$stmt = $db->prepare("SELECT objetivo_id FROM empleados WHERE id_empleado = ?");
$stmt->execute([$supId]);
$objAsignado = (int)($stmt->fetchColumn() ?: 0);

$stmt = $db->prepare(
    "SELECT id_objetivo, nombre FROM objetivos
     WHERE supervisor_id = ? OR id_objetivo = ?
     ORDER BY nombre"
);
$stmt->execute([$supId, $objAsignado]);
$objetivos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Asistencia</title>
    <link rel="stylesheet" href="../css/style.css?v=2">
    <link rel="stylesheet" href="../css/admin.css?v=20261020">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <style>
        .sup-nav .brand { color:#fff; font-weight:700; font-size:1.1rem; display:flex; align-items:center; gap:.5rem; }
        .sup-nav .nav-user { color:rgba(255,255,255,.7); font-size:.82rem; text-align:right; }
        .sup-nav .nav-user strong { display:block; color:#fff; }
    </style>
</head>
<body>

<nav class="admin-nav sup-nav">
    <div class="brand">&#x1F6E1; TDV Seguridad</div>
    <div class="nav-links">
        <a href="dashboard.php">🟢 En vivo</a>
        <a href="informe.php">📊 Informe</a>
        <a href="asistencia.php" class="active">&#x2705; Asistencia</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($supNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Cerrar sesion</a>
    </div>
</nav>

<main class="app-content">

    <div style="text-align:center;padding:1rem 0 .3rem;">
        <div id="relojHora" style="font-size:3.4rem;font-weight:700;letter-spacing:.05em;color:var(--primary);font-variant-numeric:tabular-nums;line-height:1;">--:--:--</div>
        <div id="relojFecha" style="font-size:1rem;color:var(--text-muted);margin-top:.35rem;"></div>
    </div>

    <div class="card" id="cardEstado">
        <div class="card-title">&#x1F4CB; Registros de hoy</div>
        <div id="estadoContent">
            <div class="loc-status"><div class="spinner spinner-dark"></div> Cargando registros...</div>
        </div>
    </div>

    <div class="card">
        <div class="card-title">&#x270F; Registrar novedad</div>

        <div class="alert alert-danger"  id="regError"   role="alert"><span>&#9888;</span><span id="regErrorMsg"></span></div>
        <div class="alert alert-success" id="regSuccess" role="alert"><span>&#9989;</span><span id="regSuccessMsg"></span></div>

        <?php if (!$objetivos): ?>
            <div class="alert alert-warning show" role="alert">
                <span>&#9888;</span><span>No tiene objetivos a cargo ni asignado. Contacte al administrador.</span>
            </div>
        <?php endif; ?>

        <form id="formNovedad" novalidate>
            <div class="form-group">
                <label for="objetivoNovedad">Objetivo</label>
                <select id="objetivoNovedad" required>
                    <?php foreach ($objetivos as $o): ?>
                        <option value="<?= (int)$o['id_objetivo'] ?>"<?= (int)$o['id_objetivo'] === $objAsignado ? ' selected' : '' ?>>
                            <?= htmlspecialchars($o['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="tipoNovedad">Tipo de novedad</label>
                <select id="tipoNovedad" required>
                    <option value="">— Seleccione —</option>
                </select>
            </div>

            <div class="form-group">
                <label for="observaciones">Observaciones <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                <textarea id="observaciones" placeholder="Ingrese observaciones..."></textarea>
            </div>

            <div class="loc-status" id="locStatus">
                <span>&#x1F4F1;</span>
                <span id="locMsg">La ubicación se obtendrá al confirmar.</span>
            </div>
            <div class="progress-wrap" id="progressWrap" style="display:none;">
                <div class="progress-bar" id="progressBar"></div>
            </div>

            <button type="submit" class="btn btn-success" id="btnRegistrar" style="margin-top:1rem;"<?= $objetivos ? '' : ' disabled' ?>>
                <span id="btnIcon">&#x2705;</span>
                <span id="btnText">Confirmar asistencia</span>
            </button>
        </form>
    </div>

</main>

<footer class="app-footer">TDV Seguridad &mdash; <?= date('d/m/Y') ?></footer>

<script>window.TDV_API_BASE = '../';</script>
<script src="../js/app.js?v=5"></script>
<script>
(function reloj() {
    const DIAS  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    const MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    function tick() {
        const n = new Date();
        document.getElementById('relojHora').textContent =
            `${String(n.getHours()).padStart(2,'0')}:${String(n.getMinutes()).padStart(2,'0')}:${String(n.getSeconds()).padStart(2,'0')}`;
        document.getElementById('relojFecha').textContent =
            `${DIAS[n.getDay()]} ${n.getDate()} de ${MESES[n.getMonth()]} de ${n.getFullYear()}`;
    }
    tick(); setInterval(tick, 1000);
})();
</script>
</body>
</html>
