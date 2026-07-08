/* ============================================================
   TDV - Dashboard principal
   ============================================================ */

const MAX_LOC_WAIT_MS   = 8000;  // Esperar hasta 8 segundos para obtener ubicación
const TARGET_ACCURACY_M = 50;    // Precisión objetivos: 50 metros

// ---- Inicialización ----------------------------------------

document.addEventListener('DOMContentLoaded', () => {
    cargarTipos();
    cargarEstado();

    document.getElementById('formNovedad').addEventListener('submit', onSubmit);
});

// ---- Tipos de novedad --------------------------------------

async function cargarTipos() {
    try {
        const tipos = await apiGet('api/get_tipos.php');
        const sel   = document.getElementById('tipoNovedad');
        tipos.forEach(t => {
            const opt = document.createElement('option');
            opt.value       = t.id_tipo;
            opt.textContent = t.nombre;
            if (t.descripcion) opt.title = t.descripcion;
            sel.appendChild(opt);
        });
    } catch (e) {
        mostrarError('No se pudieron cargar los tipos de novedad.');
    }
}

// ---- Estado del día ----------------------------------------

async function cargarEstado() {
    const cont = document.getElementById('estadoContent');
    try {
        const registros = await apiGet('api/get_estado.php');
        if (registros.length === 0) {
            cont.innerHTML = '<p class="empty-msg">Sin registros hoy.</p>';
            return;
        }
        const ul = document.createElement('ul');
        ul.className = 'estado-list';
        registros.forEach(r => {
            const li   = document.createElement('li');
            const tag  = tagClass(r.tipo_nombre);
            li.innerHTML = `
                <span class="tag ${tag}">${escHtml(r.tipo_nombre)}</span>
                <span>${escHtml(r.observaciones || '—')}</span>
                <span class="estado-hora">${escHtml(r.hora.substr(0,5))} hs</span>
            `;
            ul.appendChild(li);
        });
        cont.innerHTML = '';
        cont.appendChild(ul);
    } catch (e) {
        cont.innerHTML = '<p class="empty-msg" style="color:var(--danger)">Error al cargar registros.</p>';
    }
}

function tagClass(tipo) {
    const t = tipo.toLowerCase();
    if (t.includes('entrada'))   return 'tag-entrada';
    if (t.includes('salida'))    return 'tag-salida';
    if (t.includes('incidente')) return 'tag-incidente';
    if (t.includes('novedad'))   return 'tag-novedad';
    return 'tag-default';
}

// ---- Envío del formulario -----------------------------------

async function onSubmit(e) {
    e.preventDefault();

    ocultarMensajes();

    const tipoId = document.getElementById('tipoNovedad').value;
    if (!tipoId) {
        mostrarError('Seleccione un tipo de novedad.');
        return;
    }

    const btn = document.getElementById('btnRegistrar');
    setBoton(btn, true, 'Obteniendo ubicación...');
    setLocStatus('active', null);
    mostrarProgress(true);

    let posicion;
    try {
        posicion = await obtenerUbicacionPrecisa((elapsed, total, acc) => {
            const pct = Math.min(100, Math.round((elapsed / total) * 100));
            document.getElementById('progressBar').style.width = pct + '%';
            const accTxt = acc !== null ? ` (precisión: ${acc} m)` : '';
            setLocStatus('active', `Obteniendo ubicación...${accTxt}`);
        });
    } catch (err) {
        mostrarProgress(false);
        setLocStatus('error', err.message);
        setBoton(btn, false, 'Confirmar asistencia');
        mostrarError(err.message);
        return;
    }

    mostrarProgress(false);
    document.getElementById('progressBar').style.width = '100%';
    setLocStatus('ok', `Ubicación obtenida (precisión: ${Math.round(posicion.coords.accuracy)} m)`);
    setBoton(btn, true, 'Enviando...');

    const observaciones = document.getElementById('observaciones').value.trim();

    try {
        const resp = await apiPost('api/registrar_novedad.php', {
            tipo_id:      parseInt(tipoId),
            observaciones,
            lat: posicion.coords.latitude,
            lng: posicion.coords.longitude,
        });

        mostrarExito(`${resp.mensaje} — ${resp.fecha} ${resp.hora} hs`);
        document.getElementById('observaciones').value = '';
        document.getElementById('tipoNovedad').value   = '';
        setLocStatus('ok', `Registrado a ${resp.distancia} m del objetivos.`);
        await cargarEstado();

    } catch (err) {
        mostrarError(err.message);
        setLocStatus('error', 'No se pudo registrar la novedad.');
    }

    setBoton(btn, false, 'Confirmar asistencia');
}

