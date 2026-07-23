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
    <title>TDV - Gestión de legajos</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Nav (igual que dashboard) */
        .admin-nav {
            background: var(--primary-dk);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .7rem 1.5rem;
            flex-wrap: wrap;
            gap: .5rem;
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

        /* Table */
        .data-table { width:100%;border-collapse:collapse;font-size:.88rem; }
        .data-table th {
            background:var(--primary);color:#fff;
            padding:.65rem .9rem;text-align:left;font-weight:600;
        }
        .data-table td { padding:.6rem .9rem;border-bottom:1px solid var(--bg);vertical-align:middle; }
        .data-table tr:hover td { background:#f7f9fc; }
        .data-table .actions { display:flex;gap:.4rem;flex-wrap:wrap; }

        /* Estado pills */
        .badge {
            display:inline-block;padding:.18rem .65rem;border-radius:20px;
            font-size:.75rem;font-weight:600;white-space:nowrap;
        }
        .badge-activo    { background:#eafaf1;color:#1e8449; }
        .badge-inactivo  { background:#fdecea;color:#c0392b; }
        .badge-pendiente { background:#fef9e7;color:#9a7d0a; }

        /* Section header */
        .section-header {
            display:flex;align-items:center;justify-content:space-between;
            margin-bottom:.8rem;
        }
        .section-title { font-size:1rem;font-weight:700;color:var(--primary); }

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
            width:100%;max-width:680px;max-height:90vh;overflow-y:auto;
            padding:1.8rem;
        }
        .modal-header {
            display:flex;align-items:center;justify-content:space-between;
            margin-bottom:1.2rem;
        }
        .modal-title { font-size:1.1rem;font-weight:700;color:var(--primary); }
        .modal-close {
            background:none;border:none;font-size:1.4rem;
            cursor:pointer;color:var(--text-muted);line-height:1;
        }
        .modal-close:hover { color:var(--text); }

        /* Drag and Drop Zone */
        .dropzone {
            border: 2px dashed #3498db;
            border-radius: 10px;
            background: #f7fafd;
            padding: 2.2rem;
            text-align: center;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            margin: 1.2rem 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .dropzone.dragover {
            background: #ebf5fb;
            border-color: #2980b9;
        }
        .dropzone .icon {
            font-size: 2.4rem;
            color: #3498db;
        }
        .dropzone .text {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
        }
        .dropzone .subtext {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* File list */
        .file-list {
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-height: 250px;
            overflow-y: auto;
        }
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 0.8rem;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.85rem;
        }
        .file-info {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 75%;
        }
        .file-name {
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
        }
        .file-name:hover {
            color: #3498db;
            text-decoration: underline;
        }
        .file-meta {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .file-actions {
            display: flex;
            gap: 0.3rem;
        }

        /* Progress bar */
        .progress-container {
            display: none;
            width: 100%;
            background-color: #f1f1f1;
            border-radius: 6px;
            margin-top: 0.8rem;
            overflow: hidden;
        }
        .progress-bar {
            width: 0%;
            height: 10px;
            background-color: #27ae60;
            transition: width 0.1s;
        }

        /* URL badge */
        .url-badge {
            background: #eaf2f8;
            color: #2980b9;
            padding: 0.5rem 0.8rem;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.8rem;
            word-break: break-all;
            border: 1px solid #d4e6f1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-top: 0.4rem;
        }
        .url-badge button {
            background: #fff;
            border: 1px solid #2980b9;
            color: #2980b9;
            border-radius: 4px;
            padding: 0.15rem 0.4rem;
            font-size: 0.7rem;
            cursor: pointer;
            font-family: sans-serif;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .url-badge button:hover {
            background: #2980b9;
            color: #fff;
        }

        @media (max-width:600px) {
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
        <?php if ($esAdminReal): ?>
        <a href="dashboard.php">&#x1F7E2; En vivo</a>
        <a href="usuarios.php">&#x2795; Usuarios</a>
        <?php endif; ?>
        <a href="postulantes.php">Postulantes</a>
        <a href="vigiladores.php">&#x1F464; Empleados</a>
        <a href="legajos.php" class="active">&#x1F4C1; Legajos</a>
        <a href="supervisores.php">&#x1F4BC; Supervisores</a>
        <?php if ($esAdminReal): ?>
        <a href="objetivos.php">&#x1F3AF; Objetivos</a>
        <a href="reportes.php">&#x26A0; Reportes</a>
        <?php endif; ?>
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

    <!-- Buscador -->
    <div class="card" style="margin-bottom:1rem; padding:1.2rem;">
        <div style="display:flex; flex-direction:column; gap:.4rem;">
            <label for="searchEmp" style="margin-bottom:0; font-size:0.9rem;">Buscador de empleados</label>
            <input type="text" id="searchEmp" placeholder="Buscar por nombre, DNI o Nro. legajo..." oninput="filtrarTabla()" style="max-width:450px; padding:0.6rem 0.9rem; font-size:0.9rem; margin-top:0.2rem;">
        </div>
    </div>

    <!-- Tabla de Legajos -->
    <div class="card" style="overflow-x:auto;">
        <div class="section-header">
            <span class="section-title">&#x1F4C1; Legajos de Empleados</span>
        </div>
        <div id="tablaWrap">
            <div style="padding:2rem;text-align:center;color:var(--text-muted);">
                <div class="spinner spinner-dark" style="margin:0 auto .8rem;"></div>
                Cargando...
            </div>
        </div>
    </div>

</div>

<!-- MODAL GESTIÓN DE ARCHIVOS -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <div>
                <span class="modal-title" id="modalTitle">Administrar Legajo</span>
                <div id="modalSub" style="font-size:0.85rem; color:var(--text-muted); margin-top:0.2rem;"></div>
            </div>
            <button class="modal-close" onclick="cerrarModal()">&#x2715;</button>
        </div>

        <div class="alert alert-danger" id="modalError" role="alert"><span>&#9888;</span><span id="modalErrorMsg"></span></div>

        <!-- Alerta si falta Legajo -->
        <div id="legajoFaltanteAlert" style="display:none; background:#fdf2e9; border:1px solid #f5b041; padding:0.9rem; border-radius:8px; font-size:0.88rem; color:#b7950b; margin-bottom:1.2rem;">
            <strong>El empleado no tiene número de legajo asignado.</strong><br>
            Debe asignarle un número de legajo primero para poder crear su carpeta en el servidor y publicar archivos.
        </div>

        <!-- Formulario para asignar número de legajo rápido -->
        <div class="card" style="background:#f8f9fa; border:1px solid var(--border); padding:1rem; margin-bottom:1rem;">
            <form id="formNroLegajo" onsubmit="guardarNroLegajo(event)">
                <div style="display:flex; align-items:flex-end; gap:0.8rem; flex-wrap:wrap;">
                    <div style="flex:1; min-width:180px;">
                        <label for="fNroLegajo" style="font-size:0.85rem; font-weight:700; margin-bottom:0.3rem;">Número de Legajo <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="fNroLegajo" required placeholder="Ej: 1020" style="padding: 0.5rem 0.8rem; font-size:0.9rem;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarLeg" style="height:40px; padding: 0 1.2rem;">
                        Guardar Legajo
                    </button>
                </div>
            </form>
        </div>

        <!-- Sección de subida y archivos (oculta si no hay legajo) -->
        <div id="seccionLegajoActivo" style="display:none;">
            <!-- URL Legajo -->
            <label style="font-size:0.85rem; font-weight:700; margin-bottom:0.2rem; display:block;">Ruta / URL del Legajo</label>
            <div class="url-badge">
                <span id="urlLegVal">tdvsrl.com/legajos/...</span>
                <button onclick="copiarUrlLeg()">Copiar Link</button>
            </div>

            <!-- Zona Drag and Drop -->
            <div class="dropzone" id="dropzone" onclick="document.getElementById('fUploadFiles').click()">
                <div class="icon">&#x1F4E4;</div>
                <div class="text">Arrastrá tus archivos acá o hacé clic para buscarlos</div>
                <div class="subtext">Soporta PDFs, imágenes, Word, Excel, etc. (Subida múltiple)</div>
                <input type="file" id="fUploadFiles" multiple style="display:none;" onchange="onFilesSelected(event)">
            </div>

            <!-- Progreso de subida -->
            <div class="progress-container" id="progressContainer">
                <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:var(--text-muted); margin-bottom:0.2rem;">
                    <span id="progressText">Subiendo archivos...</span>
                    <span id="progressPercent">0%</span>
                </div>
                <div class="progress-bar" id="progressBar"></div>
            </div>

            <!-- Listado de Archivos -->
            <div style="margin-top:1.5rem;">
                <label style="font-size:0.85rem; font-weight:700; border-bottom: 2px solid var(--bg); padding-bottom:0.3rem; display:block;">Archivos en el Legajo</label>
                <div class="file-list" id="fileList">
                    <p style="text-align:center; color:var(--text-muted); font-size:0.85rem; padding:1.5rem 0;">Sin archivos subidos en la carpeta.</p>
                </div>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-top:1.8rem;">
            <button class="btn btn-outline" onclick="cerrarModal()">Cerrar</button>
        </div>
    </div>
</div>

<script>
let empleados = [];
let currentEmpleadoId = 0;
let currentEmpleadoNombre = '';

// ---- Inicio -----------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    cargarLegajos();
    
    // Configurar Drag & Drop
    const dropzone = document.getElementById('dropzone');
    
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
    });
    
    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('dragover');
    });
    
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            subirArchivos(e.dataTransfer.files);
        }
    });
});

