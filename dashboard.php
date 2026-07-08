<?php
session_start();
if (!isset($_SESSION['empleado_id'])) {
    header('Location: index.php');
    exit;
}
if (!empty($_SESSION['es_admin'])) {
    header('Location: admin/dashboard.php');
    exit;
}

require_once 'config/db.php';

// Cargar datos del vigilador y su objetivos
$db   = getDB();
$stmt = $db->prepare(
    "SELECT v.nombre, '' AS apellido, v.hora_entrada, v.hora_salida,
            o.nombre AS obj_nombre, o.rad_metros
     FROM empleados v
     LEFT JOIN objetivos o ON v.objetivo_id = o.id_objetivo
     WHERE v.id_empleado = ?"
);
$stmt->execute([$_SESSION['empleado_id']]);
$vigi = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>TDV — Panel</title>
    <link rel="stylesheet" href="css/style.css?v=2">
</head>
<body>

<!-- HEADER -->
<header class="app-header">
    <div class="brand">
        <span>&#x1F6E1;</span> TDV Seguridad
    </div>
    <div class="header-user">
        <strong><?= htmlspecialchars($vigi['nombre']) ?></strong>
        <a href="api/logout.php" style="color:rgba(255,255,255,.75);font-size:.95rem;text-decoration:none;">
            Cerrar sesión
        </a>
    </div>
</header>

<!-- CONTENIDO -->
<main class="app-content">

    <!-- Reloj -->
    <div style="text-align:center;padding:1rem 0 .3rem;">
        <div id="relojHora" style="
            font-size:3.4rem;font-weight:700;letter-spacing:.05em;
            color:var(--primary);font-variant-numeric:tabular-nums;line-height:1;">
            --:--:--
        </div>
        <div id="relojFecha" style="font-size:1rem;color:var(--text-muted);margin-top:.35rem;"></div>
    </div>

    <!-- objetivos asignado -->
    <div style="text-align:center; padding:.5rem 0 .2rem;">
        <span class="objetivos-badge">
            &#x1F4CD; <?= htmlspecialchars($vigi['obj_nombre'] ?? 'Sin objetivos') ?>
        </span>
        <div style="font-size:1rem;color:var(--text-muted);margin-top:.35rem;">
            Turno: <?= substr($vigi['hora_entrada'],0,5) ?> hs &mdash; <?= substr($vigi['hora_salida'],0,5) ?> hs
        </div>
    </div>

    <!-- Estado del día -->
    <div class="card" id="cardEstado">
        <div class="card-title">&#x1F4CB; Registros de hoy</div>
        <div id="estadoContent">
            <div class="loc-status">
                <div class="spinner spinner-dark"></div>
                Cargando registros...
            </div>
        </div>
    </div>

    <!-- Formulario de registro -->
    <div class="card">
        <div class="card-title">&#x270F; Registrar novedad</div>

        <!-- Mensajes -->
        <div class="alert alert-danger"  id="regError"   role="alert"><span>&#9888;</span><span id="regErrorMsg"></span></div>
        <div class="alert alert-success" id="regSuccess" role="alert"><span>&#9989;</span><span id="regSuccessMsg"></span></div>

        <form id="formNovedad" novalidate>

            <div class="form-group">
                <label for="tipoNovedad">Tipo de novedad</label>
                <select id="tipoNovedad" required>
                    <option value="">— Seleccione —</option>
                </select>
            </div>

            <div class="form-group">
                <label for="observaciones">Observaciones <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                <textarea id="observaciones" placeholder="Ingrese observaciones..."></textarea>
            </div>

            <!-- Estado de geolocalización -->
            <div class="loc-status" id="locStatus">
                <span>&#x1F4F1;</span>
                <span id="locMsg">La ubicación se obtendrá al confirmar.</span>
            </div>
            <div class="progress-wrap" id="progressWrap" style="display:none;">
                <div class="progress-bar" id="progressBar"></div>
            </div>

            <button type="submit" class="btn btn-success" id="btnRegistrar" style="margin-top:1rem;">
                <span id="btnIcon">&#x2705;</span>
                <span id="btnText">Confirmar asistencia</span>
            </button>

        </form>
    </div>

</main>

<footer class="app-footer">
    TDV Seguridad &mdash; <?= date('d/m/Y') ?>
    &nbsp;|&nbsp;
    <button onclick="abrirReporte()"
        style="background:none;border:none;color:var(--text-muted);font-size:.9rem;
               cursor:pointer;text-decoration:underline;padding:0;">
        &#x26A0; Reportar un problema
    </button>
</footer>

<!-- MODAL reporte de error -->
<div id="modalReporte"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);
            z-index:1000;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#fff;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.25);
                width:100%;max-width:460px;padding:1.8rem;">

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.1rem;">
            <strong style="font-size:1.05rem;color:var(--primary);">&#x26A0; Reportar un problema</strong>
            <button onclick="cerrarReporte()"
                style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted);">&#x2715;</button>
        </div>

        <div class="alert alert-danger"  id="repError" role="alert"><span>&#9888;</span><span id="repErrorMsg"></span></div>
        <div class="alert alert-success" id="repOk"    role="alert"><span>&#9989;</span><span id="repOkMsg"></span></div>

        <form id="formReporte" novalidate>

            <div class="form-group">
                <label for="repAccion">¿Qué estabas intentando hacer? <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                <select id="repAccion">
                    <option value="">— Seleccioná —</option>
                    <option>Iniciar sesión</option>
                    <option>Confirmar asistencia / entrada</option>
                    <option>Confirmar salida</option>
                    <option>Registrar una novedad</option>
                    <option>Ver mis registros del día</option>
                    <option>Otro</option>
                </select>
            </div>

            <div class="form-group">
                <label for="repMensaje">¿Qué mensaje de error apareció en pantalla? <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                <input type="text" id="repMensaje" placeholder="Ej: «Fuera del área permitida»">
            </div>

            <div class="form-group">
                <label for="repDesc">Descripción del problema <span style="color:var(--danger)">*</span></label>
                <textarea id="repDesc" rows="3"
                    placeholder="Contá con detalle qué pasó, qué esperabas que ocurriera y qué ocurrió en cambio..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" id="btnEnviarRep">
                Enviar reporte
            </button>
        </form>
    </div>
