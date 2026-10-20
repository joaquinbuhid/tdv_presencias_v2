<?php
require_once __DIR__ . '/auth.php';
requireAdminRealPage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Dispositivos</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css?v=20261020">
    <link rel="stylesheet" href="../css/inventario.css?v=20261020">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

<main class="inv-shell">
    <div class="inv-head">
        <h1 class="inv-title">&#x1F4BB; Dispositivos <span class="inv-counter" id="counter"></span></h1>
        <button class="btn btn-primary btn-sm" onclick="abrirModal(0)">+ Nuevo dispositivo</button>
    </div>

    <div class="alert alert-danger"  id="tableError"   role="alert"><span>&#9888;</span><span id="tableErrorMsg"></span></div>
    <div class="alert alert-success" id="tableSuccess" role="alert"><span>&#9989;</span><span id="tableSuccessMsg"></span></div>

    <div class="inv-filters">
        <input type="text" id="fBuscar" class="inv-search" placeholder="Buscar por nombre, dueño, usuario o hardware...">
        <select id="fTipoFiltro"><option value="">Todos los tipos</option></select>
        <label class="inv-check"><input type="checkbox" id="fConTickets"> Con tickets abiertos</label>
    </div>

    <div class="card inv-card-wrap" style="overflow-x:auto;">
        <div id="tablaWrap">
            <div style="padding:2rem;text-align:center;color:var(--text-muted);">
                <div class="spinner spinner-dark" style="margin:0 auto .8rem;"></div>
                Cargando...
            </div>
        </div>
    </div>
</main>

<!-- MODAL crear / editar -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Nuevo dispositivo</span>
            <button class="modal-close" onclick="cerrarModal()" aria-label="Cerrar">&#x2715;</button>
        </div>

        <div class="alert alert-danger" id="modalError" role="alert">
            <span>&#9888;</span><span id="modalErrorMsg"></span>
        </div>

        <form id="formDispositivo" novalidate autocomplete="off">
            <input type="hidden" id="fId" value="0">

            <div class="form-row">
                <div class="form-group">
                    <label for="fNombre">Nombre <span class="req">*</span></label>
                    <input type="text" id="fNombre" required maxlength="100" placeholder="Ej: PC Recepción">
                </div>
                <div class="form-group">
                    <label for="fTipo">Tipo <span class="req">*</span></label>
                    <select id="fTipo" required><option value="">- Elegir -</option></select>
                </div>
            </div>

            <div class="form-group">
                <label for="fEmpleado">Dueño</label>
                <select id="fEmpleado"><option value="">- Sin asignar -</option></select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fUsuario">Usuario</label>
                    <input type="text" id="fUsuario" maxlength="100" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="fClave">Clave</label>
                    <div class="pass-wrap">
                        <input type="password" id="fClave" maxlength="255" autocomplete="new-password">
                        <button type="button" class="pass-toggle" onclick="toggleClave()" aria-label="Mostrar u ocultar clave">&#x1F441;</button>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="fObs">Observaciones</label>
                <textarea id="fObs" rows="2"></textarea>
            </div>

            <div class="modal-section">Hardware (opcional)</div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fMother">Mother</label>
                    <input type="text" id="fMother" maxlength="150">
                </div>
                <div class="form-group">
                    <label for="fMicro">Micro</label>
                    <input type="text" id="fMicro" maxlength="150">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="fRam">RAM</label>
                    <input type="text" id="fRam" maxlength="100" placeholder="Ej: 16 GB DDR4">
                </div>
                <div class="form-group">
                    <label for="fFuente">Fuente</label>
                    <input type="text" id="fFuente" maxlength="100">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnGuardar" style="width:auto;min-width:120px;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
let dispositivos = [];

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('formDispositivo').addEventListener('submit', onGuardar);
    ['fBuscar', 'fTipoFiltro', 'fConTickets'].forEach(id =>
        document.getElementById(id).addEventListener('input', renderTabla));
    document.getElementById('tablaWrap').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const id = Number(btn.dataset.id);
        if (btn.dataset.action === 'edit')   abrirModal(id);
        if (btn.dataset.action === 'delete') eliminar(id, btn.dataset.nombre || '');
    });
    await cargarListas();
    cargarDispositivos();
});

