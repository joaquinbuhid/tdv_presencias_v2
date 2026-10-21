<?php
require_once __DIR__ . '/auth.php';
requireBackofficePage();
// Los oficinistas ven solo sus tickets y pueden crear nuevos; el resto es de admin.
$esAdmin = esAdminReal();
$ocultoSiNoAdmin = $esAdmin ? '' : ' style="display:none;"';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Tickets</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css?v=20261020">
    <link rel="stylesheet" href="../css/inventario.css?v=20261020">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

<main class="inv-shell">
    <div class="inv-head">
        <h1 class="inv-title">&#x1F3AB; <?= $esAdmin ? 'Tickets' : 'Mis tickets' ?> <span class="inv-counter" id="counter"></span></h1>
        <button class="btn btn-primary btn-sm" onclick="abrirModal(0)">+ Nuevo ticket</button>
    </div>

    <div class="alert alert-danger"  id="tableError"   role="alert"><span>&#9888;</span><span id="tableErrorMsg"></span></div>
    <div class="alert alert-success" id="tableSuccess" role="alert"><span>&#9989;</span><span id="tableSuccessMsg"></span></div>

    <div class="inv-filters">
        <div class="inv-tabs" id="tabsEstado">
            <button type="button" data-estado="abiertos" class="active">Abiertos</button>
            <button type="button" data-estado="resueltos">Resueltos</button>
            <button type="button" data-estado="">Todos</button>
        </div>
        <select id="fDispFiltro"><option value="">Todos los dispositivos</option></select>
        <input type="text" id="fBuscar" class="inv-search" placeholder="Buscar en problema, solución o personas...">
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

<!-- MODAL crear / editar / resolver -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Nuevo ticket</span>
            <button class="modal-close" onclick="cerrarModal()" aria-label="Cerrar">&#x2715;</button>
        </div>

        <div class="alert alert-danger" id="modalError" role="alert">
            <span>&#9888;</span><span id="modalErrorMsg"></span>
        </div>

        <form id="formTicket" novalidate>
            <input type="hidden" id="fId" value="0">

            <div class="form-group">
                <label for="fDispositivo">Dispositivo <span class="req">*</span></label>
                <select id="fDispositivo" required><option value="">- Elegir -</option></select>
            </div>

            <div class="form-row"<?= $ocultoSiNoAdmin ?>>
                <div class="form-group">
                    <label for="fPedidoPor">Pedido por</label>
                    <select id="fPedidoPor"><option value="">- Sin indicar -</option></select>
                </div>
                <div class="form-group">
                    <label for="fCreacion">Fecha y hora</label>
                    <input type="datetime-local" id="fCreacion">
                </div>
            </div>

            <div class="form-group">
                <label for="fProblema">Problema <span class="req">*</span></label>
                <textarea id="fProblema" rows="3" required placeholder="Contá qué pasa: qué estabas haciendo, qué error aparece..."></textarea>
            </div>

            <div<?= $ocultoSiNoAdmin ?>>
            <div class="modal-section">Resolución</div>

            <div class="form-group">
                <label for="fSolucion">Solución</label>
                <textarea id="fSolucion" rows="3" placeholder="Si completás la solución, el ticket queda resuelto."></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fResueltoPor">Resuelto por</label>
                    <select id="fResueltoPor"><option value="">- Yo -</option></select>
                </div>
                <div class="form-group">
                    <label for="fResuelto">Resuelto el</label>
                    <input type="datetime-local" id="fResuelto">
                </div>
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
const ES_ADMIN = <?= $esAdmin ? 'true' : 'false' ?>;
let tickets = [];
let yo = 0;
const params = new URLSearchParams(location.search);
let estado = params.has('estado') ? params.get('estado') : 'abiertos';

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('formTicket').addEventListener('submit', onGuardar);
    document.getElementById('fBuscar').addEventListener('input', renderTabla);
    document.getElementById('fDispFiltro').addEventListener('change', cargarTickets);
    document.getElementById('fSolucion').addEventListener('input', () => {
        const r = document.getElementById('fResuelto');
        if (document.getElementById('fSolucion').value.trim() && !r.value) r.value = ahoraLocal();
    });
    document.getElementById('tabsEstado').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        estado = b.dataset.estado;
        marcarTab();
        cargarTickets();
    });
    document.getElementById('tablaWrap').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const id = Number(btn.dataset.id);
        if (btn.dataset.action === 'edit')    abrirModal(id);
        if (btn.dataset.action === 'resolve') abrirModal(id, true);
        if (btn.dataset.action === 'delete')  eliminar(id);
    });

    marcarTab();
    await cargarListas();
    if (params.get('dispositivo')) document.getElementById('fDispFiltro').value = params.get('dispositivo');
    await cargarTickets();
    if (params.get('nuevo')) {
        abrirModal(0, false, Number(params.get('nuevo')));
        params.delete('nuevo');
        history.replaceState(null, '', location.pathname + (params.toString() ? '?' + params : ''));
    }
});

function marcarTab() {
    document.querySelectorAll('#tabsEstado button').forEach(b =>
        b.classList.toggle('active', b.dataset.estado === estado));
}

