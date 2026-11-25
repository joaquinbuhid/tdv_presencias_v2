(() => {
  const D = window.DRIVE;
  const $ = (s) => document.querySelector(s);
  const estado = { carpeta: D.inicial, archivos: [], vista: localStorageGet('drive_vista') || 'grilla', visorIdx: -1 };

  function localStorageGet(k) { try { return localStorage.getItem(k); } catch { return null; } }
  function localStorageSet(k, v) { try { localStorage.setItem(k, v); } catch {} }

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const tam = (b) => b < 1024 ? b + ' B' : b < 1048576 ? (b / 1024).toFixed(0) + ' KB' : (b / 1048576).toFixed(1) + ' MB';
  const fecha = (f) => new Date(f.replace(' ', 'T')).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });

  function aviso(msg, error = false) {
    const el = document.createElement('div');
    el.className = 'aviso' + (error ? ' error' : '');
    el.textContent = msg;
    $('#avisos').appendChild(el);
    setTimeout(() => el.remove(), error ? 7000 : 3500);
  }

  async function api(accion, params = {}, body = null) {
    const url = 'api.php?' + new URLSearchParams({ accion, ...params });
    const r = await fetch(url, body ? { method: 'POST', body } : {});
    const data = await r.json().catch(() => ({ error: 'Respuesta inválida del servidor' }));
    if (!r.ok && data.error) throw new Error(data.error);
    return data;
  }

  // ---------- Carpetas y resumen ----------
  async function cargarResumen() {
    try {
      const r = await api('resumen');
      let total = 0, bytes = 0;
      for (const [k, v] of Object.entries(r)) {
        const c = document.querySelector(`[data-contador="${k}"]`);
        if (c) c.textContent = v.cantidad;
        total += v.cantidad; bytes += v.bytes;
      }
      $('#uso').textContent = `${total} archivos · ${tam(bytes)}`;
    } catch (e) { /* silencioso */ }
  }

  function elegirCarpeta(c) {
    estado.carpeta = c;
    document.querySelectorAll('.carpeta').forEach((b) => b.classList.toggle('activa', b.dataset.carpeta === c));
    $('#titulo').textContent = D.carpetas[c];
    $('#soltar-destino').textContent = D.carpetas[c];
    history.replaceState(null, '', '?c=' + c);
    cargar();
  }

  // ---------- Listado ----------
  let temporizador;
  async function cargar() {
    try {
      estado.archivos = await api('listar', {
        carpeta: estado.carpeta, q: $('#buscar').value.trim(), tipo: $('#tipo').value, mes: $('#mes').value,
      });
      pintar();
    } catch (e) { aviso(e.message, true); }
  }

  function previa(a) {
    if (a.miniatura) return `<img loading="lazy" src="${esc(a.miniatura)}" alt="">`;
    return `<div class="icono ${esc(a.extension)}">${esc(a.extension)}</div>`;
  }

  function pintar() {
    const g = $('#galeria');
    g.className = 'galeria ' + estado.vista;
    document.querySelectorAll('.vista button').forEach((b) => b.classList.toggle('activo', b.dataset.vista === estado.vista));
    $('#vacio').hidden = estado.archivos.length > 0;
    g.innerHTML = estado.archivos.map((a, i) => `
      <article class="tarjeta" data-i="${i}">
        <div class="previa">${previa(a)}</div>
        <div class="info">
          <div class="nombre" title="${esc(a.nombre)}">${esc(a.nombre)}</div>
          <div class="meta">${fecha(a.fecha)} · ${tam(a.tamanio)}</div>
        </div>
        <div class="acciones">
          <a href="${esc(a.descargar)}" title="Descargar" data-no-abrir>⤓</a>
          <button class="renombrar" title="Renombrar" data-no-abrir>✎</button>
          <button class="borrar" title="Eliminar" data-no-abrir>🗑</button>
        </div>
      </article>`).join('');
  }

  $('#galeria').addEventListener('click', async (ev) => {
    const t = ev.target.closest('.tarjeta'); if (!t) return;
    const a = estado.archivos[+t.dataset.i];
    if (ev.target.closest('.renombrar')) return renombrar(a);
    if (ev.target.closest('.borrar')) return eliminar(a);
    if (ev.target.closest('[data-no-abrir]')) return;
    abrirVisor(+t.dataset.i);
  });

  async function renombrar(a) {
    const nuevo = prompt('Nuevo nombre:', a.nombre);
    if (!nuevo || nuevo === a.nombre) return;
    const fd = new FormData(); fd.append('id', a.id); fd.append('nombre', nuevo); fd.append('csrf', D.csrf);
    try { await api('renombrar', {}, fd); aviso('Nombre actualizado'); cargar(); } catch (e) { aviso(e.message, true); }
  }

  async function eliminar(a) {
    if (!confirm(`¿Eliminar "${a.nombre}"?`)) return;
    const fd = new FormData(); fd.append('id', a.id); fd.append('csrf', D.csrf);
    try { await api('eliminar', {}, fd); aviso('Archivo eliminado'); cargar(); cargarResumen(); } catch (e) { aviso(e.message, true); }
  }

  // ---------- Subida ----------
  function subir(lista) {
    const archivos = [...lista];
    if (!archivos.length) return;
    const grandes = archivos.filter((f) => f.size > D.maxMB * 1048576);
    grandes.forEach((f) => aviso(`${f.name}: supera ${D.maxMB} MB`, true));
    const validos = archivos.filter((f) => f.size <= D.maxMB * 1048576);
    if (!validos.length) return;

    const fd = new FormData();
    fd.append('carpeta', estado.carpeta); fd.append('csrf', D.csrf);
    validos.forEach((f) => fd.append('archivos[]', f));

    const barra = $('#progreso'), relleno = barra.querySelector('div'), txt = barra.querySelector('span');
    barra.hidden = false; relleno.style.width = '0';
    txt.textContent = `Subiendo ${validos.length} archivo(s) a ${D.carpetas[estado.carpeta]}…`;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api.php?accion=subir');
    xhr.upload.onprogress = (e) => { if (e.lengthComputable) relleno.style.width = (e.loaded / e.total * 100) + '%'; };
    xhr.onload = () => {
      barra.hidden = true;
      let r = {}; try { r = JSON.parse(xhr.responseText); } catch {}
      if (r.subidos?.length) aviso(`${r.subidos.length} archivo(s) subido(s)`);
      (r.errores || []).forEach((m) => aviso(m, true));
      if (r.error) aviso(r.error, true);
      cargar(); cargarResumen();
    };
    xhr.onerror = () => { barra.hidden = true; aviso('Error de conexión al subir', true); };
    xhr.send(fd);
  }

  $('#selector').addEventListener('change', (e) => { subir(e.target.files); e.target.value = ''; });

  let arrastres = 0;
  window.addEventListener('dragenter', (e) => { if (e.dataTransfer?.types.includes('Files')) { arrastres++; $('#soltar').classList.add('activo'); } });
  window.addEventListener('dragleave', () => { if (--arrastres <= 0) { arrastres = 0; $('#soltar').classList.remove('activo'); } });
  window.addEventListener('dragover', (e) => e.preventDefault());
  window.addEventListener('drop', (e) => {
    e.preventDefault(); arrastres = 0; $('#soltar').classList.remove('activo');
    if (e.dataTransfer?.files?.length) subir(e.dataTransfer.files);
  });

  // ---------- Visor ----------
  function abrirVisor(i) {
    estado.visorIdx = i;
    const a = estado.archivos[i];
    $('#visor-nombre').textContent = a.nombre;
    $('#visor-descargar').href = a.descargar;
    const c = $('#visor-cuerpo');
    if (a.tipo === 'imagen') c.innerHTML = `<img src="${esc(a.url)}" alt="">`;
    else if (a.tipo === 'pdf') c.innerHTML = `<iframe src="${esc(a.url)}"></iframe>`;
    else c.innerHTML = `<div class="sin-previa"><div class="icono ${esc(a.extension)}" style="margin:0 auto 14px">${esc(a.extension)}</div>
      <p>Este tipo de archivo no tiene vista previa.</p><a class="btn" href="${esc(a.descargar)}">Descargar</a></div>`;
    $('#visor').hidden = false;
  }
  const cerrarVisor = () => { $('#visor').hidden = true; $('#visor-cuerpo').innerHTML = ''; estado.visorIdx = -1; };
  const mover = (d) => { if (estado.visorIdx < 0) return; const n = estado.archivos.length; abrirVisor((estado.visorIdx + d + n) % n); };

  $('#visor-cerrar').onclick = cerrarVisor;
  $('#visor-ant').onclick = () => mover(-1);
  $('#visor-sig').onclick = () => mover(1);
  $('#visor').addEventListener('click', (e) => { if (e.target.id === 'visor-cuerpo') cerrarVisor(); });
  document.addEventListener('keydown', (e) => {
    if ($('#visor').hidden) return;
    if (e.key === 'Escape') cerrarVisor();
    if (e.key === 'ArrowLeft') mover(-1);
    if (e.key === 'ArrowRight') mover(1);
  });

  // ---------- Filtros ----------
  $('#buscar').addEventListener('input', () => { clearTimeout(temporizador); temporizador = setTimeout(cargar, 250); });
  $('#tipo').addEventListener('change', cargar);
  $('#mes').addEventListener('change', cargar);
  document.querySelectorAll('.vista button').forEach((b) => b.addEventListener('click', () => {
    estado.vista = b.dataset.vista; localStorageSet('drive_vista', estado.vista); pintar();
  }));
  document.querySelectorAll('.carpeta').forEach((b) => b.addEventListener('click', () => elegirCarpeta(b.dataset.carpeta)));

  elegirCarpeta(estado.carpeta);
  cargarResumen();
})();