async function cargarListas() {
    try {
        const l = await apiFetch('api/get_inventario_listas.php');
        const opts = (arr, val, txt) => arr.map(x => `<option value="${Number(x[val])}">${esc(x[txt])}</option>`).join('');
        document.getElementById('fTipo').insertAdjacentHTML('beforeend', opts(l.tipos, 'id_tipo_disp', 'nombre'));
        document.getElementById('fTipoFiltro').insertAdjacentHTML('beforeend', opts(l.tipos, 'id_tipo_disp', 'nombre'));
        document.getElementById('fEmpleado').insertAdjacentHTML('beforeend', opts(l.empleados, 'id_empleado', 'nombre'));
    } catch (e) {
        mostrarError(e.message);
    }
}

// ---- Tabla ------------------------------------------------
async function cargarDispositivos() {
    try {
        dispositivos = await apiFetch('api/get_dispositivos.php');
        renderTabla();
    } catch (e) {
        document.getElementById('tablaWrap').innerHTML =
            `<p style="color:var(--danger);padding:1rem;">${esc(e.message)}</p>`;
    }
}

function renderTabla() {
    const wrap  = document.getElementById('tablaWrap');
    const q     = document.getElementById('fBuscar').value.trim().toLowerCase();
    const tipo  = document.getElementById('fTipoFiltro').value;
    const soloT = document.getElementById('fConTickets').checked;

    const list = dispositivos.filter(d => {
        if (tipo && String(d.id_tipo_disp) !== tipo) return false;
        if (soloT && !(Number(d.tickets_abiertos) > 0)) return false;
        if (!q) return true;
        return [d.nombre_disp, d.empleado_nombre, d.usuario_disp, d.tipo_nombre,
                d.mother_disp, d.micro_disp, d.ram, d.fuente, d.observaciones_disp]
            .some(v => v && String(v).toLowerCase().includes(q));
    });

    document.getElementById('counter').textContent =
        list.length === dispositivos.length ? `(${dispositivos.length})` : `(${list.length} de ${dispositivos.length})`;

    if (!list.length) {
        wrap.innerHTML = `<p style="text-align:center;color:var(--text-muted);padding:1.5rem;">
            ${dispositivos.length ? 'Ningún dispositivo coincide con el filtro.' : 'Sin dispositivos cargados.'}</p>`;
        return;
    }

    const tbody = list.map(d => {
        const specs = [['Mother', d.mother_disp], ['Micro', d.micro_disp], ['RAM', d.ram], ['Fuente', d.fuente]]
            .filter(([, v]) => v)
            .map(([k, v]) => `<b>${k}:</b> ${esc(v)}`).join('<br>');
        const abiertos = Number(d.tickets_abiertos);
        const total    = Number(d.tickets_total);
        const tickets  = abiertos > 0
            ? `<a class="pill pill-abierto" href="tickets.php?dispositivo=${Number(d.id_disp)}">${abiertos} abierto${abiertos > 1 ? 's' : ''}</a>`
            : total > 0
                ? `<a class="pill pill-neutro" href="tickets.php?dispositivo=${Number(d.id_disp)}&estado=">${total} cerrado${total > 1 ? 's' : ''}</a>`
                : '<span class="inv-muted">-</span>';
        return `<tr>
            <td class="inv-main"><div>
                <strong>${esc(d.nombre_disp)}</strong>
                ${d.observaciones_disp ? `<div class="inv-muted">${esc(d.observaciones_disp)}</div>` : ''}
            </div></td>
            <td data-label="Tipo"><div><span class="pill pill-tipo">${esc(d.tipo_nombre)}</span></div></td>
            <td data-label="Dueño"><div>${d.empleado_nombre ? esc(d.empleado_nombre) : '<span class="inv-muted">Sin asignar</span>'}</div></td>
            <td data-label="Usuario"><div>
                ${d.usuario_disp ? esc(d.usuario_disp) : '<span class="inv-muted">-</span>'}
                ${Number(d.tiene_clave) ? '<div class="inv-muted">&#x1F511; con clave</div>' : ''}
            </div></td>
            <td data-label="Hardware"><div class="inv-specs">${specs || '-'}</div></td>
            <td data-label="Tickets"><div>${tickets}</div></td>
            <td><div class="actions">
                <button class="btn btn-outline btn-sm" data-action="edit" data-id="${Number(d.id_disp)}">&#9998; Editar</button>
                <a class="btn btn-outline btn-sm" href="tickets.php?nuevo=${Number(d.id_disp)}">&#x1F3AB; Ticket</a>
                <button class="btn btn-danger btn-sm" data-action="delete" data-id="${Number(d.id_disp)}" data-nombre="${esc(d.nombre_disp)}" aria-label="Eliminar">&#x1F5D1;</button>
            </div></td>
        </tr>`;
    }).join('');

    wrap.innerHTML = `
        <table class="inv-table">
            <thead><tr>
                <th>Dispositivo</th><th>Tipo</th><th>Dueño</th><th>Usuario</th>
                <th>Hardware</th><th>Tickets</th><th>Acciones</th>
            </tr></thead>
            <tbody>${tbody}</tbody>
        </table>`;
}

