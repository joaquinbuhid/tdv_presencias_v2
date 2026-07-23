<?php
require_once __DIR__ . '/auth.php';
requireBackofficePage();
$adminNombre = $_SESSION['nombre_completo'] ?? 'Administrador';
$esAdminReal = esAdminReal();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Supervisores</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-nav {
            background: var(--primary-dk);
            display: flex; align-items: center; justify-content: space-between;
            padding: .7rem 1.5rem; flex-wrap: wrap; gap: .5rem;
        }
        .admin-nav .brand { color:#fff;font-weight:700;font-size:1.1rem;display:flex;align-items:center;gap:.5rem; }
        .admin-nav .nav-links { display:flex;gap:.3rem; }
        .admin-nav .nav-links a {
            color:rgba(255,255,255,.75);text-decoration:none;
            padding:.4rem .9rem;border-radius:6px;font-size:.88rem;transition:background .2s;
        }
        .admin-nav .nav-links a.active,
        .admin-nav .nav-links a:hover { background:rgba(255,255,255,.15);color:#fff; }
        .admin-nav .nav-user { color:rgba(255,255,255,.7);font-size:.82rem;text-align:right; }
        .admin-nav .nav-user strong { display:block;color:#fff; }

        .data-table { width:100%;border-collapse:collapse;font-size:.88rem; }
        .data-table th {
            background:var(--primary);color:#fff;
            padding:.65rem .9rem;text-align:left;font-weight:600;
        }
        .data-table td { padding:.65rem .9rem;border-bottom:1px solid var(--bg);vertical-align:middle; }
        .data-table tr:hover td { background:#f7f9fc; }
        .data-table .actions { display:flex;gap:.4rem;flex-wrap:wrap; }

        .pill { display:inline-block;padding:.18rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600;white-space:nowrap; }
        .pill-activo   { background:#eafaf1;color:#1e8449; }
        .pill-inactivo { background:#fdecea;color:#c0392b; }

        .obj-count {
            display:inline-block;background:#ebf5fb;color:#1a5276;
            border-radius:20px;padding:.15rem .65rem;font-size:.78rem;font-weight:600;
        }

        .section-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:.8rem; }
        .section-title  { font-size:1rem;font-weight:700;color:var(--primary); }

        .modal-overlay {
            display:none;position:fixed;inset:0;
            background:rgba(0,0,0,.55);z-index:1000;
            align-items:center;justify-content:center;padding:1rem;
        }
        .modal-overlay.open { display:flex; }
        .modal {
            background:#fff;border-radius:var(--radius);
            box-shadow:0 8px 40px rgba(0,0,0,.25);
            width:100%;max-width:500px;max-height:90vh;overflow-y:auto;
            padding:1.8rem;
        }
        .modal-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem; }
        .modal-title  { font-size:1.1rem;font-weight:700;color:var(--primary); }
        .modal-close  { background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted); }
        .modal-close:hover { color:var(--text); }
        .form-row { display:grid;grid-template-columns:1fr 1fr;gap:0 1rem; }

        @media (max-width:600px) {
            .form-row { grid-template-columns:1fr; }
            .admin-nav { padding:.6rem 1rem; }
        }
    </style>
</head>
<body>

<nav class="admin-nav">
    <div class="brand">&#x1F6E1; TDV Seguridad</div>
    <div class="nav-links">
        <?php if ($esAdminReal): ?>
        <a href="dashboard.php">&#x1F7E2; En vivo</a>
        <a href="usuarios.php">&#x2795; Usuarios</a>
        <?php endif; ?>
        <a href="postulantes.php">Postulantes</a>
        <a href="vigiladores.php">&#x1F464; Empleados</a>
        <a href="legajos.php">Legajos</a>
        <a href="supervisores.php" class="active">&#x1F4BC; Supervisores</a>
        <?php if ($esAdminReal): ?>
        <a href="objetivos.php">&#x1F3AF; Objetivos</a>
        <a href="reportes.php">&#x26A0; Reportes</a>
        <?php endif; ?>
        <a href="liquidacion.php">Horas</a>
        <a href="enviar_mails.php">Mails</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<div style="max-width:1100px;margin:0 auto;padding:1.2rem 1rem 2rem;">

    <div class="alert alert-danger"  id="tableError"   role="alert"><span>&#9888;</span><span id="tableErrorMsg"></span></div>
    <div class="alert alert-success" id="tableSuccess" role="alert"><span>&#9989;</span><span id="tableSuccessMsg"></span></div>

    <div class="card" style="overflow-x:auto;">
        <div class="section-header">
            <span class="section-title">&#x1F4BC; Supervisores</span>
            <?php if ($esAdminReal): ?>
            <button class="btn btn-primary btn-sm" onclick="abrirModal(0)">+ Nuevo supervisor</button>
            <?php endif; ?>
        </div>
        <div id="tablaWrap">
            <div style="padding:2rem;text-align:center;color:var(--text-muted);">
                <div class="spinner spinner-dark" style="margin:0 auto .8rem;"></div>
                Cargando...
            </div>
        </div>
    </div>

</div>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Nuevo supervisor</span>
            <button class="modal-close" onclick="cerrarModal()">&#x2715;</button>
        </div>
        <div class="alert alert-danger" id="modalError" role="alert">
            <span>&#9888;</span><span id="modalErrorMsg"></span>
        </div>
        <form id="formSupervisor" novalidate>
            <input type="hidden" id="fId" value="0">

            <div class="form-group">
                <label for="fNombre">Nombre completo <span style="color:var(--danger)">*</span></label>
                <input type="text" id="fNombre" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fDni">DNI <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="fDni" required maxlength="15">
                </div>
                <div class="form-group">
                    <label for="fTelefono">Teléfono</label>
                    <input type="text" id="fTelefono">
                </div>
            </div>

            <div class="form-group">
                <label for="fEmail">Email <span style="color:var(--danger)">*</span></label>
                <input type="email" id="fEmail" required>
                <small style="display:block;color:var(--text-muted);font-size:.75rem;margin-top:.25rem;">
                    Este email sera el usuario de acceso del supervisor.
                </small>
                <input type="hidden" id="fUsuario">
            </div>

            <div id="wrapCambiarClave" style="display:none;margin-bottom:.2rem;">
                <label style="font-size:.85rem;display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="checkbox" id="fCambiarClave" onchange="toggleClave()">
                    Cambiar contraseña
                </label>
            </div>
            <div id="wrapClaveInput" class="form-group">
                <label for="fContrasena">Contraseña <span style="color:var(--danger)" id="lblClaveReq">*</span></label>
                <input type="password" id="fContrasena" autocomplete="new-password">
            </div>

            <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:1rem;">
                <button type="button" class="btn btn-outline" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnGuardar" style="width:auto;min-width:120px;">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    cargarSupervisores();
    document.getElementById('formSupervisor').addEventListener('submit', onGuardar);
});
const ES_ADMIN_REAL = <?= $esAdminReal ? 'true' : 'false' ?>;

async function cargarSupervisores() {
    const wrap = document.getElementById('tablaWrap');
    try {
        const list = await apiFetch('api/get_supervisores.php');
        if (!list.length) {
            wrap.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:1.5rem;">Sin supervisores registrados.</p>';
            return;
        }
        const tbody = list.map(s => {
            const estado = s.estado == 1
                ? '<span class="pill pill-activo">Activo</span>'
                : '<span class="pill pill-inactivo">Inactivo</span>';
            const acciones = s.estado == 1
                ? `<button class="btn btn-outline btn-sm" onclick="abrirModal(${s.id_supervisor})">${ES_ADMIN_REAL ? '&#9998; Editar' : 'Ver'}</button>
                   ${ES_ADMIN_REAL ? `<button class="btn btn-danger btn-sm" onclick="toggleEstado(${s.id_supervisor},'desactivar')">&#x23F8; Desactivar</button>` : ''}`
                : `<button class="btn btn-outline btn-sm" onclick="abrirModal(${s.id_supervisor})">${ES_ADMIN_REAL ? '&#9998; Editar' : 'Ver'}</button>
                   ${ES_ADMIN_REAL ? `<button class="btn btn-success btn-sm" onclick="toggleEstado(${s.id_supervisor},'activar')">&#9654; Activar</button>` : ''}`;
            const objCount = parseInt(s.objetivos_asignados);
            return `<tr>
                <td><strong>${esc(s.nombre)}</strong></td>
                <td>${esc(s.dni)}</td>
                <td>${s.telefono ? esc(s.telefono) : '<span style="color:var(--text-muted)">-</span>'}</td>
                <td>${s.email    ? esc(s.email)    : '<span style="color:var(--text-muted)">-</span>'}</td>
                <td>${s.usuario  ? esc(s.usuario)  : '<span style="color:var(--text-muted)">-</span>'}</td>
                <td>
                    <span class="obj-count" title="Objetivos asignados">${objCount} objetivos${objCount !== 1 ? 's' : ''}</span>
                </td>
                <td>${estado}</td>
                <td><div class="actions">${acciones}</div></td>
            </tr>`;
        }).join('');

        wrap.innerHTML = `
            <table class="data-table">
                <thead><tr>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Usuario</th>
                    <th>Objetivos</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr></thead>
                <tbody>${tbody}</tbody>
            </table>`;
    } catch (e) {
        wrap.innerHTML = '<p style="color:var(--danger);padding:1rem;">Error al cargar supervisores.</p>';
    }
}

function abrirModal(id) {
    if (!ES_ADMIN_REAL && !id) return;
    document.getElementById('modalError').classList.remove('show');
    document.getElementById('formSupervisor').reset();
    document.getElementById('fId').value = id;
    document.getElementById('modalTitle').textContent = id ? 'Editar supervisor' : 'Nuevo supervisor';
    document.querySelectorAll('#formSupervisor input').forEach(el => {
        if (el.type !== 'hidden') el.disabled = !ES_ADMIN_REAL;
    });
    document.getElementById('btnGuardar').style.display = ES_ADMIN_REAL ? '' : 'none';

    // Contraseña: requerida en creación, opcional en edición
    const wrapCambiar  = document.getElementById('wrapCambiarClave');
    const wrapClave    = document.getElementById('wrapClaveInput');
    const lblReq       = document.getElementById('lblClaveReq');
    const cbCambiar    = document.getElementById('fCambiarClave');
    const inputClave   = document.getElementById('fContrasena');

    if (id) {
        wrapCambiar.style.display = '';
        cbCambiar.checked         = false;
        wrapClave.style.display   = 'none';
        inputClave.required       = false;
        lblReq.style.display      = 'none';

        apiFetch('api/get_supervisores.php').then(list => {
            const s = list.find(x => x.id_supervisor == id);
            if (!s) return;
            document.getElementById('fNombre').value   = s.nombre;
            document.getElementById('fDni').value      = s.dni;
            document.getElementById('fTelefono').value = s.telefono || '';
            document.getElementById('fEmail').value    = s.email    || '';
            document.getElementById('fUsuario').value  = s.usuario  || '';
        });
    } else {
        wrapCambiar.style.display = 'none';
        wrapClave.style.display   = '';
        inputClave.required       = true;
        lblReq.style.display      = '';
    }

    document.getElementById('modalOverlay').classList.add('open');
    document.getElementById('fNombre').focus();
}

function cerrarModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

function toggleClave() {
    const cb    = document.getElementById('fCambiarClave');
    const wrap  = document.getElementById('wrapClaveInput');
    const label = document.getElementById('lblClaveReq');
    wrap.style.display  = cb.checked ? '' : 'none';
    label.style.display = cb.checked ? '' : 'none';
    document.getElementById('fContrasena').required = cb.checked;
    if (cb.checked) document.getElementById('fContrasena').focus();
}

document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

async function onGuardar(e) {
    e.preventDefault();
    if (!ES_ADMIN_REAL) return;
    const errDiv  = document.getElementById('modalError');
    errDiv.classList.remove('show');

    const id         = parseInt(document.getElementById('fId').value);
    const nombre     = document.getElementById('fNombre').value.trim();
    const dni        = document.getElementById('fDni').value.trim();
    const telefono   = document.getElementById('fTelefono').value.trim();
    const email      = document.getElementById('fEmail').value.trim();
    const usuario    = email;
    const cbCambiar  = document.getElementById('fCambiarClave');
    const contrasena = (id === 0 || cbCambiar.checked)
        ? document.getElementById('fContrasena').value
        : '';

    if (!nombre || !dni || !telefono || !email) {
        document.getElementById('modalErrorMsg').textContent = 'Complete nombre completo, DNI, telefono y email.';
        errDiv.classList.add('show'); return;
    }
    if (id === 0 && !contrasena) {
        document.getElementById('modalErrorMsg').textContent = 'La contraseña es requerida.';
        errDiv.classList.add('show'); return;
    }

    const btn = document.getElementById('btnGuardar');
    btn.disabled    = true;
    btn.textContent = 'Guardando...';

    try {
        await apiFetch('api/guardar_supervisor.php', 'POST', { id, nombre, dni, telefono, email, usuario, contrasena });
        cerrarModal();
        mostrarExito(id ? 'Supervisor actualizado.' : 'Supervisor creado.');
        cargarSupervisores();
    } catch (err) {
        document.getElementById('modalErrorMsg').textContent = err.message;
        errDiv.classList.add('show');
    }

    btn.disabled    = false;
    btn.textContent = 'Guardar';
}

async function toggleEstado(id, accion) {
    if (!ES_ADMIN_REAL) return;
    if (accion === 'desactivar' && !confirm('¿Desactivar este supervisor?')) return;
    try {
        const resp = await apiFetch('api/toggle_supervisor.php', 'POST', { id, accion });
        mostrarExito(resp.mensaje);
        cargarSupervisores();
    } catch (err) {
        mostrarError(err.message);
    }
}

function mostrarExito(msg) {
    const d = document.getElementById('tableSuccess');
    document.getElementById('tableSuccessMsg').textContent = msg;
    d.classList.add('show');
    setTimeout(() => d.classList.remove('show'), 4000);
}
function mostrarError(msg) {
    const d = document.getElementById('tableError');
    document.getElementById('tableErrorMsg').textContent = msg;
    d.classList.add('show');
    setTimeout(() => d.classList.remove('show'), 6000);
}
async function apiFetch(url, method = 'GET', data = null) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (data) opts.body = JSON.stringify(data);
    const res  = await fetch(url, opts);
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Error del servidor');
    return json;
}
function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>



