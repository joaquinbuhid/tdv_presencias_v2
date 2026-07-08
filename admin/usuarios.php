<?php
session_start();
if (empty($_SESSION['es_admin'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/db.php';

$db = getDB();
$empresas = $db->query("SELECT id_empresa, nombre FROM empresas ORDER BY nombre")->fetchAll();
$objetivos = $db->query("SELECT id_objetivo, nombre FROM objetivos ORDER BY nombre")->fetchAll();
$adminNombre = $_SESSION['nombre_completo'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Alta de usuarios</title>
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
        .admin-nav .brand { color:#fff; font-weight:700; font-size:1.1rem; display:flex; align-items:center; gap:.5rem; }
        .admin-nav .nav-links { display:flex; gap:.3rem; flex-wrap:wrap; }
        .admin-nav .nav-links a {
            color: rgba(255,255,255,.75);
            text-decoration: none;
            padding: .4rem .9rem;
            border-radius: 6px;
            font-size: .88rem;
        }
        .admin-nav .nav-links a.active,
        .admin-nav .nav-links a:hover { background: rgba(255,255,255,.15); color:#fff; }
        .admin-nav .nav-user { color: rgba(255,255,255,.7); font-size: .82rem; text-align:right; }
        .admin-nav .nav-user strong { display:block; color:#fff; }
        .form-shell { max-width: 980px; margin: 0 auto; padding: 1.2rem 1rem 2rem; }
        .form-panel { background: var(--card); border-radius: 10px; box-shadow: var(--shadow); padding: 1.2rem; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 1rem; }
        .form-grid.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .section-title { font-size: 1rem; color: var(--primary); margin: .4rem 0 1rem; font-weight: 700; }
        .check-row { display:flex; align-items:center; gap:.7rem; min-height:45px; }
        .actions { display:flex; justify-content:flex-end; gap:.8rem; margin-top:1rem; }
        @media (max-width: 760px) {
            .form-grid,
            .form-grid.three { grid-template-columns: 1fr; }
            .actions { flex-direction: column; }
        }
    </style>
</head>
<body>

<nav class="admin-nav">
    <div class="brand">&#x1F6E1; TDV Seguridad</div>
    <div class="nav-links">
        <a href="dashboard.php">En vivo</a>
        <a href="usuarios.php" class="active">Usuarios</a>
        <a href="vigiladores.php">Vigiladores</a>
        <a href="supervisores.php">Supervisores</a>
        <a href="objetivos.php">Objetivos</a>
        <a href="reportes.php">Reportes</a>
        <a href="migracion.php">Migracion</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<main class="form-shell">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
        <h1 style="font-size:1.25rem;color:var(--primary);margin:0;">Alta de usuario</h1>
        <a href="dashboard.php" class="btn btn-outline" style="width:auto;">Volver</a>
    </div>

    <div class="alert alert-danger" id="msgError" role="alert"><span>&#9888;</span><span id="msgErrorText"></span></div>
    <div class="alert alert-success" id="msgOk" role="alert"><span>&#9989;</span><span id="msgOkText"></span></div>

    <form class="form-panel" id="formUsuario" novalidate>
        <div class="section-title">Datos personales</div>
        <div class="form-grid">
            <div class="form-group">
                <label for="nombre">Nombre completo <span style="color:var(--danger)">*</span></label>
                <input type="text" id="nombre" required placeholder="Juan Perez">
            </div>
            <div class="form-group">
                <label for="cuil">CUIL <span style="color:var(--danger)">*</span></label>
                <input type="text" id="cuil" required maxlength="20" placeholder="20-30111222-3">
            </div>
            <div class="form-group">
                <label for="dni">DNI <span style="color:var(--danger)">*</span></label>
                <input type="text" id="dni" required maxlength="20" placeholder="30111222">
            </div>
            <div class="form-group">
                <label for="fecha_nac">Fecha de nacimiento <span style="color:var(--danger)">*</span></label>
                <input type="date" id="fecha_nac" required>
            </div>
            <div class="form-group">
                <label for="est_civil">Estado civil <span style="color:var(--danger)">*</span></label>
                <select id="est_civil" required>
                    <option value="">Seleccione</option>
                    <option>Soltero/a</option>
                    <option>Casado/a</option>
                    <option>Divorciado/a</option>
                    <option>Viudo/a</option>
                    <option>Union convivencial</option>
                    <option>No informado</option>
                </select>
            </div>
            <div class="form-group">
                <label for="telefono">Telefono <span style="color:var(--danger)">*</span></label>
                <input type="text" id="telefono" required placeholder="1144455566">
            </div>
            <div class="form-group">
                <label for="nacionalidad">Nacionalidad</label>
                <input type="text" id="nacionalidad" placeholder="Argentina">
            </div>
        </div>

        <div class="form-group">
            <label for="domicilio">Domicilio <span style="color:var(--danger)">*</span></label>
            <textarea id="domicilio" required rows="2" placeholder="Calle, numero, localidad"></textarea>
        </div>

        <div class="section-title">Acceso y rol</div>
        <div class="form-grid">
            <div class="form-group">
                <label for="email">Email <span style="color:var(--danger)">*</span></label>
                <input type="email" id="email" required placeholder="usuario@ejemplo.com" autocomplete="username">
            </div>
            <div class="form-group">
                <label for="contrasena">Contrasena <span style="color:var(--danger)">*</span></label>
                <input type="password" id="contrasena" required minlength="6" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="tipo">Tipo de usuario</label>
                <select id="tipo">
                    <option value="1">Empleado operativo</option>
                    <option value="2">Supervisor</option>
                    <option value="9">Administrador</option>
                </select>
            </div>
            <div class="form-group">
                <label>Estado</label>
                <div class="check-row">
                    <label style="display:flex;align-items:center;gap:.45rem;margin:0;">
                        <input type="checkbox" id="activo" checked> Activo
                    </label>
                    <label style="display:flex;align-items:center;gap:.45rem;margin:0;">
                        <input type="checkbox" id="pendiente"> Pendiente
                    </label>
                </div>
            </div>
        </div>

        <div class="section-title">Asignacion laboral</div>
        <div class="form-grid three">
            <div class="form-group">
                <label for="empresa_id">Empresa</label>
                <select id="empresa_id">
                    <option value="">Sin empresa</option>
                    <?php foreach ($empresas as $empresa): ?>
                        <option value="<?= (int)$empresa['id_empresa'] ?>"><?= htmlspecialchars($empresa['nombre'] ?? 'Empresa sin nombre') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="objetivo_id">Objetivo</label>
                <select id="objetivo_id">
                    <option value="">Sin objetivo</option>
                    <?php foreach ($objetivos as $objetivo): ?>
                        <option value="<?= (int)$objetivo['id_objetivo'] ?>"><?= htmlspecialchars($objetivo['nombre'] ?? 'Objetivo sin nombre') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="nro_legajo">Nro. legajo</label>
                <input type="text" id="nro_legajo" maxlength="20">
            </div>
            <div class="form-group">
                <label for="nro_credencial">Nro. credencial</label>
                <input type="text" id="nro_credencial" maxlength="20">
            </div>
            <div class="form-group">
                <label for="fecha_venc_cred">Vencimiento credencial</label>
                <input type="date" id="fecha_venc_cred">
            </div>
            <div class="form-group">
                <label for="url_leg">URL legajo</label>
                <input type="url" id="url_leg" placeholder="https://...">
            </div>
            <div class="form-group">
                <label for="hora_entrada">Hora entrada</label>
                <input type="time" id="hora_entrada">
            </div>
            <div class="form-group">
                <label for="hora_salida">Hora salida</label>
                <input type="time" id="hora_salida">
            </div>
        </div>

        <div class="actions">
            <button type="reset" class="btn btn-outline" style="width:auto;">Limpiar</button>
            <button type="submit" class="btn btn-primary" id="btnGuardar" style="width:auto;min-width:150px;">Guardar usuario</button>
        </div>
    </form>
</main>

<script>
const form = document.getElementById('formUsuario');
const err = document.getElementById('msgError');
const ok = document.getElementById('msgOk');

function field(id) {
    return document.getElementById(id).value.trim();
}

function showError(msg) {
    document.getElementById('msgErrorText').textContent = msg;
    err.classList.add('show');
    ok.classList.remove('show');
    err.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function showOk(msg) {
    document.getElementById('msgOkText').textContent = msg;
    ok.classList.add('show');
    err.classList.remove('show');
    ok.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    err.classList.remove('show');
    ok.classList.remove('show');

    const payload = {
        nombre: field('nombre'),
        fecha_nac: field('fecha_nac'),
        est_civil: field('est_civil'),
        empresa_id: field('empresa_id'),
        domicilio: field('domicilio'),
        cuil: field('cuil'),
        dni: field('dni'),
        telefono: field('telefono'),
        nro_legajo: field('nro_legajo'),
        nro_credencial: field('nro_credencial'),
        fecha_venc_cred: field('fecha_venc_cred'),
        activo: document.getElementById('activo').checked,
        objetivo_id: field('objetivo_id'),
        hora_entrada: field('hora_entrada'),
        hora_salida: field('hora_salida'),
        pendiente: document.getElementById('pendiente').checked,
        email: field('email'),
        contrasena: document.getElementById('contrasena').value,
        tipo: field('tipo'),
        url_leg: field('url_leg'),
        nacionalidad: field('nacionalidad'),
    };

    if (!payload.nombre || !payload.fecha_nac || !payload.est_civil || !payload.domicilio || !payload.cuil || !payload.dni || !payload.telefono || !payload.email || !payload.contrasena) {
        showError('Complete todos los campos obligatorios.');
        return;
    }

    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
        const res = await fetch('api/insertar_usuario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.error || 'No se pudo guardar el usuario.');
        }
        form.reset();
        document.getElementById('activo').checked = true;
        showOk(`${data.mensaje}. ID: ${data.id}`);
    } catch (error) {
        showError(error.message);
    }

    btn.disabled = false;
    btn.textContent = 'Guardar usuario';
});
</script>
</body>
</html>


