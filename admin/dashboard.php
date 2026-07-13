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
    <title>TDV - Presencias en vivo</title>
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
        .admin-nav .nav-links { display:flex; gap:.3rem; }
        .admin-nav .nav-links a {
            color: rgba(255,255,255,.75);
            text-decoration: none;
            padding: .4rem .9rem;
            border-radius: 6px;
            font-size: .88rem;
            transition: background .2s;
        }
        .admin-nav .nav-links a.active,
        .admin-nav .nav-links a:hover { background: rgba(255,255,255,.15); color:#fff; }
        .admin-nav .nav-user { color: rgba(255,255,255,.7); font-size: .82rem; text-align:right; }
        .admin-nav .nav-user strong { display:block; color:#fff; }

        /* Summary strip */
        .summary-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .8rem;
            margin-bottom: 1.2rem;
        }
        .summary-card {
            background: var(--card);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            box-shadow: var(--shadow);
        }
        .summary-card .num {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.1;
        }
        .summary-card .lbl { font-size: .78rem; color: var(--text-muted); margin-top: .2rem; }
        .num-presente   { color: var(--success); }
        .num-ausente    { color: var(--danger);  }
        .num-completado { color: var(--text);  }
        .num-total      { color: var(--primary); }

        /* Guard cards grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1rem;
        }
        .guard-card {
            background: var(--card);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 1.1rem 1.2rem;
            border-left: 5px solid var(--border);
            transition: transform .15s;
        }
        .guard-card:hover { transform: translateY(-2px); }
        .guard-card.presente    { border-left-color: var(--success); }
        .guard-card.ausente     { border-left-color: var(--danger);  }
        .guard-card.completado  { border-left-color: var(--text);  }
        .guard-card.incompleto  { border-left-color: #f1c40f; background: #fffdf2; }
        .guard-card.sin-registro { border-left-color: #3498db; background: #f4f9ff; }
        .guard-card.sin-salida  { border-left-color: #f1c40f; background: #fffdf2; }
        .guard-card.por-iniciar { border-left-color: var(--border); }
        .guard-card.sin-objetivos { border-left-color: var(--text-muted); opacity:.7; }

        .gc-name { font-size: 1rem; font-weight: 700; color: var(--text); }
        .gc-obj  { font-size: .78rem; color: var(--text-muted); margin: .15rem 0 .6rem; }
        .gc-badge {
            display: inline-block;
            padding: .2rem .7rem;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 700;
            margin-bottom: .6rem;
        }
        .badge-presente    { background: #eafaf1; color: #1e8449; }
        .badge-ausente     { background: #fdecea; color: #c0392b; }
        .badge-completado  { background: #f2f3f4; color: #111827; }
        .badge-incompleto  { background: #fff8d7; color: #9a7d0a; }
        .badge-sin-registro { background: #eaf4ff; color: #1f618d; }
        .badge-sin-salida  { background: #fff8d7; color: #9a7d0a; }
        .badge-por-iniciar { background: #f0f2f5; color: var(--text-muted); }
        .badge-sin-objetivos { background: #f0f2f5; color: var(--text-muted); }

        .gc-times {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .3rem;
            font-size: .8rem;
        }
        .gc-time-item { display: flex; flex-direction: column; }
        .gc-time-item .tl { color: var(--text-muted); font-size: .72rem; }
        .gc-time-item .tv { font-weight: 600; color: var(--text); }
        .gc-time-item .tv.empty { color: var(--border); }

        /* Refresh bar */
        .refresh-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: .85rem;
            color: var(--text-muted);
        }
        .refresh-countdown { font-weight: 600; color: var(--accent); }
        .refresh-btn {
            background: none;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: .3rem .8rem;
            cursor: pointer;
            font-size: .82rem;
            color: var(--text-muted);
            transition: background .2s;
        }
        .refresh-btn:hover { background: var(--bg); }

        @media (max-width: 600px) {
            .summary-strip { grid-template-columns: 1fr 1fr; }
            .admin-nav { padding: .6rem 1rem; }
        }
    </style>
