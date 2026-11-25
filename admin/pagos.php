<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api/pagos_helper.php';
requireBackofficePage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Pagos a vigiladores</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css?v=20261020">
    <link rel="stylesheet" href="../css/inventario.css?v=20261020">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <style>
        [hidden] { display:none !important; }
        .pg-num { text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums; }
        .pg-total td { font-weight:700;background:#f7f9fc;border-top:2px solid var(--primary); }
        .pg-total:hover td { background:#f7f9fc !important; }
        .pg-falta { color:var(--danger);font-weight:600; }

        /* Listado de listas */
        .pg-progreso { display:flex;gap:.35rem;flex-wrap:wrap; }

        /* Cabecera de una lista */
        .pg-cab { display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;margin-bottom:1rem; }
        .pg-cab h1 { font-size:1.25rem;color:var(--primary);margin:0;display:flex;align-items:center;gap:.5rem; }
        .pg-cab .acciones { margin-left:auto;display:flex;gap:.5rem;flex-wrap:wrap; }
        .pg-cab .btn { width:auto; }
        .btn-link { background:none;border:0;cursor:pointer;color:var(--text-muted);font-size:.9rem;padding:.2rem .3rem; }
        .btn-link:hover { color:var(--primary); }

        .pg-pasos { margin-bottom:1rem;display:flex; }
        .pg-pasos button { flex:1 1 auto; }

        /* Paso 1: agregar nombres */
        .pg-agregar { display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-start;margin-bottom:1rem; }
        .pg-agregar input { flex:1 1 260px;width:auto;padding:.6rem .8rem;font-size:.95rem; }
        .pg-agregar .btn { width:auto; }
        .pg-pegar textarea { width:100%;min-height:140px;font-family:inherit;padding:.7rem .9rem;font-size:.92rem;margin-bottom:.5rem; }

        /* Celdas editables */
        .pg-input {
            width:100%;border:1.5px solid transparent;border-radius:6px;background:transparent;
            padding:.35rem .5rem;font-size:.9rem;font-family:inherit;color:var(--text);
        }
        .pg-input:hover { border-color:var(--border);background:#fff; }
        .pg-input:focus { border-color:var(--accent);background:#fff;outline:none; }
        .pg-input.num { text-align:right;font-variant-numeric:tabular-nums;max-width:170px; }
        .pg-input.vacio { border-color:#f5b7b1;background:#fdf2f0; }
        .pg-input.ok { border-color:#58d68d;transition:border-color .2s; }
        .pg-input.error { border-color:var(--danger); }
        .pg-table td { vertical-align:middle;padding:.35rem .6rem; }
        .pg-table th.n, .pg-table td.n { width:2.5rem;color:var(--text-muted);text-align:right; }
        .pg-del { background:none;border:0;cursor:pointer;font-size:.95rem;opacity:.45;padding:.3rem; }
        .pg-del:hover { opacity:1; }

        /* Paso 2: bancos */
        .pg-barra-banco {
            position:sticky;top:0;z-index:5;background:#fff;border:1px solid var(--border);border-radius:10px;
            padding:.7rem .9rem;margin-bottom:1rem;display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;
            box-shadow:0 2px 8px rgba(0,0,0,.06);
        }
        .pg-barra-banco input[type=text] { width:auto;flex:1 1 220px;padding:.55rem .8rem;font-size:.92rem; }
        .pg-barra-banco .btn { width:auto; }
        .pg-banco-grupo { margin-bottom:1.2rem; }
        .pg-banco-titulo {
            display:flex;align-items:center;gap:.6rem;font-weight:700;font-size:1rem;color:var(--primary);
            margin-bottom:.4rem;
        }
        .pg-banco-titulo.sin { color:var(--danger); }
        .pg-banco-titulo .inv-muted { font-weight:400; }
        .pg-check { width:18px;height:18px;cursor:pointer; }
        .pg-total-general {
            display:flex;justify-content:space-between;align-items:center;font-size:1.05rem;font-weight:700;
            background:var(--primary);color:#fff;border-radius:10px;padding:.8rem 1rem;margin-top:.5rem;
        }

        .pg-vacio { padding:2rem;text-align:center;color:var(--text-muted); }

        @media (max-width:760px) {
            .pg-cab .acciones { margin-left:0;width:100%; }
            .pg-cab .acciones .btn { flex:1; }
            .pg-pasos { overflow-x:auto; }
            .pg-pasos button { white-space:nowrap; }
            .pg-input.num { max-width:none; }
            .pg-table td.n { display:none !important; }
            .pg-total-general { font-size:.95rem; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

<main class="inv-shell">
    <div class="alert alert-danger"  id="tableError"   role="alert"><span>&#9888;</span><span id="tableErrorMsg"></span></div>
    <div class="alert alert-success" id="tableSuccess" role="alert"><span>&#9989;</span><span id="tableSuccessMsg"></span></div>

    <!-- ============ LISTADO DE LISTAS ============ -->
    <section id="vistaListas">
        <div class="inv-head">
            <h1 class="inv-title">&#x1F4B5; Pagos a vigiladores <span class="inv-counter" id="counterListas"></span></h1>
            <button class="btn btn-primary btn-sm" type="button" onclick="abrirNuevaLista()">+ Nueva lista de pago</button>
        </div>
        <div class="card inv-card-wrap" style="overflow-x:auto;">
            <div id="listasWrap"><div class="pg-vacio"><div class="spinner spinner-dark" style="margin:0 auto .8rem;"></div>Cargando...</div></div>
        </div>
    </section>

    <!-- ============ UNA LISTA ============ -->
    <section id="vistaLista" hidden>
        <div class="pg-cab">
            <button class="btn btn-outline btn-sm" type="button" onclick="volverAListas()">&#x2190; Listas</button>
            <h1><span id="listaNombre"></span> <button class="btn-link" type="button" onclick="renombrarLista()" title="Cambiar nombre">&#x270E;</button></h1>
            <span class="inv-muted" id="listaInfo"></span>
            <div class="acciones">
                <button class="btn btn-outline btn-sm" type="button" id="btnExcel">&#x1F4CA; Exportar a Excel</button>
                <button class="btn btn-danger btn-sm" type="button" id="btnEliminarLista" onclick="eliminarLista()">&#x1F5D1; Eliminar lista</button>
            </div>
        </div>

        <div class="inv-tabs pg-pasos" id="pasos">
            <button type="button" data-paso="1" class="active">1. Nombres y montos</button>
            <button type="button" data-paso="2">2. Separar por banco</button>
            <button type="button" data-paso="3">3. Resumen por banco</button>
        </div>

        <!-- Paso 1 -->
        <div id="paso1" class="card" style="padding:1.2rem;">
            <form class="pg-agregar" id="formNombre" autocomplete="off">
                <input type="text" id="nuevoNombre" maxlength="150" placeholder="Nombre completo del vigilador y Enter">
                <button type="submit" class="btn btn-primary btn-sm">+ Agregar</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="togglePegar()">&#x1F4CB; Pegar varios</button>
            </form>
            <div class="pg-pegar" id="pegarWrap" hidden>
                <textarea id="pegarNombres" placeholder="Un nombre por línea. Podés copiarlos de un Excel o de otra lista."></textarea>
                <div style="display:flex;gap:.6rem;justify-content:flex-end;margin-bottom:1rem;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="togglePegar()">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="agregarPegados()">Agregar todos</button>
                </div>
            </div>
            <div style="overflow-x:auto;" id="tabla1"></div>
        </div>

        <!-- Paso 2 -->
        <div id="paso2" hidden>
            <div class="pg-barra-banco">
                <label class="inv-check"><input type="checkbox" class="pg-check" id="checkTodos"> Todos</label>
                <span class="inv-muted" id="selInfo">Marcá vigiladores para asignarles un banco</span>
                <input type="text" id="bancoMasivo" list="bancosLista" maxlength="80" placeholder="Banco (ej: Banco Nación)">
                <button type="button" class="btn btn-primary btn-sm" onclick="asignarMasivo()">Asignar a marcados</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="quitarMasivo()" title="Dejar sin banco">Quitar banco</button>
            </div>
            <div id="gruposBancos"></div>
        </div>

        <!-- Paso 3 -->
        <div id="paso3" class="card" style="padding:1.2rem;" hidden>
            <div id="resumen"></div>
        </div>
    </section>
</main>

<datalist id="bancosLista"></datalist>

<!-- MODAL nombre de lista -->
<div class="modal-overlay" id="modalLista">
    <div class="modal" style="max-width:460px;">
        <div class="modal-header">
            <span class="modal-title" id="modalListaTitulo">Nueva lista de pago</span>
            <button class="modal-close" type="button" onclick="cerrarModalLista()" aria-label="Cerrar">&#x2715;</button>
        </div>
        <form id="formLista" novalidate>
            <div class="form-group">
                <label for="fListaNombre">Nombre <span class="req">*</span></label>
                <input type="text" id="fListaNombre" required maxlength="120" placeholder="Ej: Noviembre 2026 · 1ª quincena" autocomplete="off">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModalLista()">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="width:auto;min-width:120px;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF = <?= json_encode(pagosCsrf()) ?>;
const API  = 'api/pagos.php';

let esAdmin = false;
let listas = [];
let lista = null;       // lista abierta
let items = [];
let bancos = [];
let paso = 1;
let modalModo = 'nueva';
const marcados = new Set();

const $ = id => document.getElementById(id);
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const pesos = n => '$ ' + Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const montoInput = n => {
    if (n === null || n === undefined) return '';
    const dec = Number.isInteger(Number(n)) ? 0 : 2;   // 125.000 / 98.500,50
    return Number(n).toLocaleString('es-AR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
};
function fechaHora(f) { const d = new Date(String(f).replace(' ', 'T')); return isNaN(d) ? '' : d.toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' }); }
function fechaCorta(f) { const d = new Date(String(f).replace(' ', 'T')); return isNaN(d) ? '' : d.toLocaleDateString('es-AR'); }
const suma = arr => arr.reduce((s, i) => s + (i.monto || 0), 0);

function mostrarExito(msg) {
    const d = $('tableSuccess'); $('tableSuccessMsg').textContent = msg;
    $('tableError').classList.remove('show'); d.classList.add('show');
    clearTimeout(d._t); d._t = setTimeout(() => d.classList.remove('show'), 3500);
}
function mostrarError(msg) {
    const d = $('tableError'); $('tableErrorMsg').textContent = msg;
    $('tableSuccess').classList.remove('show'); d.classList.add('show');
    clearTimeout(d._t); d._t = setTimeout(() => d.classList.remove('show'), 8000);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function get(accion, params = {}) {
    const r = await fetch(`${API}?` + new URLSearchParams({ accion, ...params }));
    const data = await r.json().catch(() => ({ error: 'Respuesta inválida del servidor' }));
    if (!r.ok || data.error) throw new Error(data.error || 'Error inesperado');
    return data;
}
async function post(accion, campos) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(campos)) fd.append(k, v);
    fd.append('csrf', CSRF);
    const r = await fetch(`${API}?accion=${accion}`, { method: 'POST', body: fd });
    const data = await r.json().catch(() => ({ error: 'Respuesta inválida del servidor' }));
    if (!r.ok || data.error) throw new Error(data.error || 'Error inesperado');
    return data;
}

// ======================= LISTADO =======================
async function cargarListas() {
    try {
        const d = await get('listas');
        esAdmin = d.es_admin; listas = d.listas;
        pintarListas();
    } catch (e) { mostrarError(e.message); }
}

function estadoLista(l) {
    if (!l.cantidad) return '<span class="pill pill-neutro">Sin nombres</span>';
    const pills = [];
    pills.push(l.con_monto === l.cantidad
        ? '<span class="pill pill-resuelto">Montos completos</span>'
        : `<span class="pill pill-abierto">${l.cantidad - l.con_monto === 1 ? 'Falta 1 monto' : `Faltan ${l.cantidad - l.con_monto} montos`}</span>`);
    pills.push(l.con_banco === l.cantidad
        ? '<span class="pill pill-resuelto">Bancos completos</span>'
        : `<span class="pill pill-neutro">${l.cantidad - l.con_banco} sin banco</span>`);
    return `<div class="pg-progreso">${pills.join('')}</div>`;
}

function pintarListas() {
    $('counterListas').textContent = listas.length ? `(${listas.length})` : '';
    if (!listas.length) {
        $('listasWrap').innerHTML = `<div class="pg-vacio">Todavía no hay listas de pago.<br>
            <button class="btn btn-primary btn-sm" style="width:auto;margin-top:1rem;" onclick="abrirNuevaLista()">+ Crear la primera</button></div>`;
        return;
    }
    $('listasWrap').innerHTML = `
        <table class="inv-table">
            <thead><tr><th>Lista</th><th>Creada</th><th class="pg-num">Vigiladores</th><th>Estado</th><th class="pg-num">Total</th><th></th></tr></thead>
            <tbody>${listas.map(l => `
                <tr style="cursor:pointer;" data-abrir="${l.id}">
                    <td data-label="Lista" class="inv-main"><div><b>${esc(l.nombre)}</b></div></td>
                    <td data-label="Creada"><div>${fechaCorta(l.creado_en)}${l.creado_por ? `<div class="inv-muted">por ${esc(l.creado_por)}</div>` : ''}</div></td>
                    <td data-label="Vigiladores" class="pg-num"><div>${l.cantidad}</div></td>
                    <td data-label="Estado"><div>${estadoLista(l)}</div></td>
                    <td data-label="Total" class="pg-num"><div><b>${pesos(l.total)}</b></div></td>
                    <td><div class="actions"><button class="btn btn-primary btn-sm" type="button" data-abrir="${l.id}">Abrir</button></div></td>
                </tr>`).join('')}
            </tbody>
        </table>`;
}

$('listasWrap').addEventListener('click', e => {
    const tr = e.target.closest('[data-abrir]');
    if (tr) abrirLista(+tr.dataset.abrir);
});

// Crear / renombrar
function abrirNuevaLista() {
    modalModo = 'nueva';
    $('modalListaTitulo').textContent = 'Nueva lista de pago';
    $('fListaNombre').value = '';
    $('modalLista').classList.add('open');
    setTimeout(() => $('fListaNombre').focus(), 50);
}
function renombrarLista() {
    modalModo = 'renombrar';
    $('modalListaTitulo').textContent = 'Cambiar nombre de la lista';
    $('fListaNombre').value = lista.nombre;
    $('modalLista').classList.add('open');
    setTimeout(() => $('fListaNombre').select(), 50);
}
function cerrarModalLista() { $('modalLista').classList.remove('open'); }
$('formLista').addEventListener('submit', async e => {
    e.preventDefault();
    const nombre = $('fListaNombre').value.trim();
    if (!nombre) { $('fListaNombre').focus(); return; }
    try {
        if (modalModo === 'nueva') {
            const r = await post('crear_lista', { nombre });
            cerrarModalLista();
            await abrirLista(r.id);
            mostrarExito('Lista creada. Agregá los nombres de los vigiladores.');
            setTimeout(() => $('nuevoNombre').focus(), 100);
        } else {
            await post('renombrar_lista', { id: lista.id, nombre });
            lista.nombre = nombre; $('listaNombre').textContent = nombre;
            cerrarModalLista(); mostrarExito('Nombre actualizado.');
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
        esAdmin = d.es_admin; lista = d.lista; items = d.items; bancos = d.bancos;
        marcados.clear();
        $('vistaListas').hidden = true; $('vistaLista').hidden = false;
        $('listaNombre').textContent = lista.nombre;
        $('listaInfo').textContent = `Creada el ${fechaCorta(lista.creado_en)}${lista.creado_por ? ' por ' + lista.creado_por : ''}`;
        $('btnEliminarLista').style.display = esAdmin ? '' : 'none';
        history.replaceState(null, '', '?lista=' + id + (paso > 1 ? '&paso=' + paso : ''));
        pintarBancosSugeridos();
        irAPaso(paso);
    } catch (e) { mostrarError(e.message); volverAListas(); }
}

function volverAListas() {
    lista = null; items = []; paso = 1;
    $('vistaLista').hidden = true; $('vistaListas').hidden = false;
    history.replaceState(null, '', location.pathname);
    cargarListas();
}

function irAPaso(n) {
    paso = n;
    document.querySelectorAll('#pasos button').forEach(b => b.classList.toggle('active', +b.dataset.paso === n));
    [1, 2, 3].forEach(i => $('paso' + i).hidden = i !== n);
    if (lista) history.replaceState(null, '', '?lista=' + lista.id + (n > 1 ? '&paso=' + n : ''));
    pintarPaso();
}
document.querySelectorAll('#pasos button').forEach(b => b.addEventListener('click', () => irAPaso(+b.dataset.paso)));

function pintarPaso() {
    if (paso === 1) pintarPaso1();
    if (paso === 2) pintarPaso2();
    if (paso === 3) pintarPaso3();
}

function pintarBancosSugeridos() {
    const usados = new Set([...bancos, ...items.map(i => i.banco).filter(Boolean)]);
    $('bancosLista').innerHTML = [...usados].sort().map(b => `<option value="${esc(b)}">`).join('');
}

// ---------- Paso 1: nombres, montos y observaciones ----------
function infoMonto(i) {
    return i.monto_por ? `Monto cargado por ${i.monto_por} el ${fechaHora(i.monto_en)}` : 'Sin monto cargado';
}
function pintarPaso1() {
    if (!items.length) {
        $('tabla1').innerHTML = '<div class="pg-vacio">Todavía no hay vigiladores en esta lista. Escribí un nombre arriba y apretá Enter.</div>';
        return;
    }
    $('tabla1').innerHTML = `
        <table class="inv-table pg-table" id="t1">
            <thead><tr><th class="n">#</th><th>Nombre completo</th><th style="width:180px;" class="pg-num">Monto</th><th>Observaciones</th><th style="width:40px;"></th></tr></thead>
            <tbody>${items.map((i, n) => `
                <tr data-id="${i.id}">
                    <td class="n">${n + 1}</td>
                    <td data-label="Nombre"><div><input class="pg-input" data-campo="nombre" value="${esc(i.nombre)}" maxlength="150"
                        title="Agregado por ${esc(i.creado_por || '—')} el ${fechaHora(i.creado_en)}"></div></td>
                    <td data-label="Monto"><div><input class="pg-input num${i.monto === null ? ' vacio' : ''}" data-campo="monto" inputmode="decimal"
                        value="${montoInput(i.monto)}" placeholder="0" title="${esc(infoMonto(i))}"></div></td>
                    <td data-label="Observaciones"><div><input class="pg-input" data-campo="observaciones" value="${esc(i.observaciones || '')}" maxlength="500" placeholder="—"></div></td>
                    <td><div><button class="pg-del" type="button" data-borrar="${i.id}" title="Quitar de la lista">&#x1F5D1;</button></div></td>
                </tr>`).join('')}
                <tr class="pg-total">
                    <td class="n"></td>
                    <td data-label="Total">TOTAL <span class="inv-muted" id="t1Faltan" style="font-weight:400;"></span></td>
                    <td data-label="Monto" class="pg-num" id="t1Total"></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>`;
    actualizarTotal1();
}
function actualizarTotal1() {
    if (!$('t1Total')) return;
    $('t1Total').textContent = pesos(suma(items));
    const faltan = items.filter(i => i.monto === null).length;
    $('t1Faltan').innerHTML = faltan ? `· <span class="pg-falta">${faltan === 1 ? 'falta 1 monto' : `faltan ${faltan} montos`}</span>` : `· ${items.length} vigiladores`;
}

// Agregar de a uno
$('formNombre').addEventListener('submit', async e => {
    e.preventDefault();
    const n = $('nuevoNombre').value.trim(); if (!n) return;
    try {
        const r = await post('agregar_nombres', { id_lista: lista.id, nombres: n });
        items.push(...r.items); $('nuevoNombre').value = ''; pintarPaso1(); $('nuevoNombre').focus();
    } catch (err) { mostrarError(err.message); }
});
// Pegar varios
function togglePegar() { $('pegarWrap').hidden = !$('pegarWrap').hidden; if (!$('pegarWrap').hidden) $('pegarNombres').focus(); }
async function agregarPegados() {
    const txt = $('pegarNombres').value; if (!txt.trim()) return;
    try {
        const r = await post('agregar_nombres', { id_lista: lista.id, nombres: txt });
        items.push(...r.items); $('pegarNombres').value = ''; togglePegar(); pintarPaso1();
        mostrarExito(`${r.items.length} vigiladores agregados.`);
    } catch (err) { mostrarError(err.message); }
}

// Guardado automático al salir de cada celda
$('tabla1').addEventListener('change', async e => {
    const inp = e.target.closest('.pg-input'); if (!inp) return;
    const id = +inp.closest('tr').dataset.id, campo = inp.dataset.campo;
    const it = items.find(i => i.id === id);
    inp.classList.remove('ok', 'error');
    try {
        const r = await post('actualizar_item', { id, campo, valor: inp.value });
        Object.assign(it, r.item);
        if (campo === 'monto') {
            inp.value = montoInput(it.monto);
            inp.classList.toggle('vacio', it.monto === null);
            inp.title = infoMonto(it);
            actualizarTotal1();
        }
        inp.classList.add('ok'); setTimeout(() => inp.classList.remove('ok'), 1200);
    } catch (err) { inp.classList.add('error'); mostrarError(err.message); }
});
// Enter en un monto pasa al monto de abajo (carga rápida)
$('tabla1').addEventListener('keydown', e => {
    if (e.key !== 'Enter' || !e.target.matches('.pg-input')) return;
    e.preventDefault();
    const campo = e.target.dataset.campo;
    const sig = e.target.closest('tr').nextElementSibling?.querySelector(`[data-campo="${campo}"]`);
    if (sig) sig.focus(); else e.target.blur();
});
$('tabla1').addEventListener('click', async e => {
    const b = e.target.closest('[data-borrar]'); if (!b) return;
    const it = items.find(i => i.id === +b.dataset.borrar);
    if (!confirm(`¿Quitar a ${it.nombre} de la lista?`)) return;
    try { await post('eliminar_item', { id: it.id }); items = items.filter(i => i.id !== it.id); pintarPaso1(); }
    catch (err) { mostrarError(err.message); }
});

// ---------- Paso 2: separar por banco ----------
function grupos() {
    const g = new Map();
    for (const i of items) {
        const k = i.banco || '';
        if (!g.has(k)) g.set(k, []);
        g.get(k).push(i);
    }
    // "Sin banco" primero, después alfabético
    return [...g.entries()].sort((a, b) => a[0] === '' ? -1 : b[0] === '' ? 1 : a[0].localeCompare(b[0], 'es'));
}

function pintarPaso2() {
    for (const id of [...marcados]) if (!items.some(i => i.id === id)) marcados.delete(id);
    if (!items.length) {
        $('gruposBancos').innerHTML = '<div class="card pg-vacio">Primero agregá los vigiladores en el paso 1.</div>';
        actualizarSeleccion(); return;
    }
    $('gruposBancos').innerHTML = grupos().map(([banco, lst]) => `
        <div class="card pg-banco-grupo" style="padding:1rem 1.2rem;">
            <div class="pg-banco-titulo ${banco ? '' : 'sin'}">
                <input type="checkbox" class="pg-check" data-grupo="${esc(banco)}" title="Marcar todo el grupo">
                ${banco ? '&#x1F3E6; ' + esc(banco) : '&#x26A0; Sin banco asignado'}
                <span class="inv-muted">· ${lst.length} ${lst.length === 1 ? 'vigilador' : 'vigiladores'}</span>
            </div>
            <div style="overflow-x:auto;">
            <table class="inv-table pg-table">
                <thead><tr><th style="width:36px;"></th><th>Nombre completo</th><th class="pg-num" style="width:170px;">Monto</th><th style="width:240px;">Banco</th></tr></thead>
                <tbody>${lst.map(i => `
                    <tr data-id="${i.id}">
                        <td><input type="checkbox" class="pg-check" data-item="${i.id}"${marcados.has(i.id) ? ' checked' : ''}></td>
                        <td data-label="Nombre"><div>${esc(i.nombre)}</div></td>
                        <td data-label="Monto" class="pg-num"><div>${i.monto === null ? '<span class="pg-falta">sin monto</span>' : pesos(i.monto)}</div></td>
                        <td data-label="Banco"><div><input class="pg-input" data-banco="${i.id}" list="bancosLista" value="${esc(i.banco || '')}" placeholder="Elegir o escribir banco"
                            title="${i.banco_por ? esc('Asignado por ' + i.banco_por + ' el ' + fechaHora(i.banco_en)) : ''}"></div></td>
                    </tr>`).join('')}
                    <tr class="pg-total"><td></td><td>Subtotal ${banco ? esc(banco) : 'sin banco'}</td><td class="pg-num">${pesos(suma(lst))}</td><td></td></tr>
                </tbody>
            </table>
            </div>
        </div>`).join('') + `
        <div class="pg-total-general"><span>TOTAL GENERAL · ${items.length} vigiladores</span><span>${pesos(suma(items))}</span></div>`;
    actualizarSeleccion();
}

function actualizarSeleccion() {
    const n = marcados.size;
    $('selInfo').textContent = n ? `${n} ${n === 1 ? 'marcado' : 'marcados'}` : 'Marcá vigiladores para asignarles un banco';
    $('checkTodos').checked = items.length > 0 && n === items.length;
    $('checkTodos').indeterminate = n > 0 && n < items.length;
    document.querySelectorAll('[data-grupo]').forEach(c => {
        const ids = items.filter(i => (i.banco || '') === c.dataset.grupo).map(i => i.id);
        const m = ids.filter(id => marcados.has(id)).length;
        c.checked = m === ids.length && m > 0; c.indeterminate = m > 0 && m < ids.length;
    });
}

$('gruposBancos').addEventListener('change', async e => {
    const t = e.target;
    if (t.dataset.item) { t.checked ? marcados.add(+t.dataset.item) : marcados.delete(+t.dataset.item); return actualizarSeleccion(); }
    if (t.dataset.grupo !== undefined) {
        items.filter(i => (i.banco || '') === t.dataset.grupo).forEach(i => t.checked ? marcados.add(i.id) : marcados.delete(i.id));
        document.querySelectorAll('[data-item]').forEach(c => c.checked = marcados.has(+c.dataset.item));
        return actualizarSeleccion();
    }
    if (t.dataset.banco) {   // cambio de banco de una fila
        const it = items.find(i => i.id === +t.dataset.banco);
        try {
            const r = await post('actualizar_item', { id: it.id, campo: 'banco', valor: t.value });
            Object.assign(it, r.item); pintarBancosSugeridos(); pintarPaso2();
        } catch (err) { mostrarError(err.message); }
    }
});
$('gruposBancos').addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.dataset.banco) { e.preventDefault(); e.target.blur(); }
});
$('checkTodos').addEventListener('change', e => {
    items.forEach(i => e.target.checked ? marcados.add(i.id) : marcados.delete(i.id));
    pintarPaso2();
});

async function aplicarBanco(banco) {
    if (!marcados.size) return mostrarError('Marcá al menos un vigilador.');
    const ids = [...marcados];
    try {
        await post('asignar_banco', { ids: ids.join(','), banco });
        const d = await get('detalle', { id: lista.id });
        items = d.items; bancos = d.bancos; marcados.clear();
        pintarBancosSugeridos(); pintarPaso2();
        mostrarExito(banco ? `${ids.length} asignados a ${banco}.` : `${ids.length} quedaron sin banco.`);
        $('bancoMasivo').value = '';
    } catch (err) { mostrarError(err.message); }
}
function asignarMasivo() {
    const b = $('bancoMasivo').value.trim();
    if (!b) { $('bancoMasivo').focus(); return mostrarError('Escribí o elegí el banco.'); }
    aplicarBanco(b);
}
function quitarMasivo() { aplicarBanco(''); }
$('bancoMasivo').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); asignarMasivo(); } });

// ---------- Paso 3: resumen por banco ----------
function pintarPaso3() {
    if (!items.length) { $('resumen').innerHTML = '<div class="pg-vacio">Primero agregá los vigiladores en el paso 1.</div>'; return; }
    const g = grupos();
    const sinBanco = g.find(([b]) => b === '');
    const sinMonto = items.filter(i => i.monto === null).length;
    const avisos = [];
    if (sinBanco) avisos.push(`${sinBanco[1].length} ${sinBanco[1].length === 1 ? 'vigilador' : 'vigiladores'} sin banco asignado`);
    if (sinMonto) avisos.push(`${sinMonto} sin monto`);
    $('resumen').innerHTML = `
        ${avisos.length ? `<div class="alert alert-danger show" style="margin-bottom:1rem;"><span>&#9888;</span><span>Atención: ${avisos.join(' y ')}.</span></div>` : ''}
        <table class="inv-table pg-table" style="max-width:640px;">
            <thead><tr><th>Banco</th><th class="pg-num">Vigiladores</th><th class="pg-num">Monto</th></tr></thead>
            <tbody>
                ${g.map(([banco, lst]) => `
                <tr>
                    <td data-label="Banco"><div>${banco ? esc(banco) : '<span class="pg-falta">Sin banco asignado</span>'}</div></td>
                    <td data-label="Vigiladores" class="pg-num"><div>${lst.length}</div></td>
                    <td data-label="Monto" class="pg-num"><div>${pesos(suma(lst))}</div></td>
                </tr>`).join('')}
                <tr class="pg-total"><td>TOTAL</td><td class="pg-num">${items.length}</td><td class="pg-num">${pesos(suma(items))}</td></tr>
            </tbody>
        </table>`;
}

// ---------- Excel ----------
$('btnExcel').addEventListener('click', () => { window.location.href = 'api/export_pagos_excel.php?id=' + lista.id; });

// Modales
$('modalLista').addEventListener('click', e => { if (e.target.id === 'modalLista') cerrarModalLista(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && $('modalLista').classList.contains('open')) cerrarModalLista(); });

// Inicio: ?lista=ID&paso=N abre directo esa lista
const qs = new URLSearchParams(location.search);
if (qs.get('lista')) { paso = [1, 2, 3].includes(+qs.get('paso')) ? +qs.get('paso') : 1; abrirLista(+qs.get('lista')); }
else cargarListas();
</script>
</body>
</html>
