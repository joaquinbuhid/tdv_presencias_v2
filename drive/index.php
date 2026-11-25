<?php
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
$carpetaInicial = array_key_exists($_GET['c'] ?? '', CARPETAS) ? $_GET['c'] : array_key_first(CARPETAS);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Drive · TDV SRL</title>
<link rel="stylesheet" href="assets/drive.css">
</head>
<body>
<div class="app">
  <aside class="lateral">
    <div class="marca">TDV <span>Drive</span></div>
    <a class="volver" href="../admin/<?= esAdminReal() ? 'dashboard.php' : 'postulantes.php' ?>">← Volver al panel</a>
    <nav id="carpetas">
      <?php foreach (CARPETAS as $k => $nombre): ?>
        <button class="carpeta" data-carpeta="<?= htmlspecialchars($k) ?>">
          <svg viewBox="0 0 24 24"><path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
          <span class="nombre"><?= htmlspecialchars($nombre) ?></span>
          <span class="contador" data-contador="<?= htmlspecialchars($k) ?>">0</span>
        </button>
      <?php endforeach; ?>
    </nav>
    <div class="uso" id="uso"></div>
  </aside>

  <main class="principal" id="zona">
    <header class="barra">
      <h1 id="titulo"></h1>
      <div class="filtros">
        <input type="search" id="buscar" placeholder="Buscar por nombre…">
        <select id="tipo">
          <option value="">Todos</option>
          <option value="imagen">Imágenes</option>
          <option value="pdf">PDF</option>
          <option value="otro">Otros</option>
        </select>
        <input type="month" id="mes" title="Filtrar por mes de carga">
        <div class="vista">
          <button data-vista="grilla" class="activo" title="Galería">▦</button>
          <button data-vista="lista" title="Lista">☰</button>
        </div>
        <label class="btn-primario">
          + Subir
          <input type="file" id="selector" multiple hidden>
        </label>
      </div>
    </header>

    <div id="progreso" class="progreso" hidden><div></div><span></span></div>
    <section id="galeria" class="galeria grilla"></section>
    <div id="vacio" class="vacio" hidden>
      <p>No hay archivos acá todavía.</p>
      <p class="sub">Arrastrá archivos a esta ventana o usá <b>+ Subir</b>.</p>
    </div>
    <div class="soltar" id="soltar"><div>Soltá los archivos para subirlos a <b id="soltar-destino"></b></div></div>
  </main>
</div>

<div class="visor" id="visor" hidden>
  <div class="visor-barra">
    <span id="visor-nombre"></span>
    <div>
      <a id="visor-descargar" class="btn">Descargar</a>
      <button class="btn" id="visor-cerrar">Cerrar ✕</button>
    </div>
  </div>
  <button class="visor-nav ant" id="visor-ant">‹</button>
  <div class="visor-cuerpo" id="visor-cuerpo"></div>
  <button class="visor-nav sig" id="visor-sig">›</button>
</div>

<div id="avisos" class="avisos"></div>

<script>
  window.DRIVE = {
    csrf: <?= json_encode(csrf_token()) ?>,
    carpetas: <?= json_encode(CARPETAS, JSON_UNESCAPED_UNICODE) ?>,
    inicial: <?= json_encode($carpetaInicial) ?>,
    maxMB: <?= MAX_FILE_MB ?>
  };
</script>
<script src="assets/drive.js"></script>
</body>
</html>
