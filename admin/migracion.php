<?php
session_start();
if (empty($_SESSION['es_admin'])) {
    header('Location: ../index.php');
    exit;
}
$adminNombre = $_SESSION['nombre_completo'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Migracion</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-nav { background: var(--primary-dk); display:flex; align-items:center; justify-content:space-between; padding:.7rem 1.5rem; flex-wrap:wrap; gap:.5rem; }
        .admin-nav .brand { color:#fff; font-weight:700; font-size:1.1rem; display:flex; align-items:center; gap:.5rem; }
        .admin-nav .nav-links { display:flex; gap:.3rem; flex-wrap:wrap; }
        .admin-nav .nav-links a { color:rgba(255,255,255,.75); text-decoration:none; padding:.4rem .9rem; border-radius:6px; font-size:.88rem; }
        .admin-nav .nav-links a.active, .admin-nav .nav-links a:hover { background:rgba(255,255,255,.15); color:#fff; }
        .admin-nav .nav-user { color:rgba(255,255,255,.7); font-size:.82rem; text-align:right; }
        .admin-nav .nav-user strong { display:block; color:#fff; }
        .wrap { max-width:920px; margin:0 auto; padding:1.2rem 1rem 2rem; }
        .panel { background:var(--card); border-radius:10px; box-shadow:var(--shadow); padding:1.2rem; }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 1rem; }
        .result { margin-top:1rem; background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:1rem; font-size:.95rem; }
        .actions { display:flex; justify-content:flex-end; gap:.8rem; margin-top:1rem; flex-wrap:wrap; }
        @media (max-width:720px) { .grid { grid-template-columns:1fr; } .actions { flex-direction:column; } }
    </style>
</head>
<body>
<nav class="admin-nav">
    <div class="brand">&#x1F6E1; TDV Seguridad</div>
    <div class="nav-links">
        <a href="dashboard.php">En vivo</a>
        <a href="usuarios.php">Usuarios</a>
        <a href="postulantes.php">Postulantes</a>
        <a href="vigiladores.php">Empleados</a>
        <a href="supervisores.php">Supervisores</a>
        <a href="objetivos.php">Objetivos</a>
        <a href="informe_horas.php">Horas</a>
        <a href="migracion.php" class="active">Migracion</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<main class="wrap">
    <h1 style="font-size:1.25rem;color:var(--primary);margin:0 0 1rem;">Migrar base anterior</h1>

    <div class="alert alert-danger" id="msgError" role="alert"><span>&#9888;</span><span id="msgErrorText"></span></div>
    <div class="alert alert-success" id="msgOk" role="alert"><span>&#9989;</span><span id="msgOkText"></span></div>

    <form class="panel" id="formMigracion" novalidate>
        <div class="grid">
            <div class="form-group">
                <label for="host">Host base anterior</label>
                <input type="text" id="host" placeholder="Host de la base anterior" required>
            </div>
            <div class="form-group">
                <label for="name">Nombre base anterior</label>
                <input type="text" id="name" placeholder="Nombre de la base anterior" required>
            </div>
            <div class="form-group">
                <label for="user">Usuario base anterior</label>
                <input type="text" id="user" placeholder="Usuario de la base anterior" required>
            </div>
            <div class="form-group">
                <label for="pass">Contrasena base anterior</label>
                <input type="password" id="pass" autocomplete="off">
            </div>
        </div>

        <p style="color:var(--text-muted);font-size:.9rem;line-height:1.45;">
            La migracion no borra datos de la base nueva. Copia objetivos, tipos de novedad,
            supervisores, empleados y novedades. Si se ejecuta mas de una vez intenta evitar duplicados.
        </p>

        <div class="actions">
            <button type="button" class="btn btn-outline" id="btnPreview">Vista previa</button>
            <button type="submit" class="btn btn-primary" id="btnRun" style="width:auto;min-width:150px;">Migrar datos</button>
        </div>
    </form>

    <div class="result" id="result" style="display:none;"></div>
</main>

<script>
const form = document.getElementById('formMigracion');
const result = document.getElementById('result');

function payload(mode) {
    return {
        mode,
        host: document.getElementById('host').value.trim(),
        name: document.getElementById('name').value.trim(),
        user: document.getElementById('user').value.trim(),
        pass: document.getElementById('pass').value,
    };
}

function showError(msg) {
    document.getElementById('msgErrorText').textContent = msg;
    document.getElementById('msgError').classList.add('show');
    document.getElementById('msgOk').classList.remove('show');
}

function showOk(msg) {
    document.getElementById('msgOkText').textContent = msg;
    document.getElementById('msgOk').classList.add('show');
    document.getElementById('msgError').classList.remove('show');
}

function render(data) {
    const rows = Object.entries(data.migrated || data.counts || {})
        .map(([k, v]) => `<tr><td>${k}</td><td style="text-align:right;font-weight:700;">${v}</td></tr>`)
        .join('');
    result.innerHTML = `<table style="width:100%;border-collapse:collapse;">${rows}</table>`;
    result.style.display = 'block';
}

async function callMigration(mode) {
    const res = await fetch('api/migrar_base_anterior.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload(mode)),
    });
    const raw = await res.text();
    let data = {};
    try {
        data = raw ? JSON.parse(raw) : {};
    } catch (e) {
        throw new Error('La API devolvio una respuesta invalida.');
    }
    if (!res.ok || !data.success) {
        throw new Error(data.error || 'Error de migracion');
    }
    return data;
}

document.getElementById('btnPreview').addEventListener('click', async () => {
    try {
        const data = await callMigration('preview');
        render(data);
        showOk('Vista previa generada.');
    } catch (e) {
        showError(e.message);
    }
});

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!confirm('Confirmar migracion desde la base anterior hacia la base nueva.')) return;
    const btn = document.getElementById('btnRun');
    btn.disabled = true;
    btn.textContent = 'Migrando...';
    try {
        const data = await callMigration('run');
        render(data);
        showOk('Migracion terminada.');
    } catch (e) {
        showError(e.message);
    }
    btn.disabled = false;
    btn.textContent = 'Migrar datos';
});
</script>
</body>
</html>
