<?php
session_start();
if (empty($_SESSION['supervisor_id'])) {
    header('Location: ../index.php');
    exit;
}
$supNombre = $_SESSION['nombre_completo'] ?? 'Supervisor';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Panel Supervisor</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .sup-nav {
            background: var(--primary-dk);
            display: flex; align-items: center; justify-content: space-between;
            padding: .7rem 1.5rem; flex-wrap: wrap; gap: .5rem;
        }
        .sup-nav .brand { color:#fff; font-weight:700; font-size:1.1rem; display:flex; align-items:center; gap:.5rem; }
        .sup-nav .nav-user { color:rgba(255,255,255,.7); font-size:.82rem; text-align:right; }
        .sup-nav .nav-user strong { display:block; color:#fff; }

        .summary-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .8rem; margin-bottom: 1.2rem;
        }
        .summary-card {
            background: var(--card); border-radius: 10px;
            padding: 1rem; text-align: center; box-shadow: var(--shadow);
        }
        .summary-card .num { font-size: 2rem; font-weight: 700; line-height: 1.1; }
        .summary-card .lbl { font-size: .78rem; color: var(--text-muted); margin-top: .2rem; }
        .num-presente   { color: var(--success); }
        .num-ausente    { color: var(--danger);  }
        .num-completado { color: var(--text);  }
        .num-total      { color: var(--primary); }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1rem;
        }
        .guard-card {
            background: var(--card); border-radius: 10px;
            box-shadow: var(--shadow); padding: 1.1rem 1.2rem;
            border-left: 5px solid var(--border); transition: transform .15s;
            position: relative;
        }
        .guard-card:hover { transform: translateY(-2px); }
        .guard-card.presente    { border-left-color: var(--success); }
        .guard-card.ausente     { border-left-color: var(--danger);  }
        .guard-card.completado  { border-left-color: var(--text);  }
        .guard-card.incompleto  { border-left-color: #f1c40f; background: #fffdf2; }
        .guard-card.sin-registro { border-left-color: #3498db; background: #f4f9ff; }
        .guard-card.sin-salida  { border-left-color: #f1c40f; background: #fffdf2; }
        .guard-card.por-iniciar { border-left-color: #3498db; background: #f4f9ff; }

        .gc-name  { font-size: 1rem; font-weight: 700; color: var(--text); }
        .gc-obj   { font-size: .78rem; color: var(--text-muted); margin: .15rem 0 .5rem; }
        .gc-badge {
            display: inline-block; padding: .2rem .7rem;
            border-radius: 20px; font-size: .75rem; font-weight: 700; margin-bottom: .5rem;
        }
        .badge-presente    { background: #eafaf1; color: #1e8449; }
        .badge-ausente     { background: #fdecea; color: #c0392b; }
        .badge-completado  { background: #f2f3f4; color: #111827; }
        .badge-incompleto  { background: #fff8d7; color: #9a7d0a; }
        .badge-sin-registro { background: #eaf4ff; color: #1f618d; }
        .badge-sin-salida  { background: #fff8d7; color: #9a7d0a; }
        .badge-por-iniciar { background: #eaf4ff; color: #1f618d; }

        .gc-times {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: .3rem; font-size: .8rem;
        }
        .gc-time-item { display: flex; flex-direction: column; }
        .gc-time-item .tl { color: var(--text-muted); font-size: .72rem; }
        .gc-time-item .tv { font-weight: 600; }

        .btn-edit-turno {
            position: absolute; top: .8rem; right: .8rem;
            background: none; border: 1px solid var(--border);
            border-radius: 6px; padding: .2rem .5rem;
            font-size: .75rem; cursor: pointer; color: var(--text-muted);
            transition: background .2s, color .2s;
        }
        .btn-edit-turno:hover { background: var(--bg); color: var(--text); }

        .refresh-bar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1rem; font-size: .85rem; color: var(--text-muted);
        }
        .refresh-countdown { font-weight: 600; color: var(--accent); }
        .refresh-btn {
            background: none; border: 1px solid var(--border);
            border-radius: 6px; padding: .3rem .8rem;
            cursor: pointer; font-size: .82rem; color: var(--text-muted);
            transition: background .2s;
        }
        .refresh-btn:hover { background: var(--bg); }

        .obj-selector-card {
            background: var(--card); border-radius: 10px;
            box-shadow: var(--shadow); padding: 1rem 1.2rem;
            margin-bottom: 1.2rem;
            display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
        }
        .obj-selector-card label { font-size: .88rem; font-weight: 600; white-space: nowrap; }
        .obj-selector-card select { flex: 1; min-width: 200px; }

        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.55); z-index: 1000;
            align-items: center; justify-content: center; padding: 1rem;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: #fff; border-radius: var(--radius);
            box-shadow: 0 8px 40px rgba(0,0,0,.25);
            width: 100%; max-width: 380px;
            padding: 1.8rem;
        }
        .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.2rem; }
        .modal-title  { font-size: 1.05rem; font-weight: 700; color: var(--primary); }
        .modal-close  { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--text-muted); }
        .modal-close:hover { color: var(--text); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }

        @media (max-width: 600px) {
            .summary-strip { grid-template-columns: 1fr 1fr; }
            .sup-nav { padding: .6rem 1rem; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav class="sup-nav">
    <div class="brand">&#x1F6E1; TDV Seguridad</div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($supNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Cerrar sesion</a>
    </div>
</nav>

<div style="max-width:1200px;margin:0 auto;padding:1.2rem 1rem 2rem;">

    <!-- Encabezado con fecha y reloj -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;">
        <h2 style="font-size:1.2rem;color:var(--primary);margin:0;">
            Presencias - <span id="fechaHoy"></span>
        </h2>
        <div style="display:flex;align-items:center;gap:.5rem;
                    background:var(--card);border-radius:10px;
                    padding:.45rem 1rem;box-shadow:var(--shadow);">
            <span style="font-size:.75rem;color:var(--text-muted);">Hora actual</span>
            <span id="supReloj" style="
                font-size:1.4rem;font-weight:700;color:var(--primary);
                font-variant-numeric:tabular-nums;letter-spacing:.03em;">
                --:--:--
            </span>
        </div>
    </div>

    <!-- Selector de objetivos -->
    <div class="obj-selector-card">
        <label for="selectObjetivo">&#x1F3AF; objetivos:</label>
        <select id="selectObjetivo">
            <option value="0">- Todos mis objetivos -</option>
        </select>
    </div>

    <!-- Alertas -->
    <div class="alert alert-danger"  id="pageError"   role="alert"><span>&#9888;</span><span id="pageErrorMsg"></span></div>
    <div class="alert alert-success" id="pageSuccess" role="alert"><span>&#9989;</span><span id="pageSuccessMsg"></span></div>

    <!-- Resumen -->
    <div class="summary-strip">
        <div class="summary-card">
            <div class="num num-presente"   id="cntPresente">0</div>
            <div class="lbl">En turno</div>
        </div>
        <div class="summary-card">
            <div class="num num-ausente"    id="cntAusente">0</div>
            <div class="lbl">Ausentes</div>
        </div>
        <div class="summary-card">
            <div class="num num-completado" id="cntCompletado">0</div>
            <div class="lbl">Turno completo</div>
        </div>
        <div class="summary-card" id="cardSinSalida" style="display:none;">
            <div class="num" style="color:#9a7d0a;" id="cntSinSalida">0</div>
            <div class="lbl">Registro incompleto</div>
        </div>
        <div class="summary-card">
            <div class="num num-total"      id="cntTotal">0</div>
            <div class="lbl">Total</div>
        </div>
    </div>

    <!-- Barra de refresh -->
    <div class="refresh-bar">
        <span>Ultima actualizacion: <strong id="ultimaActz">-</strong></span>
        <div style="display:flex;align-items:center;gap:.8rem;">
            <span>Actualizando en <span class="refresh-countdown" id="countdown">30</span>s</span>
            <button class="refresh-btn" onclick="refrescar()">&#x21BB; Ahora</button>
        </div>
    </div>

    <!-- Grilla de tarjetas -->
    <div class="cards-grid" id="cardsGrid">
        <div style="color:var(--text-muted);font-size:.9rem;grid-column:1/-1;padding:2rem;text-align:center;">
            <div class="spinner spinner-dark" style="margin:0 auto .8rem;"></div>
            Cargando...
        </div>
    </div>

</div>

<!-- MODAL editar turno -->
<div class="modal-overlay" id="modalTurno">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">&#x1F552; Modificar turno</span>
            <button class="modal-close" onclick="cerrarModal()">&#x2715;</button>
        </div>
        <div class="alert alert-danger" id="modalError" role="alert">
            <span>&#9888;</span><span id="modalErrorMsg"></span>
        </div>
        <p id="modalGuardName" style="font-weight:600;color:var(--text);margin-bottom:1rem;font-size:.95rem;"></p>
        <form id="formTurno" novalidate>
            <input type="hidden" id="fVigiId" value="0">
            <div class="form-row">
                <div class="form-group">
                    <label for="fEntrada">Hora de entrada <span style="color:var(--danger)">*</span></label>
                    <input type="time" id="fEntrada" required>
                </div>
                <div class="form-group">
                    <label for="fSalida">Hora de salida <span style="color:var(--danger)">*</span></label>
                    <input type="time" id="fSalida" required>
                </div>
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
const REFRESH_SEC = 30;
let countdownVal  = REFRESH_SEC;
let timer;

document.getElementById('fechaHoy').textContent = new Date().toLocaleDateString('es-AR', {
    weekday:'long', year:'numeric', month:'long', day:'numeric'
});

// Reloj en tiempo real
(function reloj() {
    function tick() {
        const n  = new Date();
        const hh = String(n.getHours()).padStart(2,'0');
        const mm = String(n.getMinutes()).padStart(2,'0');
        const ss = String(n.getSeconds()).padStart(2,'0');
        document.getElementById('supReloj').textContent = `${hh}:${mm}:${ss}`;
    }
    tick();
    setInterval(tick, 1000);
})();

// Cargar objetivos al iniciar
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const objetivos = await apiFetch('api/get_objetivos.php');
        const sel = document.getElementById('selectObjetivo');
        objetivos.forEach(o => {
            const opt = document.createElement('option');
            opt.value       = o.id_objetivo;
            opt.textContent = o.nombre;
            sel.appendChild(opt);
        });
    } catch (e) {
        mostrarError('No se pudieron cargar los objetivos.');
    }
    refrescar();
    document.getElementById('selectObjetivo').addEventListener('change', () => {
        clearInterval(timer);
        countdownVal = REFRESH_SEC;
        document.getElementById('countdown').textContent = countdownVal;
        refrescar();
    });
    document.getElementById('formTurno').addEventListener('submit', onGuardarTurno);
});

async function refrescar() {
    clearInterval(timer);
    countdownVal = REFRESH_SEC;
    document.getElementById('countdown').textContent = countdownVal;

    const objId = document.getElementById('selectObjetivo').value;
    const url   = objId && objId !== '0'
        ? `api/get_vigiladores.php?objetivo_id=${objId}`
        : 'api/get_vigiladores.php';

    try {
        const data = await apiFetch(url);
        renderCards(data);
        document.getElementById('ultimaActz').textContent =
            new Date().toLocaleTimeString('es-AR', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    } catch (e) {
        mostrarError(e.message || 'Error al actualizar presencias.');
        document.getElementById('cardsGrid').innerHTML =
            `<p style="color:var(--danger);grid-column:1/-1;text-align:center;padding:2rem;">${esc(e.message || 'Error al actualizar presencias.')}</p>`;
    }

    timer = setInterval(() => {
        countdownVal--;
        document.getElementById('countdown').textContent = countdownVal;
        if (countdownVal <= 0) refrescar();
    }, 1000);
}

const LABELS = {
    'presente'    : 'En turno',
    'ausente'     : 'Ausente',
    'completado'  : 'Turno completado',
    'incompleto'  : 'Registro incompleto',
    'sin-registro': 'No registró asistencia',
    'sin-salida'  : 'Registro incompleto',
    'por-iniciar' : 'No registró asistencia',
};
const BADGES = {
    'presente'    : 'badge-presente',
    'ausente'     : 'badge-ausente',
    'completado'  : 'badge-completado',
    'incompleto'  : 'badge-incompleto',
    'sin-registro': 'badge-sin-registro',
    'sin-salida'  : 'badge-sin-salida',
    'por-iniciar' : 'badge-por-iniciar',
};

function renderCards(guards) {
    const grid = document.getElementById('cardsGrid');
    if (!guards.length) {
        grid.innerHTML = '<p style="color:var(--text-muted);grid-column:1/-1;text-align:center;padding:2rem;">Sin empleados para este objetivos.</p>';
        document.getElementById('cntPresente').textContent   = 0;
        document.getElementById('cntAusente').textContent    = 0;
        document.getElementById('cntCompletado').textContent = 0;
        document.getElementById('cntTotal').textContent      = 0;
        document.getElementById('cardSinSalida').style.display = 'none';
        return;
    }

    let presente = 0, ausente = 0, completado = 0, sinSalida = 0;

    grid.innerHTML = guards.map(g => {
        if (g.estado === 'presente')   presente++;
        if (g.estado === 'ausente')    ausente++;
        if (g.estado === 'completado') completado++;
        if (g.estado === 'sin-salida' || g.estado === 'incompleto') sinSalida++;

        const turnoTxt = (g.turno_entrada && g.turno_salida)
            ? `<span style="font-size:.72rem;color:var(--text-muted);">Turno: ${esc(g.turno_entrada)} - ${esc(g.turno_salida)} hs</span>`
            : '<span style="font-size:.72rem;color:var(--text-muted);">Sin turno asignado</span>';

        const alertaSinSalida = g.estado === 'sin-salida' || g.estado === 'incompleto'
            ? `<div style="font-size:.75rem;color:#9a7d0a;margin-top:.4rem;font-weight:600;">Registro incompleto de asistencia</div>`
            : '';
        const bloqueAsistencia = g.estado === 'ausente' || g.estado === 'sin-registro' || g.estado === 'por-iniciar'
            ? `<div style="font-size:.8rem;color:var(--text-muted);margin-top:.75rem;">
                   No registró asistencia hoy.
               </div>`
            : `<div class="gc-times" style="margin-top:.5rem;">
                <div class="gc-time-item">
                    <span class="tl">Entrada hoy</span>
                    <span class="tv">${g.hora_entrada_hoy ? g.hora_entrada_hoy + ' hs' : '-'}</span>
                </div>
                <div class="gc-time-item">
                    <span class="tl">Salida hoy</span>
                    <span class="tv">${g.hora_salida_hoy ? g.hora_salida_hoy + ' hs' : '-'}</span>
                </div>
            </div>`;

        const objLine = document.getElementById('selectObjetivo').value === '0'
            ? `<div class="gc-obj">&#x1F4CD; ${esc(g.objetivo_nombre)}</div>`
            : '';

        return `
        <div class="guard-card ${esc(g.estado)}">
            <button class="btn-edit-turno" title="Modificar turno"
                onclick="abrirModal(${g.id_empleado},'${esc(g.nombre)} ${esc(g.apellido)}','${g.turno_entrada||''}','${g.turno_salida||''}')">
                &#9998; Turno
            </button>
            <div class="gc-name">${esc(g.apellido)}, ${esc(g.nombre)}</div>
            ${objLine}
            ${turnoTxt}
            <div class="gc-badge ${BADGES[g.estado] || ''}" style="margin-top:.5rem;">
                ${LABELS[g.estado] || g.estado}
            </div>
            ${bloqueAsistencia}
            ${alertaSinSalida}
        </div>`;
    }).join('');

    document.getElementById('cntPresente').textContent   = presente;
    document.getElementById('cntAusente').textContent    = ausente;
    document.getElementById('cntCompletado').textContent = completado;
    document.getElementById('cntTotal').textContent      = guards.length;
    document.getElementById('cntSinSalida').textContent  = sinSalida;
    document.getElementById('cardSinSalida').style.display = sinSalida > 0 ? '' : 'none';
}

// ---- Modal editar turno ----
function abrirModal(vigiladorId, nombre, entrada, salida) {
    document.getElementById('modalError').classList.remove('show');
    document.getElementById('fVigiId').value          = vigiladorId;
    document.getElementById('modalGuardName').textContent = nombre;
    document.getElementById('fEntrada').value          = entrada || '';
    document.getElementById('fSalida').value           = salida  || '';
    document.getElementById('modalTurno').classList.add('open');
    document.getElementById('fEntrada').focus();
}

function cerrarModal() {
    document.getElementById('modalTurno').classList.remove('open');
}

document.getElementById('modalTurno').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

async function onGuardarTurno(e) {
    e.preventDefault();
    const errDiv = document.getElementById('modalError');
    errDiv.classList.remove('show');

    const empleado_id  = parseInt(document.getElementById('fVigiId').value);
    const hora_entrada  = document.getElementById('fEntrada').value;
    const hora_salida   = document.getElementById('fSalida').value;

    if (!hora_entrada || !hora_salida) {
        document.getElementById('modalErrorMsg').textContent = 'Complete ambas horas.';
        errDiv.classList.add('show'); return;
    }

    const btn = document.getElementById('btnGuardar');
    btn.disabled    = true;
    btn.textContent = 'Guardando...';

    try {
        const res = await apiFetch('api/actualizar_turno.php', 'POST', { empleado_id, hora_entrada, hora_salida });
        cerrarModal();
        mostrarExito(res.mensaje);
        refrescar();
    } catch (err) {
        document.getElementById('modalErrorMsg').textContent = err.message;
        errDiv.classList.add('show');
    }

    btn.disabled    = false;
    btn.textContent = 'Guardar';
}

// ---- Helpers ----
function mostrarExito(msg) {
    const d = document.getElementById('pageSuccess');
    document.getElementById('pageSuccessMsg').textContent = msg;
    d.classList.add('show');
    setTimeout(() => d.classList.remove('show'), 4000);
}
function mostrarError(msg) {
    const d = document.getElementById('pageError');
    document.getElementById('pageErrorMsg').textContent = msg;
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
        throw new Error('La API devolvio una respuesta invalida.');
    }
    if (!res.ok) throw new Error(json.error || 'Error del servidor');
    return json;
}
function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>

