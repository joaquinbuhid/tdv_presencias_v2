<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api/retenciones_helper.php';
requireBackofficePage();
$mesActual = RETENCIONES_MESES[(int)date('n') - 1];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Retenciones</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css?v=20261020">
    <link rel="stylesheet" href="../css/inventario.css?v=20261020">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <style>
        /* Alta de ingreso */
        .ing-form { display:flex;flex-wrap:wrap;gap:.8rem;align-items:flex-end; }
        .ing-form .form-group { margin:0;flex:1 1 160px; }
        .ing-form label { font-size:.85rem; }
        .ing-form input, .ing-form select { width:100%;height:44px;padding:.6rem .8rem;font-size:.95rem;font-family:inherit; }
        .ing-form .btn { width:auto;flex:0 0 auto;padding:.65rem 1.2rem; }
        .ing-titulo { font-size:.95rem;font-weight:700;color:var(--primary);margin-bottom:.8rem; }

        /* Tabla */
        .ret-table td { vertical-align:middle; }
        .ret-table td.monto { font-weight:600;white-space:nowrap;font-variant-numeric:tabular-nums; }
        .ret-table th.facturas-th { width:42%; }

        /* Celda de facturas: botón + y facturas apiladas */
        .fact-cell { display:flex;align-items:flex-start;gap:.6rem; }
        .fact-add {
            flex:none;width:30px;height:30px;border-radius:8px;cursor:pointer;
            border:2px solid var(--primary);background:#fff;color:var(--primary);
            font-size:1.15rem;font-weight:700;line-height:1;
            display:flex;align-items:center;justify-content:center;
            transition:background .15s, color .15s;
        }
        .fact-add:hover { background:var(--primary);color:#fff; }
        .fact-stack { display:flex;flex-direction:column;gap:.35rem;min-width:0; }
        .fact-chip {
            display:flex;align-items:center;gap:.45rem;cursor:pointer;text-align:left;
            border:1.5px solid #5d8fd8;border-radius:6px;background:#fff;
            padding:.3rem .75rem;font-size:.9rem;font-weight:600;color:var(--text);
            min-width:130px;max-width:280px;transition:background .15s, border-color .15s;
        }
        .fact-chip:hover { background:#ebf5fb;border-color:#2980b9; }
        .fact-chip .num { white-space:nowrap; }
        .fact-chip .obj {
            font-weight:400;font-size:.78rem;color:var(--text-muted);
            overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
        }
        .fact-chip .clip { margin-left:auto;font-size:.85rem; }
        .fact-vacia { font-size:.8rem;color:var(--text-muted);font-style:italic;padding-top:.35rem; }

        .ret-total { font-size:.85rem;color:var(--text-muted);margin-left:auto; }
        .ret-total b { color:var(--text); }

        /* Detalle de factura */
        .det-contexto {
            background:#f7f9fc;border-left:4px solid var(--primary);border-radius:8px;
            padding:.55rem .9rem;font-size:.85rem;color:var(--text-muted);margin-bottom:1rem;
        }
        .det-contexto b { color:var(--text); }
        .det-grid { display:grid;grid-template-columns:8.5rem 1fr;gap:.55rem 1rem;font-size:.92rem;margin-bottom:1rem; }
        .det-grid dt { color:var(--text-muted);font-weight:600;font-size:.82rem;padding-top:.1rem; }
        .det-grid dd { margin:0;white-space:pre-wrap;word-break:break-word; }
        .det-adjunto {
            border:1px solid var(--border);border-radius:10px;background:#f7f9fc;
            display:flex;align-items:center;justify-content:center;overflow:hidden;
            max-height:42vh;
        }
        .det-adjunto img { max-width:100%;max-height:42vh;object-fit:contain; }
        .det-adjunto iframe { width:100%;height:42vh;border:0; }
        .modal textarea { font-family:inherit; }
        .file-hint { font-size:.78rem;color:var(--text-muted);margin-top:.3rem; }

        @media (max-width:760px) {
            .ing-form .btn { width:100%;flex:1 1 100%; }
            .ret-total { margin-left:0;width:100%; }
            .fact-chip { max-width:none;flex:1; }
            .det-grid { grid-template-columns:1fr;gap:.2rem; }
            .det-grid dd { margin-bottom:.5rem; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

<main class="inv-shell">
    <div class="inv-head">
        <h1 class="inv-title">&#x1F9FE; Retenciones <span class="inv-counter" id="counter"></span></h1>
    </div>

    <div class="alert alert-danger"  id="tableError"   role="alert"><span>&#9888;</span><span id="tableErrorMsg"></span></div>
    <div class="alert alert-success" id="tableSuccess" role="alert"><span>&#9989;</span><span id="tableSuccessMsg"></span></div>

    <!-- Alta de ingreso -->
    <div class="card" style="margin-bottom:1rem;padding:1.2rem;">
        <div class="ing-titulo">Registrar ingreso</div>
        <form class="ing-form" id="formIngreso" novalidate>
            <div class="form-group">
                <label for="iFecha">Fecha <span class="req">*</span></label>
                <input type="date" id="iFecha" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label for="iPeriodo">Período <span class="req">*</span></label>
                <select id="iPeriodo" required>
                    <?php foreach (RETENCIONES_MESES as $m): ?>
                        <option value="<?= $m ?>"<?= $m === $mesActual ? ' selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="iMonto">Ingreso ($) <span class="req">*</span></label>
                <input type="text" id="iMonto" required inputmode="decimal" placeholder="1000000.00" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-primary" id="btnIngreso">Registrar</button>
        </form>
    </div>

    <div class="inv-filters">
        <select id="fPeriodo" style="flex:0 1 220px;">
            <option value="">Todos los períodos</option>
            <?php foreach (RETENCIONES_MESES as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
        </select>
        <input type="text" id="fBuscar" class="inv-search" placeholder="Buscar por número de factura u objetivo...">
        <span class="ret-total" id="total"></span>
        <button type="button" class="btn btn-outline btn-sm" id="btnExcel" style="width:auto;white-space:nowrap;">&#x1F4CA; Exportar a Excel</button>
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

<!-- MODAL nueva factura -->
<div class="modal-overlay" id="modalFactura">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Agregar factura</span>
            <button class="modal-close" type="button" onclick="cerrarFactura()" aria-label="Cerrar">&#x2715;</button>
        </div>
        <div class="det-contexto" id="facContexto"></div>
        <div class="alert alert-danger" id="facError" role="alert"><span>&#9888;</span><span id="facErrorMsg"></span></div>

        <form id="formFactura" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <label for="fNumero">Número de factura <span class="req">*</span></label>
                    <input type="text" id="fNumero" required maxlength="50" placeholder="F_000021" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="fObjetivo">Objetivo <span class="req">*</span></label>
                    <input type="text" id="fObjetivo" required maxlength="150" placeholder="Ej: Shopping Norte">
                </div>
            </div>
            <div class="form-group">
                <label for="fObs">Observaciones</label>
                <textarea id="fObs" rows="3" maxlength="2000" placeholder="Opcional"></textarea>
            </div>
            <div class="form-group">
                <label for="fAdjunto">Adjunto de la factura</label>
                <input type="file" id="fAdjunto" accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx,.docx,.zip">
                <div class="file-hint">Opcional · PDF, imagen, Excel, Word o ZIP · hasta <?= RETENCIONES_MAX_MB ?> MB</div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarFactura()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnFactura" style="width:auto;min-width:140px;">Agregar factura</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL detalle factura -->
<div class="modal-overlay" id="modalDetalle">
    <div class="modal" style="max-width:760px;">
        <div class="modal-header">
            <span class="modal-title" id="detTitulo"></span>
            <button class="modal-close" type="button" onclick="cerrarDetalle()" aria-label="Cerrar">&#x2715;</button>
        </div>
        <div class="det-contexto" id="detContexto"></div>
        <dl class="det-grid" id="detGrid"></dl>
        <div id="detAdjunto"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-danger" id="detEliminar" style="width:auto;margin-right:auto;">Eliminar factura</button>
            <a class="btn btn-outline" id="detDescargar" style="width:auto;text-decoration:none;">&#x2B07; Descargar adjunto</a>
            <button type="button" class="btn btn-primary" onclick="cerrarDetalle()" style="width:auto;">Cerrar</button>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode(retencionesCsrf()) ?>;
const MAX_MB = <?= RETENCIONES_MAX_MB ?>;
const API = 'api/retenciones.php';

let ingresos = [];
let esAdmin = false;
let ingresoActual = null;   // ingreso al que se le agrega factura
let facturaActual = null;   // factura abierta en el detalle

const $ = id => document.getElementById(id);
function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
const pesos = n => '$ ' + Number(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function fecha(f) { const [y, m, d] = String(f).slice(0, 10).split('-'); return d ? `${d}/${m}/${y}` : ''; }
function fechaHora(f) { const d = new Date(String(f).replace(' ', 'T')); return isNaN(d) ? '' : d.toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' }); }
const tam = b => b < 1048576 ? Math.max(1, Math.round(b / 1024)) + ' KB' : (b / 1048576).toFixed(1) + ' MB';

function mostrarExito(msg) {
    const d = $('tableSuccess'); $('tableSuccessMsg').textContent = msg;
    $('tableError').classList.remove('show'); d.classList.add('show');
    clearTimeout(d._t); d._t = setTimeout(() => d.classList.remove('show'), 4000);
}
function mostrarError(msg) {
    const d = $('tableError'); $('tableErrorMsg').textContent = msg;
    $('tableSuccess').classList.remove('show'); d.classList.add('show');
    clearTimeout(d._t); d._t = setTimeout(() => d.classList.remove('show'), 8000);
}

async function post(accion, fd) {
    fd.append('csrf', CSRF);
    const r = await fetch(`${API}?accion=${accion}`, { method: 'POST', body: fd });
    const data = await r.json().catch(() => ({ error: 'Respuesta inválida del servidor' }));
    if (!r.ok || data.error) throw new Error(data.error || 'Error inesperado');
    return data;
}

// ---------- Carga y tabla ----------
async function cargar() {
    try {
        const r = await fetch(`${API}?accion=listar`);
        const data = await r.json();
        if (!r.ok || data.error) throw new Error(data.error || 'No se pudo cargar');
        ingresos = data.ingresos; esAdmin = data.es_admin;
        pintar();
    } catch (e) { mostrarError(e.message); }
}

function filtrados() {
    const per = $('fPeriodo').value;
    const q = $('fBuscar').value.trim().toLowerCase();
    return ingresos.filter(i => {
        if (per && i.periodo !== per) return false;
        if (!q) return true;
        return i.facturas.some(f => f.numero.toLowerCase().includes(q) || f.objetivo.toLowerCase().includes(q));
    });
}

function chip(f) {
    return `<button type="button" class="fact-chip" data-factura="${f.id}" title="${esc(f.numero + ' · ' + f.objetivo + (f.creado_por ? ' · subida por ' + f.creado_por : ''))}">
        <span class="num">${esc(f.numero)}</span>
        ${f.adjunto ? '<span class="clip" title="Tiene adjunto">&#x1F4CE;</span>' : ''}
    </button>`;
}

function pintar() {
    const lista = filtrados();
    const nFact = lista.reduce((s, i) => s + i.facturas.length, 0);
    const total = lista.reduce((s, i) => s + i.monto, 0);
    $('counter').textContent = `(${lista.length} ${lista.length === 1 ? 'ingreso' : 'ingresos'} · ${nFact} ${nFact === 1 ? 'factura' : 'facturas'})`;
    $('total').innerHTML = lista.length ? `Total ingresos: <b>${pesos(total)}</b>` : '';

    if (!lista.length) {
        $('tablaWrap').innerHTML = `<div style="padding:2rem;text-align:center;color:var(--text-muted);">
            ${ingresos.length ? 'No hay ingresos que coincidan con los filtros.' : 'Todavía no hay ingresos registrados. Cargá el primero arriba.'}</div>`;
        return;
    }
    $('tablaWrap').innerHTML = `
        <table class="inv-table ret-table">
            <thead><tr>
                <th>Fecha</th><th>Período</th><th>Ingreso</th><th class="facturas-th">Factura/s</th>${esAdmin ? '<th></th>' : ''}
            </tr></thead>
            <tbody>${lista.map(i => `
                <tr>
                    <td data-label="Fecha"><div>${fecha(i.fecha)}${i.creado_por ? `<div class="inv-muted" title="Registró el ingreso el ${fechaHora(i.creado_en)}">por ${esc(i.creado_por)}</div>` : ''}</div></td>
                    <td data-label="Período"><div>${esc(i.periodo)}</div></td>
                    <td data-label="Ingreso" class="monto"><div>${pesos(i.monto)}</div></td>
                    <td data-label="Factura/s"><div>
                        <div class="fact-cell">
                            <button type="button" class="fact-add" data-agregar="${i.id}" title="Agregar factura">+</button>
                            <div class="fact-stack">
                                ${i.facturas.length ? i.facturas.map(chip).join('') : '<span class="fact-vacia">Sin facturas</span>'}
                            </div>
                        </div>
                    </div></td>
                    ${esAdmin ? `<td><div class="actions">
                        <button type="button" class="btn btn-outline btn-sm" data-eliminar="${i.id}" title="Eliminar ingreso">&#x1F5D1;</button>
                    </div></td>` : ''}
                </tr>`).join('')}
            </tbody>
        </table>`;
}

$('tablaWrap').addEventListener('click', e => {
    const add = e.target.closest('[data-agregar]');
    if (add) return abrirFactura(+add.dataset.agregar);
    const ch = e.target.closest('[data-factura]');
    if (ch) return abrirDetalle(+ch.dataset.factura);
    const del = e.target.closest('[data-eliminar]');
    if (del) return eliminarIngreso(+del.dataset.eliminar);
});
$('fPeriodo').addEventListener('change', pintar);
// Exporta lo que se ve: respeta el filtro de período y la búsqueda
$('btnExcel').addEventListener('click', () => {
    const q = new URLSearchParams();
    if ($('fPeriodo').value) q.set('periodo', $('fPeriodo').value);
    if ($('fBuscar').value.trim()) q.set('q', $('fBuscar').value.trim());
    window.location.href = 'api/export_retenciones_excel.php' + (q.toString() ? '?' + q : '');
});
$('fBuscar').addEventListener('input', pintar);

// ---------- Alta de ingreso ----------
$('formIngreso').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('fecha', $('iFecha').value);
    fd.append('periodo', $('iPeriodo').value);
    fd.append('monto', $('iMonto').value);
    if (!$('iFecha').value) return mostrarError('Completá la fecha.');
    if (!$('iMonto').value.trim()) { $('iMonto').focus(); return mostrarError('Completá el monto del ingreso.'); }
    const btn = $('btnIngreso'); btn.disabled = true;
    try {
        await post('crear_ingreso', fd);
        $('iMonto').value = '';
        mostrarExito('Ingreso registrado. Ya se le pueden agregar facturas con el botón +.');
        await cargar();
    } catch (err) { mostrarError(err.message); }
    finally { btn.disabled = false; }
});

async function eliminarIngreso(id) {
    const i = ingresos.find(x => x.id === id); if (!i) return;
    const extra = i.facturas.length ? ` y sus ${i.facturas.length} factura(s)` : '';
    if (!confirm(`¿Eliminar el ingreso del ${fecha(i.fecha)} (${i.periodo}, ${pesos(i.monto)})${extra}?`)) return;
    const fd = new FormData(); fd.append('id', id);
    try { await post('eliminar_ingreso', fd); mostrarExito('Ingreso eliminado.'); cargar(); }
    catch (err) { mostrarError(err.message); }
}

// ---------- Agregar factura ----------
function contexto(i) {
    return `Ingreso del <b>${fecha(i.fecha)}</b> · <b>${esc(i.periodo)}</b> · <b>${pesos(i.monto)}</b>` +
        (i.creado_por ? ` · registrado por <b>${esc(i.creado_por)}</b>` : '');
}
function abrirFactura(id) {
    ingresoActual = ingresos.find(x => x.id === id); if (!ingresoActual) return;
    $('formFactura').reset();
    $('facError').classList.remove('show');
    $('facContexto').innerHTML = contexto(ingresoActual) +
        (ingresoActual.facturas.length ? ` · ${ingresoActual.facturas.length} factura(s) cargada(s)` : '');
    $('modalFactura').classList.add('open');
    setTimeout(() => $('fNumero').focus(), 50);
}
function cerrarFactura() { $('modalFactura').classList.remove('open'); ingresoActual = null; }

$('formFactura').addEventListener('submit', async e => {
    e.preventDefault();
    const err = m => { $('facErrorMsg').textContent = m; $('facError').classList.add('show'); };
    if (!$('fNumero').value.trim()) { $('fNumero').focus(); return err('Falta el número de factura.'); }
    if (!$('fObjetivo').value.trim()) { $('fObjetivo').focus(); return err('Falta el objetivo.'); }
    const archivo = $('fAdjunto').files[0];
    if (archivo && archivo.size > MAX_MB * 1048576) return err(`El adjunto supera ${MAX_MB} MB.`);

    const fd = new FormData();
    fd.append('id_ingreso', ingresoActual.id);
    fd.append('numero', $('fNumero').value);
    fd.append('objetivo', $('fObjetivo').value);
    fd.append('observaciones', $('fObs').value);
    if (archivo) fd.append('adjunto', archivo);

    const btn = $('btnFactura'); btn.disabled = true; btn.textContent = archivo ? 'Subiendo...' : 'Guardando...';
    try {
        const r = await post('agregar_factura', fd);
        ingresoActual.facturas.push(r.factura);   // se apila en la misma fila
        cerrarFactura(); pintar();
        mostrarExito(`Factura ${r.factura.numero} agregada.`);
    } catch (ex) { err(ex.message); }
    finally { btn.disabled = false; btn.textContent = 'Agregar factura'; }
});

// ---------- Detalle de factura ----------
function abrirDetalle(idFactura) {
    let ing = null, f = null;
    for (const i of ingresos) { f = i.facturas.find(x => x.id === idFactura); if (f) { ing = i; break; } }
    if (!f) return;
    facturaActual = f;
    $('detTitulo').textContent = 'Factura ' + f.numero;
    $('detContexto').innerHTML = contexto(ing);
    $('detGrid').innerHTML = `
        <dt>Número</dt><dd>${esc(f.numero)}</dd>
        <dt>Objetivo</dt><dd>${esc(f.objetivo)}</dd>
        <dt>Observaciones</dt><dd>${f.observaciones ? esc(f.observaciones) : '<span class="inv-muted">—</span>'}</dd>
        <dt>Subida por</dt><dd>${f.creado_por ? esc(f.creado_por) : '<span class="inv-muted">—</span>'} <span class="inv-muted">· ${fechaHora(f.creado_en)}</span></dd>
        <dt>Adjunto</dt><dd>${f.adjunto ? esc(f.adjunto.nombre) + ` <span class="inv-muted">(${tam(f.adjunto.tamanio)})</span>` : '<span class="inv-muted">Sin adjunto</span>'}</dd>`;
    const a = f.adjunto;
    $('detAdjunto').innerHTML = !a ? '' : a.imagen
        ? `<div class="det-adjunto"><img src="${esc(a.url)}" alt="Adjunto"></div>`
        : a.pdf ? `<div class="det-adjunto"><iframe src="${esc(a.url)}"></iframe></div>` : '';
    $('detDescargar').style.display = a ? '' : 'none';
    if (a) $('detDescargar').href = a.descargar;
    $('detEliminar').style.display = esAdmin ? '' : 'none';
    $('modalDetalle').classList.add('open');
}
function cerrarDetalle() { $('modalDetalle').classList.remove('open'); $('detAdjunto').innerHTML = ''; facturaActual = null; }

$('detEliminar').addEventListener('click', async () => {
    const f = facturaActual; if (!f || !confirm(`¿Eliminar la factura ${f.numero}?`)) return;
    const fd = new FormData(); fd.append('id', f.id);
    try { await post('eliminar_factura', fd); cerrarDetalle(); mostrarExito(`Factura ${f.numero} eliminada.`); cargar(); }
    catch (err) { mostrarError(err.message); }
});

// Cerrar modales con clic afuera o Escape
['modalFactura', 'modalDetalle'].forEach(id => $(id).addEventListener('click', e => {
    if (e.target.id === id) id === 'modalFactura' ? cerrarFactura() : cerrarDetalle();
}));
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if ($('modalFactura').classList.contains('open')) cerrarFactura();
    if ($('modalDetalle').classList.contains('open')) cerrarDetalle();
});

cargar();
</script>
</body>
</html>