</head>
<body>

<nav class="admin-nav">
    <div class="brand">&#x1F6E1; TDV Seguridad</div>
    <div class="nav-links">
        <a href="dashboard.php" class="active">&#x1F7E2; En vivo</a>
        <a href="usuarios.php">&#x2795; Usuarios</a>
        <a href="postulantes.php">Postulantes</a>
        <a href="vigiladores.php">&#x1F464; Empleados</a>
        <a href="supervisores.php">&#x1F4BC; Supervisores</a>
        <a href="objetivos.php">&#x1F3AF; Objetivos</a>
        <a href="reportes.php" style="position:relative;">&#x26A0; Reportes<span id="navBadgeRep" class="nav-badge" style="display:none;">!</span></a>
        <a href="informe_horas.php">Horas</a>
        <a href="migracion.php">Migracion</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<div style="max-width:1200px;margin:0 auto;padding:1.2rem 1rem 2rem;">

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;">
        <h2 style="font-size:1.2rem;color:var(--primary);margin:0;">
            Presencias - <span id="fechaHoy"></span>
        </h2>
        <div style="display:flex;align-items:center;gap:.5rem;
                    background:var(--card);border-radius:10px;
                    padding:.45rem 1rem;box-shadow:var(--shadow);">
            <span style="font-size:.75rem;color:var(--text-muted);">Hora actual</span>
            <span id="adminReloj" style="
                font-size:1.4rem;font-weight:700;color:var(--primary);
                font-variant-numeric:tabular-nums;letter-spacing:.03em;">
                --:--:--
            </span>
        </div>
    </div>

    <!-- Resumen -->
    <div class="summary-strip">
        <div class="summary-card">
            <div class="num num-presente"  id="cntPresente">0</div>
            <div class="lbl">En turno</div>
        </div>
        <div class="summary-card">
            <div class="num num-ausente"   id="cntAusente">0</div>
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
            <div class="num num-total"     id="cntTotal">0</div>
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
            Cargando presencias...
        </div>
    </div>

</div>

<script>
const REFRESH_SEC = 30;
let countdown = REFRESH_SEC;
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
        document.getElementById('adminReloj').textContent = `${hh}:${mm}:${ss}`;
    }
    tick();
    setInterval(tick, 1000);
})();

