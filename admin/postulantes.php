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
    <title>TDV - Postulantes</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-nav {
            background: var(--primary-dk);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .7rem 1.5rem;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .admin-nav .brand { color:#fff; font-weight:700; font-size:1.1rem; display:flex; align-items:center; gap:.5rem; }
        .admin-nav .nav-links { display:flex; gap:.3rem; flex-wrap:wrap; }
        .admin-nav .nav-links a {
            color: rgba(255,255,255,.75);
            text-decoration: none;
            padding: .4rem .9rem;
            border-radius: 6px;
            font-size: .88rem;
        }
        .admin-nav .nav-links a.active,
        .admin-nav .nav-links a:hover { background: rgba(255,255,255,.15); color:#fff; }
        .admin-nav .nav-user { color: rgba(255,255,255,.7); font-size: .82rem; text-align:right; }
        .admin-nav .nav-user strong { display:block; color:#fff; }

        .page-shell { max-width: 1400px; margin: 0 auto; padding: 1.2rem 1rem 2rem; }
        .page-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
        .page-title { font-size:1.25rem; color:var(--primary); margin:0; }
        .panel { background:var(--card); border-radius:10px; box-shadow:var(--shadow); padding:1rem; margin-bottom:1rem; }
        .filters {
            display:grid;
            grid-template-columns: 1.5fr repeat(3, minmax(130px, 1fr));
            gap:.75rem;
            align-items:end;
        }
        .filters .form-group { margin-bottom:0; }
        .filters label { font-size:.82rem; color:var(--text-muted); }
        .filters input,
        .filters select { font-size:.95rem; padding:.7rem .8rem; border-radius:8px; }
        .filter-actions { display:flex; gap:.5rem; justify-content:flex-end; align-items:end; }
        .row-actions { display:flex; gap:.45rem; align-items:center; flex-wrap:nowrap; }

        .table-wrap { overflow-x:auto; background:var(--card); border-radius:10px; box-shadow:var(--shadow); }
        table { width:100%; border-collapse:collapse; min-width:1200px; }
        th {
            background:var(--primary);
            color:#fff;
            text-align:left;
            padding:.75rem .8rem;
            font-size:.82rem;
            white-space:nowrap;
        }
        td {
            padding:.75rem .8rem;
            border-bottom:1px solid var(--border);
            font-size:.86rem;
            vertical-align:top;
        }
        tr:hover td { background:#fafafa; }
        .name { font-weight:700; color:var(--text); }
        .muted { color:var(--text-muted); font-size:.78rem; }
        .pill {
            display:inline-flex;
            align-items:center;
            border-radius:999px;
            padding:.18rem .55rem;
            font-size:.74rem;
            font-weight:700;
            white-space:nowrap;
        }
        .pill-si { background:#eafaf1; color:#1e8449; }
        .pill-no { background:#fdecea; color:#c0392b; }
        .pill-info { background:#ebf5fb; color:#1a5276; }
        .detail-row { display:none; }
        .detail-row.open { display:table-row; }
        .detail-box {
            display:grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap:.7rem;
            padding:.35rem 0;
        }
        .detail-item { background:var(--bg); border-radius:8px; padding:.65rem .75rem; }
        .detail-label { color:var(--text-muted); font-size:.72rem; font-weight:700; text-transform:uppercase; margin-bottom:.2rem; }
        .detail-value { color:var(--text); font-size:.86rem; word-break:break-word; }
        .empty { text-align:center; color:var(--text-muted); padding:2rem; }
        .counter { color:var(--text-muted); font-size:.88rem; }
        @media (max-width: 900px) {
            .filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filter-actions { justify-content:flex-start; }
            .detail-box { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 580px) {
            .filters { grid-template-columns: 1fr; }
            .detail-box { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav class="admin-nav">
    <div class="brand">&#x1F6E1; TDV Seguridad</div>
    <div class="nav-links">
        <?php if ($esAdminReal): ?>
        <a href="dashboard.php">En vivo</a>
        <a href="usuarios.php">Usuarios</a>
        <?php endif; ?>
        <a href="postulantes.php" class="active">Postulantes</a>
        <a href="vigiladores.php">Empleados</a>
        <a href="supervisores.php">Supervisores</a>
        <?php if ($esAdminReal): ?>
        <a href="objetivos.php">Objetivos</a>
        <a href="reportes.php">Reportes</a>
        <?php endif; ?>
        <a href="liquidacion.php">Horas</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<main class="page-shell">
    <div class="page-head">
        <h1 class="page-title">Postulantes</h1>
        <span class="counter" id="counter">Cargando...</span>
    </div>

    <section class="panel">
        <form class="filters" id="formFiltros">
            <div class="form-group">
                <label for="q">Busqueda</label>
                <input type="text" id="q" placeholder="Nombre, DNI, email, telefono, localidad o puesto">
            </div>
            <div class="form-group">
                <label for="experiencia_seguridad">Experiencia</label>
                <select id="experiencia_seguridad">
                    <option value="">Todas</option>
                    <option value="si">Si</option>
                    <option value="no">No</option>
                </select>
            </div>
            <div class="form-group">
                <label for="curso_habilitante">Curso</label>
                <select id="curso_habilitante">
                    <option value="">Todos</option>
                    <option value="si">Si</option>
                    <option value="no">No</option>
                </select>
            </div>
            <div class="form-group">
                <label for="credencial_vigente">Credencial</label>
                <select id="credencial_vigente">
                    <option value="">Todas</option>
                    <option value="si">Si</option>
                    <option value="no">No</option>
                </select>
            </div>
            <div class="form-group">
                <label for="disponibilidad_horaria">Disponibilidad</label>
                <select id="disponibilidad_horaria">
                    <option value="">Todas</option>
                    <option>Full Time</option>
                    <option>Turno Diurno</option>
                    <option>Turno Nocturno</option>
                    <option>Rotativos</option>
                </select>
            </div>
            <div class="form-group">
                <label for="genero">Género</label>
                <select id="genero">
                    <option value="">Todos</option>
                    <option value="1">Masculino</option>
                    <option value="2">Femenino</option>
                    <option value="vacio">No especificado</option>
                </select>
            </div>
            <div class="form-group">
                <label for="parte_track_seguridad">Fue parte</label>
                <select id="parte_track_seguridad">
                    <option value="">Todos</option>
                    <option value="si">Si</option>
                    <option value="no">No</option>
                </select>
            </div>
            <div class="form-group">
                <label for="puesto_postula">Puesto</label>
                <input type="text" id="puesto_postula" placeholder="Ej: Vigilador">
            </div>
            <div class="form-group">
                <label for="edad_desde">Edad desde</label>
                <input type="number" id="edad_desde" min="0" placeholder="Min">
            </div>
            <div class="form-group">
                <label for="edad_hasta">Edad hasta</label>
                <input type="number" id="edad_hasta" min="0" placeholder="Max">
            </div>
            <div class="form-group">
                <label for="desde">Desde</label>
                <input type="date" id="desde">
            </div>
            <div class="form-group">
                <label for="hasta">Hasta</label>
                <input type="date" id="hasta">
            </div>
            <div class="filter-actions">
                <button type="button" class="btn btn-outline btn-sm" id="btnLimpiar">Limpiar</button>
                <button type="button" class="btn btn-outline btn-sm" id="btnExcel">Excel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="btnBuscar">Filtrar</button>
            </div>
        </form>
    </section>

    <div class="panel" id="bulkActionsBar" style="display:none; justify-content:space-between; align-items:center; background:#ebf5fb; border:1px solid #aed6f1; padding:.8rem 1.2rem; margin-bottom:1rem; border-radius:10px;">
        <span style="font-weight:600; color:#1a5276;" id="bulkCountText">0 seleccionados</span>
        <div style="display:flex; gap:.5rem;">
            <button type="button" class="btn btn-primary btn-sm" id="btnBulkExcel" style="background:#27ae60; border-color:#27ae60;">🟢 Exportar Excel</button>
            <button type="button" class="btn btn-danger btn-sm" id="btnBulkDelete">🔴 Eliminar seleccionados</button>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll"></th>
                    <th>Postulante</th>
                    <th>Contacto</th>
                    <th>Localidad</th>
                    <th>Puesto</th>
                    <th>Disponibilidad</th>
                    <th>Experiencia</th>
                    <th>Curso</th>
                    <th>Credencial</th>
                    <th>Registro</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody">
                <tr><td colspan="11" class="empty">Cargando postulantes...</td></tr>
            </tbody>
        </table>
    </div>
</main>

<script>
const form = document.getElementById('formFiltros');
const tbody = document.getElementById('tbody');
const counter = document.getElementById('counter');
const POSTULANTES_UPLOAD_URL = 'https://postulaciones.tdvsrl.com/uploads/';

document.addEventListener('DOMContentLoaded', () => cargar());
form.addEventListener('submit', (e) => {
    e.preventDefault();
    cargar();
});
document.getElementById('btnLimpiar').addEventListener('click', () => {
    form.reset();
    cargar();
});
document.getElementById('btnExcel').addEventListener('click', () => {
    const query = paramsFiltros().toString();
    window.location.href = 'api/export_postulantes_excel.php' + (query ? '?' + query : '');
});

function valor(id) {
    return document.getElementById(id).value.trim();
}

async function cargar() {
    tbody.innerHTML = '<tr><td colspan="11" class="empty">Cargando postulantes...</td></tr>';
    counter.textContent = 'Cargando...';
    document.getElementById('selectAll').checked = false;
    actualizarSeleccion();

    const params = paramsFiltros();

    try {
        const res = await fetch('api/get_postulantes.php?' + params.toString());
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Error al cargar postulantes');
        render(data);
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="11" class="empty" style="color:var(--danger);">${esc(e.message)}</td></tr>`;
        counter.textContent = 'Error';
    }
}

function paramsFiltros() {
    const params = new URLSearchParams();
    ['q', 'experiencia_seguridad', 'curso_habilitante', 'credencial_vigente', 'disponibilidad_horaria', 'parte_track_seguridad', 'puesto_postula', 'edad_desde', 'edad_hasta', 'genero', 'desde', 'hasta'].forEach(id => {
        const v = valor(id);
        if (v) params.set(id, v);
    });
    return params;
}

function render(items) {
    counter.textContent = `${items.length} postulante${items.length === 1 ? '' : 's'}`;
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="11" class="empty">No hay postulantes para esos filtros.</td></tr>';
        return;
    }

    tbody.innerHTML = items.map((p) => {
        const adjunto = renderAdjunto(p.archivo_adjunto);
        const genLabel = p.genero && p.genero !== 'No especificado' ? ` - ${esc(p.genero)}` : '';
        return `
            <tr>
                <td style="text-align: center; vertical-align: middle;">
                    <input type="checkbox" class="postulante-select" value="${Number(p.id)}" onclick="actualizarSeleccion()">
                </td>
                <td>
                    <div class="name">${esc(p.nombre_completo)}</div>
                    <div class="muted">DNI ${esc(p.dni)} - Nac. ${fmtFecha(p.fecha_nacimiento)}${p.edad ? ` (${esc(p.edad)} años)` : ''}${genLabel}</div>
                </td>
                <td>
                    <div>${esc(p.telefono)}</div>
                    <div class="muted">${esc(p.email)}</div>
                </td>
                <td>${esc(p.localidad_residencia)}</td>
                <td>${esc(p.puesto_postula)}</td>
                <td><span class="pill pill-info">${esc(p.disponibilidad_horaria)}</span></td>
                <td>${pillSiNo(p.experiencia_seguridad)}</td>
                <td>${pillSiNo(p.curso_habilitante)}</td>
                <td>${pillSiNo(p.credencial_vigente)}</td>
                <td>${esc(p.fecha_registro_fmt || '')}</td>
                <td>
                    <div class="row-actions">
                        <button class="btn btn-outline btn-sm" onclick="toggleDetalle(${Number(p.id)})">Ver</button>
                        <button class="btn btn-danger btn-sm" onclick="eliminarPostulante(${Number(p.id)})">Eliminar</button>
                    </div>
                </td>
            </tr>
            <tr class="detail-row" id="detalle-${Number(p.id)}">
                <td colspan="11">
                    <div class="detail-box">
                        <div class="detail-item">
                            <div class="detail-label">Fue parte de Track</div>
                            <div class="detail-value">${pillSiNo(p.parte_track_seguridad)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Archivo adjunto</div>
                            <div class="detail-value">${adjunto}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Email</div>
                            <div class="detail-value">${esc(p.email)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Telefono</div>
                            <div class="detail-value">${esc(p.telefono)}</div>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function toggleDetalle(id) {
    const row = document.getElementById('detalle-' + id);
    if (row) row.classList.toggle('open');
}

async function eliminarPostulante(id) {
    if (!confirm('Eliminar este postulante?')) return;

    try {
        const res = await fetch('api/eliminar_postulante.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.error || 'No se pudo eliminar el postulante.');
        }
        cargar();
    } catch (e) {
        alert(e.message);
    }
}

function pillSiNo(v) {
    const ok = v === 'si';
    return `<span class="pill ${ok ? 'pill-si' : 'pill-no'}">${ok ? 'Si' : 'No'}</span>`;
}

function renderAdjunto(path) {
    if (!path) return '<span class="muted">Sin archivo</span>';
    const url = archivoUrl(path);
    const texto = esc(nombreArchivo(path));
    return `<a href="${escAttr(url)}" target="_blank" rel="noopener">${texto}</a>`;
}

function archivoUrl(path) {
    if (/^https?:\/\//i.test(path)) return path;
    return POSTULANTES_UPLOAD_URL + encodeURIComponent(nombreArchivo(path));
}

function nombreArchivo(path) {
    return String(path).replace(/\\/g, '/').split('/').pop();
}

function fmtFecha(fecha) {
    if (!fecha) return '-';
    const partes = fecha.split('-');
    return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : esc(fecha);
}

function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escAttr(s) {
    return esc(s).replace(/'/g, '&#039;');
}

// Toggle select all
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.postulante-select');
    checkboxes.forEach(cb => cb.checked = this.checked);
    actualizarSeleccion();
});

function actualizarSeleccion() {
    const checkboxes = document.querySelectorAll('.postulante-select');
    const checked = Array.from(checkboxes).filter(cb => cb.checked);
    const selectAll = document.getElementById('selectAll');
    
    if (checkboxes.length === 0) {
        selectAll.checked = false;
    } else {
        selectAll.checked = checked.length === checkboxes.length;
    }

    const bar = document.getElementById('bulkActionsBar');
    const countText = document.getElementById('bulkCountText');
    
    if (checked.length > 0) {
        bar.style.display = 'flex';
        countText.textContent = `${checked.length} seleccionado${checked.length === 1 ? '' : 's'}`;
    } else {
        bar.style.display = 'none';
    }
}

// Bulk Excel Export
document.getElementById('btnBulkExcel').addEventListener('click', () => {
    const checkboxes = document.querySelectorAll('.postulante-select:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value).join(',');
    if (ids) {
        window.location.href = 'api/export_postulantes_excel.php?ids=' + ids;
    }
});

// Bulk Delete
document.getElementById('btnBulkDelete').addEventListener('click', async () => {
    const checkboxes = document.querySelectorAll('.postulante-select:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    if (ids.length === 0) return;
    
    if (!confirm(`¿Eliminar en lote los ${ids.length} postulantes seleccionados?`)) return;

    try {
        const res = await fetch('api/eliminar_postulantes_lote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids }),
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.error || 'No se pudieron eliminar los postulantes.');
        }
        document.getElementById('selectAll').checked = false;
        cargar();
    } catch (e) {
        alert(e.message);
    }
});
</script>
</body>
</html>