</div>

<script src="js/app.js"></script>

<!-- HELP DROPDOWN -->
<div class="help-dropdown-wrap" id="helpWrapDash">
    <div class="help-dropdown-panel" id="helpPanelDash">
        <div class="help-dropdown-inner">
            <div class="help-dropdown-title">&#x2753; ¿Cómo registrar asistencia?</div>

            <div class="help-step">
                <div class="help-step-num">1</div>
                <div class="help-step-text">
                    Seleccione el <strong>tipo de novedad</strong> en el formulario.
                </div>
            </div>

            <div class="help-step">
                <div class="help-step-num">2</div>
                <div class="help-step-text">
                    Si está <strong>ingresando</strong> a su puesto, seleccione <strong>"Entrada"</strong>. Si ya se está <strong>retirando</strong>, seleccione <strong>"Salida"</strong>.
                </div>
            </div>

            <div class="help-step">
                <div class="help-step-num">3</div>
                <div class="help-step-text">
                    Para ambos casos, <strong>espere a que la ubicación se calibre</strong> correctamente antes de confirmar.
                </div>
            </div>

            <div class="help-note">
                <span>&#x26A0; Importante:</span> Si detecta algún problema, por favor haga el reporte usando el botón <strong>"Reportar un problema"</strong> que se encuentra en la parte inferior de la pantalla.
            </div>
        </div>
    </div>
    <button class="help-fab" id="helpFabDash" title="Ayuda" aria-label="Ayuda">?</button>
</div>

<script>
(function reloj() {
    const DIAS   = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    const MESES  = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    function tick() {
        const n  = new Date();
        const hh = String(n.getHours()).padStart(2,'0');
        const mm = String(n.getMinutes()).padStart(2,'0');
        const ss = String(n.getSeconds()).padStart(2,'0');
        document.getElementById('relojHora').textContent  = `${hh}:${mm}:${ss}`;
        document.getElementById('relojFecha').textContent =
            `${DIAS[n.getDay()]} ${n.getDate()} de ${MESES[n.getMonth()]} de ${n.getFullYear()}`;
    }
    tick();
    setInterval(tick, 1000);
})();

// ---- Modal reporte de error --------------------------------
function abrirReporte() {
    document.getElementById('formReporte').reset();
    document.getElementById('repError').classList.remove('show');
    document.getElementById('repOk').classList.remove('show');
    const m = document.getElementById('modalReporte');
    m.style.display = 'flex';
}

function cerrarReporte() {
    document.getElementById('modalReporte').style.display = 'none';
}

document.getElementById('modalReporte').addEventListener('click', function(e) {
    if (e.target === this) cerrarReporte();
});

document.getElementById('formReporte').addEventListener('submit', async function(e) {
    e.preventDefault();
    const errDiv = document.getElementById('repError');
    const okDiv  = document.getElementById('repOk');
    errDiv.classList.remove('show');
    okDiv.classList.remove('show');

    const accion        = document.getElementById('repAccion').value;
    const mensaje_error = document.getElementById('repMensaje').value.trim();
    const descripcion   = document.getElementById('repDesc').value.trim();

    if (!descripcion) {
        document.getElementById('repErrorMsg').textContent = 'Por favor describí el problema.';
        errDiv.classList.add('show'); return;
    }

    const btn = document.getElementById('btnEnviarRep');
    btn.disabled    = true;
    btn.textContent = 'Enviando...';

    try {
        const res = await fetch('api/reportar_error.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                accion, mensaje_error, descripcion,
                user_agent: navigator.userAgent
            })
        });
        const data = await res.json();
        if (res.ok && data.success) {
            document.getElementById('formReporte').reset();
            document.getElementById('repOkMsg').textContent = data.mensaje;
            okDiv.classList.add('show');
            setTimeout(cerrarReporte, 3000);
        } else {
            document.getElementById('repErrorMsg').textContent = data.error || 'Error al enviar.';
            errDiv.classList.add('show');
        }
    } catch(err) {
        document.getElementById('repErrorMsg').textContent = 'No se pudo conectar al servidor.';
        errDiv.classList.add('show');
    }

    btn.disabled    = false;
    btn.textContent = 'Enviar reporte';
});

// ---- Help dropdown toggle (dashboard) ----
(function() {
    const fab   = document.getElementById('helpFabDash');
    const panel = document.getElementById('helpPanelDash');

    fab.addEventListener('click', function(e) {
        e.stopPropagation();
        const open = panel.classList.toggle('open');
        fab.classList.toggle('active', open);
    });

    document.addEventListener('click', function(e) {
        if (!document.getElementById('helpWrapDash').contains(e.target)) {
            panel.classList.remove('open');
            fab.classList.remove('active');
        }
    });
})();
</script>

</body>
</html>