async function cargarListas() {
    try {
        const l = await apiFetch('api/get_inventario_listas.php');
        yo = Number(l.yo);
        const opt = d =>
            `<option value="${Number(d.id_disp)}">${esc(d.nombre_disp)} · ${esc(d.tipo_nombre)}${d.empleado_nombre ? ' · ' + esc(d.empleado_nombre) : ''}</option>`;
        const disps = l.dispositivos.map(opt).join('');
        document.getElementById('fDispFiltro').insertAdjacentHTML('beforeend', disps);

        // Al oficinista le mostramos primero sus propios dispositivos.
        const mios = l.dispositivos.filter(d => Number(d.es_mio));
        document.getElementById('fDispositivo').insertAdjacentHTML('beforeend', !ES_ADMIN && mios.length
            ? `<optgroup label="Mis dispositivos">${mios.map(opt).join('')}</optgroup>
               <optgroup label="Otros">${l.dispositivos.filter(d => !Number(d.es_mio)).map(opt).join('')}</optgroup>`
            : disps);

        if (!ES_ADMIN) return;

        const emp = arr => arr.map(e => `<option value="${Number(e.id_empleado)}">${esc(e.nombre)}</option>`).join('');
        document.getElementById('fPedidoPor').insertAdjacentHTML('beforeend', emp(l.empleados));
        // Resuelven los usuarios de oficina (3 = oficinista, 4 = administrador).
        document.getElementById('fResueltoPor').insertAdjacentHTML('beforeend',
            emp(l.empleados.filter(e => [3, 4].includes(Number(e.tipo)) && Number(e.id_empleado) !== yo)));
    } catch (e) {
        mostrarError(e.message);
    }
}

// ---- Tabla ------------------------------------------------
async function cargarTickets() {
    const disp = document.getElementById('fDispFiltro').value;
    const qs = new URLSearchParams({ estado });
    if (disp) qs.set('dispositivo', disp);
    try {
        tickets = await apiFetch('api/get_tickets.php?' + qs);
        renderTabla();
    } catch (e) {
        document.getElementById('tablaWrap').innerHTML =
            `<p style="color:var(--danger);padding:1rem;">${esc(e.message)}</p>`;
    }
}

function renderTabla() {
    const wrap = document.getElementById('tablaWrap');
    const q = document.getElementById('fBuscar').value.trim().toLowerCase();

    const list = tickets.filter(t => !q || [t.problema, t.solucion, t.nombre_disp, t.pedido_por_nombre, t.resuelto_por_nombre]
        .some(v => v && String(v).toLowerCase().includes(q)));

    document.getElementById('counter').textContent =
        list.length === tickets.length ? `(${tickets.length})` : `(${list.length} de ${tickets.length})`;

    if (!list.length) {
        wrap.innerHTML = `<p style="text-align:center;color:var(--text-muted);padding:1.5rem;">
            ${tickets.length ? 'Ningún ticket coincide con la búsqueda.' : 'No hay tickets para mostrar.'}</p>`;
        return;
    }

    const tbody = list.map(t => {
        const resuelto = !!t.fecha_hora_resuelto;
        const estadoHtml = resuelto
            ? `<span class="pill pill-resuelto">Resuelto</span>
               <div class="inv-muted">${fmtFecha(t.fecha_hora_resuelto)}${t.resuelto_por_nombre ? ' · ' + esc(t.resuelto_por_nombre) : ''}</div>`
            : `<span class="pill pill-abierto">Abierto</span>
               <div class="inv-muted">hace ${hace(t.fecha_hora_creacion)}</div>`;
        return `<tr>
            <td class="inv-main"><div>
                <strong>#${Number(t.id_ticket)} · ${esc(t.nombre_disp)}</strong>
                <div class="inv-muted">${esc(t.tipo_nombre)} · ${fmtFecha(t.fecha_hora_creacion)}</div>
            </div></td>
            ${ES_ADMIN ? `<td data-label="Pedido por"><div>${t.pedido_por_nombre ? esc(t.pedido_por_nombre) : '<span class="inv-muted">-</span>'}</div></td>` : ''}
            <td data-label="Problema"><div class="inv-problema">${esc(t.problema)}${t.solucion
                ? `<div class="inv-muted" style="margin-top:.35rem;"><b>Solución:</b> ${esc(t.solucion)}</div>` : ''}</div></td>
            <td data-label="Estado"><div>${estadoHtml}</div></td>
            ${ES_ADMIN ? `<td><div class="actions">
                ${resuelto ? '' : `<button class="btn btn-success btn-sm" data-action="resolve" data-id="${Number(t.id_ticket)}">&#10003; Resolver</button>`}
                <button class="btn btn-outline btn-sm" data-action="edit" data-id="${Number(t.id_ticket)}">&#9998; Editar</button>
                <button class="btn btn-danger btn-sm" data-action="delete" data-id="${Number(t.id_ticket)}" aria-label="Eliminar">&#x1F5D1;</button>
            </div></td>` : ''}
        </tr>`;
    }).join('');

    wrap.innerHTML = `
        <table class="inv-table">
            <thead><tr>
                <th>Ticket</th>${ES_ADMIN ? '<th>Pedido por</th>' : ''}<th>Problema</th><th>Estado</th>${ES_ADMIN ? '<th>Acciones</th>' : ''}
            </tr></thead>
            <tbody>${tbody}</tbody>
        </table>`;
}

