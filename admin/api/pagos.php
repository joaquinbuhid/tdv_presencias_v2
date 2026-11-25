<?php
/**
 * API de Pagos a vigiladores.
 *   GET  ?accion=listas
 *   GET  ?accion=detalle&id=N
 *   POST ?accion=crear_lista       (nombre)
 *   POST ?accion=renombrar_lista   (id, nombre)
 *   POST ?accion=eliminar_lista    (id)                        sólo administradores
 *   POST ?accion=agregar_nombres   (id_lista, nombres)         uno por línea
 *   POST ?accion=actualizar_item   (id, campo, valor)          campo: nombre|monto|observaciones|banco
 *   POST ?accion=asignar_banco     (ids = "1,2,3", banco)      banco vacío = quitar banco
 *   POST ?accion=eliminar_item     (id)
 * Todas las POST llevan csrf.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/pagos_helper.php';
requireBackofficeApi();

function responder($data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function exigirPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['error' => 'Método no permitido'], 405);
    if (!pagosCsrfOk($_POST['csrf'] ?? null)) responder(['error' => 'La sesión venció, recargá la página'], 403);
}

function texto(?string $v, int $max): string
{
    $v = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F]/u', ' ', (string)$v)));
    return mb_substr($v, 0, $max);
}

function listaActiva(PDO $db, int $id): array
{
    $st = $db->prepare("SELECT * FROM pagos_listas WHERE id = ? AND eliminado_en IS NULL");
    $st->execute([$id]);
    $l = $st->fetch();
    if (!$l) responder(['error' => 'La lista no existe o fue eliminada'], 404);
    return $l;
}

/** Devuelve el item si su lista está activa. */
function itemActivo(PDO $db, int $id): array
{
    $st = $db->prepare("SELECT i.* FROM pagos_items i JOIN pagos_listas l ON l.id = i.id_lista
                        WHERE i.id = ? AND l.eliminado_en IS NULL");
    $st->execute([$id]);
    $i = $st->fetch();
    if (!$i) responder(['error' => 'El vigilador no existe en una lista activa'], 404);
    return $i;
}

function formatearItem(array $i): array
{
    return [
        'id'            => (int)$i['id'],
        'nombre'        => $i['nombre'],
        'monto'         => $i['monto'] === null ? null : (float)$i['monto'],
        'observaciones' => $i['observaciones'],
        'banco'         => $i['banco'],
        'creado_por'    => $i['creado_por'],
        'creado_en'     => $i['creado_en'],
        'monto_por'     => $i['monto_por'],
        'monto_en'      => $i['monto_en'],
        'banco_por'     => $i['banco_por'],
        'banco_en'      => $i['banco_en'],
    ];
}

$accion = $_GET['accion'] ?? '';
$db = getDB();
$ahora = date('Y-m-d H:i:s');
[$autorId, $autorNombre] = pagosAutor();

try {
    switch ($accion) {

    case 'listas':
        $rows = $db->query("
            SELECT l.id, l.nombre, l.creado_por, l.creado_en,
                   COUNT(i.id)                                   AS cantidad,
                   COALESCE(SUM(i.monto), 0)                     AS total,
                   SUM(CASE WHEN i.monto IS NOT NULL THEN 1 ELSE 0 END) AS con_monto,
                   SUM(CASE WHEN i.banco IS NOT NULL THEN 1 ELSE 0 END) AS con_banco
            FROM pagos_listas l
            LEFT JOIN pagos_items i ON i.id_lista = l.id
            WHERE l.eliminado_en IS NULL
            GROUP BY l.id, l.nombre, l.creado_por, l.creado_en
            ORDER BY l.creado_en DESC, l.id DESC")->fetchAll();
        responder([
            'es_admin' => esAdminReal(),
            'listas'   => array_map(fn($r) => [
                'id' => (int)$r['id'], 'nombre' => $r['nombre'], 'creado_por' => $r['creado_por'], 'creado_en' => $r['creado_en'],
                'cantidad' => (int)$r['cantidad'], 'total' => (float)$r['total'],
                'con_monto' => (int)$r['con_monto'], 'con_banco' => (int)$r['con_banco'],
            ], $rows),
        ]);

    case 'detalle':
        $l = listaActiva($db, (int)($_GET['id'] ?? 0));
        $st = $db->prepare("SELECT * FROM pagos_items WHERE id_lista = ? ORDER BY id");
        $st->execute([$l['id']]);
        $items = array_map('formatearItem', $st->fetchAll());
        // Bancos ya usados en cualquier lista, como sugerencias
        $bancos = $db->query("SELECT DISTINCT banco FROM pagos_items WHERE banco IS NOT NULL ORDER BY banco")->fetchAll(PDO::FETCH_COLUMN);
        responder([
            'es_admin' => esAdminReal(),
            'lista'    => ['id' => (int)$l['id'], 'nombre' => $l['nombre'], 'creado_por' => $l['creado_por'], 'creado_en' => $l['creado_en']],
            'items'    => $items,
            'bancos'   => $bancos,
        ]);

    case 'crear_lista':
        exigirPost();
        $nombre = texto($_POST['nombre'] ?? '', 120);
        if ($nombre === '') responder(['error' => 'Poné un nombre para la lista (ej: Noviembre 2026)'], 400);
        $db->prepare("INSERT INTO pagos_listas (nombre, creado_por_id, creado_por, creado_en) VALUES (?,?,?,?)")
           ->execute([$nombre, $autorId, $autorNombre, $ahora]);
        responder(['ok' => true, 'id' => (int)$db->lastInsertId()]);

    case 'renombrar_lista':
        exigirPost();
        $l = listaActiva($db, (int)($_POST['id'] ?? 0));
        $nombre = texto($_POST['nombre'] ?? '', 120);
        if ($nombre === '') responder(['error' => 'El nombre no puede quedar vacío'], 400);
        $db->prepare("UPDATE pagos_listas SET nombre = ? WHERE id = ?")->execute([$nombre, $l['id']]);
        responder(['ok' => true]);

    case 'eliminar_lista':
        exigirPost();
        if (!esAdminReal()) responder(['error' => 'Sólo un administrador puede eliminar listas'], 403);
        $l = listaActiva($db, (int)($_POST['id'] ?? 0));
        $db->prepare("UPDATE pagos_listas SET eliminado_en = ? WHERE id = ?")->execute([$ahora, $l['id']]);
        responder(['ok' => true]);

    case 'agregar_nombres':
        exigirPost();
        $l = listaActiva($db, (int)($_POST['id_lista'] ?? 0));
        $nombres = [];
        foreach (preg_split('/\R/u', (string)($_POST['nombres'] ?? '')) as $n) {
            $n = texto($n, 150);
            if ($n !== '') $nombres[] = $n;
        }
        if (!$nombres) responder(['error' => 'Escribí al menos un nombre'], 400);
        if (count($nombres) > 500) responder(['error' => 'Máximo 500 nombres por vez'], 400);

        $st = $db->prepare("INSERT INTO pagos_items (id_lista, nombre, creado_por_id, creado_por, creado_en) VALUES (?,?,?,?,?)");
        $db->beginTransaction();
        $ids = [];
        foreach ($nombres as $n) { $st->execute([$l['id'], $n, $autorId, $autorNombre, $ahora]); $ids[] = (int)$db->lastInsertId(); }
        $db->commit();
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $db->prepare("SELECT * FROM pagos_items WHERE id IN ($ph) ORDER BY id");
        $st->execute($ids);
        responder(['ok' => true, 'items' => array_map('formatearItem', $st->fetchAll())]);

    case 'actualizar_item':
        exigirPost();
        $it = itemActivo($db, (int)($_POST['id'] ?? 0));
        $campo = $_POST['campo'] ?? '';
        $valor = (string)($_POST['valor'] ?? '');
        switch ($campo) {
            case 'nombre':
                $v = texto($valor, 150);
                if ($v === '') responder(['error' => 'El nombre no puede quedar vacío'], 400);
                $db->prepare("UPDATE pagos_items SET nombre = ? WHERE id = ?")->execute([$v, $it['id']]);
                break;
            case 'monto':
                $v = pagosParsearMonto($valor);
                if ($v === false) responder(['error' => "\"$valor\" no es un monto válido. Usá por ejemplo 125000 o 125.000,50"], 400);
                $db->prepare("UPDATE pagos_items SET monto = ?, monto_por_id = ?, monto_por = ?, monto_en = ? WHERE id = ?")
                   ->execute([$v === null ? null : number_format($v, 2, '.', ''), $autorId, $autorNombre, $ahora, $it['id']]);
                break;
            case 'observaciones':
                $v = texto($valor, 500);
                $db->prepare("UPDATE pagos_items SET observaciones = ? WHERE id = ?")->execute([$v !== '' ? $v : null, $it['id']]);
                break;
            case 'banco':
                $v = texto($valor, 80);
                $db->prepare("UPDATE pagos_items SET banco = ?, banco_por_id = ?, banco_por = ?, banco_en = ? WHERE id = ?")
                   ->execute([$v !== '' ? $v : null, $autorId, $autorNombre, $ahora, $it['id']]);
                break;
            default:
                responder(['error' => 'Campo inválido'], 400);
        }
        responder(['ok' => true, 'item' => formatearItem(itemActivo($db, (int)$it['id']))]);

    case 'asignar_banco':
        exigirPost();
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? '')))));
        if (!$ids) responder(['error' => 'Marcá al menos un vigilador'], 400);
        $banco = texto($_POST['banco'] ?? '', 80);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $db->prepare("UPDATE pagos_items SET banco = ?, banco_por_id = ?, banco_por = ?, banco_en = ?
                            WHERE id IN ($ph) AND id_lista IN (SELECT id FROM pagos_listas WHERE eliminado_en IS NULL)");
        $st->execute(array_merge([$banco !== '' ? $banco : null, $autorId, $autorNombre, $ahora], $ids));
        responder(['ok' => true, 'actualizados' => $st->rowCount()]);

    case 'eliminar_item':
        exigirPost();
        $it = itemActivo($db, (int)($_POST['id'] ?? 0));
        $db->prepare("DELETE FROM pagos_items WHERE id = ?")->execute([$it['id']]);
        responder(['ok' => true]);

    default:
        responder(['error' => 'Acción desconocida'], 400);
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[pagos] ' . $e->getMessage());
    responder(['error' => 'Error interno del servidor'], 500);
}
