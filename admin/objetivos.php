<?php
require_once __DIR__ . '/auth.php';
requireAdminRealPage();
$adminNombre = $_SESSION['nombre_completo'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Objetivos</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-nav {
            background: var(--primary-dk);
            display: flex; align-items: center; justify-content: space-between;
            padding: .7rem 1.5rem; flex-wrap: wrap; gap: .5rem;
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

        .data-table { width:100%;border-collapse:collapse;font-size:.88rem; }
        .data-table th {
            background:var(--primary);color:#fff;
            padding:.65rem .9rem;text-align:left;font-weight:600;
        }
        .data-table td { padding:.65rem .9rem;border-bottom:1px solid var(--bg);vertical-align:middle; }
        .data-table tr:hover td { background:#f7f9fc; }
        .data-table .actions { display:flex;gap:.4rem;flex-wrap:wrap; }

        /* Coord display */
        .coord-link {
            font-size:.78rem;color:var(--accent);text-decoration:none;
            display:inline-flex;align-items:center;gap:.2rem;
        }
        .coord-link:hover { text-decoration:underline; }

        .radio-badge {
            background:#ebf5fb;color:#1a5276;
            border-radius:20px;padding:.15rem .65rem;
            font-size:.78rem;font-weight:600;
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
            width:100%;max-width:580px;max-height:90vh;overflow-y:auto;
            padding:1.8rem;
        }
        .modal-header {
            display:flex;align-items:center;justify-content:space-between;
            margin-bottom:1.2rem;
        }
        .modal-title { font-size:1.1rem;font-weight:700;color:var(--primary); }
        .modal-close { background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted); }
        .modal-close:hover { color:var(--text); }

        .form-row { display:grid;grid-template-columns:1fr 1fr;gap:0 1rem; }
        .form-row-3 { display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 1rem; }

        .field-hint {
            font-size:.75rem;color:var(--text-muted);
            margin-top:.25rem;line-height:1.3;
        }
        .field-hint a { color:var(--accent); }

        /* Mapa hint box */
        .maps-hint {
            background:#f0f7ff;border:1px solid #bee3f8;
            border-radius:8px;padding:.7rem 1rem;
            font-size:.82rem;color:#1a5276;
            margin-bottom:1rem;
            display:flex;align-items:flex-start;gap:.6rem;
        }
        .section-header {
            display:flex;align-items:center;justify-content:space-between;
            margin-bottom:.8rem;
        }
        .section-title { font-size:1rem;font-weight:700;color:var(--primary); }

        @media (max-width:600px) {
            .form-row, .form-row-3 { grid-template-columns:1fr; }
            .data-table td:nth-child(3),
            .data-table th:nth-child(3) { display:none; }
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
        <a href="postulantes.php">Postulantes</a>
        <a href="vigiladores.php">&#x1F464; Empleados</a>
        <a href="supervisores.php">&#x1F4BC; Supervisores</a>
        <a href="objetivos.php" class="active">&#x1F3AF; Objetivos</a>
        <a href="reportes.php">&#x26A0; Reportes</a>
        <a href="liquidacion.php">Horas</a>
        <a href="enviar_mails.php">Mails</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<div style="max-width:1200px;margin:0 auto;padding:1.2rem 1rem 2rem;">

    <div class="alert alert-danger"  id="tableError"   role="alert"><span>&#9888;</span><span id="tableErrorMsg"></span></div>
    <div class="alert alert-success" id="tableSuccess" role="alert"><span>&#9989;</span><span id="tableSuccessMsg"></span></div>

    <div class="card" style="overflow-x:auto;">
        <div class="section-header">
            <span class="section-title">&#x1F4CD; Objetivos / Puestos de guardia</span>
            <button class="btn btn-primary btn-sm" onclick="abrirModal(0)">+ Nuevo objetivos</button>
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
            <span class="modal-title" id="modalTitle">Nuevo objetivos</span>
            <button class="modal-close" onclick="cerrarModal()">&#x2715;</button>
        </div>

        <div class="alert alert-danger" id="modalError" role="alert">
            <span>&#9888;</span><span id="modalErrorMsg"></span>
        </div>

        <!-- Ayuda para coordenadas -->
        <div class="maps-hint">
            <span>&#x1F5FA;</span>
            <span>
                Para obtener coordenadas: abrí
                <a href="https://maps.google.com" target="_blank" rel="noopener">Google Maps</a>,
                buscá la ubicación, hacé <strong>click derecho</strong> sobre el punto exacto
                y copiá las coordenadas que aparecen (ej: <code>-34.6037, -58.3816</code>).
            </span>
        </div>

        <form id="formObjetivo" novalidate>
            <input type="hidden" id="fId" value="0">

            <div class="form-group">
                <label for="fNombre">Nombre del objetivos <span style="color:var(--danger)">*</span></label>
                <input type="text" id="fNombre" required placeholder="Ej: Sede Central">
            </div>

            <div class="form-group">
                <label for="fDescripcion">Descripción</label>
                <textarea id="fDescripcion" rows="2" placeholder="Descripción del puesto..."></textarea>
            </div>

            <!-- Botón GPS -->
            <div style="margin-bottom:.8rem;">
                <button type="button" id="btnGps"
                    style="display:inline-flex;align-items:center;gap:.5rem;
                           background:#ebf5fb;color:#1a5276;border:1.5px solid #bee3f8;
                           border-radius:8px;padding:.55rem 1.1rem;font-size:.88rem;
                           font-weight:600;cursor:pointer;transition:background .2s;"
                    onmouseover="this.style.background='#d6eaf8'"
                    onmouseout="this.style.background='#ebf5fb'"
                    onclick="obtenerGPS()">
                    <span id="gpsIcon">&#x1F4CD;</span>
                    <span id="gpsTxt">Usar mi ubicación actual</span>
                </button>
                <span id="gpsStatus" style="font-size:.78rem;color:var(--text-muted);margin-left:.6rem;"></span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fLat">Latitud <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="fLat" required step="any"
                           min="-90" max="90" placeholder="-34.603760">
                    <p class="field-hint">Número negativo para Sur (Argentina)</p>
                </div>
                <div class="form-group">
                    <label for="fLng">Longitud <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="fLng" required step="any"
                           min="-180" max="180" placeholder="-58.381620">
                    <p class="field-hint">Número negativo para Oeste (Argentina)</p>
                </div>
            </div>

            <div class="form-group">
                <label for="fRadio">Radio de verificación (metros) <span style="color:var(--danger)">*</span></label>
                <input type="number" id="fRadio" required min="1" max="5000" value="200">
                <p class="field-hint">
                    Distancia máxima permitida desde el punto central para marcar asistencia.
                    200 m es un valor típico para un edificio.
                </p>
            </div>

            <div class="form-group">
                <label for="fSupervisor">Supervisor asignado</label>
                <select id="fSupervisor">
                    <option value="">- Sin supervisor -</option>
                </select>
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
document.addEventListener('DOMContentLoaded', () => {
    cargarObjetivos();
    document.getElementById('formObjetivo').addEventListener('submit', onGuardar);
    cargarSupervisoresSelect();
});

async function cargarSupervisoresSelect() {
    try {
        const list = await apiFetch('api/get_supervisores.php');
        const sel  = document.getElementById('fSupervisor');
        list.filter(s => s.estado == 1).forEach(s => {
            const opt = document.createElement('option');
            opt.value       = s.id_supervisor;
            opt.textContent = `${s.apellido}, ${s.nombre}`;
            sel.appendChild(opt);
        });
    } catch(e) {}
}

// ---- Tabla ------------------------------------------------
async function cargarObjetivos() {
    const wrap = document.getElementById('tablaWrap');
    try {
        const list = await apiFetch('api/get_objetivos.php?full=1');

        if (!list.length) {
            wrap.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:1.5rem;">Sin objetivos registrados.</p>';
            return;
        }

        const tbody = list.map(o => {
            const mapsUrl = `https://www.google.com/maps?q=${o.coord_lat},${o.coord_long}`;
            const asig = parseInt(o.vigiladores_asignados);
            const supHtml = o.supervisor_nombre
                ? `<span style="font-weight:600">${esc(o.supervisor_nombre)}</span>
                   ${o.supervisor_telefono ? `<br><small style="color:var(--text-muted)">${esc(o.supervisor_telefono)}</small>` : ''}`
                : `<span style="color:var(--text-muted)">-</span>`;
            return `<tr>
                <td>
                    <strong>${esc(o.nombre)}</strong>
                    ${o.descripcion ? `<br><small style="color:var(--text-muted)">${esc(o.descripcion)}</small>` : ''}
                </td>
                <td>
                    <a class="coord-link" href="${mapsUrl}" target="_blank" rel="noopener">
                        &#x1F4CD; ${parseFloat(o.coord_lat).toFixed(6)}, ${parseFloat(o.coord_long).toFixed(6)}
                    </a>
                </td>
                <td><span class="radio-badge">${esc(o.rad_metros)} m</span></td>
                <td>${supHtml}</td>
                <td style="text-align:center;">
                    <span style="font-weight:${asig>0?'700':'400'};color:${asig>0?'var(--primary)':'var(--text-muted)'}">
                        ${asig}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <button class="btn btn-outline btn-sm" onclick="abrirModal(${o.id_objetivo})">&#9998; Editar</button>
                        <button class="btn btn-danger btn-sm" onclick="eliminar(${o.id_objetivo},'${esc(o.nombre)}')"
                            ${asig > 0 ? 'title="Tiene empleados asignados"' : ''}>
                            &#x1F5D1;
                        </button>
                    </div>
                </td>
            </tr>`;
        }).join('');

        wrap.innerHTML = `
            <table class="data-table">
                <thead><tr>
                    <th>Nombre</th>
                    <th>Coordenadas</th>
                    <th>Radio</th>
                    <th>Supervisor</th>
                    <th style="text-align:center;">empleados</th>
                    <th>Acciones</th>
                </tr></thead>
                <tbody>${tbody}</tbody>
            </table>`;
    } catch (e) {
        wrap.innerHTML = '<p style="color:var(--danger);padding:1rem;">Error al cargar objetivos.</p>';
    }
}

// ---- Modal ------------------------------------------------
let listaCache = [];

async function abrirModal(id) {
    document.getElementById('modalError').classList.remove('show');
    document.getElementById('formObjetivo').reset();
    document.getElementById('fId').value   = id;
    document.getElementById('fRadio').value = 200;
    document.getElementById('modalTitle').textContent = id ? 'Editar objetivos' : 'Nuevo objetivos';

    if (id) {
        try {
            const list = await apiFetch('api/get_objetivos.php?full=1');
            const o = list.find(x => x.id_objetivo == id);
            if (o) {
                document.getElementById('fNombre').value      = o.nombre;
                document.getElementById('fDescripcion').value = o.descripcion || '';
                document.getElementById('fLat').value         = o.coord_lat;
                document.getElementById('fLng').value         = o.coord_long;
                document.getElementById('fRadio').value       = o.rad_metros;
                document.getElementById('fSupervisor').value  = o.supervisor_id || '';
                document.getElementById('fEntrada').value     = o.hora_entrada.substr(0,5);
                document.getElementById('fSalida').value      = o.hora_salida.substr(0,5);
            }
        } catch(e) {}
    }

    document.getElementById('modalOverlay').classList.add('open');
    document.getElementById('fNombre').focus();
}

function cerrarModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

// ---- Guardar ----------------------------------------------
async function onGuardar(e) {
    e.preventDefault();
    const errDiv = document.getElementById('modalError');
    errDiv.classList.remove('show');

    const id          = parseInt(document.getElementById('fId').value);
    const nombre      = document.getElementById('fNombre').value.trim();
    const descripcion = document.getElementById('fDescripcion').value.trim();
    const lat         = document.getElementById('fLat').value.trim();
    const lng         = document.getElementById('fLng').value.trim();
    const radio       = document.getElementById('fRadio').value.trim();
    if (!nombre || !lat || !lng || !radio) {
        document.getElementById('modalErrorMsg').textContent = 'Complete todos los campos obligatorios.';
        errDiv.classList.add('show'); return;
    }

    const btn = document.getElementById('btnGuardar');
    btn.disabled    = true;
    btn.textContent = 'Guardando...';

    try {
        await apiFetch('api/guardar_objetivo.php', 'POST', {
            id, nombre, descripcion,
            coord_lat:    parseFloat(lat),
            coord_long:   parseFloat(lng),
            rad_metros:  parseInt(radio),
            supervisor_id: document.getElementById('fSupervisor').value || null,
        });
        cerrarModal();
        mostrarExito(id ? 'objetivos actualizado.' : 'objetivos creado.');
        cargarObjetivos();
    } catch (err) {
        document.getElementById('modalErrorMsg').textContent = err.message;
        errDiv.classList.add('show');
    }

    btn.disabled    = false;
    btn.textContent = 'Guardar';
}

// ---- Eliminar ---------------------------------------------
async function eliminar(id, nombre) {
    if (!confirm(`¿Eliminar el objetivo "${nombre}"?\nEsta acción no se puede deshacer.`)) return;

    try {
        const resp = await apiFetch('api/eliminar_objetivo.php', 'POST', { id });
        mostrarExito(resp.mensaje);
        cargarObjetivos();
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
    const res  = await fetch(url, opts);
    const raw = await res.text();
    let json = {};
    try {
        json = raw ? JSON.parse(raw) : {};
    } catch (e) {
        if (res.ok) {
            return { success: true, warning: 'Respuesta no JSON, pero la operacion fue aceptada.' };
        }
        throw new Error('La API devolvio una respuesta invalida.');
    }
    if (!res.ok) throw new Error(json.error || 'Error del servidor');
    return json;
}

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ---- GPS para coordenadas del objetivos -------------------
function obtenerGPS() {
    if (!navigator.geolocation) {
        document.getElementById('gpsStatus').textContent = 'Geolocalización no disponible en este navegador.';
        return;
    }

    const btn    = document.getElementById('btnGps');
    const icon   = document.getElementById('gpsIcon');
    const txt    = document.getElementById('gpsTxt');
    const status = document.getElementById('gpsStatus');

    btn.disabled      = true;
    icon.innerHTML    = '<span class="spinner spinner-dark" style="width:14px;height:14px;border-width:2px;"></span>';
    txt.textContent   = 'Obteniendo ubicación...';
    status.textContent = '';

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = Math.round(pos.coords.accuracy);

            document.getElementById('fLat').value = lat.toFixed(8);
            document.getElementById('fLng').value = lng.toFixed(8);

            icon.textContent   = 'OK';
            txt.textContent    = 'Ubicación obtenida';
            status.innerHTML   = `Precisión: <strong>${acc} m</strong> &nbsp;·&nbsp;
                <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank"
                   style="color:var(--accent);font-size:.78rem;">Ver en mapa</a>`;
            btn.disabled = false;
        },
        (err) => {
            const msgs = {
                1: 'Permiso denegado. Habilite la ubicación en el navegador.',
                2: 'No se pudo obtener la ubicación. Verifique que el GPS esté activo.',
                3: 'Tiempo de espera agotado.'
            };
            icon.textContent  = 'ðŸ“';
            txt.textContent   = 'Usar mi ubicación actual';
            status.textContent = msgs[err.code] || 'Error al obtener ubicación.';
            status.style.color = 'var(--danger)';
            btn.disabled = false;
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}
</script>
</body>
</html>



