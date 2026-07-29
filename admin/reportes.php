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
    <title>TDV - Reportes de error</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <style>
        .admin-nav {
            background: var(--primary-dk);
            display: flex; align-items: center; justify-content: space-between;
            padding: .7rem 1.5rem; flex-wrap: wrap; gap: .5rem;
        }
        .admin-nav .brand { color:#fff;font-weight:700;font-size:1.1rem;display:flex;align-items:center;gap:.5rem; }
        .admin-nav .nav-links { display:flex;gap:.3rem;flex-wrap:wrap; }
        .admin-nav .nav-links a {
            color:rgba(255,255,255,.75);text-decoration:none;
            padding:.4rem .9rem;border-radius:6px;font-size:.88rem;transition:background .2s;
            position:relative;
        }
        .admin-nav .nav-links a.active,
        .admin-nav .nav-links a:hover { background:rgba(255,255,255,.15);color:#fff; }
        .admin-nav .nav-user { color:rgba(255,255,255,.7);font-size:.82rem;text-align:right; }
        .admin-nav .nav-user strong { display:block;color:#fff; }

        /* Badge pendientes en nav */
        .nav-badge {
            position:absolute;top:-5px;right:-4px;
            background:var(--danger);color:#fff;
            font-size:.65rem;font-weight:700;
            border-radius:10px;padding:.1rem .35rem;
            line-height:1.3;
        }

        /* Filtros */
        .filtros {
            display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;
        }
        .filtro-btn {
            padding:.35rem .9rem;border-radius:20px;font-size:.82rem;font-weight:600;
            border:2px solid var(--border);background:var(--card);color:var(--text-muted);
            cursor:pointer;transition:all .2s;
        }
        .filtro-btn.active { border-color:var(--primary);color:var(--primary);background:var(--bg); }
        .filtro-btn:hover  { border-color:var(--primary);color:var(--primary); }

        /* Tarjetas de reporte */
        .reporte-card {
            background:var(--card);border-radius:10px;box-shadow:var(--shadow);
            padding:1.2rem 1.4rem;margin-bottom:.9rem;
            border-left:5px solid var(--border);
        }
        .reporte-card.pendiente { border-left-color:var(--danger); }
        .reporte-card.revisado  { border-left-color:var(--warning); }
        .reporte-card.resuelto  { border-left-color:var(--success); opacity:.75; }

        .rep-header {
            display:flex;align-items:flex-start;justify-content:space-between;
            gap:.8rem;flex-wrap:wrap;margin-bottom:.7rem;
        }
        .rep-quien { font-weight:700;font-size:.95rem; }
        .rep-meta  { font-size:.78rem;color:var(--text-muted);margin-top:.1rem; }

        .estado-pill {
            display:inline-block;padding:.2rem .7rem;border-radius:20px;
            font-size:.75rem;font-weight:700;white-space:nowrap;
        }
        .estado-pendiente { background:#fdecea;color:#c0392b; }
        .estado-revisado  { background:#fef9e7;color:#9a7d0a; }
        .estado-resuelto  { background:#eafaf1;color:#1e8449; }

        .rep-campo { margin-bottom:.5rem;font-size:.88rem; }
        .rep-label { font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.15rem; }
        .rep-valor { background:var(--bg);border-radius:6px;padding:.4rem .7rem;word-break:break-word; }
        .rep-valor.error-txt { font-family:monospace;font-size:.82rem;color:var(--danger); }

        .rep-footer {
            display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;
            margin-top:.9rem;padding-top:.7rem;border-top:1px solid var(--bg);
        }

        /* Modal notas */
        .modal-overlay {
            display:none;position:fixed;inset:0;
            background:rgba(0,0,0,.55);z-index:1000;
            align-items:center;justify-content:center;padding:1rem;
        }
        .modal-overlay.open { display:flex; }
        .modal {
            background:#fff;border-radius:var(--radius);
            box-shadow:0 8px 40px rgba(0,0,0,.25);
            width:100%;max-width:480px;padding:1.8rem;
        }
        .modal-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:1.1rem; }
        .modal-title  { font-size:1.05rem;font-weight:700;color:var(--primary); }
        .modal-close  { background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted); }

        .section-title { font-size:1rem;font-weight:700;color:var(--primary);margin-bottom:1rem; }

        @media(max-width:600px){
            .admin-nav { padding:.6rem 1rem; }
            .rep-header { flex-direction:column; }
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
        <a href="legajos.php">Legajos</a>
        <a href="supervisores.php">&#x1F4BC; Supervisores</a>
        <a href="objetivos.php">&#x1F3AF; Objetivos</a>
        <a href="reportes.php" class="active">
            &#x26A0; Reportes
            <span class="nav-badge" id="navBadge" style="display:none;">0</span>
        </a>
        <a href="liquidacion.php">Horas</a>
        <a href="enviar_mails.php">Mails</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<div style="max-width:900px;margin:0 auto;padding:1.2rem 1rem 2rem;">

    <div class="alert alert-success" id="toastOk" role="alert"><span>&#9989;</span><span id="toastMsg"></span></div>

    <p class="section-title">&#x26A0; Reportes de error</p>

    <!-- Filtros -->
    <div class="filtros">
        <button class="filtro-btn active" onclick="filtrar('')"      id="fTodos">Todos</button>
        <button class="filtro-btn"        onclick="filtrar('pendiente')" id="fPendiente">Pendientes</button>
        <button class="filtro-btn"        onclick="filtrar('revisado')"  id="fRevisado">Revisados</button>
        <button class="filtro-btn"        onclick="filtrar('resuelto')"  id="fResuelto">Resueltos</button>
    </div>

    <div id="listaReportes">
        <div style="padding:2rem;text-align:center;color:var(--text-muted);">
            <div class="spinner spinner-dark" style="margin:0 auto .8rem;"></div>
            Cargando reportes...
        </div>
    </div>

</div>

<!-- Modal actualizar estado -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Actualizar reporte</span>
            <button class="modal-close" onclick="cerrarModal()">&#x2715;</button>
        </div>
        <input type="hidden" id="mId">
        <div class="form-group">
            <label for="mEstado">Estado</label>
            <select id="mEstado">
                <option value="pendiente">Pendiente</option>
                <option value="revisado">Revisado</option>
                <option value="resuelto">Resuelto</option>
            </select>
        </div>
        <div class="form-group">
            <label for="mNotas">Notas internas <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
            <textarea id="mNotas" rows="3" placeholder="Diagnóstico, solución aplicada..."></textarea>
        </div>
        <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:1rem;">
            <button class="btn btn-outline" style="width:auto" onclick="cerrarModal()">Cancelar</button>
            <button class="btn btn-primary" style="width:auto;min-width:110px;" id="btnGuardar" onclick="guardarEstado()">Guardar</button>
        </div>
    </div>
</div>

<script>
let filtroActivo = '';

document.addEventListener('DOMContentLoaded', () => cargar(''));

function filtrar(estado) {
    filtroActivo = estado;
    document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('active'));
    const ids = { '':'fTodos', 'pendiente':'fPendiente', 'revisado':'fRevisado', 'resuelto':'fResuelto' };
    document.getElementById(ids[estado]).classList.add('active');
    cargar(estado);
}

async function cargar(estado) {
    const lista = document.getElementById('listaReportes');
    lista.innerHTML = `<div style="padding:2rem;text-align:center;color:var(--text-muted);">
        <div class="spinner spinner-dark" style="margin:0 auto .8rem;"></div>Cargando...</div>`;
    try {
        const url  = 'api/get_reportes.php' + (estado ? `?estado=${estado}` : '');
        const data = await apiFetch(url);

        // Badge de pendientes en nav
        const pendientes = data.filter(r => r.estado === 'pendiente').length;
        const badge = document.getElementById('navBadge');
        badge.textContent    = pendientes;
        badge.style.display  = pendientes > 0 ? 'inline' : 'none';

        if (!data.length) {
            lista.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:2rem;">Sin reportes' + (estado ? ' en este estado' : '') + '.</p>';
            return;
        }

        lista.innerHTML = data.map(r => {
            const estadoPill = `<span class="estado-pill estado-${r.estado}">${estadoLabel(r.estado)}</span>`;
            const agente = r.user_agent
                ? `<div style="font-size:.72rem;color:var(--text-muted);margin-top:.3rem;word-break:break-all;">${esc(r.user_agent)}</div>`
                : '';
            const notasAdmin = r.notas_admin
                ? `<div class="rep-campo">
                       <div class="rep-label">Notas del administrador</div>
                       <div class="rep-valor">${esc(r.notas_admin)}</div>
                   </div>`
                : '';
            const revision = r.fecha_revision
                ? `<span style="font-size:.75rem;color:var(--text-muted);">Revisado: ${esc(r.fecha_revision)}</span>`
                : '';

            return `
            <div class="reporte-card ${esc(r.estado)}" id="rep-${r.id_reporte}">
                <div class="rep-header">
                    <div>
                        <div class="rep-quien">&#x1F464; ${esc(r.vigilador_nombre)}</div>
                        <div class="rep-meta">DNI ${esc(r.dni)} &nbsp;·&nbsp; ${esc(r.fecha)} ${esc(r.hora.substr(0,5))} hs &nbsp;·&nbsp; IP: ${esc(r.ip_dispositivo || '-')}</div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.3rem;">
                        ${estadoPill}
                        ${revision}
                    </div>
                </div>

                ${r.accion ? `
                <div class="rep-campo">
                    <div class="rep-label">¿Qué estaba haciendo?</div>
                    <div class="rep-valor">${esc(r.accion)}</div>
                </div>` : ''}

                ${r.mensaje_error ? `
                <div class="rep-campo">
                    <div class="rep-label">Mensaje de error</div>
                    <div class="rep-valor error-txt">${esc(r.mensaje_error)}</div>
                </div>` : ''}

                <div class="rep-campo">
                    <div class="rep-label">Descripción del problema</div>
                    <div class="rep-valor">${esc(r.descripcion)}</div>
                </div>

                ${notasAdmin}
                ${agente}

                <div class="rep-footer">
                    <button class="btn btn-outline btn-sm" onclick="abrirModal(${r.id_reporte},'${esc(r.estado)}','${esc(r.notas_admin||'')}')">
                        &#9998; Actualizar estado
                    </button>
                    ${r.estado !== 'resuelto'
                        ? `<button class="btn btn-success btn-sm" onclick="resolverRapido(${r.id_reporte})">&#9989; Marcar resuelto</button>`
                        : ''}
                </div>
            </div>`;
        }).join('');
    } catch(e) {
        lista.innerHTML = '<p style="color:var(--danger);padding:1rem;">Error al cargar reportes.</p>';
    }
}

function estadoLabel(s) {
    return { pendiente:'Pendiente', revisado:'Revisado', resuelto:'Resuelto' }[s] || s;
}

// ---- Modal ------------------------------------------------
function abrirModal(id, estado, notas) {
    document.getElementById('mId').value      = id;
    document.getElementById('mEstado').value  = estado;
    document.getElementById('mNotas').value   = notas;
    document.getElementById('modalOverlay').classList.add('open');
}

function cerrarModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

async function guardarEstado() {
    const id     = parseInt(document.getElementById('mId').value);
    const estado = document.getElementById('mEstado').value;
    const notas  = document.getElementById('mNotas').value.trim();
    const btn    = document.getElementById('btnGuardar');
    btn.disabled = true; btn.textContent = 'Guardando...';
    try {
        const resp = await apiFetch('api/actualizar_reporte.php', 'POST', { id, estado, notas_admin: notas });
        cerrarModal();
        mostrarToast(resp.mensaje);
        cargar(filtroActivo);
    } catch(e) {
        alert(e.message);
    }
    btn.disabled = false; btn.textContent = 'Guardar';
}

async function resolverRapido(id) {
    try {
        const resp = await apiFetch('api/actualizar_reporte.php', 'POST', { id, estado: 'resuelto', notas_admin: '' });
        mostrarToast(resp.mensaje);
        cargar(filtroActivo);
    } catch(e) { alert(e.message); }
}

function mostrarToast(msg) {
    const d = document.getElementById('toastOk');
    document.getElementById('toastMsg').textContent = msg;
    d.classList.add('show');
    setTimeout(() => d.classList.remove('show'), 3500);
}

async function apiFetch(url, method = 'GET', data = null) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (data) opts.body = JSON.stringify(data);
    const res  = await fetch(url, opts);
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Error del servidor');
    return json;
}

function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>



