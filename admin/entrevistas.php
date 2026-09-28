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
    <title>TDV - Entrevistas</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <style>
        .page-shell { max-width: 1200px; margin: 0 auto; padding: 1.2rem 1rem 2rem; }
        .page-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
        .page-title { font-size:1.25rem; color:var(--primary); margin:0; }
        .panel { background:var(--card); border-radius:10px; box-shadow:var(--shadow); padding:1rem; margin-bottom:1rem; }
        .section-title { font-size:1rem; color:var(--primary); margin:.2rem 0 1rem; font-weight:700; }
        .form-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:0 1rem; }
        .form-grid .full { grid-column:1 / -1; }
        .form-group { margin-bottom:1rem; }
        .form-group label { font-size:.85rem; }
        .form-group input,
        .form-group select,
        .form-group textarea { font-size:.95rem; padding:.7rem .8rem; border-radius:8px; }

        .detail-box { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:.7rem; margin-top:1rem; }
        .detail-item { background:var(--bg); border-radius:8px; padding:.65rem .75rem; }
        .detail-label { color:var(--text-muted); font-size:.72rem; font-weight:700; text-transform:uppercase; margin-bottom:.2rem; }
        .detail-value { color:var(--text); font-size:.86rem; word-break:break-word; }

        .puntaje-bar {
            display:flex; gap:1rem; flex-wrap:wrap; align-items:stretch;
            margin-top:.5rem;
        }
        .puntaje-box {
            flex:1; min-width:220px;
            background:var(--bg); border:1px solid var(--border); border-radius:10px;
            padding:.9rem 1rem; text-align:center;
        }
        .puntaje-box .puntaje-label { font-size:.78rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); }
        .puntaje-box .puntaje-valor { font-size:2rem; font-weight:700; color:var(--primary); line-height:1.2; }
        .puntaje-hint { font-size:.78rem; color:var(--text-muted); margin-top:.2rem; }

        .hint-punto { display:none; font-size:.78rem; color:#1e8449; font-weight:600; margin-top:.3rem; }
        .hint-punto.show { display:block; }
        .row-actions { display:flex; gap:.45rem; align-items:center; flex-wrap:nowrap; }

        .table-wrap { overflow-x:auto; background:var(--card); border-radius:10px; box-shadow:var(--shadow); }
        table { width:100%; border-collapse:collapse; min-width:900px; }
        th { background:var(--primary); color:#fff; text-align:left; padding:.75rem .8rem; font-size:.82rem; white-space:nowrap; }
        td { padding:.75rem .8rem; border-bottom:1px solid var(--border); font-size:.86rem; vertical-align:top; }
        tr:hover td { background:#fafafa; }
        .name { font-weight:700; color:var(--text); }
        .muted { color:var(--text-muted); font-size:.78rem; }
        .empty { text-align:center; color:var(--text-muted); padding:2rem; }
        .detail-row { display:none; }
        .detail-row.open { display:table-row; }

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

        @media (max-width: 900px) {
            .form-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
            .detail-box { grid-template-columns:repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 580px) {
            .form-grid { grid-template-columns:1fr; }
            .detail-box { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

<main class="page-shell">
    <div class="page-head">
        <h1 class="page-title">Entrevistas</h1>
    </div>

    <div class="alert alert-danger"  id="msgError" role="alert"><span>&#9888;</span><span id="msgErrorText"></span></div>
    <div class="alert alert-success" id="msgOk" role="alert"><span>&#9989;</span><span id="msgOkText"></span></div>

    <!-- PASO 1: POSTULANTE -->
    <section class="panel">
        <div class="section-title">1. Postulante</div>
        <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
            <button type="button" class="btn btn-primary btn-sm" id="btnBuscarPostulante">Buscar postulante</button>
            <span class="muted" id="postulanteElegido">Todavía no se eligió ningún postulante.</span>
        </div>
        <div class="detail-box" id="datosPostulante" style="display:none;"></div>
    </section>

    <!-- PASO 2: ENTREVISTA -->
    <section class="panel">
        <div class="section-title">2. Datos de la entrevista</div>
        <form id="formEntrevista" novalidate>
            <input type="hidden" id="postulante_id" value="">

            <div class="form-grid">
                <div class="form-group">
                    <label for="peso">Peso (kg)</label>
                    <input type="number" id="peso" min="0" step="0.1" placeholder="Ej: 78.5">
                </div>
                <div class="form-group">
                    <label for="altura">Altura (cm)</label>
                    <input type="number" id="altura" min="0" step="0.1" placeholder="Ej: 175">
                </div>
                <div class="form-group">
                    <label for="relacion_peso_altura">Relación peso/altura <span style="color:var(--danger)">*</span></label>
                    <select id="relacion_peso_altura" class="puntaje-input" required>
                        <option value="">Puntaje 1 a 5</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="apariencia_vestimenta">Apariencia / vestimenta <span style="color:var(--danger)">*</span></label>
                    <select id="apariencia_vestimenta" class="puntaje-input" required>
                        <option value="">Puntaje 1 a 5</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modulacion_habla">Modulación y habla <span style="color:var(--danger)">*</span></label>
                    <select id="modulacion_habla" class="puntaje-input" required>
                        <option value="">Puntaje 1 a 5</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="estado_civil">Estado civil</label>
                    <select id="estado_civil">
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
                    <label for="hijos">Hijos</label>
                    <input type="number" id="hijos" min="0" step="1" placeholder="0">
                </div>
                <div class="form-group">
                    <label for="fecha_ultimo_trabajo">Último trabajo en relación de dependencia</label>
                    <input type="date" id="fecha_ultimo_trabajo" class="puntaje-input">
                    <div class="hint-punto" id="hintPuntoTrabajo">+1 punto: hace más de 6 meses sin relación de dependencia</div>
                </div>
                <div class="form-group">
                    <label for="tiene_vehiculo">Vehículo</label>
                    <select id="tiene_vehiculo" class="puntaje-input">
                        <option value="">Seleccione</option>
                        <option value="si">Si</option>
                        <option value="no">No</option>
                    </select>
                    <div class="hint-punto" id="hintPuntoVehiculo">+1 punto: tiene vehículo</div>
                </div>
                <div class="form-group" id="grupoVehiculo" style="display:none;">
                    <label for="vehiculo">¿Qué vehículo?</label>
                    <input type="text" id="vehiculo" placeholder="Ej: Auto Fiat Cronos / Moto">
                </div>
                <div class="form-group full">
                    <label for="domicilio">Domicilio</label>
                    <input type="text" id="domicilio" placeholder="Calle, número, localidad">
                </div>
                <div class="form-group">
                    <label for="valoracion_personal">Valoración personal <span style="color:var(--danger)">*</span></label>
                    <select id="valoracion_personal" class="puntaje-input" required>
                        <option value="">Puntaje 1 a 5</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column:span 2;">
                    <label for="valoracion_texto">Comentario de la valoración</label>
                    <textarea id="valoracion_texto" rows="2" placeholder="Observaciones del entrevistador..."></textarea>
                </div>
            </div>

            <div class="puntaje-bar">
                <div class="puntaje-box">
                    <div class="puntaje-label">Puntaje sin valoración personal</div>
                    <div class="puntaje-valor" id="puntajeBase">0</div>
                    <div class="puntaje-hint">Peso/altura + apariencia + habla + punto por tiempo sin empleo + punto por vehículo</div>
                </div>
                <div class="puntaje-box">
                    <div class="puntaje-label">Puntaje con valoración personal</div>
                    <div class="puntaje-valor" id="puntajeTotal">0</div>
                    <div class="puntaje-hint">Puntaje anterior + valoración personal</div>
                </div>
            </div>

            <div style="display:flex; gap:.8rem; justify-content:flex-end; margin-top:1.2rem; flex-wrap:wrap;">
                <button type="button" class="btn btn-outline" id="btnLimpiar">Limpiar</button>
                <button type="submit" class="btn btn-primary" id="btnGuardar" style="width:auto; min-width:180px;">Guardar entrevista</button>
            </div>
        </form>
    </section>

    <!-- HISTORIAL -->
    <section class="panel">
        <div class="section-title" style="display:flex; justify-content:space-between; align-items:center;">
            <span>Entrevistas registradas</span>
            <span class="muted" id="counterEntrevistas"></span>
        </div>
        <div class="table-wrap" style="box-shadow:none;">
            <table>
                <thead>
                    <tr>
                        <th>Postulante</th>
                        <th>Fecha</th>
                        <th>Entrevistador</th>
                        <th>P. sin valoración</th>
                        <th>P. total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyEntrevistas">
                    <tr><td colspan="6" class="empty">Cargando entrevistas...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<!-- MODAL POSTULANTES -->
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

<script>
let postulantes = [];
let postulanteActual = null;

const modalPostulantes = document.getElementById('modalPostulantes');
const listaPostulantes = document.getElementById('listaPostulantes');
const buscarPostulante = document.getElementById('buscarPostulante');
const form = document.getElementById('formEntrevista');
const err = document.getElementById('msgError');
const ok = document.getElementById('msgOk');

document.addEventListener('DOMContentLoaded', () => {
    cargarEntrevistas();

    document.getElementById('btnBuscarPostulante').addEventListener('click', abrirModalPostulantes);
    document.getElementById('btnCerrarPostulantes').addEventListener('click', () => modalPostulantes.classList.remove('open'));
    buscarPostulante.addEventListener('input', renderPostulantes);

    document.getElementById('tiene_vehiculo').addEventListener('change', toggleVehiculo);
    document.querySelectorAll('.puntaje-input').forEach(el => el.addEventListener('input', actualizarPuntajes));

    document.getElementById('btnLimpiar').addEventListener('click', limpiarFormulario);
    form.addEventListener('submit', onGuardar);
});

// ---- Modal postulantes ------------------------------------
async function abrirModalPostulantes() {
    modalPostulantes.classList.add('open');
    buscarPostulante.focus();
    if (postulantes.length) { renderPostulantes(); return; }
    try {
        const res = await fetch('api/get_postulantes.php');
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'No se pudieron cargar los postulantes.');
        postulantes = data;
        renderPostulantes();
    } catch (error) {
        listaPostulantes.innerHTML = `<div style="padding:1rem;color:var(--danger);">${esc(error.message)}</div>`;
    }
}

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
    postulanteActual = p;
    document.getElementById('postulante_id').value = p.id;
    document.getElementById('postulanteElegido').textContent = `${p.nombre_completo} (DNI ${p.dni})`;

    const box = document.getElementById('datosPostulante');
    box.style.display = 'grid';
    box.innerHTML = [
        ['Nombre', p.nombre_completo],
        ['DNI', p.dni],
        ['Fecha de nacimiento', fmtFecha(p.fecha_nacimiento) + (p.edad ? ` (${esc(p.edad)} años)` : '')],
        ['Género', p.genero],
        ['Teléfono', p.telefono],
        ['Email', p.email],
        ['Localidad', p.localidad_residencia],
        ['Puesto', p.puesto_postula],
        ['Disponibilidad', p.disponibilidad_horaria],
        ['Experiencia en seguridad', siNo(p.experiencia_seguridad)],
        ['Curso habilitante', siNo(p.curso_habilitante)],
        ['Credencial vigente', siNo(p.credencial_vigente)],
        ['Fue parte de Track', siNo(p.parte_track_seguridad)],
        ['Monotributista', siNo(p.monotributista)],
    ].map(([label, value]) => `
        <div class="detail-item">
            <div class="detail-label">${esc(label)}</div>
            <div class="detail-value">${esc(value) || '-'}</div>
        </div>`).join('');

    modalPostulantes.classList.remove('open');
}

// ---- Formulario -------------------------------------------
function toggleVehiculo() {
    const tiene = document.getElementById('tiene_vehiculo').value === 'si';
    document.getElementById('grupoVehiculo').style.display = tiene ? '' : 'none';
    if (!tiene) document.getElementById('vehiculo').value = '';
}

function puntoTrabajo() {
    const fecha = document.getElementById('fecha_ultimo_trabajo').value;
    if (!fecha) return 0;
    const limite = new Date();
    limite.setMonth(limite.getMonth() - 6);
    return new Date(fecha + 'T00:00:00') < limite ? 1 : 0;
}

function puntoVehiculo() {
    return document.getElementById('tiene_vehiculo').value === 'si' ? 1 : 0;
}

function puntaje(id) {
    const v = parseInt(document.getElementById(id).value, 10);
    return (v >= 1 && v <= 5) ? v : 0;
}

function actualizarPuntajes() {
    const extra = puntoTrabajo();
    const extraVehiculo = puntoVehiculo();
    document.getElementById('hintPuntoTrabajo').classList.toggle('show', extra === 1);
    document.getElementById('hintPuntoVehiculo').classList.toggle('show', extraVehiculo === 1);
    const base = puntaje('relacion_peso_altura') + puntaje('apariencia_vestimenta') + puntaje('modulacion_habla') + extra + extraVehiculo;
    document.getElementById('puntajeBase').textContent = base;
    document.getElementById('puntajeTotal').textContent = base + puntaje('valoracion_personal');
}

function limpiarFormulario() {
    form.reset();
    postulanteActual = null;
    document.getElementById('postulante_id').value = '';
    document.getElementById('postulanteElegido').textContent = 'Todavía no se eligió ningún postulante.';
    document.getElementById('datosPostulante').style.display = 'none';
    document.getElementById('grupoVehiculo').style.display = 'none';
    actualizarPuntajes();
}

async function onGuardar(e) {
    e.preventDefault();
    err.classList.remove('show');
    ok.classList.remove('show');

    const postulanteId = parseInt(document.getElementById('postulante_id').value, 10);
    if (!postulanteId) {
        showError('Primero elegí un postulante con el botón "Buscar postulante".');
        return;
    }

    const payload = {
        postulante_id: postulanteId,
        peso: field('peso'),
        altura: field('altura'),
        relacion_peso_altura: field('relacion_peso_altura'),
        apariencia_vestimenta: field('apariencia_vestimenta'),
        modulacion_habla: field('modulacion_habla'),
        estado_civil: field('estado_civil'),
        hijos: field('hijos'),
        domicilio: field('domicilio'),
        tiene_vehiculo: field('tiene_vehiculo'),
        vehiculo: field('vehiculo'),
        fecha_ultimo_trabajo: field('fecha_ultimo_trabajo'),
        valoracion_personal: field('valoracion_personal'),
        valoracion_texto: field('valoracion_texto'),
    };

    for (const campo of ['relacion_peso_altura', 'apariencia_vestimenta', 'modulacion_habla', 'valoracion_personal']) {
        if (!payload[campo]) {
            showError('Completá todos los puntajes (1 a 5).');
            return;
        }
    }
    if (payload.tiene_vehiculo === 'si' && !payload.vehiculo) {
        showError('Indicá qué vehículo tiene el postulante.');
        return;
    }

    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
        const res = await fetch('api/guardar_entrevista.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.error || 'No se pudo guardar la entrevista.');
        }
        showOk(`Entrevista guardada. Puntaje sin valoración: ${data.puntaje_sin_valoracion} · Puntaje total: ${data.puntaje_total}`);
        limpiarFormulario();
        cargarEntrevistas();
    } catch (error) {
        showError(error.message);
    }

    btn.disabled = false;
    btn.textContent = 'Guardar entrevista';
}

// ---- Historial --------------------------------------------
async function cargarEntrevistas() {
    const tbody = document.getElementById('tbodyEntrevistas');
    const counter = document.getElementById('counterEntrevistas');
    try {
        const res = await fetch('api/get_entrevistas.php');
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Error al cargar entrevistas');
        counter.textContent = `${data.length} entrevista${data.length === 1 ? '' : 's'}`;
        if (!data.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="empty">Todavía no hay entrevistas registradas.</td></tr>';
            return;
        }
        tbody.innerHTML = data.map((it) => `
            <tr>
                <td>
                    <div class="name">${esc(it.nombre_completo)}</div>
                    <div class="muted">DNI ${esc(it.dni)} · ${esc(it.puesto_postula || '')}</div>
                </td>
                <td>${esc(it.fecha_fmt)}</td>
                <td>${esc(it.entrevistador || '-')}</td>
                <td><strong>${esc(it.puntaje_sin_valoracion)}</strong></td>
                <td><strong>${esc(it.puntaje_total)}</strong></td>
                <td style="white-space:nowrap;">
                    <div class="row-actions">
                        <button class="btn btn-outline btn-sm" onclick="toggleDetalle(${Number(it.id_entrevista)})">Ver</button>
                        <button class="btn btn-success btn-sm" onclick="contratarEntrevista(${Number(it.id_entrevista)})">Contratar</button>
                    </div>
                </td>
            </tr>
            <tr class="detail-row" id="detalle-${Number(it.id_entrevista)}">
                <td colspan="6">
                    <div class="detail-box">
                        ${detalle('Peso', it.peso ? it.peso + ' kg' : '')}
                        ${detalle('Altura', it.altura ? it.altura + ' cm' : '')}
                        ${detalle('Relación peso/altura', it.relacion_peso_altura)}
                        ${detalle('Apariencia/vestimenta', it.apariencia_vestimenta)}
                        ${detalle('Modulación y habla', it.modulacion_habla)}
                        ${detalle('Estado civil', it.estado_civil)}
                        ${detalle('Hijos', it.hijos)}
                        ${detalle('Domicilio', it.domicilio)}
                        ${detalle('Vehículo', it.tiene_vehiculo === 'si' ? (it.vehiculo || 'Si') : siNo(it.tiene_vehiculo))}
                        ${detalle('Último trabajo en rel. dep.', fmtFecha(it.fecha_ultimo_trabajo))}
                        ${detalle('Punto +6 meses', it.punto_ultimo_trabajo == 1 ? 'Si (+1)' : 'No')}
                        ${detalle('Punto vehículo', it.tiene_vehiculo === 'si' ? 'Si (+1)' : 'No')}
                        ${detalle('Valoración personal', it.valoracion_personal)}
                        ${detalle('Comentario', it.valoracion_texto)}
                        ${detalle('Teléfono', it.telefono)}
                        ${detalle('Email', it.email)}
                        ${detalle('Localidad', it.localidad_residencia)}
                    </div>
                </td>
            </tr>`).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" class="empty" style="color:var(--danger);">${esc(e.message)}</td></tr>`;
    }
}

function toggleDetalle(id) {
    const row = document.getElementById('detalle-' + id);
    if (row) row.classList.toggle('open');
}

async function contratarEntrevista(id) {
    if (!confirm('¿Marcar esta entrevista como contratada? Pasará al historial de contratados.')) return;
    try {
        const res = await fetch('api/contratar_entrevista.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || 'No se pudo actualizar la entrevista.');
        showOk('Entrevista marcada como contratada. Ahora figura en la página Historial.');
        cargarEntrevistas();
    } catch (e) {
        showError(e.message);
    }
}

function detalle(label, value) {
    return `
        <div class="detail-item">
            <div class="detail-label">${esc(label)}</div>
            <div class="detail-value">${esc(value) || '-'}</div>
        </div>`;
}

// ---- Utilidades -------------------------------------------
function field(id) {
    return document.getElementById(id).value.trim();
}

function siNo(v) {
    return v === 'si' ? 'Si' : (v === 'no' ? 'No' : '');
}

function fmtFecha(fecha) {
    if (!fecha) return '';
    const partes = String(fecha).split('-');
    return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : String(fecha);
}

function esc(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
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
</script>
</body>
</html>
