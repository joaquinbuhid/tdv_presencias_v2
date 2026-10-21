<?php
require_once __DIR__ . '/auth.php';

$navActual  = basename($_SERVER['SCRIPT_NAME'] ?? '');
$navNombre  = $_SESSION['nombre_completo'] ?? 'Administrador';
$navEsAdmin = esAdminReal();

$navGrupos = [
    'Operación' => [
        ['archivo' => 'dashboard.php',    'icono' => "\u{1F7E2}", 'texto' => 'En vivo',      'soloAdmin' => true],
        ['archivo' => 'objetivos.php',    'icono' => "\u{1F3AF}", 'texto' => 'Objetivos',    'soloAdmin' => true],
        ['archivo' => 'supervisores.php', 'icono' => "\u{1F4BC}", 'texto' => 'Supervisores', 'soloAdmin' => false],
        ['archivo' => 'liquidacion.php',  'icono' => "\u{23F1}",  'texto' => 'Horas',        'soloAdmin' => false],
        ['archivo' => 'reportes.php',     'icono' => "\u{26A0}",  'texto' => 'Reportes',     'soloAdmin' => true, 'badge' => true],
    ],
    'Personal' => [
        ['archivo' => 'vigiladores.php',  'icono' => "\u{1F464}", 'texto' => 'Empleados',    'soloAdmin' => false],
        ['archivo' => 'legajos.php',      'icono' => "\u{1F4C1}", 'texto' => 'Legajos',      'soloAdmin' => false],
        ['archivo' => 'usuarios.php',     'icono' => "\u{2795}",  'texto' => 'Usuarios',     'soloAdmin' => true],
    ],
    'Selección' => [
        ['archivo' => 'postulantes.php',  'icono' => "\u{1F4CB}", 'texto' => 'Postulantes',  'soloAdmin' => false],
        ['archivo' => 'entrevistas.php',  'icono' => "\u{1F4DD}", 'texto' => 'Entrevistas',  'soloAdmin' => false],
        ['archivo' => 'historial_entrevistas.php', 'icono' => "\u{2705}", 'texto' => 'Contratados', 'soloAdmin' => false],
    ],
    'Sistemas' => [
        ['archivo' => 'dispositivos.php', 'icono' => "\u{1F4BB}", 'texto' => 'Dispositivos', 'soloAdmin' => true],
        ['archivo' => 'tickets.php',      'icono' => "\u{1F3AB}", 'texto' => 'Tickets',      'soloAdmin' => false],
    ],
    'Comunicación' => [
        ['archivo' => 'enviar_mails.php', 'icono' => "\u{2709}",  'texto' => 'Mails',        'soloAdmin' => false],
    ],
];
?>
<header class="side-topbar">
    <button type="button" class="side-toggle" id="sideToggle" aria-label="Abrir menú" aria-controls="sideNav" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
    <div class="brand">&#x1F6E1; TDV Seguridad</div>
</header>

<div class="side-backdrop" id="sideBackdrop"></div>

<nav class="side-nav" id="sideNav" aria-label="Menú principal">
    <div class="brand">&#x1F6E1; TDV Seguridad</div>

    <div class="side-links">
        <?php foreach ($navGrupos as $grupo => $items): ?>
            <?php $items = array_filter($items, fn($i) => !$i['soloAdmin'] || $navEsAdmin); ?>
            <?php if (!$items) continue; ?>
            <div class="side-group"><?= htmlspecialchars($grupo) ?></div>
            <?php foreach ($items as $item): ?>
                <a href="<?= $item['archivo'] ?>"<?= $navActual === $item['archivo'] ? ' class="active" aria-current="page"' : '' ?>>
                    <span class="side-icon"><?= $item['icono'] ?></span>
                    <span class="side-text"><?= htmlspecialchars($item['texto']) ?></span>
                    <?php if (!empty($item['badge'])): ?>
                        <span class="nav-badge" id="navBadge" style="display:none;">0</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <div class="side-user">
        <strong><?= htmlspecialchars($navNombre) ?></strong>
        <a href="../api/logout.php">Cerrar sesión</a>
    </div>
</nav>

<script>
(function () {
    const nav      = document.getElementById('sideNav');
    const toggle   = document.getElementById('sideToggle');
    const backdrop = document.getElementById('sideBackdrop');

    function setOpen(open) {
        document.body.classList.toggle('side-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    }

    toggle.addEventListener('click', () => setOpen(!document.body.classList.contains('side-open')));
    backdrop.addEventListener('click', () => setOpen(false));
    nav.addEventListener('click', e => { if (e.target.closest('a')) setOpen(false); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') setOpen(false); });
})();
</script>
