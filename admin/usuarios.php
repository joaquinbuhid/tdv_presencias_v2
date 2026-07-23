<?php
require_once __DIR__ . '/auth.php';
$altaEmpleado = ($_GET['origen'] ?? '') === 'empleados';
if ($altaEmpleado) {
    requireBackofficePage();
} else {
    requireAdminRealPage();
}

require_once '../config/db.php';

$db = getDB();
$empresas = $db->query("SELECT id_empresa, nombre FROM empresas ORDER BY nombre")->fetchAll();
$objetivos = $db->query("SELECT id_objetivo, nombre FROM objetivos ORDER BY nombre")->fetchAll();
$adminNombre = $_SESSION['nombre_completo'] ?? 'Administrador';
$esAdminReal = esAdminReal();
$tipoInicial = $altaEmpleado ? 1 : (isset($_GET['tipo']) ? (int)$_GET['tipo'] : 1);
if (!in_array($tipoInicial, [1, 2, 3, 4], true)) {
    $tipoInicial = 1;
}
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
        .modal-overlay { display:none; position:fixed; inset:0; z-index:1000; background:rgba(0,0,0,.55); align-items:center; justify-content:center; padding:1rem; }
        .modal-overlay.open { display:flex; }
        .modal { width:100%; max-width:680px; max-height:90vh; overflow:auto; background:#fff; border-radius:10px; box-shadow:0 8px 40px rgba(0,0,0,.25); padding:1.4rem; }
        .modal-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
        .modal-title { color:var(--primary); font-size:1.1rem; font-weight:700; }
        .modal-close { border:0; background:transparent; color:var(--text-muted); font-size:1.4rem; cursor:pointer; }
        .postulante-list { margin-top:1rem; border:1px solid var(--border); border-radius:8px; max-height:330px; overflow:auto; }
        .postulante-item { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.8rem; border-bottom:1px solid var(--border); }
        .postulante-item:last-child { border-bottom:0; }
        .postulante-item small { color:var(--text-muted); display:block; margin-top:.2rem; }
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
        <?php if ($esAdminReal): ?>
        <a href="dashboard.php">En vivo</a>
        <a href="usuarios.php" class="<?= $altaEmpleado ? '' : 'active' ?>">Usuarios</a>
        <?php endif; ?>
        <a href="postulantes.php">Postulantes</a>
        <a href="vigiladores.php" class="<?= $altaEmpleado ? 'active' : '' ?>">Empleados</a>
        <a href="legajos.php">Legajos</a>
        <a href="supervisores.php">Supervisores</a>
        <?php if ($esAdminReal): ?>
        <a href="objetivos.php">Objetivos</a>
        <a href="reportes.php">Reportes</a>
        <?php endif; ?>
        <a href="liquidacion.php">Horas</a>
        <a href="enviar_mails.php">Mails</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<main class="form-shell">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
        <h1 style="font-size:1.25rem;color:var(--primary);margin:0;"><?= $altaEmpleado ? 'Alta de empleado' : 'Alta de usuario' ?></h1>
        <a href="<?= $altaEmpleado ? 'vigiladores.php' : 'dashboard.php' ?>" class="btn btn-outline" style="width:auto;">Volver</a>
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
                <select id="tipo" <?= $altaEmpleado ? 'disabled' : '' ?>>
                    <option value="1">Vigilador</option>
                    <option value="2">Supervisor</option>
                    <option value="3">Oficinista</option>
                    <option value="4">Administrador</option>
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
                <label for="fecha_alta">Fecha de alta</label>
                <input type="date" id="fecha_alta" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label for="empresa_id">Empresa</label>
                <select id="empresa_id">
                    <option value="">Sin empresa</option>
                    <?php foreach ($empresas as $empresa): ?>
                        <option value="<?= (int)$empresa['id_empresa'] ?>"><?= htmlspecialchars($empresa['nombre'] ?? 'Empresa sin nombre') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="grupoObjetivo">
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
            <button type="submit" class="btn btn-primary" id="btnGuardar" style="width:auto;min-width:150px;"><?= $altaEmpleado ? 'Guardar empleado' : 'Guardar usuario' ?></button>
        </div>
    </form>
</main>

<?php if ($altaEmpleado): ?>
<div class="modal-overlay" id="modalImportar" role="dialog" aria-modal="true" aria-labelledby="tituloImportar">
    <div class="modal">
        <div class="modal-header"><span class="modal-title" id="tituloImportar">¿Importar datos de un postulante?</span></div>
        <p style="margin-top:0;">Podés buscar un postulante y completar automáticamente nombre, DNI, fecha de nacimiento, teléfono y email. Los demás datos se completan manualmente.</p>
        <div style="display:flex;justify-content:flex-end;gap:.7rem;flex-wrap:wrap;">
            <button type="button" class="btn btn-outline" id="btnManual">Completar manualmente</button>
            <button type="button" class="btn btn-primary" id="btnBuscarPostulante">Buscar postulante</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalPostulantes" role="dialog" aria-modal="true" aria-labelledby="tituloPostulantes">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="tituloPostulantes">Seleccionar postulante</span>
            <button type="button" class="modal-close" id="btnCerrarPostulantes" aria-label="Cerrar">&#x2715;</button>
        </div>
        <div class="form-group">
            <label for="buscarPostulante">Buscar por nombre, DNI, email o teléfono</label>
            <input type="search" id="buscarPostulante" placeholder="Escribí para buscar..." autocomplete="off">
        </div>
        <div class="postulante-list" id="listaPostulantes"><div style="padding:1rem;color:var(--text-muted);">Cargando postulantes...</div></div>
    </div>
</div>
<?php endif; ?>

<script>
const form = document.getElementById('formUsuario');
const err = document.getElementById('msgError');
const ok = document.getElementById('msgOk');
const tipoSelect = document.getElementById('tipo');
const grupoObjetivo = document.getElementById('grupoObjetivo');
const objetivoSelect = document.getElementById('objetivo_id');
const TIPO_INICIAL = <?= $tipoInicial ?>;
const ALTA_EMPLEADO = <?= $altaEmpleado ? 'true' : 'false' ?>;

tipoSelect.value = String(TIPO_INICIAL);

function esc(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function actualizarCamposPorTipo() {
    const esEmpleado = tipoSelect.value === '1';
    grupoObjetivo.style.display = esEmpleado ? '' : 'none';
    objetivoSelect.disabled = !esEmpleado;
    if (!esEmpleado) objetivoSelect.value = '';
}

tipoSelect.addEventListener('change', actualizarCamposPorTipo);
actualizarCamposPorTipo();

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
        alta_empleado: ALTA_EMPLEADO,
        nombre: field('nombre'),
        fecha_nac: field('fecha_nac'),
        fecha_alta: field('fecha_alta'),
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
        objetivo_id: tipoSelect.value === '1' ? field('objetivo_id') : '',
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
        tipoSelect.value = String(TIPO_INICIAL);
        actualizarCamposPorTipo();
        showOk(`${data.mensaje}. ID: ${data.id}`);
    } catch (error) {
        showError(error.message);
    }

    btn.disabled = false;
    btn.textContent = ALTA_EMPLEADO ? 'Guardar empleado' : 'Guardar usuario';
});

