<?php
session_start();
if (isset($_SESSION['empleado_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title>TDV â€” Registrarse</title>
    <link rel="stylesheet" href="css/style.css?v=2">
</head>
<body>

<div class="login-wrap">
    <div class="login-card" style="max-width:480px;">

        <div class="login-logo">
            <span class="shield">&#x1F6E1;</span>
            <h1>TDV Seguridad</h1>
            <p>Solicitud de cuenta</p>
        </div>

        <div class="alert alert-info show" style="margin-bottom:1.2rem;">
            <span>&#x2139;</span>
            <span>Tu cuenta quedarÃ¡ <strong>pendiente de aprobaciÃ³n</strong> hasta que un administrador la habilite.</span>
        </div>

        <div class="alert alert-danger"  id="regError"   role="alert"><span>&#9888;</span><span id="regErrorMsg"></span></div>
        <div class="alert alert-success" id="regSuccess" role="alert"><span>&#9989;</span><span id="regSuccessMsg"></span></div>

        <form id="formRegistro" novalidate>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1rem;">
                <div class="form-group">
                    <label for="nombre">Nombre <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="nombre" required placeholder="Juan">
                </div>
                <div class="form-group">
                    <label for="apellido">Apellido <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="apellido" required placeholder="PÃ©rez">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1rem;">
                <div class="form-group">
                    <label for="dni">DNI <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="dni" required placeholder="30111222" maxlength="15">
                </div>
                <div class="form-group">
                    <label for="telefono">TelÃ©fono</label>
                    <input type="text" id="telefono" placeholder="1144455566">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email <span style="color:var(--danger)">*</span></label>
                <input type="email" id="email" required placeholder="correo@ejemplo.com" autocomplete="username">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1rem;">
                <div class="form-group">
                    <label for="pass1">ContraseÃ±a <span style="color:var(--danger)">*</span></label>
                    <input type="password" id="pass1" required autocomplete="new-password" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢">
                </div>
                <div class="form-group">
                    <label for="pass2">Confirmar <span style="color:var(--danger)">*</span></label>
                    <input type="password" id="pass2" required autocomplete="new-password" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="btnReg">
                Solicitar cuenta
            </button>

        </form>

        <p style="text-align:center;margin-top:1.2rem;font-size:.88rem;color:var(--text-muted);">
            Â¿Ya tenÃ©s cuenta? <a href="index.php" style="color:var(--accent);">Iniciar sesiÃ³n</a>
        </p>
    </div>
</div>

<script>
document.getElementById('formRegistro').addEventListener('submit', async function(e) {
    e.preventDefault();

    const err  = document.getElementById('regError');
    const ok   = document.getElementById('regSuccess');
    const btn  = document.getElementById('btnReg');

    err.classList.remove('show');
    ok.classList.remove('show');

    const nombre   = document.getElementById('nombre').value.trim();
    const apellido = document.getElementById('apellido').value.trim();
    const dni      = document.getElementById('dni').value.trim();
    const telefono = document.getElementById('telefono').value.trim();
    const email    = document.getElementById('email').value.trim();
    const usuario  = email;
    const pass1    = document.getElementById('pass1').value;
    const pass2    = document.getElementById('pass2').value;

    if (!nombre || !apellido || !dni || !telefono || !email || !pass1) {
        document.getElementById('regErrorMsg').textContent = 'Complete todos los campos obligatorios.';
        err.classList.add('show'); return;
    }
    if (pass1 !== pass2) {
        document.getElementById('regErrorMsg').textContent = 'Las contraseÃ±as no coinciden.';
        err.classList.add('show'); return;
    }
    if (pass1.length < 6) {
        document.getElementById('regErrorMsg').textContent = 'La contraseÃ±a debe tener al menos 6 caracteres.';
        err.classList.add('show'); return;
    }

    btn.disabled = true;
    btn.textContent = 'Enviando...';

    try {
        const res  = await fetch('api/registro.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ nombre, apellido, dni, telefono, email, usuario, contrasena: pass1 })
        });
        const data = await res.json();

        if (res.ok && data.success) {
            document.getElementById('formRegistro').style.display = 'none';
            document.getElementById('regSuccessMsg').textContent =
                'Solicitud enviada. Un administrador revisarÃ¡ tu cuenta a la brevedad.';
            ok.classList.add('show');
        } else {
            document.getElementById('regErrorMsg').textContent = data.error || 'Error al registrarse.';
            err.classList.add('show');
            btn.disabled    = false;
            btn.textContent = 'Solicitar cuenta';
        }
    } catch (e) {
        document.getElementById('regErrorMsg').textContent = 'No se pudo conectar al servidor.';
        err.classList.add('show');
        btn.disabled    = false;
        btn.textContent = 'Solicitar cuenta';
    }
});
</script>
</body>
</html>