// ---- Cargar Empleados y legajos ---------------------------
async function cargarLegajos() {
    const wrap = document.getElementById('tablaWrap');
    try {
        empleados = await apiFetch('api/get_legajos.php');
        renderTabla(empleados);
    } catch (e) {
        wrap.innerHTML = '<p style="color:var(--danger);padding:1rem;">Error al cargar legajos de empleados.</p>';
    }
}

// ---- Renderizar Tabla -------------------------------------
function renderTabla(list) {
    const wrap = document.getElementById('tablaWrap');
    if (!list.length) {
        wrap.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:1.5rem;">Sin empleados registrados.</p>';
        return;
    }

    const tbody = list.map(e => {
        const nroLegajo = esc(e.nro_legajo) || '<span style="color:var(--text-muted);font-style:italic;">No asignado</span>';
        
        let filesBadge = '';
        if (!e.nro_legajo) {
            filesBadge = '<span class="badge badge-secondary">Sin carpeta</span>';
        } else if (e.folder_exists) {
            filesBadge = `<span class="badge badge-success">${e.files_count} archivos</span>`;
        } else {
            filesBadge = '<span class="badge badge-warning">No inicializada</span>';
        }

        let estadoPill = '';
        if (e.pendiente == 1) {
            estadoPill = '<span class="badge badge-pendiente">Pendiente</span>';
        } else if (e.activo == 1) {
            estadoPill = '<span class="badge badge-activo">Activo</span>';
        } else {
            estadoPill = '<span class="badge badge-inactivo">Inactivo</span>';
        }

        return `<tr data-id="${e.id_empleado}">
            <td><strong>${esc(e.nombre)}</strong><br><small style="color:var(--text-muted);">${esc(e.rol)}</small></td>
            <td>${esc(e.dni)}</td>
            <td><strong>${nroLegajo}</strong></td>
            <td>${filesBadge}</td>
            <td>${estadoPill}</td>
            <td>
                <div class="actions">
                    <button class="btn btn-outline btn-sm" onclick="abrirLegajo(${e.id_empleado}, '${esc(e.nombre)}', '${esc(e.nro_legajo)}', '${esc(e.url_leg)}')">
                        &#x1F4C2; Gestionar Archivos
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');

    wrap.innerHTML = `
        <table class="data-table" id="tablaLegajos">
            <thead><tr>
                <th>Nombre / Rol</th>
                <th>DNI / CUIL</th>
                <th>Nro. Legajo</th>
                <th>Carpeta Legajo</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr></thead>
            <tbody>${tbody}</tbody>
        </table>`;
}

// ---- Filtrar Tabla en Tiempo Real -------------------------
function filtrarTabla() {
    const q = document.getElementById('searchEmp').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#tablaLegajos tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(q)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// ---- Modal Abrir ------------------------------------------
function abrirLegajo(idEmpleado, nombre, nroLegajo, urlLeg) {
    currentEmpleadoId = idEmpleado;
    currentEmpleadoNombre = nombre;
    
    document.getElementById('modalError').classList.remove('show');
    document.getElementById('modalTitle').textContent = `Legajo de: ${nombre}`;
    document.getElementById('modalSub').textContent = `Empleado ID: ${idEmpleado}`;
    document.getElementById('fNroLegajo').value = nroLegajo === 'null' || nroLegajo === 'undefined' ? '' : nroLegajo;
    
    actualizarVistaModal(nroLegajo, urlLeg);
    
    if (nroLegajo && nroLegajo !== 'null' && nroLegajo !== '') {
        cargarArchivos(idEmpleado);
    }
    
    document.getElementById('modalOverlay').classList.add('open');
}

function actualizarVistaModal(nroLegajo, urlLeg) {
    const hasLegajo = nroLegajo && nroLegajo !== 'null' && nroLegajo !== '';
    
    if (hasLegajo) {
        document.getElementById('legajoFaltanteAlert').style.display = 'none';
        document.getElementById('seccionLegajoActivo').style.display = 'block';
        document.getElementById('urlLegVal').textContent = urlLeg || `tdvsrl.com/legajos/${nroLegajo}+${currentEmpleadoNombre.replace(/ /g, '+')}`;
    } else {
        document.getElementById('legajoFaltanteAlert').style.display = 'block';
        document.getElementById('seccionLegajoActivo').style.display = 'none';
    }
}

function cerrarModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

// Click fuera de modal cierra
document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

// ---- Guardar número de legajo rápido ---------------------
async function guardarNroLegajo(e) {
    e.preventDefault();
    const errDiv = document.getElementById('modalError');
    errDiv.classList.remove('show');
    
    const nroLegajo = document.getElementById('fNroLegajo').value.trim();
    if (!nroLegajo) {
        document.getElementById('modalErrorMsg').textContent = 'Ingrese un número de legajo válido.';
        errDiv.classList.add('show');
        return;
    }
    
    const btn = document.getElementById('btnGuardarLeg');
    btn.disabled = true;
    btn.textContent = 'Guardando...';
    
    try {
        const resp = await apiFetch('api/guardar_nro_legajo.php', 'POST', {
            id_empleado: currentEmpleadoId,
            nro_legajo: nroLegajo
        });
        
        mostrarExito('Número de legajo asignado correctamente.');
        
        // Actualizar datos locales
        const emp = empleados.find(x => x.id_empleado === currentEmpleadoId);
        if (emp) {
            emp.nro_legajo = resp.nro_legajo;
            emp.url_leg = resp.url_leg;
        }
        
        // Actualizar vista del modal
        actualizarVistaModal(resp.nro_legajo, resp.url_leg);
        
        // Cargar archivos y refrescar tabla principal
        cargarArchivos(currentEmpleadoId);
        cargarLegajos();
        
    } catch (err) {
        document.getElementById('modalErrorMsg').textContent = err.message;
        errDiv.classList.add('show');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar Legajo';
    }
}

// ---- Cargar Listado de Archivos --------------------------
async function cargarArchivos(idEmpleado) {
    const listWrap = document.getElementById('fileList');
    listWrap.innerHTML = '<div style="padding:1rem; text-align:center; color:var(--text-muted);"><div class="spinner spinner-dark" style="margin:0 auto .4rem;"></div>Cargando archivos...</div>';
    
    try {
        const res = await apiFetch(`api/get_legajo_files.php?id_empleado=${idEmpleado}`);
        
        // Actualizar URL de legajo en el badge
        if (res.url_leg) {
            document.getElementById('urlLegVal').textContent = res.url_leg;
        }
        
        if (!res.files || res.files.length === 0) {
            listWrap.innerHTML = '<p style="text-align:center; color:var(--text-muted); font-size:0.85rem; padding:1.5rem 0;">Sin archivos subidos en la carpeta.</p>';
            return;
        }
        
        listWrap.innerHTML = res.files.map(f => {
            const sizeKB = (f.size / 1024).toFixed(1);
            return `<div class="file-item">
                <div class="file-info">
                    <a href="${f.url}" target="_blank" class="file-name" title="Ver/Descargar archivo">
                        &#x1F4C4; ${esc(f.name)}
                    </a>
                    <span class="file-meta">${sizeKB} KB · Modificado: ${f.date}</span>
                </div>
                <div class="file-actions">
                    <button class="btn btn-danger btn-sm" onclick="eliminarArchivo('${esc(f.name)}')" style="padding: 0.3rem 0.6rem; font-size:0.75rem;">
                        &#x1F5D1; Eliminar
                    </button>
                </div>
            </div>`;
        }).join('');
        
    } catch (e) {
        listWrap.innerHTML = `<p style="color:var(--danger); font-size:0.85rem; padding:1rem; text-align:center;">Error al listar archivos: ${e.message}</p>`;
    }
}

// ---- Evento de selección de archivos --------------------
function onFilesSelected(e) {
    if (e.target.files.length) {
        subirArchivos(e.target.files);
    }
}

// ---- Subir Archivos vía AJAX con progreso -----------------
function subirArchivos(files) {
    const errDiv = document.getElementById('modalError');
    errDiv.classList.remove('show');
    
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    
    const formData = new FormData();
    formData.append('id_empleado', currentEmpleadoId);
    
    for (let i = 0; i < files.length; i++) {
        formData.append('archivos[]', files[i]);
    }
    
    progressContainer.style.display = 'block';
    progressBar.style.width = '0%';
    progressPercent.textContent = '0%';
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/upload_legajo.php', true);
    
    // Evento de progreso
    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            progressPercent.textContent = percent + '%';
        }
    });
    
    xhr.onload = function() {
        progressContainer.style.display = 'none';
        
        if (xhr.status === 200) {
            try {
                const resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    mostrarExito(`Se subieron ${resp.uploaded_count} archivos correctamente.`);
                    if (resp.errors && resp.errors.length > 0) {
                        document.getElementById('modalErrorMsg').innerHTML = 'Archivos subidos con algunas advertencias:<br>' + resp.errors.join('<br>');
                        errDiv.classList.add('show');
                    }
                    
                    // Refrescar listado y tabla principal
                    cargarArchivos(currentEmpleadoId);
                    cargarLegajos();
                } else {
                    document.getElementById('modalErrorMsg').textContent = resp.error || 'Error desconocido al subir archivos.';
                    errDiv.classList.add('show');
                }
            } catch (e) {
                document.getElementById('modalErrorMsg').textContent = 'Respuesta del servidor no válida.';
                errDiv.classList.add('show');
            }
        } else {
            try {
                const resp = JSON.parse(xhr.responseText);
                document.getElementById('modalErrorMsg').textContent = resp.error || 'Error del servidor al subir archivos.';
            } catch (e) {
                document.getElementById('modalErrorMsg').textContent = 'Error del servidor al procesar la subida.';
            }
            errDiv.classList.add('show');
        }
    };
    
    xhr.onerror = function() {
        progressContainer.style.display = 'none';
        document.getElementById('modalErrorMsg').textContent = 'Error de conexión de red.';
        errDiv.classList.add('show');
    };
    
    xhr.send(formData);
}

// ---- Eliminar Archivo -------------------------------------
async function eliminarArchivo(fileName) {
    if (!confirm(`¿Estás seguro que deseas eliminar el archivo "${fileName}"?`)) return;
    
    const errDiv = document.getElementById('modalError');
    errDiv.classList.remove('show');
    
    try {
        await apiFetch('api/delete_legajo_file.php', 'POST', {
            id_empleado: currentEmpleadoId,
            file_name: fileName
        });
        
        mostrarExito('Archivo eliminado correctamente.');
        cargarArchivos(currentEmpleadoId);
        cargarLegajos();
    } catch (e) {
        document.getElementById('modalErrorMsg').textContent = e.message;
        errDiv.classList.add('show');
    }
}

// ---- Copiar URL del Legajo --------------------------------
function copiarUrlLeg() {
    const urlText = document.getElementById('urlLegVal').textContent;
    navigator.clipboard.writeText(urlText).then(() => {
        const btn = document.querySelector('.url-badge button');
        const oldText = btn.textContent;
        btn.textContent = '¡Copiado!';
        btn.style.background = '#27ae60';
        btn.style.color = '#fff';
        btn.style.borderColor = '#27ae60';
        
        setTimeout(() => {
            btn.textContent = oldText;
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 1500);
    }).catch(() => {
        alert('No se pudo copiar la URL automáticamente.');
    });
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
    setTimeout(() => d.classList.remove('show'), 5000);
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
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>
