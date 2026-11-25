<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api/archivos_helper.php';
requireBackofficePage();
$carpetaInicial = array_key_exists($_GET['c'] ?? '', ARCHIVOS_CARPETAS) ? $_GET['c'] : array_key_first(ARCHIVOS_CARPETAS);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Facturas y pagos</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css?v=20261020">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <style>
        /* Section header */
        .section-header {
            display:flex;align-items:center;justify-content:space-between;
            margin-bottom:.8rem;gap:.8rem;flex-wrap:wrap;
        }
        .section-title { font-size:1rem;font-weight:700;color:var(--primary); }
        .section-sub { font-size:.82rem;color:var(--text-muted);font-weight:400;margin-left:.4rem; }

        /* Carpetas */
        .carpetas { display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem; }
        .carpeta-btn {
            display:flex;align-items:center;gap:.55rem;
            padding:.65rem 1.1rem;border-radius:10px;cursor:pointer;
            border:2px solid var(--border);background:#fff;color:var(--text);
            font-size:.95rem;font-weight:600;transition:border-color .2s, background .2s;
        }
        .carpeta-btn:hover { border-color:var(--primary); }
        .carpeta-btn.active { background:var(--primary);border-color:var(--primary);color:#fff; }
        .carpeta-btn .count {
            font-size:.75rem;font-weight:700;border-radius:20px;padding:.1rem .55rem;
            background:var(--bg);color:var(--text-muted);
        }
        .carpeta-btn.active .count { background:rgba(255,255,255,.2);color:#fff; }

        /* Filtros */
        .filtros { display:flex;gap:.6rem;flex-wrap:wrap;align-items:center; }
        .filtros input, .filtros select {
            width:auto;padding:.55rem .8rem;font-size:.9rem;
        }
        .filtros #buscar { flex:1;min-width:200px;max-width:380px; }
        .filtros input[type="month"] {
            border:2px solid var(--border);border-radius:10px;color:var(--text);
            background:#fff;outline:none;font-family:inherit;
        }
        .filtros input[type="month"]:focus { border-color:var(--accent); }
        .vista-toggle { display:flex;border:2px solid var(--border);border-radius:10px;overflow:hidden; }
        .vista-toggle button {
            border:0;background:#fff;padding:.45rem .75rem;cursor:pointer;
            color:var(--text-muted);font-size:1rem;
        }
        .vista-toggle button.active { background:var(--primary);color:#fff; }

        /* Drag and Drop Zone (igual que legajos) */
        .dropzone {
            border:2px dashed #3498db;border-radius:10px;background:#f7fafd;
            padding:1.4rem;text-align:center;cursor:pointer;
            transition:background .2s, border-color .2s;margin:1rem 0 0;
            display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.35rem;
        }
        .dropzone.dragover { background:#ebf5fb;border-color:#2980b9; }
        .dropzone .icon { font-size:2rem;color:#3498db; }
        .dropzone .text { font-weight:600;color:#2c3e50;font-size:.95rem; }
        .dropzone .subtext { font-size:.8rem;color:var(--text-muted); }
        .progress-container { display:none;margin-top:.8rem; }
        .progress-container.show { display:block; }

        /* Galería */
        .galeria.grilla {
            display:grid;grid-template-columns:repeat(auto-fill, minmax(170px, 1fr));gap:1rem;
        }
        .archivo {
            background:#fff;border:1px solid var(--border);border-radius:10px;
            overflow:hidden;cursor:pointer;position:relative;
            transition:box-shadow .15s, transform .15s;
        }
        .archivo:hover { box-shadow:0 6px 18px rgba(0,0,0,.10);transform:translateY(-1px); }
        .archivo .previa {
            aspect-ratio:4/3;background:#f7f9fc;display:flex;
            align-items:center;justify-content:center;overflow:hidden;
        }
        .archivo .previa img { width:100%;height:100%;object-fit:cover; }
        .archivo .info { padding:.55rem .7rem; }
        .archivo .nombre {
            font-size:.85rem;font-weight:600;white-space:nowrap;
            overflow:hidden;text-overflow:ellipsis;
        }
        .archivo .meta { font-size:.75rem;color:var(--text-muted);margin-top:.15rem; }
        .archivo .acciones { position:absolute;top:.4rem;right:.4rem;display:none;gap:.3rem; }
        .archivo:hover .acciones { display:flex; }
        .acciones a, .acciones button {
            width:30px;height:30px;border:0;border-radius:8px;cursor:pointer;
            background:rgba(255,255,255,.95);box-shadow:0 1px 4px rgba(0,0,0,.2);
            display:flex;align-items:center;justify-content:center;
            text-decoration:none;color:var(--text);font-size:.85rem;
        }
        .acciones .borrar:hover { background:var(--danger);color:#fff; }

        .icono-ext {
            width:52px;height:64px;border-radius:6px;position:relative;
            display:flex;align-items:flex-end;justify-content:center;padding-bottom:.5rem;
            color:#fff;font-weight:700;font-size:.72rem;text-transform:uppercase;background:#7f8c8d;
        }
        .icono-ext::before {
            content:"";position:absolute;top:0;right:0;border-style:solid;border-width:0 14px 14px 0;
            border-color:#f7f9fc #f7f9fc rgba(0,0,0,.2) rgba(0,0,0,.2);
        }
        .icono-ext.pdf { background:#e74c3c; }
        .icono-ext.xls, .icono-ext.xlsx, .icono-ext.csv { background:#27ae60; }
        .icono-ext.doc, .icono-ext.docx { background:#2980b9; }
        .icono-ext.zip { background:#b7950b; }

        /* Vista lista */
        .galeria.lista { display:flex;flex-direction:column; }
        .galeria.lista .archivo {
            display:grid;grid-template-columns:46px 1fr auto;align-items:center;gap:.9rem;
            border:0;border-bottom:1px solid var(--bg);border-radius:0;padding:.45rem .6rem;
        }
        .galeria.lista .archivo:hover { transform:none;box-shadow:none;background:#f7f9fc; }
        .galeria.lista .previa { aspect-ratio:1;width:46px;border-radius:6px; }
        .galeria.lista .icono-ext { width:28px;height:34px;font-size:.5rem;padding-bottom:.25rem; }
        .galeria.lista .icono-ext::before { border-width:0 8px 8px 0; }
        .galeria.lista .info { padding:0;display:flex;justify-content:space-between;gap:1rem;min-width:0; }
        .galeria.lista .acciones { position:static;display:flex; }
        .galeria.lista .acciones a, .galeria.lista .acciones button { box-shadow:none;background:transparent; }

        .vacio { padding:2.5rem 1rem;text-align:center;color:var(--text-muted);font-size:.92rem; }

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
            width:100%;max-width:480px;max-height:90vh;overflow-y:auto;padding:1.8rem;
        }
        .modal-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;gap:1rem; }
        .modal-title { font-size:1.1rem;font-weight:700;color:var(--primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
        .modal-close { background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted);line-height:1; }
        .modal-close:hover { color:var(--text); }

        /* Visor */
        .modal.visor {
            max-width:1100px;height:90vh;display:flex;flex-direction:column;
            padding:1.2rem 1.4rem;overflow:hidden;
        }
        .visor .modal-header { margin-bottom:.8rem; }
        .visor-acciones { display:flex;gap:.5rem;align-items:center;flex-shrink:0; }
        .visor-cuerpo {
            flex:1;min-height:0;position:relative;background:#f7f9fc;border-radius:10px;
            display:flex;align-items:center;justify-content:center;overflow:hidden;
        }
        .visor-cuerpo img { max-width:100%;max-height:100%;object-fit:contain; }
        .visor-cuerpo iframe { width:100%;height:100%;border:0; }
        .visor-nav {
            position:absolute;top:50%;transform:translateY(-50%);z-index:2;
            width:40px;height:56px;border:0;border-radius:8px;cursor:pointer;
            background:rgba(0,0,0,.55);color:#fff;font-size:1.6rem;
        }
        .visor-nav:hover { background:var(--primary); }
        .visor-nav.ant { left:.6rem; } .visor-nav.sig { right:.6rem; }
        .visor-pie { font-size:.8rem;color:var(--text-muted);margin-top:.6rem; }

        @media (max-width:600px) {
            .archivo .acciones { display:flex; }
            .galeria.grilla { grid-template-columns:repeat(auto-fill, minmax(140px, 1fr));gap:.7rem; }
            .galeria.lista .meta { display:none; }
            .modal.visor { height:85vh;padding:1rem; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

<div style="max-width:1200px;margin:0 auto;padding:1.2rem 1rem 2rem;">

    <div class="alert alert-danger"  id="msgError"   role="alert"><span>&#9888;</span><span id="msgErrorTxt"></span></div>
    <div class="alert alert-success" id="msgSuccess" role="alert"><span>&#9989;</span><span id="msgSuccessTxt"></span></div>

    <!-- Carpetas, filtros y subida -->
    <div class="card" style="margin-bottom:1rem; padding:1.2rem;">
        <div class="carpetas" id="carpetas">
            <?php foreach (ARCHIVOS_CARPETAS as $k => $nombre): ?>
                <button type="button" class="carpeta-btn" data-carpeta="<?= htmlspecialchars($k) ?>">
                    &#x1F4C2; <?= htmlspecialchars($nombre) ?>
                    <span class="count" data-count="<?= htmlspecialchars($k) ?>">0</span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="filtros">
            <input type="text" id="buscar" placeholder="Buscar por nombre...">
            <select id="tipo">
                <option value="">Todos los tipos</option>
                <option value="imagen">Imágenes</option>
                <option value="pdf">PDF</option>
                <option value="otro">Otros</option>
            </select>
            <input type="month" id="mes" title="Mes de carga">
            <div class="vista-toggle">
                <button type="button" data-vista="grilla" title="Galería">&#x25A6;</button>
                <button type="button" data-vista="lista" title="Lista">&#x2630;</button>
            </div>
        </div>

        <div class="dropzone" id="dropzone">
            <div class="icon">&#x1F4E4;</div>
            <div class="text">Arrastrá archivos acá o hacé clic para subirlos a <span id="dzDestino"></span></div>
            <div class="subtext">PDF, imágenes, Excel, Word, CSV o ZIP · hasta <?= ARCHIVOS_MAX_MB ?> MB por archivo · subida múltiple</div>
            <input type="file" id="fArchivos" multiple style="display:none;">
        </div>

        <div class="progress-container" id="progreso">
            <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:var(--text-muted); margin-bottom:0.2rem;">
                <span id="progresoTxt">Subiendo archivos...</span>
                <span id="progresoPct">0%</span>
            </div>
            <div class="progress-wrap"><div class="progress-bar" id="progresoBar"></div></div>
        </div>
    </div>

    <!-- Galería -->
    <div class="card">
        <div class="section-header">
            <span class="section-title">&#x1F5C2; <span id="titulo"></span><span class="section-sub" id="subtitulo"></span></span>
        </div>
        <div id="galeria" class="galeria grilla">
            <div class="vacio" style="grid-column:1/-1;">
                <div class="spinner spinner-dark" style="margin:0 auto .8rem;"></div>
                Cargando...
            </div>
        </div>
    </div>

</div>

<!-- VISOR -->
<div class="modal-overlay" id="visorOverlay">
    <div class="modal visor">
        <div class="modal-header">
            <span class="modal-title" id="visorTitulo"></span>
            <div class="visor-acciones">
                <a class="btn btn-outline btn-sm" id="visorDescargar" style="padding:.45rem .9rem;font-size:.85rem;text-decoration:none;">&#x2B07; Descargar</a>
                <button class="modal-close" id="visorCerrar">&#x2715;</button>
            </div>
        </div>
        <div class="visor-cuerpo">
            <button class="visor-nav ant" id="visorAnt" title="Anterior">&#x2039;</button>
            <div id="visorContenido" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"></div>
            <button class="visor-nav sig" id="visorSig" title="Siguiente">&#x203A;</button>
        </div>
        <div class="visor-pie" id="visorPie"></div>
    </div>
</div>

<!-- RENOMBRAR -->
<div class="modal-overlay" id="renOverlay">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Renombrar archivo</span>
            <button class="modal-close" onclick="cerrarRenombrar()">&#x2715;</button>
        </div>
        <form id="formRenombrar">
            <div class="form-group">
                <label for="fNombre" style="font-size:.85rem;font-weight:700;">Nombre</label>
                <input type="text" id="fNombre" required maxlength="255" style="padding:.6rem .9rem;font-size:.95rem;">
            </div>
            <div style="display:flex;gap:.6rem;justify-content:flex-end;">
                <button type="button" class="btn btn-outline btn-sm" onclick="cerrarRenombrar()">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF     = <?= json_encode(archivosCsrf()) ?>;
const CARPETAS = <?= json_encode(ARCHIVOS_CARPETAS, JSON_UNESCAPED_UNICODE) ?>;
const MAX_MB   = <?= ARCHIVOS_MAX_MB ?>;
const API      = 'api/archivos.php';

const est = { carpeta: <?= json_encode($carpetaInicial) ?>, archivos: [], vista: leerVista(), idx: -1, renId: 0 };
const $ = (id) => document.getElementById(id);

function leerVista() { try { return localStorage.getItem('archivos_vista') || 'grilla'; } catch { return 'grilla'; } }
function guardarVista(v) { try { localStorage.setItem('archivos_vista', v); } catch {} }

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function tam(b) { return b < 1024 ? b + ' B' : b < 1048576 ? Math.round(b / 1024) + ' KB' : (b / 1048576).toFixed(1) + ' MB'; }
function fecha(f) { const d = new Date(String(f).replace(' ', 'T')); return isNaN(d) ? '' : d.toLocaleDateString('es-AR'); }

let msgTimer;
function mostrarMsg(tipo, txt) {
    clearTimeout(msgTimer);
    $('msgError').classList.remove('show'); $('msgSuccess').classList.remove('show');
    const box = tipo === 'error' ? 'msgError' : 'msgSuccess';
    $(box + 'Txt').textContent = txt;
    $(box).classList.add('show');
    msgTimer = setTimeout(() => $(box).classList.remove('show'), tipo === 'error' ? 8000 : 4000);
}

async function api(accion, params = {}, body = null) {
    const r = await fetch(API + '?' + new URLSearchParams({ accion, ...params }), body ? { method: 'POST', body } : {});
    const data = await r.json().catch(() => ({ error: 'Respuesta inválida del servidor' }));
    if (!r.ok && data.error) throw new Error(data.error);
    return data;
}

// ---------- Carpetas ----------
async function cargarResumen() {
    try {
        const r = await api('resumen');
        for (const [k, v] of Object.entries(r)) {
            const el = document.querySelector(`[data-count="${k}"]`);
            if (el) el.textContent = v.cantidad;
        }
    } catch (e) {}
}

function elegirCarpeta(c) {
    est.carpeta = c;
    document.querySelectorAll('.carpeta-btn').forEach(b => b.classList.toggle('active', b.dataset.carpeta === c));
    $('titulo').textContent = CARPETAS[c];
    $('dzDestino').textContent = CARPETAS[c];
    history.replaceState(null, '', '?c=' + c);
    cargar();
}

// ---------- Listado ----------
async function cargar() {
    try {
        est.archivos = await api('listar', {
            carpeta: est.carpeta, q: $('buscar').value.trim(), tipo: $('tipo').value, mes: $('mes').value,
        });
        pintar();
    } catch (e) { mostrarMsg('error', e.message); }
}

function previa(a) {
    return a.miniatura
        ? `<img loading="lazy" src="${esc(a.miniatura)}" alt="">`
        : `<div class="icono-ext ${esc(a.extension)}">${esc(a.extension)}</div>`;
}

function pintar() {
    const g = $('galeria');
    g.className = 'galeria ' + est.vista;
    document.querySelectorAll('.vista-toggle button').forEach(b => b.classList.toggle('active', b.dataset.vista === est.vista));
    const n = est.archivos.length;
    $('subtitulo').textContent = n === 1 ? '1 archivo' : `${n} archivos`;
    if (!n) {
        const filtrando = $('buscar').value || $('tipo').value || $('mes').value;
        g.innerHTML = `<div class="vacio" style="grid-column:1/-1;">${filtrando ? 'No hay archivos que coincidan con los filtros.' : 'Todavía no hay archivos en esta carpeta.'}</div>`;
        return;
    }
    g.innerHTML = est.archivos.map((a, i) => `
        <div class="archivo" data-i="${i}">
            <div class="previa">${previa(a)}</div>
            <div class="info">
                <div class="nombre" title="${esc(a.nombre)}">${esc(a.nombre)}</div>
                <div class="meta">${fecha(a.fecha)} · ${tam(a.tamanio)}</div>
            </div>
            <div class="acciones">
                <a href="${esc(a.descargar)}" title="Descargar" data-accion="descargar">&#x2B07;</a>
                <button type="button" title="Renombrar" data-accion="renombrar">&#x270E;</button>
                <button type="button" class="borrar" title="Eliminar" data-accion="eliminar">&#x1F5D1;</button>
            </div>
        </div>`).join('');
}

$('galeria').addEventListener('click', e => {
    const card = e.target.closest('.archivo'); if (!card) return;
    const i = +card.dataset.i, a = est.archivos[i];
    const acc = e.target.closest('[data-accion]')?.dataset.accion;
    if (acc === 'descargar') return;
    if (acc === 'renombrar') return abrirRenombrar(a);
    if (acc === 'eliminar') return eliminar(a);
    abrirVisor(i);
});

// ---------- Renombrar / eliminar ----------
function abrirRenombrar(a) {
    est.renId = a.id; $('fNombre').value = a.nombre;
    $('renOverlay').classList.add('open');
    setTimeout(() => $('fNombre').select(), 50);
}
function cerrarRenombrar() { $('renOverlay').classList.remove('open'); }
$('formRenombrar').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(); fd.append('id', est.renId); fd.append('nombre', $('fNombre').value); fd.append('csrf', CSRF);
    try { await api('renombrar', {}, fd); cerrarRenombrar(); mostrarMsg('ok', 'Nombre actualizado.'); cargar(); }
    catch (err) { mostrarMsg('error', err.message); }
});

async function eliminar(a) {
    if (!confirm(`¿Eliminar "${a.nombre}"?`)) return;
    const fd = new FormData(); fd.append('id', a.id); fd.append('csrf', CSRF);
    try { await api('eliminar', {}, fd); mostrarMsg('ok', 'Archivo eliminado.'); cargar(); cargarResumen(); }
    catch (err) { mostrarMsg('error', err.message); }
}

// ---------- Subida ----------
function subir(lista) {
    const todos = [...lista]; if (!todos.length) return;
    const grandes = todos.filter(f => f.size > MAX_MB * 1048576);
    const validos = todos.filter(f => f.size <= MAX_MB * 1048576);
    if (grandes.length) mostrarMsg('error', `${grandes.map(f => f.name).join(', ')}: supera ${MAX_MB} MB.`);
    if (!validos.length) return;

    const fd = new FormData();
    fd.append('carpeta', est.carpeta); fd.append('csrf', CSRF);
    validos.forEach(f => fd.append('archivos[]', f));

    $('progreso').classList.add('show');
    $('progresoBar').style.width = '0%'; $('progresoPct').textContent = '0%';
    $('progresoTxt').textContent = `Subiendo ${validos.length} archivo(s) a ${CARPETAS[est.carpeta]}...`;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', API + '?accion=subir');
    xhr.upload.onprogress = e => {
        if (!e.lengthComputable) return;
        const p = Math.round(e.loaded / e.total * 100);
        $('progresoBar').style.width = p + '%'; $('progresoPct').textContent = p + '%';
    };
    xhr.onload = () => {
        $('progreso').classList.remove('show');
        let r = {}; try { r = JSON.parse(xhr.responseText); } catch {}
        const errores = [...(r.errores || []), ...(r.error ? [r.error] : [])];
        if (errores.length) mostrarMsg('error', errores.join(' · '));
        else if (r.subidos?.length) mostrarMsg('ok', `${r.subidos.length} archivo(s) subido(s) a ${CARPETAS[est.carpeta]}.`);
        cargar(); cargarResumen();
    };
    xhr.onerror = () => { $('progreso').classList.remove('show'); mostrarMsg('error', 'Error de conexión al subir.'); };
    xhr.send(fd);
}

const dz = $('dropzone');
dz.addEventListener('click', () => $('fArchivos').click());
$('fArchivos').addEventListener('change', e => { subir(e.target.files); e.target.value = ''; });
['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); }));
['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); }));
dz.addEventListener('drop', e => subir(e.dataTransfer.files));
// Soltar en cualquier parte de la página también sube
window.addEventListener('dragover', e => e.preventDefault());
window.addEventListener('drop', e => { e.preventDefault(); if (!dz.contains(e.target) && e.dataTransfer?.files?.length) subir(e.dataTransfer.files); });

// ---------- Visor ----------
function abrirVisor(i) {
    est.idx = i;
    const a = est.archivos[i];
    $('visorTitulo').textContent = a.nombre;
    $('visorTitulo').title = a.nombre;
    $('visorDescargar').href = a.descargar;
    $('visorPie').textContent = `${i + 1} de ${est.archivos.length} · ${fecha(a.fecha)} · ${tam(a.tamanio)}${a.subido_por ? ' · Subido por ' + a.subido_por : ''}`;
    const c = $('visorContenido');
    if (a.tipo === 'imagen') c.innerHTML = `<img src="${esc(a.url)}" alt="">`;
    else if (a.tipo === 'pdf') c.innerHTML = `<iframe src="${esc(a.url)}"></iframe>`;
    else c.innerHTML = `<div style="text-align:center;color:var(--text-muted);">
            <div class="icono-ext ${esc(a.extension)}" style="margin:0 auto 1rem;">${esc(a.extension)}</div>
            <p style="margin-bottom:1rem;">Este tipo de archivo no tiene vista previa.</p>
            <a class="btn btn-primary btn-sm" style="text-decoration:none;" href="${esc(a.descargar)}">&#x2B07; Descargar</a></div>`;
    const varios = est.archivos.length > 1;
    $('visorAnt').style.display = $('visorSig').style.display = varios ? '' : 'none';
    $('visorOverlay').classList.add('open');
}
function cerrarVisor() { $('visorOverlay').classList.remove('open'); $('visorContenido').innerHTML = ''; est.idx = -1; }
function moverVisor(d) { if (est.idx < 0) return; const n = est.archivos.length; abrirVisor((est.idx + d + n) % n); }

$('visorCerrar').onclick = cerrarVisor;
$('visorAnt').onclick = () => moverVisor(-1);
$('visorSig').onclick = () => moverVisor(1);
$('visorOverlay').addEventListener('click', e => { if (e.target.id === 'visorOverlay') cerrarVisor(); });
$('renOverlay').addEventListener('click', e => { if (e.target.id === 'renOverlay') cerrarRenombrar(); });
document.addEventListener('keydown', e => {
    if ($('renOverlay').classList.contains('open')) { if (e.key === 'Escape') cerrarRenombrar(); return; }
    if (!$('visorOverlay').classList.contains('open')) return;
    if (e.key === 'Escape') cerrarVisor();
    if (e.key === 'ArrowLeft') moverVisor(-1);
    if (e.key === 'ArrowRight') moverVisor(1);
});

// ---------- Filtros ----------
let buscarTimer;
$('buscar').addEventListener('input', () => { clearTimeout(buscarTimer); buscarTimer = setTimeout(cargar, 250); });
$('tipo').addEventListener('change', cargar);
$('mes').addEventListener('change', cargar);
document.querySelectorAll('.vista-toggle button').forEach(b => b.addEventListener('click', () => {
    est.vista = b.dataset.vista; guardarVista(est.vista); pintar();
}));
document.querySelectorAll('.carpeta-btn').forEach(b => b.addEventListener('click', () => elegirCarpeta(b.dataset.carpeta)));

elegirCarpeta(est.carpeta);
cargarResumen();
</script>
</body>
</html>
