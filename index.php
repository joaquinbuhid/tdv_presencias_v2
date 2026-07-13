<?php
session_start();
// Si ya tiene sesión, ir al dashboard
if (isset($_SESSION['empleado_id'])) {
    if (!empty($_SESSION['es_admin'])) {
        header('Location: admin/dashboard.php');
    } elseif (!empty($_SESSION['es_supervisor'])) {
        header('Location: supervisor/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>TDV — Ingresar</title>
    <link rel="stylesheet" href="css/style.css?v=2">
</head>
<body>

<div class="login-wrap">
    <div class="login-card">

        <div class="login-logo">
            <h1>TDV Seguridad</h1>
            <p>Sistema de Asistencias</p>
        </div>

        <div class="alert alert-danger" id="loginError" role="alert">
            <span>&#9888;</span>
            <span id="loginErrorMsg"></span>
        </div>

        <form id="loginForm" novalidate>
            <div class="form-group">
                <label for="usuario">Email</label>
                <input type="email" id="usuario" name="usuario"
                       autocomplete="username"
                       placeholder="Ingrese su email"
                       required autofocus>
            </div>

            <div class="form-group">
                <label for="contrasena">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena"
                       autocomplete="current-password"
                       placeholder="Ingrese su contraseña"
                       required>
            </div>

            <button type="submit" class="btn btn-primary" id="btnLogin">
                Ingresar
            </button>
        </form>

        <p style="text-align:center;margin-top:1.2rem;font-size:.88rem;color:var(--text-muted);">
            ¿Primera vez? <a href="registro.php" style="color:var(--accent);">Solicitar cuenta</a>
        </p>
        <p class="app-footer" style="margin-top:.8rem;">
            Versión 1.0 &mdash; <?= date('Y') ?>
        </p>
    </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn      = document.getElementById('btnLogin');
    const errDiv   = document.getElementById('loginError');
    const errMsg   = document.getElementById('loginErrorMsg');
    const usuario  = document.getElementById('usuario').value.trim();
    const clave    = document.getElementById('contrasena').value;

    errDiv.classList.remove('show');

    if (!usuario || !clave) {
        errMsg.textContent = 'Por favor complete todos los campos.';
        errDiv.classList.add('show');
        return;
    }

    btn.disabled    = true;
    btn.textContent = 'Verificando...';

    try {
        const res  = await fetch('api/login.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ usuario, contrasena: clave })
        });
        const raw = await res.text();
        let data = {};
        try {
            data = raw ? JSON.parse(raw) : {};
        } catch (parseErr) {
            if (res.ok) {
                window.location.reload();
                return;
            }
            throw parseErr;
        }

        if (res.ok && data.success) {
            btn.textContent = 'Accediendo...';
            window.location.href = data.es_admin
                ? 'admin/dashboard.php'
                : (data.es_supervisor ? 'supervisor/dashboard.php' : 'dashboard.php');
        } else {
            errMsg.textContent = data.error || 'Error al iniciar sesión.';
            errDiv.classList.add('show');
            btn.disabled    = false;
            btn.textContent = 'Ingresar';
        }
    } catch (err) {
        errMsg.textContent = 'No se pudo conectar al servidor. Intente nuevamente.';
        errDiv.classList.add('show');
        btn.disabled    = false;
        btn.textContent = 'Ingresar';
    }
});
</script>

<!-- HELP DROPDOWN -->
<div class="help-dropdown-wrap" id="helpWrap">
    <div class="help-dropdown-panel" id="helpPanel">
        <div class="help-dropdown-inner">
            <div class="help-dropdown-title">&#x2753; ¿Cómo ingresar?</div>

            <div class="help-step">
                <div class="help-step-num">1</div>
                <div class="help-step-text">
                    Si es la <strong>primera vez</strong> que ingresa, presione en <strong>"Solicitar cuenta"</strong> (el enlace debajo del botón Ingresar).
                </div>
            </div>

            <div class="help-step">
                <div class="help-step-num">2</div>
                <div class="help-step-text">
                    Rellene todos los datos solicitados. Genere su <strong>usuario y contraseña</strong>. El usuario puede ser cualquiera (por ejemplo: <strong>nombreapellido</strong>). La contraseña también puede ser del formato que prefiera.
                </div>
            </div>

            <div class="help-step">
                <div class="help-step-num">3</div>
                <div class="help-step-text">
                    Al terminar presione en <strong>"Solicitar cuenta"</strong>.
                </div>
            </div>

            <div class="help-step">
                <div class="help-step-num">4</div>
                <div class="help-step-text">
                    <strong>Espere aproximadamente 20 minutos</strong> a que le habiliten su cuenta.
                </div>
            </div>

            <div class="help-step">
                <div class="help-step-num">5</div>
                <div class="help-step-text">
                    Una vez habilitada, podrá <strong>ingresar con normalidad</strong> usando su usuario y contraseña.
                </div>
            </div>

            <div class="help-note">
                <span>&#x1F4A1; Tip:</span> Si ya tiene cuenta y no puede ingresar, verifique que su usuario y contraseña estén correctos o comuníquese con su supervisor.
            </div>
        </div>
    </div>
    <button class="help-fab" id="helpFab" title="Ayuda" aria-label="Ayuda">?</button>
</div>

<script>
// ---- Help dropdown toggle ----
(function() {
    const fab   = document.getElementById('helpFab');
    const panel = document.getElementById('helpPanel');

    fab.addEventListener('click', function(e) {
        e.stopPropagation();
        const open = panel.classList.toggle('open');
        fab.classList.toggle('active', open);
    });

    document.addEventListener('click', function(e) {
        if (!document.getElementById('helpWrap').contains(e.target)) {
            panel.classList.remove('open');
            fab.classList.remove('active');
        }
    });
})();
</script>

</body>
</html>

