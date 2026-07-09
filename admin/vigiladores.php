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
    <title>TDV - Gestión de empleados</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Nav (igual que dashboard) */
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
        .admin-nav .nav-links { display:flex;gap:.3rem; }
        .admin-nav .nav-links a {
            color:rgba(255,255,255,.75);text-decoration:none;
            padding:.4rem .9rem;border-radius:6px;font-size:.88rem;transition:background .2s;
        }
        .admin-nav .nav-links a.active,
        .admin-nav .nav-links a:hover { background:rgba(255,255,255,.15);color:#fff; }
        .admin-nav .nav-user { color:rgba(255,255,255,.7);font-size:.82rem;text-align:right; }
        .admin-nav .nav-user strong { display:block;color:#fff; }

        /* Table */
        .data-table { width:100%;border-collapse:collapse;font-size:.88rem; }
        .data-table th {
            background:var(--primary);color:#fff;
            padding:.65rem .9rem;text-align:left;font-weight:600;
        }
        .data-table td { padding:.6rem .9rem;border-bottom:1px solid var(--bg);vertical-align:middle; }
        .data-table tr:hover td { background:#f7f9fc; }
        .data-table .actions { display:flex;gap:.4rem;flex-wrap:wrap; }

        /* Estado pills */
        .pill {
            display:inline-block;padding:.18rem .65rem;border-radius:20px;
            font-size:.75rem;font-weight:600;white-space:nowrap;
        }
        .pill-activo    { background:#eafaf1;color:#1e8449; }
        .pill-inactivo  { background:#fdecea;color:#c0392b; }
        .pill-pendiente { background:#fef9e7;color:#9a7d0a; }

        /* Section header */
        .section-header {
            display:flex;align-items:center;justify-content:space-between;
            margin-bottom:.8rem;
        }
        .section-title { font-size:1rem;font-weight:700;color:var(--primary); }

        .turno-pill {
            background:var(--bg);border:1px solid var(--border);
            border-radius:6px;padding:.15rem .55rem;
            font-size:.78rem;font-family:monospace;white-space:nowrap;
        }

        /* Pending banner */
        .pending-banner {
            background:#fef9e7;border:1px solid #f6c90e;
            border-radius:8px;padding:.7rem 1rem;
            font-size:.88rem;color:#9a7d0a;
            margin-bottom:1rem;
        }

        /* Modal */
        .modal-overlay {
            display:none;position:fixed;inset:0;
            background:rgba(0,0,0,.55);z-index:1000;
            align-items:center;justify-content:center;padding:1rem;
        }
        .modal-overlay.open { display:flex; }
        .modal {
            background:#fff;border-radius:var(--radius);
            box-shadow:0 8px 40px rgba(0,0,0,.25);
            width:100%;max-width:560px;max-height:90vh;overflow-y:auto;
            padding:1.8rem;
        }
        .modal-header {
            display:flex;align-items:center;justify-content:space-between;
            margin-bottom:1.2rem;
        }
        .modal-title { font-size:1.1rem;font-weight:700;color:var(--primary); }
        .modal-close {
            background:none;border:none;font-size:1.4rem;
            cursor:pointer;color:var(--text-muted);line-height:1;
        }
        .modal-close:hover { color:var(--text); }
        .form-row { display:grid;grid-template-columns:1fr 1fr;gap:0 1rem; }

        @media (max-width:600px) {
            .data-table td:nth-child(3),
            .data-table th:nth-child(3) { display:none; }
            .form-row { grid-template-columns:1fr; }
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
        <a href="vigiladores.php" class="active">&#x1F464; Empleados</a>
        <a href="supervisores.php">&#x1F4BC; Supervisores</a>
        <a href="objetivos.php">&#x1F3AF; Objetivos</a>
        <a href="reportes.php">&#x26A0; Reportes</a>
        <a href="migracion.php">Migracion</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<div style="max-width:1200px;margin:0 auto;padding:1.2rem 1rem 2rem;">

    <!-- Pendientes (se muestra sólo si hay) -->
    <div id="pendientesBanner" style="display:none;" class="pending-banner">
        <strong>&#9888; Hay solicitudes de cuenta pendientes de aprobación.</strong>
        Ver en la tabla de abajo (marcadas en amarillo).
    </div>

    <div class="alert alert-danger"  id="tableError"   role="alert"><span>&#9888;</span><span id="tableErrorMsg"></span></div>
    <div class="alert alert-success" id="tableSuccess" role="alert"><span>&#9989;</span><span id="tableSuccessMsg"></span></div>

    <!-- Tabla de empleados -->
    <div class="card" style="overflow-x:auto;">
        <div class="section-header">
            <span class="section-title">&#x1F464; empleados</span>
            <button class="btn btn-primary btn-sm" onclick="abrirModal(0)">+ Nuevo vigilador</button>
        </div>
        <div id="tablaWrap">
            <div style="padding:2rem;text-align:center;color:var(--text-muted);">
                <div class="spinner spinner-dark" style="margin:0 auto .8rem;"></div>
                Cargando...
            </div>
        </div>
    </div>

</div>

<!-- MODAL crear / editar -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Nuevo vigilador</span>
            <button class="modal-close" onclick="cerrarModal()">&#x2715;</button>
        </div>

        <div class="alert alert-danger" id="modalError" role="alert"><span>&#9888;</span><span id="modalErrorMsg"></span></div>

        <form id="formVigilador" novalidate>
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
                <input type="hidden" id="fUsuario">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fPass">Contraseña <span id="passRequired" style="color:var(--danger)">*</span></label>
                    <input type="password" id="fPass" autocomplete="new-password" placeholder="••••••••">
                    <small id="passHint" style="display:none;color:var(--text-muted);font-size:.75rem;">
                        Dejar vacío para mantener la actual.
                    </small>
                </div>
                <div class="form-group">
                    <label for="fObjetivo">Objetivo asignado</label>
                    <select id="fObjetivo">
                        <option value="">- Sin objetivo -</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fHoraEntrada">Hora de entrada</label>
                    <input type="time" id="fHoraEntrada">
                </div>
                <div class="form-group">
                    <label for="fHoraSalida">Hora de salida</label>
                    <input type="time" id="fHoraSalida">
                </div>
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
let objetivos = [];

// ---- Inicio -----------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    cargarObjetivos();
    cargarVigiladores();
    document.getElementById('formVigilador').addEventListener('submit', onGuardar);
});

// ---- Cargar objetivos para el select ----------------------
async function cargarObjetivos() {
    try {
        objetivos = await apiFetch('api/get_objetivos.php');
        const sel = document.getElementById('fObjetivo');
        objetivos.forEach(o => {
            const opt = document.createElement('option');
            opt.value       = o.id_objetivo;
            opt.textContent = o.nombre;
            sel.appendChild(opt);
        });
    } catch (e) { /* seguimos sin objetivos */ }
}

// ---- Tabla de empleados ---------------------------------
async function cargarVigiladores() {
    const wrap = document.getElementById('tablaWrap');
    try {
        const list = await apiFetch('api/get_vigiladores.php');
        const pendientes = list.filter(v => v.pendiente == 1);
        document.getElementById('pendientesBanner').style.display = pendientes.length ? 'block' : 'none';

        if (!list.length) {
            wrap.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:1.5rem;">Sin empleados registrados.</p>';
            return;
        }

        const tbody = list.map(v => {
            let estadoPill, acciones;
            if (v.pendiente == 1) {
                estadoPill = '<span class="pill pill-pendiente">Pendiente</span>';
                acciones = `
                    <button class="btn btn-success btn-sm" onclick="toggleEstado(${v.id_empleado},'aprobar')">&#9989; Aprobar</button>
                    <button class="btn btn-outline btn-sm" onclick="abrirModal(${v.id_empleado})">&#9998;</button>`;
            } else if (v.activo == 1) {
                estadoPill = '<span class="pill pill-activo">Activo</span>';
                acciones = `
                    <button class="btn btn-outline btn-sm" onclick="abrirModal(${v.id_empleado})">&#9998; Editar</button>
                    <button class="btn btn-danger  btn-sm" onclick="toggleEstado(${v.id_empleado},'desactivar')">&#x23F8; Desactivar</button>`;
            } else {
                estadoPill = '<span class="pill pill-inactivo">Inactivo</span>';
                acciones = `
                    <button class="btn btn-outline btn-sm" onclick="abrirModal(${v.id_empleado})">&#9998; Editar</button>
                    <button class="btn btn-success btn-sm" onclick="toggleEstado(${v.id_empleado},'activar')">&#9654; Activar</button>`;
            }
            const turno = (v.hora_entrada && v.hora_salida)
                ? `<span class="turno-pill">${v.hora_entrada.substr(0,5)} - ${v.hora_salida.substr(0,5)}</span>`
                : '<span style="color:var(--text-muted)">-</span>';
            const bg = v.pendiente == 1 ? 'background:#fffde7;' : '';
            return `<tr style="${bg}" data-empleado-id="${v.id_empleado}">
                <td><strong>${esc(v.nombre)}</strong><br>
                    <small style="color:var(--text-muted);">@${esc(v.usuario)}</small></td>
                <td>${esc(v.dni)}</td>
                <td>${esc(v.objetivo_nombre || '-')}</td>
                <td>${turno}</td>
                <td class="estado-cell">${estadoPill}</td>
                <td><div class="actions acciones-cell">${acciones}</div></td>
            </tr>`;
        }).join('');

        wrap.innerHTML = `
            <table class="data-table">
                <thead><tr>
                    <th>Nombre / Usuario</th>
                    <th>DNI</th>
                    <th>Objetivo</th>
                    <th>Turno</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr></thead>
                <tbody>${tbody}</tbody>
            </table>`;
    } catch (e) {
        wrap.innerHTML = '<p style="color:var(--danger);padding:1rem;">Error al cargar empleados.</p>';
    }
}

// ---- Modal ------------------------------------------------
function abrirModal(id) {
    document.getElementById('modalError').classList.remove('show');
    document.getElementById('formVigilador').reset();
    document.getElementById('fId').value = id;

    const esEdicion = id > 0;
    document.getElementById('modalTitle').textContent = esEdicion ? 'Editar vigilador' : 'Nuevo vigilador';
    document.getElementById('passRequired').style.display = esEdicion ? 'none' : 'inline';
    document.getElementById('passHint').style.display     = esEdicion ? 'inline' : 'none';

    if (esEdicion) {
        // Cargar datos del vigilador desde la tabla ya cargada
        apiFetch('api/get_vigiladores.php').then(list => {
            const v = list.find(x => x.id_empleado == id);
            if (!v) return;
            document.getElementById('fNombre').value      = v.nombre;
            document.getElementById('fDni').value         = v.dni;
            document.getElementById('fTelefono').value   = v.telefono || '';
            document.getElementById('fEmail').value       = v.email    || '';
            document.getElementById('fUsuario').value     = v.usuario;
            document.getElementById('fObjetivo').value   = v.objetivo_id || '';
            document.getElementById('fHoraEntrada').value = v.hora_entrada ? v.hora_entrada.substr(0,5) : '';
            document.getElementById('fHoraSalida').value  = v.hora_salida  ? v.hora_salida.substr(0,5)  : '';
        });
    }

    document.getElementById('modalOverlay').classList.add('open');
}

function cerrarModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

// Click fuera del modal cierra
document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

// ---- Guardar vigilador ------------------------------------
async function onGuardar(e) {
    e.preventDefault();
    const errDiv = document.getElementById('modalError');
    errDiv.classList.remove('show');

    const id          = parseInt(document.getElementById('fId').value);
    const nombre      = document.getElementById('fNombre').value.trim();
    const dni         = document.getElementById('fDni').value.trim();
    const telefono    = document.getElementById('fTelefono').value.trim();
    const email       = document.getElementById('fEmail').value.trim();
    const usuario     = email;
    const pass        = document.getElementById('fPass').value;
    const obj_id      = document.getElementById('fObjetivo').value;
    const horaEntrada = document.getElementById('fHoraEntrada').value;
    const horaSalida  = document.getElementById('fHoraSalida').value;

    if (!nombre || !dni || !telefono || !email) {
        document.getElementById('modalErrorMsg').textContent = 'Complete todos los campos obligatorios.';
        errDiv.classList.add('show'); return;
    }
    if (!id && !pass) {
        document.getElementById('modalErrorMsg').textContent = 'Ingrese una contraseña.';
        errDiv.classList.add('show'); return;
    }

    const btn = document.getElementById('btnGuardar');
    btn.disabled    = true;
    btn.textContent = 'Guardando...';

    try {
        await apiFetch('api/guardar_vigilador.php', 'POST', {
            id, nombre, dni, telefono, email, usuario,
            contrasena:   pass,
            objetivo_id:  obj_id     !== '' ? obj_id     : null,
            hora_entrada: horaEntrada !== '' ? horaEntrada : null,
            hora_salida:  horaSalida  !== '' ? horaSalida  : null,
        });
        cerrarModal();
        mostrarExito(id ? 'Vigilador actualizado.' : 'Vigilador creado.');
        cargarVigiladores();
    } catch (err) {
        document.getElementById('modalErrorMsg').textContent = err.message;
        errDiv.classList.add('show');
    }

    btn.disabled    = false;
    btn.textContent = 'Guardar';
}

// ---- Toggle estado ----------------------------------------
async function toggleEstado(id, accion) {
    const labels = { aprobar:'Aprobar', activar:'Activar', desactivar:'Desactivar' };
    if (accion === 'desactivar' && !confirm('¿Desactivar este vigilador?')) return;

    try {
        const resp = await apiFetch('api/toggle_estado.php', 'POST', { id, accion });
        mostrarExito(resp.mensaje);
        actualizarFilaEstado(id, accion === 'desactivar' ? 0 : 1);
    } catch (err) {
        mostrarError(err.message);
    }
}

function actualizarFilaEstado(id, activo) {
    const row = document.querySelector(`tr[data-empleado-id="${id}"]`);
    if (!row) {
        cargarVigiladores();
        return;
    }

    const estadoCell = row.querySelector('.estado-cell');
    const accionesCell = row.querySelector('.acciones-cell');

    if (estadoCell) {
        estadoCell.innerHTML = activo
            ? '<span class="pill pill-activo">Activo</span>'
            : '<span class="pill pill-inactivo">Inactivo</span>';
    }

    if (accionesCell) {
        accionesCell.innerHTML = activo
            ? `<button class="btn btn-outline btn-sm" onclick="abrirModal(${id})">&#9998; Editar</button>
               <button class="btn btn-danger btn-sm" onclick="toggleEstado(${id},'desactivar')">&#x23F8; Desactivar</button>`
            : `<button class="btn btn-outline btn-sm" onclick="abrirModal(${id})">&#9998; Editar</button>
               <button class="btn btn-success btn-sm" onclick="toggleEstado(${id},'activar')">&#9654; Activar</button>`;
    }
}

// ---- Utilidades -------------------------------------------
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
    setTimeout(() => d.classList.remove('show'), 5000);
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
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>



