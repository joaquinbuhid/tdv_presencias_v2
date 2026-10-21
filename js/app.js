/* ============================================================
   TDV - Dashboard principal
   ============================================================ */

const MAX_LOC_WAIT_MS      = 20000; // Esperar hasta 20 segundos si no se llega a la precisión buscada
const TARGET_ACCURACY_M    = 50;    // Precisión objetivo: 50 metros
const LECTURA_VIGENCIA_MS  = 30000; // Lecturas del GPS precalentado que se aceptan al marcar
const PRECISION_MUY_BAJA_M = 500;   // Por encima de esto, la señal se considera muy débil...
const AVISO_SENAL_DEBIL_MS = 25000; // ...si sigue así después de este tiempo buscando
const API_BASE             = window.TDV_API_BASE || '';

// ---- Inicialización ----------------------------------------

document.addEventListener('DOMContentLoaded', () => {
    iniciarGps();
    Promise.all([cargarTipos(), cargarEstado()]).then(sugerirTipo);

    document.getElementById('formNovedad').addEventListener('submit', onSubmit);
});

// ---- Marca sugerida -----------------------------------------
// Con una entrada abierta se sugiere Salida; si no, Entrada.
// Solo se preselecciona si el usuario todavía no eligió un tipo.

async function sugerirTipo() {
    const sel = document.getElementById('tipoNovedad');
    let hint = document.getElementById('tipoHint');
    if (!hint) {
        hint = document.createElement('p');
        hint.id = 'tipoHint';
        hint.style.cssText = 'font-size:.9rem;color:var(--text-muted);margin-top:.45rem;';
        sel.insertAdjacentElement('afterend', hint);
    }

    let turno;
    try {
        turno = await apiGet(API_BASE + 'api/get_turno_abierto.php');
    } catch (e) {
        hint.textContent = '';
        return;
    }

    const sugerido = turno.abierta ? 'Salida' : 'Entrada';
    const opt = [...sel.options].find(o => o.textContent === sugerido);
    if (opt && !sel.value) sel.value = opt.value;

    hint.textContent = turno.abierta
        ? `Tenés una entrada abierta desde ${turno.desde}. Al terminar el turno marcá la Salida.`
        : 'No tenés un turno abierto. Para empezar, marcá la Entrada.';
}

// ---- Tipos de novedad --------------------------------------

async function cargarTipos() {
    try {
        const tipos = await apiGet(API_BASE + 'api/get_tipos.php');
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
        const registros = await apiGet(API_BASE + 'api/get_estado.php');
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
    gpsUiLibreDesde = Infinity;
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
        gpsUiLibreDesde = Date.now() + 8000;
        return;
    }

    mostrarProgress(false);
    document.getElementById('progressBar').style.width = '100%';
    setLocStatus('ok', `Ubicación obtenida (precisión: ${Math.round(posicion.coords.accuracy)} m)`);
    setBoton(btn, true, 'Enviando...');

    const observaciones = document.getElementById('observaciones').value.trim();

    try {
        const selObj  = document.getElementById('objetivoNovedad');
        const payload = {
            tipo_id:      parseInt(tipoId),
            observaciones,
            lat: posicion.coords.latitude,
            lng: posicion.coords.longitude,
            precision: Math.round(posicion.coords.accuracy),
        };
        if (selObj && selObj.value) payload.objetivo_id = parseInt(selObj.value);
        const resp = await apiPost(API_BASE + 'api/registrar_novedad.php', payload);

        mostrarExito(`${resp.mensaje} — ${resp.fecha} ${resp.hora} hs`);
        document.getElementById('observaciones').value = '';
        document.getElementById('tipoNovedad').value   = '';
        setLocStatus('ok', `Registrado a ${resp.distancia} m del objetivos.`);
        await cargarEstado();
        await sugerirTipo();

    } catch (err) {
        mostrarError(err.message);
        setLocStatus('error', 'No se pudo registrar la novedad.');
    }

    setBoton(btn, false, 'Confirmar asistencia');
    // Dejar a la vista el resultado unos segundos antes de volver a mostrar la señal.
    gpsUiLibreDesde = Date.now() + 8000;
}

// ---- GPS precalentado ---------------------------------------
// El GPS se enciende al abrir la página (y se apaga si pasa a segundo plano),
// así al marcar ya tiene señal. Los teléfonos viejos tardan en engancharse.

let gpsWatchId    = null;
let gpsLecturas   = [];   // { pos, t } de los últimos LECTURA_VIGENCIA_MS
let gpsInicio     = 0;
let gpsUiLibreDesde = 0;  // mientras se marca (y un rato después) no se pisa el estado