// ---- Modal ------------------------------------------------
function abrirModal(id, resolver = false, dispositivoInicial = 0) {
    document.getElementById('modalError').classList.remove('show');
    document.getElementById('formTicket').reset();
    document.getElementById('fId').value = id;

    const t = id ? tickets.find(x => Number(x.id_ticket) === id) : null;
    if (id && !t) return;

    document.getElementById('modalTitle').textContent =
        !id ? 'Nuevo ticket' : resolver ? `Resolver ticket #${id}` : `Editar ticket #${id}`;

    if (t) {
        document.getElementById('fDispositivo').value = t.id_dispositivo;
        document.getElementById('fPedidoPor').value   = t.pedido_por || '';
        document.getElementById('fCreacion').value    = aInputFecha(t.fecha_hora_creacion);
        document.getElementById('fProblema').value    = t.problema || '';
        document.getElementById('fSolucion').value    = t.solucion || '';
        setResueltoPor(t.resuelto_por);
        document.getElementById('fResuelto').value    = aInputFecha(t.fecha_hora_resuelto);
    } else {
        document.getElementById('fCreacion').value = ahoraLocal();
        if (dispositivoInicial) document.getElementById('fDispositivo').value = dispositivoInicial;
    }
    if (resolver && !document.getElementById('fResuelto').value) {
        document.getElementById('fResuelto').value = ahoraLocal();
    }

    document.getElementById('modalOverlay').classList.add('open');
    document.getElementById(resolver ? 'fSolucion' : (t || dispositivoInicial ? 'fProblema' : 'fDispositivo')).focus();
}

// "- Yo -" (valor vacío) representa al usuario logueado.
function setResueltoPor(idEmp) {
    const sel = document.getElementById('fResueltoPor');
    const v = Number(idEmp) || 0;
    sel.value = (!v || v === yo) ? '' : String(v);
    if (v && v !== yo && sel.value !== String(v)) {
        // Quien lo resolvió ya no es usuario de oficina activo: se agrega para no perder el dato.
        const t = tickets.find(x => Number(x.resuelto_por) === v);
        sel.insertAdjacentHTML('beforeend', `<option value="${v}">${esc(t?.resuelto_por_nombre || ('#' + v))}</option>`);
        sel.value = String(v);
    }
}

function cerrarModal() {
    document.getElementById('modalOverlay').classList.remove('open');
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
    const payload = {
        id,
        id_dispositivo:      document.getElementById('fDispositivo').value,
        pedido_por:          document.getElementById('fPedidoPor').value,
        fecha_hora_creacion: document.getElementById('fCreacion').value,
        problema:            document.getElementById('fProblema').value,
        solucion:            document.getElementById('fSolucion').value,
        resuelto_por:        document.getElementById('fResueltoPor').value || yo,
        fecha_hora_resuelto: document.getElementById('fResuelto').value,
    };

    if (!payload.id_dispositivo || !payload.problema.trim()) {
        document.getElementById('modalErrorMsg').textContent = 'Elegí el dispositivo y describí el problema.';
        errDiv.classList.add('show');
        return;
    }

    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
        await apiFetch('api/guardar_ticket.php', 'POST', payload);
        cerrarModal();
        mostrarExito(id ? 'Ticket actualizado.' : 'Ticket creado.');
        cargarTickets();
    } catch (err) {
        document.getElementById('modalErrorMsg').textContent = err.message;
        errDiv.classList.add('show');
    }

    btn.disabled = false;
    btn.textContent = 'Guardar';
}

// ---- Eliminar ---------------------------------------------
async function eliminar(id) {
    if (!confirm(`¿Eliminar el ticket #${id}?\nEsta acción no se puede deshacer.`)) return;
    try {
        const resp = await apiFetch('api/eliminar_ticket.php', 'POST', { id });
        mostrarExito(resp.mensaje);
        cargarTickets();
    } catch (err) {
        mostrarError(err.message);
    }
}

// ---- Fechas -----------------------------------------------
function parseFecha(s) { return s ? new Date(String(s).replace(' ', 'T')) : null; }

function fmtFecha(s) {
    const d = parseFecha(s);
    if (!d || isNaN(d)) return '';
    const p = n => String(n).padStart(2, '0');
    return `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

function aInputFecha(s) { return s ? String(s).replace(' ', 'T').slice(0, 16) : ''; }

function ahoraLocal() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
}

function hace(s) {
    const d = parseFecha(s);
    if (!d || isNaN(d)) return '';
    const min = Math.max(0, Math.round((Date.now() - d) / 60000));
    if (min < 60)   return `${min} min`;
    if (min < 1440) return `${Math.round(min / 60)} h`;
    return `${Math.round(min / 1440)} d`;
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
