<?php
session_start();
if (empty($_SESSION['es_admin'])) {
    header('Location: ../index.php');
    exit;
}
$adminNombre = $_SESSION['nombre_completo'] ?? 'Administrador';
require_once __DIR__ . '/auth.php';
$esAdminReal = esAdminReal();

require_once __DIR__ . '/../config/db.php';

$error = '';
$postulantes = [];
$empleados = [];

try {
    $db = getDB();
    // Load lists
    $stmtP = $db->query("SELECT id, nombre_completo AS nombre, email, dni, telefono FROM postulantes ORDER BY nombre_completo ASC");
    $postulantes = $stmtP->fetchAll();

    $stmtE = $db->query("SELECT id_empleado AS id, nombre, email, dni, telefono FROM empleados ORDER BY nombre ASC");
    $empleados = $stmtE->fetchAll();
} catch (Exception $e) {
    $error = 'Error de base de datos al cargar destinatarios: ' . $e->getMessage();
}

$pre_type = $_GET['type'] ?? 'postulantes';
$pre_ids = $_GET['ids'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV — Envío de Emails Masivos</title>
    <link rel="stylesheet" href="../css/style.css">
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
        }
        .admin-nav .nav-links a.active,
        .admin-nav .nav-links a:hover { background:rgba(255,255,255,.15);color:#fff; }
        .admin-nav .nav-user { color:rgba(255,255,255,.7);font-size:.82rem;text-align:right; }
        .admin-nav .nav-user strong { display:block; color:#fff; }

        .layout-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .panel {
            background: var(--card);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            border-top: 4px solid var(--primary);
            display: flex;
            flex-direction: column;
            height: calc(100vh - 160px);
            min-height: 550px;
        }
        
        .panel-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .recipient-selector-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            background: var(--bg);
            padding: 0.5rem;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        .recipient-selector-group label {
            flex: 1;
            text-align: center;
            padding: 0.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            user-select: none;
        }
        
        .recipient-selector-group input { display: none; }
        
        .recipient-selector-group label.active {
            background: var(--primary);
            color: white;
        }

        .scrollable-list {
            flex: 1;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-top: 0.8rem;
            padding: 0.3rem;
            background: #fafafa;
        }
        
        .recipient-row {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.6rem 0.8rem;
            border-bottom: 1px solid var(--border);
            background: white;
            border-radius: 4px;
            margin-bottom: 0.3rem;
            transition: background 0.15s;
        }
        .recipient-row:hover { background: #f1f5f9; }
        .recipient-row input[type="checkbox"] { cursor: pointer; transform: scale(1.1); }
        .recipient-row .info { flex: 1; min-width: 0; }
        .recipient-row .info .name { font-weight: 600; font-size: 0.88rem; color: var(--text); }
        .recipient-row .info .sub { font-size: 0.75rem; color: var(--text-muted); }
        
        .variable-badges {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
            margin-bottom: 1.2rem;
        }
        
        .var-badge {
            background: #e2e8f0;
            color: #475569;
            font-size: 0.78rem;
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            cursor: pointer;
            user-select: none;
            font-weight: 600;
            transition: all 0.15s;
        }
        .var-badge:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Modal Progress style */
        .progress-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.75);
            display: flex; align-items: center; justify-content: center;
            z-index: 1000;
            opacity: 0; pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .progress-overlay.active { opacity: 1; pointer-events: auto; }
        
        .progress-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            width: 90%;
            max-width: 550px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        
        .progress-bar-wrap {
            background: #e2e8f0;
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
            margin: 1.2rem 0;
            position: relative;
        }
        .progress-bar-fill {
            background: var(--primary);
            height: 100%;
            width: 0%;
            transition: width 0.15s ease-out;
        }
        
        .sending-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-block {
            background: var(--bg);
            border-radius: 8px;
            padding: 0.8rem;
            border: 1px solid var(--border);
        }
        .stat-block .num { font-size: 1.4rem; font-weight: 700; color: var(--primary); }
        .stat-block .lbl { font-size: 0.75rem; color: var(--text-muted); }
        
        .sending-log {
            text-align: left;
            background: #0f172a;
            color: #38bdf8;
            font-family: monospace;
            font-size: 0.78rem;
            height: 150px;
            overflow-y: auto;
            border-radius: 6px;
            padding: 0.8rem;
            margin-bottom: 1.5rem;
            border: 1px solid #1e293b;
        }
        .sending-log div { margin-bottom: 0.25rem; }
        .sending-log .err-log { color: #f87171; }
        .sending-log .ok-log { color: #4ade80; }
        
        @media(max-width: 820px) {
            .layout-grid { grid-template-columns: 1fr; }
            .panel { height: auto; }
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
        <a href="postulantes.php">Postulantes</a>
        <a href="vigiladores.php">Empleados</a>
        <a href="legajos.php">Legajos</a>
        <a href="supervisores.php">Supervisores</a>
        <?php if ($esAdminReal): ?>
        <a href="objetivos.php">Objetivos</a>
        <a href="reportes.php">Reportes</a>
        <?php endif; ?>
        <a href="liquidacion.php">Horas</a>
        <a href="enviar_mails.php" class="active">Mails</a>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($adminNombre) ?></strong>
        <a href="../api/logout.php" style="color:rgba(255,255,255,.6);font-size:.78rem;text-decoration:none;">Salir</a>
    </div>
</nav>

<div class="page-shell" style="max-width: 1300px; margin: 0 auto; padding: 1.5rem 1rem;">

    <div class="page-head">
        <h1 class="page-title">✉️ Panel de Envío de Emails Masivos</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger show"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="layout-grid">
        <!-- Panel Izquierdo: Destinatarios -->
        <div class="panel">
            <div class="panel-title">
                <span>👥 Destinatarios</span>
                <span id="selected-counter" style="font-size:0.8rem; background: var(--primary); color:white; padding: 0.15rem 0.5rem; border-radius: 10px;">0 seleccionados</span>
            </div>
            
            <div class="recipient-selector-group">
                <label id="lbl-postulantes" class="active">
                    <input type="radio" name="recipient_type" value="postulantes" checked onchange="toggleType('postulantes')">
                    Postulantes
                </label>
                <label id="lbl-empleados">
                    <input type="radio" name="recipient_type" value="empleados" onchange="toggleType('empleados')">
                    Empleados
                </label>
            </div>
            
            <div style="display:flex; gap:0.5rem; margin-bottom: 0.5rem;">
                <input type="text" id="recipient-search" placeholder="Buscar por nombre, DNI o email..." style="flex:1; font-size:0.85rem; padding:0.5rem;" oninput="filtrarDestinatarios()">
            </div>
            
            <div style="display:flex; justify-content:space-between; align-items:center; font-size: 0.8rem; margin: 0.3rem 0;">
                <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-weight:600;">
                    <input type="checkbox" id="chk-all" onchange="toggleSelectAll(this.checked)">
                    Seleccionar Todos
                </label>
                <div style="color:var(--text-muted);">
                    Mostrando <span id="showing-count">0</span> de <span id="total-count">0</span>
                </div>
            </div>
            
            <div class="scrollable-list" id="recipients-list-container">
                <!-- Se inyecta dinámicamente desde JS -->
            </div>
        </div>
        
        <!-- Panel Derecho: Mensaje -->
        <div class="panel">
            <div class="panel-title">📝 Redactar Mensaje</div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="email-subject">Asunto del Correo <span style="color:var(--danger)">*</span></label>
                <input type="text" id="email-subject" placeholder="Ej: Convocatoria para entrevista laboral" style="width: 100%;">
            </div>
            
            <div class="form-group" style="margin-bottom: 0.2rem; flex: 1; display:flex; flex-direction:column;">
                <label for="email-body">Contenido HTML <span style="color:var(--danger)">*</span></label>
                <textarea id="email-body" placeholder="Pegue aquí el código HTML de su correo..." style="width: 100%; flex: 1; font-family: monospace; font-size: 0.82rem; resize: none; margin-top:0.3rem;"></textarea>
            </div>
            
            <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.3rem; font-weight:600;">
                Variables disponibles (haz clic para insertar en el cursor):
            </div>
            <div class="variable-badges">
                <span class="var-badge" onclick="insertVariable('{nombre}')">{nombre}</span>
                <span class="var-badge" onclick="insertVariable('{email}')">{email}</span>
                <span class="var-badge" onclick="insertVariable('{dni}')">{dni}</span>
                <span class="var-badge" onclick="insertVariable('{telefono}')">{telefono}</span>
            </div>
            
            <button class="btn btn-primary" style="width: 100%; padding:0.8rem;" onclick="prepararEnvio()">
                ✉️ Iniciar Envío Masivo
            </button>
        </div>
    </div>

</div>

<!-- Modal Progress Overlay -->
<div class="progress-overlay" id="progress-modal">
    <div class="progress-card">
        <h3 id="progress-title" style="margin-top:0; color:var(--primary);">Enviando correos masivos...</h3>
        <p style="font-size: 0.88rem; color:var(--text-muted); margin-bottom: 0.5rem;" id="progress-subtitle">Procesando cola de envíos</p>
        
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" id="progress-bar"></div>
        </div>
        
        <div class="sending-stats">
            <div class="stat-block">
                <div class="num" id="stat-processed">0</div>
                <div class="lbl">Procesados</div>
            </div>
            <div class="stat-block">
                <div class="num" id="stat-ok" style="color:#27ae60;">0</div>
                <div class="lbl">Exitosos</div>
            </div>
            <div class="stat-block">
                <div class="num" id="stat-err" style="color:#e74c3c;">0</div>
                <div class="lbl">Errores</div>
            </div>
        </div>
        
        <div class="sending-log" id="log-console">
            <!-- Consola de envío en vivo -->
        </div>
        
        <div style="display:flex; gap:0.5rem; justify-content:center;">
            <button class="btn btn-danger" id="btn-pause" onclick="pausarEnvio()">Pausar</button>
            <button class="btn btn-secondary" id="btn-close" style="display:none;" onclick="cerrarProgreso()">Cerrar</button>
        </div>
    </div>
</div>

<script>
const postulantes = <?= json_encode($postulantes) ?>;
const empleados = <?= json_encode($empleados) ?>;
const preType = <?= json_encode($pre_type) ?>;
const preIds = <?= json_encode($pre_ids) ?>.split(',').filter(x => x).map(Number);

let currentType = 'postulantes';
let checkedIds = new Set();
let isSending = false;
let stopRequested = false;
let sendQueue = [];
let currentIndex = 0;
let stats = { processed: 0, ok: 0, err: 0 };

document.addEventListener('DOMContentLoaded', () => {
    // Set initial toggle based on pre-selection
    if (preType === 'empleados') {
        document.querySelector('input[value="empleados"]').checked = true;
        toggleType('empleados');
    } else {
        toggleType('postulantes');
    }
    
    // Add pre-selected checkboxes
    if (preIds.length > 0) {
        preIds.forEach(id => checkedIds.add(id));
        renderList();
        updateSelectedCounter();
    }
});

function toggleType(type) {
    currentType = type;
    checkedIds.clear();
    document.getElementById('chk-all').checked = false;
    document.getElementById('recipient-search').value = '';
    
    // Toggle active state classes
    if (type === 'postulantes') {
        document.getElementById('lbl-postulantes').classList.add('active');
        document.getElementById('lbl-empleados').classList.remove('active');
    } else {
        document.getElementById('lbl-empleados').classList.add('active');
        document.getElementById('lbl-postulantes').classList.remove('active');
    }
    
    renderList();
    updateSelectedCounter();
}

function getActiveList() {
    return currentType === 'postulantes' ? postulantes : empleados;
}

function renderList() {
    const list = getActiveList();
    const query = document.getElementById('recipient-search').value.toLowerCase().trim();
    const container = document.getElementById('recipients-list-container');
    
    let filtered = list;
    if (query !== '') {
        filtered = list.filter(item => {
            return item.nombre.toLowerCase().includes(query) ||
                   item.dni.toLowerCase().includes(query) ||
                   (item.email && item.email.toLowerCase().includes(query));
        });
    }
    
    document.getElementById('total-count').textContent = list.length;
    document.getElementById('showing-count').textContent = filtered.length;
    
    if (filtered.length === 0) {
        container.innerHTML = `<div style="padding:1.5rem; text-align:center; color:var(--text-muted); font-size:0.88rem;">No se encontraron destinatarios.</div>`;
        return;
    }
    
    container.innerHTML = filtered.map(item => {
        const isChecked = checkedIds.has(item.id) ? 'checked' : '';
        return `
            <div class="recipient-row">
                <input type="checkbox" data-id="${item.id}" ${isChecked} onchange="toggleSelect(${item.id}, this.checked)">
                <div class="info">
                    <div class="name">${esc(item.nombre)}</div>
                    <div class="sub">DNI ${esc(item.dni)} ${item.email ? ` - ${esc(item.email)}` : ' - (Sin correo registrado)'}</div>
                </div>
            </div>
        `;
    }).join('');
    
    // Sync master checkbox state
    const allFilteredChecked = filtered.every(item => checkedIds.has(item.id));
    document.getElementById('chk-all').checked = filtered.length > 0 && allFilteredChecked;
}

function toggleSelect(id, isChecked) {
    if (isChecked) {
        checkedIds.add(id);
    } else {
        checkedIds.delete(id);
    }
    updateSelectedCounter();
}

function toggleSelectAll(isChecked) {
    const list = getActiveList();
    const query = document.getElementById('recipient-search').value.toLowerCase().trim();
    
    let filtered = list;
    if (query !== '') {
        filtered = list.filter(item => {
            return item.nombre.toLowerCase().includes(query) ||
                   item.dni.toLowerCase().includes(query) ||
                   (item.email && item.email.toLowerCase().includes(query));
        });
    }
    
    filtered.forEach(item => {
        if (isChecked) {
            checkedIds.add(item.id);
        } else {
            checkedIds.delete(item.id);
        }
    });
    
    renderList();
    updateSelectedCounter();
}

function filtrarDestinatarios() {
    renderList();
}

function updateSelectedCounter() {
    document.getElementById('selected-counter').textContent = `${checkedIds.size} seleccionados`;
}

function insertVariable(variable) {
    const txtArea = document.getElementById('email-body');
    const inputSubj = document.getElementById('email-subject');
    
    // Determine which field is focused
    let field = document.activeElement === inputSubj ? inputSubj : txtArea;
    
    const start = field.selectionStart;
    const end = field.selectionEnd;
    const text = field.value;
    
    field.value = text.substring(0, start) + variable + text.substring(end);
    field.focus();
    field.selectionStart = field.selectionEnd = start + variable.length;
}

function esc(val) {
    if (!val) return '';
    return String(val)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function prepararEnvio() {
    if (checkedIds.size === 0) {
        alert('Por favor, seleccione al menos un destinatario.');
        return;
    }
    
    const subject = document.getElementById('email-subject').value.trim();
    const body = document.getElementById('email-body').value.trim();
    
    if (subject === '') {
        alert('Por favor, ingrese el asunto del correo.');
        return;
    }
    
    if (body === '') {
        alert('Por favor, pegue el contenido HTML del correo.');
        return;
    }
    
    const confirmSend = confirm(`¿Desea iniciar el envío masivo de correos a los ${checkedIds.size} destinatarios seleccionados?`);
    if (!confirmSend) return;
    
    // Prepare Queue
    const activeList = getActiveList();
    sendQueue = activeList.filter(item => checkedIds.has(item.id));
    currentIndex = 0;
    stats = { processed: 0, ok: 0, err: 0 };
    stopRequested = false;
    
    // Setup modal
    document.getElementById('stat-processed').textContent = '0';
    document.getElementById('stat-ok').textContent = '0';
    document.getElementById('stat-err').textContent = '0';
    document.getElementById('progress-bar').style.width = '0%';
    document.getElementById('progress-title').textContent = 'Enviando correos masivos...';
    document.getElementById('progress-subtitle').textContent = `Procesando 1 de ${sendQueue.length}`;
    document.getElementById('log-console').innerHTML = '';
    document.getElementById('btn-pause').style.display = 'inline-block';
    document.getElementById('btn-pause').textContent = 'Pausar';
    document.getElementById('btn-close').style.display = 'none';
    
    document.getElementById('progress-modal').classList.add('active');
    
    procesarCola();
}

async function procesarCola() {
    isSending = true;
    const subject = document.getElementById('email-subject').value.trim();
    const body = document.getElementById('email-body').value.trim();
    
    const total = sendQueue.length;
    const consoleLog = document.getElementById('log-console');
    
    while (currentIndex < total && !stopRequested) {
        const item = sendQueue[currentIndex];
        document.getElementById('progress-subtitle').textContent = `Procesando ${currentIndex + 1} de ${total}`;
        
        appendLog(`[${currentIndex + 1}/${total}] Enviando a ${item.nombre} (${item.email || 'Sin correo'})...`);
        
        try {
            const response = await fetch('api/enviar_mail_destinatario.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: item.id,
                    type: currentType,
                    subject: subject,
                    body: body
                })
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                stats.ok++;
                updateLogSuccess('¡Enviado con éxito!');
            } else {
                stats.err++;
                updateLogDanger(`ERROR: ${result.error || 'Falla del servidor SMTP'}`);
            }
        } catch (e) {
            stats.err++;
            updateLogDanger(`ERROR de Red: No se pudo conectar al servidor`);
        }
        
        stats.processed++;
        currentIndex++;
        
        // Update stats UI
        document.getElementById('stat-processed').textContent = stats.processed;
        document.getElementById('stat-ok').textContent = stats.ok;
        document.getElementById('stat-err').textContent = stats.err;
        
        // Progress bar fill
        const pct = Math.round((stats.processed / total) * 100);
        document.getElementById('progress-bar').style.width = `${pct}%`;
        
        // Small delay to prevent resource hogging
        await new Promise(r => setTimeout(r, 400));
    }
    
    isSending = false;
    
    if (stopRequested) {
        appendLog('--- Envío PAUSADO por el usuario ---', 'warn');
        document.getElementById('btn-pause').textContent = 'Reanudar';
        document.getElementById('progress-title').textContent = 'Envío Pausado';
    } else {
        appendLog('--- Envío Masivo Finalizado ---', 'ok');
        document.getElementById('progress-title').textContent = 'Envío Completado';
        document.getElementById('btn-pause').style.display = 'none';
        document.getElementById('btn-close').style.display = 'inline-block';
    }
}

function pausarEnvio() {
    if (isSending) {
        stopRequested = true;
        document.getElementById('btn-pause').textContent = 'Pausando...';
    } else {
        // Resume
        stopRequested = false;
        document.getElementById('btn-pause').textContent = 'Pausar';
        document.getElementById('progress-title').textContent = 'Enviando correos masivos...';
        procesarCola();
    }
}

function cerrarProgreso() {
    document.getElementById('progress-modal').classList.remove('active');
}

function appendLog(txt, type = '') {
    const consoleLog = document.getElementById('log-console');
    const div = document.createElement('div');
    div.textContent = txt;
    if (type === 'ok') div.className = 'ok-log';
    if (type === 'err') div.className = 'err-log';
    consoleLog.appendChild(div);
    consoleLog.scrollTop = consoleLog.scrollHeight;
}

function updateLogSuccess(txt) {
    const consoleLog = document.getElementById('log-console');
    const last = consoleLog.lastElementChild;
    if (last) {
        last.textContent += ` ${txt}`;
        last.className = 'ok-log';
    }
}

function updateLogDanger(txt) {
    const consoleLog = document.getElementById('log-console');
    const last = consoleLog.lastElementChild;
    if (last) {
        last.textContent += ` ${txt}`;
        last.className = 'err-log';
    }
}
</script>
</body>
</html>
