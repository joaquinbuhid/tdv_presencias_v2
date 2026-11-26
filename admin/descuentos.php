<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api/descuentos_helper.php';
requireBackofficePage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Descuentos</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css?v=20261020">
    <link rel="stylesheet" href="../css/inventario.css?v=20261020">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <style>
        [hidden] { display:none !important; }
        .ds-num { text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums; }
        .ds-vacio { padding:2rem;text-align:center;color:var(--text-muted); }

        .pill-carga    { background:#ebf5fb;color:#1a5276; }
        .pill-revision { background:#fef5e7;color:#9a6300; }

        /* Cabecera de lista */
        .ds-cab { display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;margin-bottom:.8rem; }
        .ds-cab h1 { font-size:1.25rem;color:var(--primary);margin:0;display:flex;align-items:center;gap:.5rem; }
        .ds-cab .acciones { margin-left:auto;display:flex;gap:.5rem;flex-wrap:wrap; }
        .ds-cab .btn { width:auto; }
        .btn-link { background:none;border:0;cursor:pointer;color:var(--text-muted);font-size:.9rem;padding:.2rem .3rem; }
        .btn-link:hover { color:var(--primary); }

        .ds-etapa {
            display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;border-radius:10px;padding:.7rem 1rem;
            margin-bottom:1rem;font-size:.9rem;border:1px solid var(--border);background:#fff;
        }
        .ds-etapa.carga { border-left:4px solid #2980b9; }
        .ds-etapa.revision { border-left:4px solid #f39c12;background:#fffbf2; }
        .ds-vivo { margin-left:auto;font-size:.8rem;color:var(--text-muted);display:flex;align-items:center;gap:.35rem;white-space:nowrap; }
        .ds-vivo .punto { width:8px;height:8px;border-radius:50%;background:#27ae60;box-shadow:0 0 0 3px rgba(39,174,96,.2); }
        .ds-vivo.off .punto { background:#bbb;box-shadow:none; }

        .ds-agregar { display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-start;margin-bottom:1rem; }
        .ds-agregar input { flex:1 1 240px;width:auto;padding:.6rem .8rem;font-size:.95rem; }
        .ds-agregar .btn { width:auto; }
        .ds-agregar .ds-buscar { flex:0 1 230px;margin-left:auto; }
        .ds-pegar textarea { width:100%;min-height:130px;font-family:inherit;padding:.7rem .9rem;font-size:.92rem;margin-bottom:.5rem; }

        .ds-table td { vertical-align:middle;padding:.3rem .55rem; }
        .ds-table th { white-space:nowrap; }
        .ds-table th.n, .ds-table td.n { width:2.4rem;color:var(--text-muted);text-align:right; }
        .ds-table td.total { font-weight:700;min-width:120px; }
        .ds-table tr.ds-foot td { font-weight:700;background:#f7f9fc;border-top:2px solid var(--primary); }
        .ds-table tr.ds-foot:hover td { background:#f7f9fc !important; }

        .ds-input {
            width:100%;border:1.5px solid transparent;border-radius:6px;background:transparent;
            padding:.35rem .5rem;font-size:.9rem;font-family:inherit;color:var(--text);
            transition:border-color .2s, background .6s;
        }
        .ds-input:hover:not([readonly]) { border-color:var(--border);background:#fff; }
        .ds-input:focus { border-color:var(--accent);background:#fff;outline:none; }
        .ds-input.num { text-align:right;font-variant-numeric:tabular-nums;min-width:110px; }
        .ds-input[readonly], .ds-input:disabled { cursor:default; }
        select.ds-input { min-width:150px;cursor:pointer;appearance:auto; }
        select.ds-input:disabled { color:var(--text);opacity:1;appearance:none; }
        select.ds-input.sin-empresa { color:var(--text-muted); }
        .ds-agregar .ds-filtro-emp { flex:0 1 180px;width:auto;padding:.6rem .8rem;font-size:.95rem; }
        .ds-input.ok { border-color:#58d68d; }
        .ds-input.error { border-color:var(--danger);background:#fdf2f0; }
        .ds-input.remoto { background:#fff6cc; }          /* lo cambió otro usuario */
        .ds-fila-nueva td { animation:dsNueva 2s ease; }
        @keyframes dsNueva { from { background:#fff6cc; } to { background:transparent; } }
        .ds-icono { background:none;border:0;cursor:pointer;font-size:.95rem;opacity:.45;padding:.25rem; }
        .ds-icono:hover { opacity:1; }

        /* Historial */
        .ds-hist { width:100%;border-collapse:collapse;font-size:.85rem; }
        .ds-hist td { padding:.45rem .5rem;border-bottom:1px solid var(--bg);vertical-align:top; }
        .ds-hist .cuando { color:var(--text-muted);white-space:nowrap; }
        .ds-hist .de { color:var(--text-muted);text-decoration:line-through; }

        @media (max-width:760px) {
            .ds-cab .acciones { margin-left:0;width:100%; }
            .ds-cab .acciones .btn { flex:1 1 45%; }
            .ds-vivo { margin-left:0; }
            .ds-agregar .ds-buscar, .ds-agregar .ds-filtro-emp { margin-left:0;flex:1 1 100%; }
            .ds-table td.n { display:none !important; }
            .ds-input.num { min-width:0; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

<main class="inv-shell">
    <div class="alert alert-danger"  id="tableError"   role="alert"><span>&#9888;</span><span id="tableErrorMsg"></span></div>
    <div class="alert alert-success" id="tableSuccess" role="alert"><span>&#9989;</span><span id="tableSuccessMsg"></span></div>

    <!-- ============ LISTADO ============ -->
    <section id="vistaListas">
        <div class="inv-head">
            <h1 class="inv-title">&#x2796; Descuentos <span class="inv-counter" id="counterListas"></span></h1>
            <button class="btn btn-primary btn-sm" type="button" onclick="abrirModalLista('nueva')">+ Nueva lista de descuentos</button>
        </div>
        <div class="card inv-card-wrap" style="overflow-x:auto;">
            <div id="listasWrap"><div class="ds-vacio"><div class="spinner spinner-dark" style="margin:0 auto .8rem;"></div>Cargando...</div></div>
        </div>
    </section>

    <!-- ============ UNA LISTA ============ -->
    <section id="vistaLista" hidden>
        <div class="ds-cab">
            <button class="btn btn-outline btn-sm" type="button" onclick="volverAListas()">&#x2190; Listas</button>
            <h1><span id="listaNombre"></span> <button class="btn-link" type="button" id="btnRenombrar" onclick="abrirModalLista('renombrar')" title="Cambiar nombre">&#x270E;</button></h1>
            <div class="acciones">
                <button class="btn btn-outline btn-sm" type="button" onclick="abrirHistorial()">&#x1F552; Historial</button>
                <button class="btn btn-outline btn-sm" type="button" id="btnEtapa"></button>
                <button class="btn btn-primary btn-sm" type="button" id="btnExcel">&#x1F4CA; Exportar Vigilador | Total</button>
                <button class="btn btn-danger btn-sm" type="button" id="btnEliminarLista" onclick="eliminarLista()">&#x1F5D1;</button>
            </div>
        </div>

        <div class="ds-etapa" id="etapaBox">
            <span id="etapaTexto"></span>
            <span class="ds-vivo" id="vivo"><span class="punto"></span><span id="vivoTxt">En vivo</span></span>
        </div>

        <div class="card" style="padding:1.2rem;">
            <form class="ds-agregar" id="formNombre" autocomplete="off">
                <input type="text" id="nuevoNombre" maxlength="150" placeholder="Nombre del vigilador y Enter">
                <button type="submit" class="btn btn-primary btn-sm">+ Agregar</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="togglePegar()">&#x1F4CB; Pegar varios</button>
                <input type="text" id="buscar" class="ds-buscar" placeholder="&#x1F50D; Buscar vigilador">
                <select id="filtroEmpresa" class="ds-filtro-emp"><option value="">Todas las empresas</option></select>
            </form>
            <div class="ds-pegar" id="pegarWrap" hidden>
                <textarea id="pegarNombres" placeholder="Un nombre por línea. Los que ya estén en la lista se saltean."></textarea>
                <div style="display:flex;gap:.6rem;justify-content:flex-end;margin-bottom:1rem;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="togglePegar()">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="agregarPegados()">Agregar todos</button>
                </div>
            </div>
            <div style="overflow-x:auto;" id="tablaWrap"></div>
        </div>
    </section>
</main>

<!-- MODAL nombre de lista -->
<div class="modal-overlay" id="modalLista">
    <div class="modal" style="max-width:460px;">
        <div class="modal-header">
            <span class="modal-title" id="modalListaTitulo"></span>
            <button class="modal-close" type="button" onclick="cerrarModal('modalLista')" aria-label="Cerrar">&#x2715;</button>
        </div>
        <form id="formLista" novalidate>
            <div class="form-group">
                <label for="fListaNombre">Nombre <span class="req">*</span></label>
                <input type="text" id="fListaNombre" required maxlength="120" placeholder="Ej: Descuentos Noviembre 2026" autocomplete="off">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalLista')">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="width:auto;min-width:120px;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL historial -->
<div class="modal-overlay" id="modalHist">
    <div class="modal" style="max-width:760px;">
        <div class="modal-header">
            <span class="modal-title" id="histTitulo">Historial</span>
            <button class="modal-close" type="button" onclick="cerrarModal('modalHist')" aria-label="Cerrar">&#x2715;</button>
        </div>
        <div id="histCuerpo"></div>
    </div>
</div>

<script>
const CSRF = <?= json_encode(descCsrf()) ?>;
const API  = 'api/descuentos.php';
const CAMPOS = ['adelantos', 'ropa_medico', 'otros'];
const REFRESCO_MS = 8000;

let esAdmin = false, puedeEditar = false;
let listas = [], lista = null, items = [], empresas = [];
let modalModo = 'nueva';
let timerVivo = null, ultimoRefresco = null, pendienteRepintar = false;

const $ = id => document.getElementById(id);
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const pesos = n => '$ ' + Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const montoInput = n => {
    if (n === null || n === undefined) return '';
    const dec = Number.isInteger(Number(n)) ? 0 : 2;
    return Number(n).toLocaleString('es-AR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
};
const crudo = v => v === null || v === undefined ? '' : String(v);   // valor "anterior" que se manda al servidor
const totalItem = i => (i.adelantos || 0) + (i.ropa_medico || 0) + (i.otros || 0);
function fechaHora(f) { const d = new Date(String(f).replace(' ', 'T')); return isNaN(d) ? '' : d.toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' }); }
function fechaCorta(f) { const d = new Date(String(f).replace(' ', 'T')); return isNaN(d) ? '' : d.toLocaleDateString('es-AR'); }

function mostrarExito(msg) {
    const d = $('tableSuccess'); $('tableSuccessMsg').textContent = msg;
    $('tableError').classList.remove('show'); d.classList.add('show');
    clearTimeout(d._t); d._t = setTimeout(() => d.classList.remove('show'), 3500);
}
function mostrarError(msg) {
    const d = $('tableError'); $('tableErrorMsg').textContent = msg;
    $('tableSuccess').classList.remove('show'); d.classList.add('show');
    clearTimeout(d._t); d._t = setTimeout(() => d.classList.remove('show'), 9000);
}

async function get(accion, params = {}) {
    const r = await fetch(`${API}?` + new URLSearchParams({ accion, ...params }));
    const data = await r.json().catch(() => ({ error: 'Respuesta inválida del servidor' }));
    if (!r.ok || data.error) throw Object.assign(new Error(data.error || 'Error inesperado'), { data, status: r.status });
    return data;
}
async function post(accion, campos) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(campos)) fd.append(k, v);
    fd.append('csrf', CSRF);
    const r = await fetch(`${API}?accion=${accion}`, { method: 'POST', body: fd });
    const data = await r.json().catch(() => ({ error: 'Respuesta inválida del servidor' }));
    if (!r.ok || data.error) throw Object.assign(new Error(data.error || 'Error inesperado'), { data, status: r.status });
    return data;
}

// ======================= LISTADO =======================
const pillEtapa = e => e === 'revision'
    ? '<span class="pill pill-revision">En revisión</span>'
    : '<span class="pill pill-carga">En carga</span>';

async function cargarListas() {
    try { const d = await get('listas'); esAdmin = d.es_admin; listas = d.listas; pintarListas(); }
    catch (e) { mostrarError(e.message); }
}
function pintarListas() {
    $('counterListas').textContent = listas.length ? `(${listas.length})` : '';
    if (!listas.length) {
        $('listasWrap').innerHTML = `<div class="ds-vacio">Todavía no hay listas de descuentos.<br>
            <button class="btn btn-primary btn-sm" style="width:auto;margin-top:1rem;" onclick="abrirModalLista('nueva')">+ Crear la primera</button></div>`;
        return;
    }
    $('listasWrap').innerHTML = `
        <table class="inv-table">
            <thead><tr><th>Lista</th><th>Etapa</th><th>Creada</th><th class="ds-num">Vigiladores</th><th class="ds-num">Total descuentos</th><th>Último cambio</th><th></th></tr></thead>
            <tbody>${listas.map(l => `
                <tr style="cursor:pointer;" data-abrir="${l.id}">
                    <td data-label="Lista" class="inv-main"><div><b>${esc(l.nombre)}</b></div></td>
                    <td data-label="Etapa"><div>${pillEtapa(l.estado)}</div></td>
                    <td data-label="Creada"><div>${fechaCorta(l.creado_en)}${l.creado_por ? `<div class="inv-muted">por ${esc(l.creado_por)}</div>` : ''}</div></td>
                    <td data-label="Vigiladores" class="ds-num"><div>${l.cantidad}</div></td>
                    <td data-label="Total" class="ds-num"><div><b>${pesos(l.total)}</b></div></td>
                    <td data-label="Último cambio"><div class="inv-muted">${l.ultimo_cambio ? fechaHora(l.ultimo_cambio) : '—'}</div></td>
                    <td><div class="actions"><button class="btn btn-primary btn-sm" type="button" data-abrir="${l.id}">Abrir</button></div></td>
                </tr>`).join('')}
            </tbody>
        </table>`;
}
$('listasWrap').addEventListener('click', e => { const t = e.target.closest('[data-abrir]'); if (t) abrirLista(+t.dataset.abrir); });

// Crear / renombrar
function abrirModalLista(modo) {
    modalModo = modo;
    $('modalListaTitulo').textContent = modo === 'nueva' ? 'Nueva lista de descuentos' : 'Cambiar nombre de la lista';
    $('fListaNombre').value = modo === 'nueva' ? '' : lista.nombre;
    $('modalLista').classList.add('open');
    setTimeout(() => $('fListaNombre').select(), 50);
}
function cerrarModal(id) { $(id).classList.remove('open'); }
$('formLista').addEventListener('submit', async e => {
    e.preventDefault();
    const nombre = $('fListaNombre').value.trim(); if (!nombre) return $('fListaNombre').focus();
    try {
        if (modalModo === 'nueva') {
            const r = await post('crear_lista', { nombre });
            cerrarModal('modalLista'); await abrirLista(r.id);
            mostrarExito('Lista creada. Ya pueden cargar descuentos varios usuarios a la vez.');
            setTimeout(() => $('nuevoNombre').focus(), 100);
        } else {
            await post('renombrar_lista', { id: lista.id, nombre });
            lista.nombre = nombre; pintarCabecera(); cerrarModal('modalLista'); mostrarExito('Nombre actualizado.');
        }
    } catch (err) { mostrarError(err.message); }
});

async function eliminarLista() {
    if (!confirm(`¿Eliminar la lista "${lista.nombre}" con sus ${items.length} vigiladores?`)) return;
    try { await post('eliminar_lista', { id: lista.id }); volverAListas(); mostrarExito('Lista eliminada.'); }
    catch (e) { mostrarError(e.message); }
}

// ======================= UNA LISTA =======================
async function abrirLista(id) {
    try {
        const d = await get('detalle', { id });
        aplicarDetalle(d);
        $('vistaListas').hidden = true; $('vistaLista').hidden = false;
        $('buscar').value = ''; $('filtroEmpresa').value = '';
        history.replaceState(null, '', '?lista=' + id);
        pintarCabecera(); pintarTabla();
        iniciarVivo();
    } catch (e) { mostrarError(e.message); volverAListas(); }
}
function aplicarDetalle(d) {
    esAdmin = d.es_admin; puedeEditar = d.puede_editar; lista = d.lista; items = d.items;
    if (d.empresas && !empresas.length) {
        empresas = d.empresas;
        $('filtroEmpresa').innerHTML = '<option value="">Todas las empresas</option><option value="-">Sin empresa</option>' +
            empresas.map(e => `<option value="${esc(e)}">${esc(e)}</option>`).join('');
    }
    ultimoRefresco = Date.now();
}
function volverAListas() {
    detenerVivo(); lista = null; items = [];
    $('vistaLista').hidden = true; $('vistaListas').hidden = false;
    history.replaceState(null, '', location.pathname);
    cargarListas();
}

function pintarCabecera() {
    $('listaNombre').textContent = lista.nombre;
    $('btnRenombrar').style.display = puedeEditar ? '' : 'none';
    const rev = lista.estado === 'revision';
    $('etapaBox').className = 'ds-etapa ' + lista.estado;
    $('etapaTexto').innerHTML = rev
        ? `${pillEtapa('revision')} Pasada a revisión${lista.revision_por ? ' por <b>' + esc(lista.revision_por) + '</b>' : ''}${lista.revision_en ? ' el ' + fechaHora(lista.revision_en) : ''} ·
           ${esAdmin ? 'Sólo los administradores pueden editar.' : 'Está en modo lectura.'}`
        : `${pillEtapa('carga')} Todos pueden agregar y editar a la vez. Los cambios se guardan solos y los de los demás aparecen automáticamente.`;
    $('btnEtapa').style.display = esAdmin ? '' : 'none';
    $('btnEtapa').innerHTML = rev ? '&#x21A9; Reabrir carga' : '&#x2714; Pasar a revisión';
    $('btnExcel').style.display = esAdmin ? '' : 'none';
    $('btnEliminarLista').style.display = esAdmin ? '' : 'none';
    $('formNombre').querySelectorAll('input:not(.ds-buscar), button').forEach(el => el.style.display = puedeEditar ? '' : 'none');
    if (!puedeEditar) $('pegarWrap').hidden = true;
}

$('btnEtapa').addEventListener('click', async () => {
    const aRevision = lista.estado === 'carga';
    const msg = aRevision
        ? `¿Pasar "${lista.nombre}" a revisión? Los oficinistas ya no van a poder modificarla.`
        : `¿Reabrir la carga de "${lista.nombre}"? Todos van a poder volver a editarla.`;
    if (!confirm(msg)) return;
    try {
        await post('cambiar_etapa', { id: lista.id, estado: aRevision ? 'revision' : 'carga' });
        aplicarDetalle(await get('detalle', { id: lista.id })); pintarCabecera(); pintarTabla();
        mostrarExito(aRevision ? 'Lista pasada a revisión.' : 'Carga reabierta.');
    } catch (e) { mostrarError(e.message); }
});

// ---------- Tabla ----------
function celdaMonto(i, campo) {
    return `<input class="ds-input num" data-campo="${campo}" data-orig="${crudo(i[campo])}" inputmode="decimal"
        value="${montoInput(i[campo])}" ${puedeEditar ? 'placeholder="—"' : 'readonly'}>`;
}
function celdaEmpresa(i) {
    return `<select class="ds-input${i.empresa ? '' : ' sin-empresa'}" data-campo="empresa" data-orig="${esc(i.empresa || '')}" ${puedeEditar ? '' : 'disabled'}>
        <option value="">${puedeEditar ? 'Elegir...' : '—'}</option>
        ${empresas.map(e => `<option value="${esc(e)}"${e === i.empresa ? ' selected' : ''}>${esc(e)}</option>`).join('')}
    </select>`;
}
function filaHtml(i, n) {
    return `
    <tr data-id="${i.id}" title="${esc(i.actualizado_por ? 'Último cambio: ' + i.actualizado_por + ', ' + fechaHora(i.actualizado_en) : 'Agregado por ' + (i.creado_por || '—') + ', ' + fechaHora(i.creado_en))}">
        <td class="n">${n}</td>
        <td data-label="Vigilador"><div><input class="ds-input" data-campo="nombre" data-orig="${esc(i.nombre)}" value="${esc(i.nombre)}" maxlength="150" ${puedeEditar ? '' : 'readonly'}></div></td>
        <td data-label="Empresa"><div>${celdaEmpresa(i)}</div></td>
        <td data-label="Adelantos"><div>${celdaMonto(i, 'adelantos')}</div></td>
        <td data-label="Ropa/médico"><div>${celdaMonto(i, 'ropa_medico')}</div></td>
        <td data-label="Otros"><div>${celdaMonto(i, 'otros')}</div></td>
        <td data-label="Total" class="ds-num total"><div data-total>${pesos(totalItem(i))}</div></td>
        <td><div style="display:flex;gap:.1rem;justify-content:flex-end;">
            <button class="ds-icono" type="button" data-hist="${i.id}" title="Historial de este vigilador">&#x1F552;</button>
            ${puedeEditar ? `<button class="ds-icono" type="button" data-borrar="${i.id}" title="Quitar de la lista">&#x1F5D1;</button>` : ''}
        </div></td>
    </tr>`;
}
function coincide(i) {
    const q = $('buscar').value.trim().toLowerCase(), emp = $('filtroEmpresa').value;
    if (q && !i.nombre.toLowerCase().includes(q)) return false;
    if (emp === '-') return !i.empresa;
    return !emp || i.empresa === emp;
}
function visibles() { return items.filter(coincide); }
function pintarTabla(idsNuevos = []) {
    pendienteRepintar = false;
    if (!items.length) {
        $('tablaWrap').innerHTML = `<div class="ds-vacio">${puedeEditar ? 'Todavía no hay vigiladores. Escribí un nombre arriba y apretá Enter.' : 'Esta lista no tiene vigiladores.'}</div>`;
        return;
    }
    const lst = visibles();
    $('tablaWrap').innerHTML = `
        <table class="inv-table ds-table">
            <thead><tr><th class="n">#</th><th style="min-width:200px;">Vigilador</th><th>Empresa</th><th class="ds-num">Adelantos</th><th class="ds-num">Desc. Ropa/médico</th><th class="ds-num">Otros</th><th class="ds-num">Total</th><th style="width:64px;"></th></tr></thead>
            <tbody id="tbody">${lst.map(i => filaHtml(i, items.indexOf(i) + 1)).join('')}</tbody>
            <tfoot><tr class="ds-foot">
                <td class="n"></td>
                <td data-label="Totales" colspan="2">TOTALES <span class="inv-muted" style="font-weight:400;" id="totCount"></span></td>
                <td data-label="Adelantos" class="ds-num" id="totAdelantos"></td>
                <td data-label="Ropa/médico" class="ds-num" id="totRopa"></td>
                <td data-label="Otros" class="ds-num" id="totOtros"></td>
                <td data-label="Total" class="ds-num" id="totTotal"></td>
                <td></td>
            </tr></tfoot>
        </table>`;
    idsNuevos.forEach(id => document.querySelector(`tr[data-id="${id}"]`)?.classList.add('ds-fila-nueva'));
    actualizarTotales();
}
function actualizarTotales() {
    if (!$('totTotal')) return;
    const vis = document.querySelectorAll('#tbody tr').length;
    $('totCount').textContent = `· ${items.length} vigiladores${vis !== items.length ? ` (mostrando ${vis})` : ''}`;
    const s = c => items.reduce((a, i) => a + (i[c] || 0), 0);
    $('totAdelantos').textContent = pesos(s('adelantos'));
    $('totRopa').textContent = pesos(s('ropa_medico'));
    $('totOtros').textContent = pesos(s('otros'));
    $('totTotal').textContent = pesos(items.reduce((a, i) => a + totalItem(i), 0));
}
function refrescarFila(i) {
    const tr = document.querySelector(`tr[data-id="${i.id}"]`); if (!tr) return;
    tr.querySelector('[data-total]').textContent = pesos(totalItem(i));
}
$('buscar').addEventListener('input', () => pintarTabla());
$('filtroEmpresa').addEventListener('change', () => pintarTabla());

// Agregar
$('formNombre').addEventListener('submit', async e => {
    e.preventDefault();
    const n = $('nuevoNombre').value.trim(); if (!n) return;
    await agregar(n, () => { $('nuevoNombre').value = ''; $('nuevoNombre').focus(); });
});
function togglePegar() { $('pegarWrap').hidden = !$('pegarWrap').hidden; if (!$('pegarWrap').hidden) $('pegarNombres').focus(); }
async function agregarPegados() {
    const t = $('pegarNombres').value; if (!t.trim()) return;
    await agregar(t, r => { $('pegarNombres').value = ''; togglePegar(); mostrarExito(`${r.items.length} vigiladores agregados.` + (r.repetidos.length ? ` ${r.repetidos.length} ya estaban y se saltearon.` : '')); });
}
async function agregar(nombres, alTerminar) {
    try {
        const r = await post('agregar_nombres', { id_lista: lista.id, nombres });
        const ya = new Set(items.map(i => i.id));
        r.items.forEach(i => { if (!ya.has(i.id)) items.push(i); });
        $('buscar').value = ''; $('filtroEmpresa').value = '';
        pintarTabla(r.items.map(i => i.id));
        alTerminar(r);
        if (r.repetidos.length && r.items.length === 1) mostrarError(`${r.repetidos.join(', ')} ya estaba en la lista.`);
    } catch (err) {
        mostrarError(err.message);
        if (err.data?.repetidos) refrescar(true);   // probablemente lo agregó otro: traer lo último
    }
}

// Guardado automático de cada celda
$('tablaWrap').addEventListener('change', async e => {
    const inp = e.target.closest('.ds-input'); if (!inp || inp.readOnly || inp.disabled) return;
    const tr = inp.closest('tr'), id = +tr.dataset.id, campo = inp.dataset.campo;
    const it = items.find(i => i.id === id); if (!it) return;
    inp.classList.remove('ok', 'error', 'remoto');
    try {
        const r = await post('actualizar', { id, campo, valor: inp.value, anterior: inp.dataset.orig });
        Object.assign(it, r.item);
        sincronizarInput(inp, it, campo);
        refrescarFila(it); actualizarTotales();
        tr.title = 'Último cambio: ' + (it.actualizado_por || '—') + ', ' + fechaHora(it.actualizado_en);
        inp.classList.add('ok'); setTimeout(() => inp.classList.remove('ok'), 1200);
    } catch (err) {
        if (err.data?.conflicto) {               // otro usuario lo cambió: mostrar su valor
            Object.assign(it, err.data.item);
            sincronizarInput(inp, it, campo);
            refrescarFila(it); actualizarTotales();
            inp.classList.add('remoto');
        } else if (err.data?.borrado) {
            refrescar(true);
        } else {
            inp.classList.add('error');
        }
        mostrarError(err.message);
    }
});
function sincronizarInput(inp, it, campo) {
    if (campo === 'empresa') {
        inp.value = it.empresa || ''; inp.dataset.orig = it.empresa || '';
        inp.classList.toggle('sin-empresa', !it.empresa);
        return;
    }
    inp.value = campo === 'nombre' ? it.nombre : montoInput(it[campo]);
    inp.dataset.orig = campo === 'nombre' ? it.nombre : crudo(it[campo]);
}
// Enter baja a la misma columna de la fila siguiente
$('tablaWrap').addEventListener('keydown', e => {
    if (e.key !== 'Enter' || !e.target.matches('.ds-input')) return;
    e.preventDefault();
    const sig = e.target.closest('tr').nextElementSibling?.querySelector(`[data-campo="${e.target.dataset.campo}"]`);
    if (sig) sig.focus(); else e.target.blur();
});
$('tablaWrap').addEventListener('click', async e => {
    const h = e.target.closest('[data-hist]'); if (h) return abrirHistorial(+h.dataset.hist);
    const b = e.target.closest('[data-borrar]'); if (!b) return;
    const it = items.find(i => i.id === +b.dataset.borrar);
    if (!confirm(`¿Quitar a ${it.nombre} de la lista?`)) return;
    try { await post('eliminar_item', { id: it.id }); items = items.filter(i => i.id !== it.id); pintarTabla(); }
    catch (err) { mostrarError(err.message); refrescar(true); }
});
// Si quedó un repintado pendiente mientras alguien escribía, se hace al salir de la celda
$('tablaWrap').addEventListener('focusout', () => setTimeout(() => {
    if (pendienteRepintar && !document.activeElement?.closest?.('#tablaWrap')) pintarTabla();
}, 50));

// ---------- Actualización en vivo ----------
function iniciarVivo() {
    detenerVivo();
    timerVivo = setInterval(() => { if (!document.hidden) refrescar(); else marcarVivo(); }, REFRESCO_MS);
    marcarVivo();
}
function detenerVivo() { clearInterval(timerVivo); timerVivo = null; }
function marcarVivo(error = false) {
    $('vivo').classList.toggle('off', error);
    const seg = ultimoRefresco ? Math.round((Date.now() - ultimoRefresco) / 1000) : null;
    $('vivoTxt').textContent = error ? 'Sin conexión, reintentando...' : seg === null || seg < 3 ? 'En vivo · actualizado recién' : `En vivo · hace ${seg} s`;
}
async function refrescar(forzar = false) {
    if (!lista) return;
    let d;
    try { d = await get('detalle', { id: lista.id }); }
    catch (e) {
        if (e.status === 404) { detenerVivo(); mostrarError('Esta lista fue eliminada por otro usuario.'); return volverAListas(); }
        return marcarVivo(true);
    }
    const editabaAntes = puedeEditar, estadoAntes = lista.estado, nombreAntes = lista.nombre;
    const idsAntes = items.map(i => i.id).join(','), idsAhora = d.items.map(i => i.id).join(',');
    const viejos = new Map(items.map(i => [i.id, i]));
    aplicarDetalle(d);
    if (estadoAntes !== lista.estado || nombreAntes !== lista.nombre || editabaAntes !== puedeEditar) {
        pintarCabecera();
        if (estadoAntes !== lista.estado) mostrarExito(lista.estado === 'revision' ? 'Un administrador pasó la lista a revisión.' : 'Se reabrió la carga de la lista.');
    }
    const enfocado = document.activeElement?.closest?.('#tablaWrap .ds-input');
    // Cambió la etapa o los permisos: se repinta sí o sí (pasa a modo lectura / vuelve a ser editable)
    if (forzar || editabaAntes !== puedeEditar || !$('tbody')) {
        pintarTabla(items.filter(i => !viejos.has(i.id)).map(i => i.id));
        return marcarVivo();
    }
    const tbody = $('tbody');
    const ahora = new Set(items.map(i => i.id));
    // Filas que borró otro usuario
    if (idsAntes !== idsAhora) {
        tbody.querySelectorAll('tr[data-id]').forEach(tr => {
            if (ahora.has(+tr.dataset.id)) return;
            if (tr.contains(enfocado)) { pendienteRepintar = true; return; }
            tr.remove();
        });
        // Filas que agregó otro usuario (respetando el buscador)
        items.filter(i => !viejos.has(i.id) && coincide(i)).forEach(i => {
            tbody.insertAdjacentHTML('beforeend', filaHtml(i, items.indexOf(i) + 1));
            tbody.lastElementChild.classList.add('ds-fila-nueva');
        });
        tbody.querySelectorAll('tr[data-id]').forEach(tr => {
            const n = items.findIndex(i => i.id === +tr.dataset.id);
            if (n >= 0) tr.querySelector('td.n').textContent = n + 1;
        });
    }
    // Celdas que cambió otro, sin tocar la que se está editando
    for (const it of items) {
        const v = viejos.get(it.id), tr = tbody.querySelector(`tr[data-id="${it.id}"]`);
        if (!tr || !v) continue;
        for (const c of ['nombre', 'empresa', ...CAMPOS]) {
            if (v[c] === it[c]) continue;
            const inp = tr.querySelector(`[data-campo="${c}"]`);
            if (!inp || inp === enfocado) continue;
            sincronizarInput(inp, it, c);
            inp.classList.add('remoto'); setTimeout(() => inp.classList.remove('remoto'), 2500);
        }
        refrescarFila(it);
        if (it.actualizado_por) tr.title = 'Último cambio: ' + it.actualizado_por + ', ' + fechaHora(it.actualizado_en);
    }
    actualizarTotales();
    marcarVivo();
}
setInterval(() => { if (timerVivo) marcarVivo($('vivo').classList.contains('off')); }, 1000);
document.addEventListener('visibilitychange', () => { if (!document.hidden && timerVivo) refrescar(); });

// ---------- Historial ----------
async function abrirHistorial(idItem = null) {
    const it = idItem ? items.find(i => i.id === idItem) : null;
    $('histTitulo').textContent = it ? 'Historial de ' + it.nombre : 'Historial de la lista';
    $('histCuerpo').innerHTML = '<div class="ds-vacio"><div class="spinner spinner-dark" style="margin:0 auto;"></div></div>';
    $('modalHist').classList.add('open');
    try {
        const d = await get('historial', idItem ? { id_lista: lista.id, id_item: idItem } : { id_lista: lista.id });
        if (!d.historial.length) { $('histCuerpo').innerHTML = '<div class="ds-vacio">Sin movimientos todavía.</div>'; return; }
        $('histCuerpo').innerHTML = `<table class="ds-hist"><tbody>${d.historial.map(h => {
            let que;
            if (h.accion === 'alta') que = `Agregó a <b>${esc(h.vigilador)}</b>`;
            else if (h.accion === 'baja') que = `Quitó a <b>${esc(h.vigilador)}</b>${h.anterior ? ` (tenía ${esc(h.anterior)} de descuentos)` : ''}`;
            else if (h.accion === 'etapa') que = `Cambió la etapa: ${esc(h.anterior)} → <b>${esc(h.nuevo)}</b>`;
            else que = `${it ? '' : `<b>${esc(h.vigilador)}</b> · `}${esc(h.campo)}: <span class="de">${h.anterior !== null ? esc(h.anterior) : 'vacío'}</span> → <b>${h.nuevo !== null ? esc(h.nuevo) : 'vacío'}</b>`;
            return `<tr><td class="cuando">${fechaHora(h.fecha)}</td><td style="white-space:nowrap;">${esc(h.usuario || '—')}</td><td>${que}</td></tr>`;
        }).join('')}</tbody></table>`;
    } catch (e) { $('histCuerpo').innerHTML = `<div class="ds-vacio">${esc(e.message)}</div>`; }
}

// ---------- Excel ----------
$('btnExcel').addEventListener('click', () => { window.location.href = 'api/export_descuentos_excel.php?id=' + lista.id; });

['modalLista', 'modalHist'].forEach(id => $(id).addEventListener('click', e => { if (e.target.id === id) cerrarModal(id); }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') ['modalLista', 'modalHist'].forEach(cerrarModal); });

const qs = new URLSearchParams(location.search);
if (qs.get('lista')) abrirLista(+qs.get('lista')); else cargarListas();
</script>
</body>
</html>