async function refrescar() {
    clearInterval(timer);
    countdown = REFRESH_SEC;
    document.getElementById('countdown').textContent = countdown;

    try {
        const res = await fetch('api/get_presentes.php');
        const data = await readJson(res);
        if (!res.ok) throw new Error(data.error || 'Error al cargar presencias.');
        renderCards(data);
        document.getElementById('ultimaActz').textContent =
            new Date().toLocaleTimeString('es-AR', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    } catch (e) {
        console.error(e);
        document.getElementById('cardsGrid').innerHTML =
            `<p style="color:var(--danger);grid-column:1/-1;text-align:center;padding:2rem;">${esc(e.message || 'Error al cargar presencias.')}</p>`;
    }

    timer = setInterval(() => {
        countdown--;
        document.getElementById('countdown').textContent = countdown;
        if (countdown <= 0) refrescar();
    }, 1000);
}

function renderCards(guards) {
    const grid = document.getElementById('cardsGrid');
    let presente=0, ausente=0, completado=0;

    if (!guards.length) {
        grid.innerHTML = '<p style="color:var(--text-muted);grid-column:1/-1;text-align:center;">Sin empleados activos registrados.</p>';
        document.getElementById('cntPresente').textContent = 0;
        document.getElementById('cntAusente').textContent = 0;
        document.getElementById('cntCompletado').textContent = 0;
        document.getElementById('cntTotal').textContent = 0;
        document.getElementById('cardSinSalida').style.display = 'none';
        return;
    }

    const labels = {
        'presente'    : 'En turno',
        'ausente'     : 'Ausente',
        'completado'  : 'Turno completado',
        'incompleto'  : 'Registro incompleto',
        'sin-registro': 'No registró asistencia',
        'sin-salida'  : 'Registro incompleto',
        'por-iniciar' : 'Por iniciar',
        'sin-objetivos': 'Sin objetivos',
    };
    const badges = {
        'presente'    : 'badge-presente',
        'ausente'     : 'badge-ausente',
        'completado'  : 'badge-completado',
        'incompleto'  : 'badge-incompleto',
        'sin-registro': 'badge-sin-registro',
        'sin-salida'  : 'badge-sin-salida',
        'por-iniciar' : 'badge-por-iniciar',
        'sin-objetivos': 'badge-sin-objetivos',
    };

    let sinSalida = 0;
    grid.innerHTML = guards.map(g => {
        if (g.estado === 'presente')   presente++;
        if (g.estado === 'ausente')    ausente++;
        if (g.estado === 'completado') completado++;
        if (g.estado === 'sin-salida' || g.estado === 'incompleto') sinSalida++;

        const turnoTxt = (g.turno_entrada && g.turno_salida)
            ? `<span style="font-size:.72rem;color:var(--text-muted);">Turno: ${esc(g.turno_entrada)} - ${esc(g.turno_salida)} hs</span>`
            : '';

        const alertaSinSalida = g.estado === 'sin-salida' || g.estado === 'incompleto'
            ? `<div style="font-size:.75rem;color:#9a7d0a;margin-top:.4rem;font-weight:600;">
                 Registro incompleto de asistencia
               </div>`
            : '';
        const bloqueAsistencia = g.estado === 'ausente' || g.estado === 'sin-registro' || g.estado === 'por-iniciar'
            ? `<div style="font-size:.8rem;color:var(--text-muted);margin-top:.75rem;">
                   ${g.estado === 'por-iniciar' ? 'Aún no inició el turno.' : (g.estado === 'sin-registro' ? 'No registró asistencia y no tiene horario asignado.' : 'No registró asistencia hoy.')}
               </div>`
            : `<div class="gc-times" style="margin-top:.5rem;">
                <div class="gc-time-item">
                    <span class="tl">Entrada</span>
                    <span class="tv">${g.hora_entrada_hoy ? g.hora_entrada_hoy + ' hs' : '-'}</span>
                </div>
                <div class="gc-time-item">
                    <span class="tl">Salida</span>
                    <span class="tv">${g.hora_salida_hoy ? g.hora_salida_hoy + ' hs' : '-'}</span>
                </div>
            </div>`;

        return `
        <div class="guard-card ${esc(g.estado)}">
            <div class="gc-name">${esc(g.nombre)} ${esc(g.apellido)}</div>
            <div class="gc-obj">&#x1F4CD; ${esc(g.objetivo_nombre || 'Sin objetivos asignado')}</div>
            ${turnoTxt}
            <div class="gc-badge ${badges[g.estado] || 'badge-sin-objetivos'}" style="margin-top:.5rem;">
                ${labels[g.estado] || g.estado}
            </div>
            ${bloqueAsistencia}
            ${alertaSinSalida}
        </div>`;
    }).join('');

    document.getElementById('cntPresente').textContent   = presente;
    document.getElementById('cntAusente').textContent    = ausente;
    document.getElementById('cntCompletado').textContent = completado;
    document.getElementById('cntTotal').textContent      = guards.filter(g => g.id_objetivo).length;

    // Mostrar contador de sin-salida si hay alguno
    const cntSinSalida = document.getElementById('cntSinSalida');
    if (cntSinSalida) {
        cntSinSalida.textContent = sinSalida;
        document.getElementById('cardSinSalida').style.display = sinSalida > 0 ? '' : 'none';
    }
}

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function readJson(res) {
    const raw = await res.text();
    try {
        return raw ? JSON.parse(raw) : {};
    } catch (e) {
        throw new Error('La API devolvio una respuesta invalida.');
    }
}

// Arrancar
refrescar();
</script>
</body>
</html>