// ---- Modal ------------------------------------------------
const campos = {
    fNombre: 'nombre_disp', fTipo: 'id_tipo_disp', fEmpleado: 'id_empleado',
    fUsuario: 'usuario_disp', fClave: 'clave_disp', fObs: 'observaciones_disp',
    fMother: 'mother_disp', fMicro: 'micro_disp', fRam: 'ram', fFuente: 'fuente',
};

async function abrirModal(id) {
    document.getElementById('modalError').classList.remove('show');
    document.getElementById('formDispositivo').reset();
    document.getElementById('fClave').type = 'password';
    document.getElementById('fId').value = id;
    document.getElementById('modalTitle').textContent = id ? 'Editar dispositivo' : 'Nuevo dispositivo';

    if (id) {
        try {
            const d = await apiFetch(`api/get_dispositivos.php?id=${id}`);
            for (const [el, campo] of Object.entries(campos)) {
                document.getElementById(el).value = d[campo] ?? '';
            }
        } catch (e) {
            mostrarError(e.message);
            return;
        }
    }

    document.getElementById('modalOverlay').classList.add('open');
    document.getElementById('fNombre').focus();
}

function cerrarModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

function toggleClave() {
    const el = document.getElementById('fClave');
    el.type = el.type === 'password' ? 'text' : 'password';
}

document.getElementById('modalOverlay').addEventListener('click', function (e) {
    if (e.target === this) cerrarModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModal(); });

// ---- Guardar ----------------------------------------------
async function onGuardar(e) {
    e.preventDefault();
    const errDiv = document.getElementById('modalError');
    errDiv.classList.remove('show');

    const id = parseInt(document.getElementById('fId').value);
    const payload = { id };
    for (const [el, campo] of Object.entries(campos)) {
        payload[campo] = document.getElementById(el).value;
    }

    if (!payload.nombre_disp.trim() || !payload.id_tipo_disp) {
        document.getElementById('modalErrorMsg').textContent = 'Completá nombre y tipo.';
        errDiv.classList.add('show');
        return;
    }

    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
        await apiFetch('api/guardar_dispositivo.php', 'POST', payload);
        cerrarModal();
        mostrarExito(id ? 'Dispositivo actualizado.' : 'Dispositivo creado.');
        cargarDispositivos();
    } catch (err) {
        document.getElementById('modalErrorMsg').textContent = err.message;
        errDiv.classList.add('show');
    }

    btn.disabled = false;
    btn.textContent = 'Guardar';
}

// ---- Eliminar ---------------------------------------------
async function eliminar(id, nombre) {
    if (!confirm(`¿Eliminar el dispositivo "${nombre}"?\nEsta acción no se puede deshacer.`)) return;
    try {
        const resp = await apiFetch('api/eliminar_dispositivo.php', 'POST', { id });
        mostrarExito(resp.mensaje);
        cargarDispositivos();
    } catch (err) {
        mostrarError(err.message);
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
    setTimeout(() => d.classList.remove('show'), 6000);
}

async function apiFetch(url, method = 'GET', data = null) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (data) opts.body = JSON.stringify(data);
    const res = await fetch(url, opts);
    const raw = await res.text();
    let json = {};
    try {
        json = raw ? JSON.parse(raw) : {};
    } catch (e) {
        throw new Error('La API devolvió una respuesta inválida.');
    }
    if (!res.ok) throw new Error(json.error || 'Error del servidor');
    return json;
}

function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>
</body>
</html>
