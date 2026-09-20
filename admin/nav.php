<?php
require_once __DIR__ . '/auth.php';

$navActual  = basename($_SERVER['SCRIPT_NAME'] ?? '');
$navNombre  = $_SESSION['nombre_completo'] ?? 'Administrador';
$navEsAdmin = esAdminReal();

$navItems = [
    ['archivo' => 'dashboard.php',    'texto' => "\u{1F7E2} En vivo",      'soloAdmin' => true],
    ['archivo' => 'usuarios.php',     'texto' => "\u{2795} Usuarios",      'soloAdmin' => true],
    ['archivo' => 'postulantes.php',  'texto' => 'Postulantes',            'soloAdmin' => false],
    ['archivo' => 'vigiladores.php',  'texto' => "\u{1F464} Empleados",    'soloAdmin' => false],
    ['archivo' => 'legajos.php',      'texto' => "\u{1F4C1} Legajos",      'soloAdmin' => false],
    ['archivo' => 'supervisores.php', 'texto' => "\u{1F4BC} Supervisores", 'soloAdmin' => false],
    ['archivo' => 'objetivos.php',    'texto' => "\u{1F3AF} Objetivos",    'soloAdmin' => true],
    ['archivo' => 'reportes.php',     'texto' => "\u{26A0} Reportes",      'soloAdmin' => true, 'badge' => true],
    ['archivo' => 'liquidacion.php',  'texto' => 'Horas',                  'soloAdmin' => false],
    ['archivo' => 'enviar_mails.php', 'texto' => 'Mails',                  'soloAdmin' => false],
];
?>
<nav class="admin-nav">
    <div class="brand">&#x1F6E1; TDV Seguridad</div>
    <div class="nav-links">
        <?php foreach ($navItems as $item): ?>
            <?php if ($item['soloAdmin'] && !$navEsAdmin) continue; ?>
            <a href="<?= $item['archivo'] ?>"<?= $navActual === $item['archivo'] ? ' class="active"' : '' ?>>
                <?= htmlspecialchars($item['texto']) ?>
                <?php if (!empty($item['badge'])): ?>
                    <span class="nav-badge" id="navBadge" style="display:none;">0</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="nav-user">
        <strong><?= htmlspecialchars($navNombre) ?></strong>
        <a href="../api/logout.php">Salir</a>
    </div>
</nav>
