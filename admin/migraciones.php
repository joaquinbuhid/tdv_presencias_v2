<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
requireAdminRealPage();

$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS migraciones (
    nombre varchar(190) NOT NULL,
    aplicada_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$dir = __DIR__ . '/../sql/migrations';
$archivos = array_map('basename', glob($dir . '/*.sql') ?: []);
sort($archivos);
$aplicadas = $db->query("SELECT nombre, aplicada_en FROM migraciones")->fetchAll(PDO::FETCH_KEY_PAIR);
$pendientes = array_values(array_diff($archivos, array_keys($aplicadas)));

$resultados = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($pendientes as $nombre) {
        $sql = file_get_contents($dir . '/' . $nombre);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $sentencias = array_filter(array_map('trim', explode(';', $sql)));
        try {
            foreach ($sentencias as $sentencia) {
                $db->exec($sentencia);
            }
            $db->prepare("INSERT INTO migraciones (nombre) VALUES (?)")->execute([$nombre]);
            $resultados[] = ['nombre' => $nombre, 'ok' => true, 'msg' => 'Aplicada'];
        } catch (Throwable $e) {
            $resultados[] = ['nombre' => $nombre, 'ok' => false, 'msg' => $e->getMessage()];
            break;
        }
    }
    $aplicadas = $db->query("SELECT nombre, aplicada_en FROM migraciones")->fetchAll(PDO::FETCH_KEY_PAIR);
    $pendientes = array_values(array_diff($archivos, array_keys($aplicadas)));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDV - Migraciones</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <style>
        .mig-list { list-style:none;padding:0;margin:0; }
        .mig-list li { padding:.6rem 0;border-bottom:1px solid var(--bg);display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;font-size:.9rem; }
        .mig-list code { word-break:break-all; }
        .pill { border-radius:20px;padding:.15rem .65rem;font-size:.78rem;font-weight:600;white-space:nowrap; }
        .pill-ok { background:#eafaf1;color:#1e8449; }
        .pill-pend { background:#fef9e7;color:#9a7d0a; }
        .msg-err { color:#c0392b;font-size:.82rem;width:100%; }
    </style>
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

<div style="max-width:800px;margin:0 auto;padding:1.2rem 1rem 2rem;">

    <?php foreach ($resultados as $r): ?>
        <div class="alert <?= $r['ok'] ? 'alert-success' : 'alert-danger' ?> show" role="alert">
            <span><?= $r['ok'] ? '&#9989;' : '&#9888;' ?></span>
            <span><code><?= htmlspecialchars($r['nombre']) ?></code>: <?= htmlspecialchars($r['msg']) ?></span>
        </div>
    <?php endforeach; ?>

    <div class="card">
        <div class="card-title">&#x1F6E0; Migraciones de base de datos</div>
        <p style="font-size:.9rem;color:var(--text-muted);margin-bottom:1rem;">
            Aplica sobre esta base los cambios de <code>sql/migrations/</code> que todavía no se ejecutaron.
        </p>

        <?php if (!$archivos): ?>
            <p>No hay archivos de migración.</p>
        <?php else: ?>
            <ul class="mig-list">
                <?php foreach ($archivos as $nombre): ?>
                    <li>
                        <code><?= htmlspecialchars($nombre) ?></code>
                        <?php if (isset($aplicadas[$nombre])): ?>
                            <span class="pill pill-ok">Aplicada <?= htmlspecialchars($aplicadas[$nombre]) ?></span>
                        <?php else: ?>
                            <span class="pill pill-pend">Pendiente</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($pendientes): ?>
            <form method="post" style="margin-top:1.2rem;" onsubmit="return confirm('¿Aplicar <?= count($pendientes) ?> migración(es) pendiente(s)?');">
                <button type="submit" class="btn btn-primary btn-sm">Aplicar <?= count($pendientes) ?> pendiente(s)</button>
            </form>
        <?php else: ?>
            <p style="margin-top:1.2rem;font-size:.9rem;color:var(--success);">&#9989; La base está al día.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
