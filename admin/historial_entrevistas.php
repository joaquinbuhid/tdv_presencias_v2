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
    <title>TDV - Historial de contratados</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <style>
        .page-shell { max-width: 1200px; margin: 0 auto; padding: 1.2rem 1rem 2rem; }
        .page-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
        .page-title { font-size:1.25rem; color:var(--primary); margin:0; }
        .panel { background:var(--card); border-radius:10px; box-shadow:var(--shadow); padding:1rem; margin-bottom:1rem; }
        .section-title { font-size:1rem; color:var(--primary); margin:.2rem 0 1rem; font-weight:700; }

        .detail-box { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:.7rem; margin-top:1rem; }
        .detail-item { background:var(--bg); border-radius:8px; padding:.65rem .75rem; }
        .detail-label { color:var(--text-muted); font-size:.72rem; font-weight:700; text-transform:uppercase; margin-bottom:.2rem; }
        .detail-value { color:var(--text); font-size:.86rem; word-break:break-word; }

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

        @media (max-width: 900px) {
            .detail-box { grid-template-columns:repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 580px) {
            .detail-box { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

<main class="page-shell">
    <div class="page-head">
        <h1 class="page-title">Historial de contratados</h1>
    </div>

    <div class="alert alert-danger"  id="msgError" role="alert"><span>&#9888;</span><span id="msgErrorText"></span></div>
    <div class="alert alert-success" id="msgOk" role="alert"><span>&#9989;</span><span id="msgOkText"></span></div>

    <section class="panel">
        <div class="section-title" style="display:flex; justify-content:space-between; align-items:center;">
            <span>Entrevistas que terminaron en contratación</span>
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
                    <tr><td colspan="6" class="empty">Cargando historial...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
const err = document.getElementById('msgError');
const ok = document.getElementById('msgOk');

document.addEventListener('DOMContentLoaded', cargarHistorial);

async function cargarHistorial() {
    const tbody = document.getElementById('tbodyEntrevistas');
    const counter = document.getElementById('counterEntrevistas');
    try {
        const res = await fetch('api/get_entrevistas.php?contratadas=1');
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Error al cargar el historial');
        counter.textContent = `${data.length} contratado${data.length === 1 ? '' : 's'}`;
        if (!data.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="empty">Todavía no hay entrevistas contratadas.</td></tr>';
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
                        <button class="btn btn-outline btn-sm" onclick="devolverEntrevista(${Number(it.id_entrevista)})">Devolver</button>
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

async function devolverEntrevista(id) {
    if (!confirm('¿Quitar esta entrevista del historial de contratados? Volverá a la lista de entrevistas.')) return;
    try {
        const res = await fetch('api/contratar_entrevista.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, contratado: 0 }),
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || 'No se pudo actualizar la entrevista.');
        showOk('La entrevista volvió a la lista de entrevistas.');
        cargarHistorial();
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