// ---- Geolocalización con espera de precisión ---------------

function obtenerUbicacionPrecisa(onProgress) {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Geolocalización no disponible en este dispositivo.'));
            return;
        }

        let mejorPosicion = null;
        let watchId       = null;
        let elapsed       = 0;

        const tick = setInterval(() => {
            elapsed += 500;
            if (onProgress) {
                const acc = mejorPosicion ? Math.round(mejorPosicion.coords.accuracy) : null;
                onProgress(elapsed, MAX_LOC_WAIT_MS, acc);
            }
        }, 500);

        const finalizar = (pos) => {
            clearInterval(tick);
            if (watchId !== null) navigator.geolocation.clearWatch(watchId);
            if (pos) resolve(pos);
            else reject(new Error('No se pudo obtener la ubicación. Verifique que el GPS esté activo.'));
        };

        const timer = setTimeout(() => finalizar(mejorPosicion), MAX_LOC_WAIT_MS);

        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                if (!mejorPosicion || pos.coords.accuracy < mejorPosicion.coords.accuracy) {
                    mejorPosicion = pos;
                }
                // Resolver antes si ya alcanzamos precisión suficiente
                if (pos.coords.accuracy <= TARGET_ACCURACY_M) {
                    clearTimeout(timer);
                    finalizar(mejorPosicion);
                }
            },
            (err) => {
                clearTimeout(timer);
                clearInterval(tick);
                if (watchId !== null) navigator.geolocation.clearWatch(watchId);
                const msgs = {
                    1: 'Permiso de ubicación denegado. Habilite la ubicación en su dispositivo.',
                    2: 'No se pudo obtener la ubicación. Verifique que el GPS esté activo.',
                    3: 'Tiempo de espera agotado al obtener la ubicación.'
                };
                reject(new Error(msgs[err.code] || 'Error al obtener ubicación.'));
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: MAX_LOC_WAIT_MS }
        );
    });
}

// ---- Utilidades UI -----------------------------------------

function setBoton(btn, disabled, texto) {
    btn.disabled = disabled;
    document.getElementById('btnText').textContent = texto;
    document.getElementById('btnIcon').innerHTML   = disabled
        ? '<span class="spinner" style="width:16px;height:16px;border-width:2px;"></span>'
        : '&#x2705;';
}

function setLocStatus(estado, msg) {
    const el = document.getElementById('locStatus');
    el.className = 'loc-status ' + (estado || '');
    if (msg !== null) {
        const icons = { active: '&#x1F4CD;', ok: '&#9989;', error: '&#9888;' };
        el.innerHTML = `<span>${icons[estado] || '&#x1F4F1;'}</span><span>${escHtml(msg)}</span>`;
    }
}

function mostrarProgress(visible) {
    document.getElementById('progressWrap').style.display = visible ? 'block' : 'none';
    if (!visible) document.getElementById('progressBar').style.width = '0%';
}

function mostrarError(msg) {
    const d = document.getElementById('regError');
    document.getElementById('regErrorMsg').textContent = msg;
    d.classList.add('show');
    d.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function mostrarExito(msg) {
    const d = document.getElementById('regSuccess');
    document.getElementById('regSuccessMsg').textContent = msg;
    d.classList.add('show');
    d.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function ocultarMensajes() {
    document.getElementById('regError').classList.remove('show');
    document.getElementById('regSuccess').classList.remove('show');
}

// ---- Utilidades API ----------------------------------------

async function apiGet(url) {
    const res = await fetch(url);
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Error del servidor');
    return data;
}

async function apiPost(url, payload) {
    const res = await fetch(url, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload)
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Error del servidor');
    return data;
}

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