if (ALTA_EMPLEADO) {
    let postulantes = [];
    const modalImportar = document.getElementById('modalImportar');
    const modalPostulantes = document.getElementById('modalPostulantes');
    const listaPostulantes = document.getElementById('listaPostulantes');
    const buscarPostulante = document.getElementById('buscarPostulante');

    modalImportar.classList.add('open');
    document.getElementById('btnManual').addEventListener('click', () => modalImportar.classList.remove('open'));
    document.getElementById('btnBuscarPostulante').addEventListener('click', async () => {
        modalImportar.classList.remove('open');
        modalPostulantes.classList.add('open');
        buscarPostulante.focus();
        if (postulantes.length) return;
        try {
            const res = await fetch('api/get_postulantes.php');
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'No se pudieron cargar los postulantes.');
            postulantes = data;
            renderPostulantes();
        } catch (error) {
            listaPostulantes.innerHTML = `<div style="padding:1rem;color:var(--danger);">${esc(error.message)}</div>`;
        }
    });
    document.getElementById('btnCerrarPostulantes').addEventListener('click', () => modalPostulantes.classList.remove('open'));
    buscarPostulante.addEventListener('input', renderPostulantes);

    function renderPostulantes() {
        const q = buscarPostulante.value.trim().toLowerCase();
        const visibles = postulantes.filter((p) => [p.nombre_completo, p.dni, p.email, p.telefono]
            .some((valor) => String(valor || '').toLowerCase().includes(q)));
        if (!visibles.length) {
            listaPostulantes.innerHTML = '<div style="padding:1rem;color:var(--text-muted);">No se encontraron postulantes.</div>';
            return;
        }
        listaPostulantes.innerHTML = visibles.map((p) => `
            <div class="postulante-item">
                <div><strong>${esc(p.nombre_completo)}</strong><small>DNI ${esc(p.dni)} · ${esc(p.email)} · ${esc(p.telefono)}</small></div>
                <button type="button" class="btn btn-primary btn-sm" data-postulante-id="${Number(p.id)}">Elegir</button>
            </div>`).join('');
        listaPostulantes.querySelectorAll('[data-postulante-id]').forEach((button) => {
            button.addEventListener('click', () => importarPostulante(Number(button.dataset.postulanteId)));
        });
    }

    function importarPostulante(id) {
        const p = postulantes.find((item) => Number(item.id) === id);
        if (!p) return;
        document.getElementById('nombre').value = p.nombre_completo || '';
        document.getElementById('dni').value = p.dni || '';
        document.getElementById('fecha_nac').value = p.fecha_nacimiento || '';
        document.getElementById('telefono').value = p.telefono || '';
        document.getElementById('email').value = p.email || '';
        modalPostulantes.classList.remove('open');
        document.getElementById('cuil').focus();
    }
}
</script>
</body>
</html>


