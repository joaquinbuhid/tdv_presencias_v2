<?php
session_start();
if (empty($_SESSION['es_admin'])) {
    header('Location: ../index.php');
    exit;
}
$adminNombre = $_SESSION['nombre_completo'] ?? 'Administrador';
$hoy = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Informe de horas</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-nav {
            background: var(--primary-dk);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .7rem 1.5rem;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .admin-nav .brand { color:#fff;font-weight:700;font-size:1.1rem;display:flex;align-items:center;gap:.5rem; }
        .admin-nav .nav-links { display:flex;gap:.3rem;flex-wrap:wrap; }
        .admin-nav .nav-links a {
            color:rgba(255,255,255,.75);text-decoration:none;
            padding:.4rem .9rem;border-radius:6px;font-size:.88rem;transition:background .2s;
        }
        .admin-nav .nav-links a.active,
        .admin-nav .nav-links a:hover { background:rgba(255,255,255,.15);color:#fff; }
        .admin-nav .nav-user { color:rgba(255,255,255,.7);font-size:.82rem;text-align:right; }
        .admin-nav .nav-user strong { display:block;color:#fff; }
        .form-panel {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            max-width: 720px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .page-title { font-size:1.25rem;color:var(--primary);margin:0 0 1rem; }
        .hint { color:var(--text-muted);font-size:.88rem;margin:.2rem 0 1.2rem;line-height:1.45; }
        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .admin-nav { padding:.6rem 1rem; }
        }
    </style>
</head>
<body>
<nav class="admin-nav">
    <div class="brand">&#x1F6E1; TDV Seguridad</div>
    <div class="nav-links">
        <a href="dashboard.php">&#x1F7E2; En vivo</a>
        <a href="usuarios.php">&#x2795; Usuarios</a>
        <a href="postulantes.php">Postulantes</a>
        <a href="vigiladores.php">&#x1F464; Empleados</a>
        <a href="supervisores.php">&#x1F4BC; Supervisores</a>
        <a href="objetivos.php">&#x1F3AF; Objetivos</a>
        <a href="reportes.php">&#x26A0; Reportes</a>
        <a href="informe_horas.php" class="active">Horas</a>
        <a href="migracion.php">Migracion</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<main style="max-width:1200px;margin:0 auto;padding:1.2rem 1rem 2rem;">
    <h1 class="page-title">Informe de horas</h1>
    <form class="form-panel" id="formInforme" action="api/generar_informe_horas.php" method="get" target="_blank">
        <p class="hint">
            Seleccione un rango de fechas. El informe agrupa por dia y vigilador, usando la primera entrada y la ultima salida registradas.
        </p>
        <div class="alert alert-danger" id="msgError" role="alert">
            <span>&#9888;</span><span id="msgErrorText"></span>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="desde">Fecha desde <span style="color:var(--danger)">*</span></label>
                <input type="date" id="desde" name="desde" required max="<?= htmlspecialchars($hoy) ?>">
            </div>
            <div class="form-group">
                <label for="hasta">Fecha hasta <span style="color:var(--danger)">*</span></label>
                <input type="date" id="hasta" name="hasta" required max="<?= htmlspecialchars($hoy) ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:auto;min-width:180px;">Generar PDF</button>
    </form>
</main>

<script>
const form = document.getElementById('formInforme');
const err = document.getElementById('msgError');
const errText = document.getElementById('msgErrorText');

form.addEventListener('submit', (e) => {
    err.classList.remove('show');
    const desde = document.getElementById('desde').value;
    const hasta = document.getElementById('hasta').value;

    if (!desde || !hasta) {
        e.preventDefault();
        errText.textContent = 'Seleccione las dos fechas.';
        err.classList.add('show');
        return;
    }

    if (hasta < desde) {
        e.preventDefault();
        errText.textContent = 'La fecha hasta debe ser igual o mayor que la fecha desde.';
        err.classList.add('show');
    }
});
</script>
</body>
</html>