function iniciarGps() {
    if (!navigator.geolocation || gpsWatchId !== null) return;
    gpsInicio = Date.now();
    if (Date.now() >= gpsUiLibreDesde) setLocStatus('active', 'Buscando señal GPS...');

    gpsWatchId = navigator.geolocation.watchPosition(
        (pos) => {
            guardarLectura(pos);
            mostrarSenalGps();
        },
        (err) => {
            // Sin señal momentánea el watch sigue intentando; solo se corta si no hay permiso.
            if (err.code === 1) {
                detenerGps();
                if (Date.now() >= gpsUiLibreDesde) {
                    setLocStatus('error', 'Permiso de ubicación denegado. Habilite la ubicación en su dispositivo.');
                }
            }
        },
        { enableHighAccuracy: true, maximumAge: 0 }
    );
}

function detenerGps() {
    if (gpsWatchId === null) return;
    navigator.geolocation.clearWatch(gpsWatchId);
    gpsWatchId = null;
}

function guardarLectura(pos) {
    const ahora = Date.now();
    gpsLecturas.push({ pos, t: ahora });
    gpsLecturas = gpsLecturas.filter(l => ahora - l.t <= LECTURA_VIGENCIA_MS);
}

function mejorLecturaReciente() {
    const ahora = Date.now();
    return gpsLecturas
        .filter(l => ahora - l.t <= LECTURA_VIGENCIA_MS)
        .reduce((mejor, l) => (!mejor || l.pos.coords.accuracy < mejor.coords.accuracy) ? l.pos : mejor, null);
}

function mostrarSenalGps() {
    if (Date.now() < gpsUiLibreDesde) return;
    const pos = mejorLecturaReciente();
    if (!pos) return;

    const acc = Math.round(pos.coords.accuracy);
    if (acc <= TARGET_ACCURACY_M) {
        setLocStatus('ok', `Señal GPS lista (±${acc} m). Ya podés marcar.`);
    } else if (acc >= PRECISION_MUY_BAJA_M && Date.now() - gpsInicio > AVISO_SENAL_DEBIL_MS) {
        setLocStatus('error', `Señal GPS muy débil (±${acc} m). Si estás bajo techo salí al aire libre, y revisá que la ubicación del teléfono esté en "alta precisión" y no en ahorro de batería.`);
    } else {
        setLocStatus('active', `Mejorando señal GPS... (±${acc} m). Esperá a que se ponga en verde.`);
    }
}

document.addEventListener('visibilitychange', () => {
    if (document.hidden) detenerGps();
    else iniciarGps();
});

// ---- Geolocalización con espera de precisión ---------------
// Arranca con la mejor lectura del precalentado: si ya es buena se usa al instante;
// si no, sigue escuchando hasta MAX_LOC_WAIT_MS y se queda con la mejor.

function obtenerUbicacionPrecisa(onProgress) {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Geolocalización no disponible en este dispositivo.'));
            return;
        }

        let mejorPosicion = mejorLecturaReciente();
        if (mejorPosicion && mejorPosicion.coords.accuracy <= TARGET_ACCURACY_M) {
            resolve(mejorPosicion);
            return;
        }

        let watchId = null;
        let elapsed = 0;
        let timer   = null;

        const tick = setInterval(() => {
            elapsed += 500;
            if (onProgress) {
                const acc = mejorPosicion ? Math.round(mejorPosicion.coords.accuracy) : null;
                onProgress(elapsed, MAX_LOC_WAIT_MS, acc);
            }
        }, 500);

        const finalizar = (pos, error) => {
            clearTimeout(timer);
            clearInterval(tick);
            if (watchId !== null) navigator.geolocation.clearWatch(watchId);
            if (pos) resolve(pos);
            else reject(new Error(error || 'No se pudo obtener la ubicación. Verifique que el GPS esté activo.'));
        };

        timer = setTimeout(() => finalizar(mejorPosicion), MAX_LOC_WAIT_MS);

        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                guardarLectura(pos);
                if (!mejorPosicion || pos.coords.accuracy < mejorPosicion.coords.accuracy) {
                    mejorPosicion = pos;
                }
                // Resolver antes si ya alcanzamos precisión suficiente
                if (pos.coords.accuracy <= TARGET_ACCURACY_M) {
                    finalizar(mejorPosicion);
                }
            },
            (err) => {
                // Si ya hay una lectura (del precalentado o de esta espera), usar esa.
                if (mejorPosicion && err.code !== 1) {
                    finalizar(mejorPosicion);
                    return;
                }
                const msgs = {
                    1: 'Permiso de ubicación denegado. Habilite la ubicación en su dispositivo.',
                    2: 'No se pudo obtener la ubicación. Verifique que el GPS esté activo.',
                    3: 'Tiempo de espera agotado al obtener la ubicación.'
                };
                finalizar(null, msgs[err.code] || 'Error al obtener ubicación.');
            },
            { enableHighAccuracy: true, maximumAge: 0 }
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

