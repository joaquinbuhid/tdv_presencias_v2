<?php
/**
 * API de Descuentos a vigiladores.
 *   GET  ?accion=listas
 *   GET  ?accion=detalle&id=N
 *   GET  ?accion=historial&id_lista=N[&id_item=M]
 *   POST ?accion=crear_lista      (nombre)
 *   POST ?accion=renombrar_lista  (id, nombre)
 *   POST ?accion=cambiar_etapa    (id, estado=carga|revision)      sólo administradores
 *   POST ?accion=eliminar_lista   (id)                             sólo administradores
 *   POST ?accion=agregar_nombres  (id_lista, nombres)              uno por línea
 *   POST ?accion=actualizar       (id, campo, valor, anterior)     campo: nombre|adelantos|ropa_medico|otros
 *   POST ?accion=eliminar_item    (id)
 * En etapa 'carga' editan todos; en 'revision' sólo administradores.
 * "anterior" es el valor que el usuario tenía en pantalla: si otro lo cambió mientras tanto, responde 409.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/pagos_helper.php';   // pagosParsearMonto, pagosAutor, pagosDescargarXlsx
require_once __DIR__ . '/descuentos_helper.php';
requireBackofficeApi();

const DESC_CAMPOS_MONTO = ['adelantos', 'ropa_medico', 'otros'];
const DESC_NOMBRES_CAMPO = ['nombre' => 'Vigilador', 'adelantos' => 'Adelantos', 'ropa_medico' => 'Desc. Ropa/médico', 'otros' => 'Otros'];

function responder($data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function exigirPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['error' => 'Método no permitido'], 405);
    $t = $_POST['csrf'] ?? null;
    if (!is_string($t) || empty($_SESSION['descuentos_csrf']) || !hash_equals($_SESSION['descuentos_csrf'], $t)) {
        responder(['error' => 'La sesión venció, recargá la página'], 403);
    }
}

function exigirAdmin(string $que): void
{
    if (!esAdminReal()) responder(['error' => "Sólo un administrador puede $que"], 403);
}

function texto(?string $v, int $max): string
{
    $v = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F]/u', ' ', (string)$v)));
    return mb_substr($v, 0, $max);
}

function clave(string $nombre): string
{
    return mb_strtolower(texto($nombre, 150));
}

function listaActiva(PDO $db, int $id): array
{
    $st = $db->prepare("SELECT * FROM descuentos_listas WHERE id = ? AND eliminado_en IS NULL");
    $st->execute([$id]);
    $l = $st->fetch();
    if (!$l) responder(['error' => 'La lista no existe o fue eliminada'], 404);
    return $l;
}

function puedeEditar(array $lista): bool
{
    return $lista['estado'] === 'carga' || esAdminReal();
}

function exigirEdicion(array $lista): void
{
    if (!puedeEditar($lista)) {
        responder(['error' => 'La lista está en revisión: sólo un administrador puede modificarla'], 403);
    }
}

function itemConLista(PDO $db, int $id): array
{
    $st = $db->prepare("SELECT i.*, l.estado, l.id AS lista_id FROM descuentos_items i
                        JOIN descuentos_listas l ON l.id = i.id_lista
                        WHERE i.id = ? AND l.eliminado_en IS NULL");
    $st->execute([$id]);
    $i = $st->fetch();
    if (!$i) responder(['error' => 'Esa fila ya no existe (puede que otro usuario la haya borrado)', 'borrado' => true], 404);
    return $i;
}

function num($v): ?float { return $v === null ? null : (float)$v; }

function formatearItem(array $i): array
{
    return [
        'id'              => (int)$i['id'],
        'nombre'          => $i['nombre'],
        'adelantos'       => num($i['adelantos']),
        'ropa_medico'     => num($i['ropa_medico']),
        'otros'           => num($i['otros']),
        'creado_por'      => $i['creado_por'],
        'creado_en'       => $i['creado_en'],
        'actualizado_por' => $i['actualizado_por'],
        'actualizado_en'  => $i['actualizado_en'],
    ];
}

function formatearLista(array $l): array
{
    return [
        'id' => (int)$l['id'], 'nombre' => $l['nombre'], 'estado' => $l['estado'],
        'creado_por' => $l['creado_por'], 'creado_en' => $l['creado_en'],
        'revision_por' => $l['revision_por'], 'revision_en' => $l['revision_en'],
    ];
}

function mostrarValor(?string $campo, $v): ?string
{
    if ($v === null || $v === '') return null;
    if (in_array($campo, DESC_CAMPOS_MONTO, true)) return number_format((float)$v, 2, ',', '.');
    return (string)$v;
}

function registrar(PDO $db, int $idLista, ?int $idItem, string $vigilador, string $accion,
                   ?string $campo = null, $anterior = null, $nuevo = null): void
{
    [$uid, $unombre] = pagosAutor();
    $db->prepare("INSERT INTO descuentos_historial
        (id_lista, id_item, vigilador, accion, campo, valor_anterior, valor_nuevo, usuario_id, usuario, fecha)
        VALUES (?,?,?,?,?,?,?,?,?,?)")
       ->execute([$idLista, $idItem, $vigilador, $accion, $campo,
                  mostrarValor($campo, $anterior), mostrarValor($campo, $nuevo), $uid, $unombre, date('Y-m-d H:i:s')]);
}

$accion = $_GET['accion'] ?? '';
$db = getDB();
$ahora = date('Y-m-d H:i:s');
[$autorId, $autorNombre] = pagosAutor();

try {
    switch ($accion) {

    case 'listas':
        $rows = $db->query("
            SELECT l.*, COUNT(i.id) AS cantidad,
                   COALESCE(SUM(COALESCE(i.adelantos,0) + COALESCE(i.ropa_medico,0) + COALESCE(i.otros,0)), 0) AS total,
                   MAX(i.actualizado_en) AS ultimo_cambio
            FROM descuentos_listas l
            LEFT JOIN descuentos_items i ON i.id_lista = l.id
            WHERE l.eliminado_en IS NULL
            GROUP BY l.id, l.nombre, l.estado, l.creado_por_id, l.creado_por, l.creado_en,
                     l.revision_por_id, l.revision_por, l.revision_en, l.eliminado_en
            ORDER BY l.creado_en DESC, l.id DESC")->fetchAll();
        responder([
            'es_admin' => esAdminReal(),
            'listas'   => array_map(fn($r) => formatearLista($r) + [
                'cantidad' => (int)$r['cantidad'], 'total' => (float)$r['total'], 'ultimo_cambio' => $r['ultimo_cambio'],
            ], $rows),
        ]);

    case 'detalle':
        $l = listaActiva($db, (int)($_GET['id'] ?? 0));
        $st = $db->prepare("SELECT * FROM descuentos_items WHERE id_lista = ? ORDER BY id");
        $st->execute([$l['id']]);
        responder([
            'es_admin'      => esAdminReal(),
            'puede_editar'  => puedeEditar($l),
            'lista'         => formatearLista($l),
            'items'         => array_map('formatearItem', $st->fetchAll()),
        ]);

    case 'historial':
        $l = listaActiva($db, (int)($_GET['id_lista'] ?? 0));
        $sql = "SELECT * FROM descuentos_historial WHERE id_lista = ?";
        $p = [$l['id']];
        if (!empty($_GET['id_item'])) { $sql .= " AND id_item = ?"; $p[] = (int)$_GET['id_item']; }
        $sql .= " ORDER BY fecha DESC, id DESC LIMIT 300";
        $st = $db->prepare($sql); $st->execute($p);
        responder(['historial' => array_map(fn($h) => [
            'vigilador' => $h['vigilador'], 'accion' => $h['accion'],
            'campo' => $h['campo'] ? (DESC_NOMBRES_CAMPO[$h['campo']] ?? $h['campo']) : null,
            'anterior' => $h['valor_anterior'], 'nuevo' => $h['valor_nuevo'],
            'usuario' => $h['usuario'], 'fecha' => $h['fecha'],
        ], $st->fetchAll())]);

    case 'crear_lista':
        exigirPost();
        $nombre = texto($_POST['nombre'] ?? '', 120);
        if ($nombre === '') responder(['error' => 'Poné un nombre para la lista (ej: Descuentos Noviembre 2026)'], 400);
        $db->prepare("INSERT INTO descuentos_listas (nombre, estado, creado_por_id, creado_por, creado_en) VALUES (?, 'carga', ?, ?, ?)")
           ->execute([$nombre, $autorId, $autorNombre, $ahora]);
        responder(['ok' => true, 'id' => (int)$db->lastInsertId()]);

    case 'renombrar_lista':
        exigirPost();
        $l = listaActiva($db, (int)($_POST['id'] ?? 0));
        exigirEdicion($l);
        $nombre = texto($_POST['nombre'] ?? '', 120);
        if ($nombre === '') responder(['error' => 'El nombre no puede quedar vacío'], 400);
        $db->prepare("UPDATE descuentos_listas SET nombre = ? WHERE id = ?")->execute([$nombre, $l['id']]);
        responder(['ok' => true]);

    case 'cambiar_etapa':
        exigirPost();
        exigirAdmin('cambiar la etapa de la lista');
        $l = listaActiva($db, (int)($_POST['id'] ?? 0));
        $estado = $_POST['estado'] ?? '';
        if (!in_array($estado, ['carga', 'revision'], true)) responder(['error' => 'Etapa inválida'], 400);
        if ($estado === 'revision') {
            $db->prepare("UPDATE descuentos_listas SET estado = 'revision', revision_por_id = ?, revision_por = ?, revision_en = ? WHERE id = ?")
               ->execute([$autorId, $autorNombre, $ahora, $l['id']]);
        } else {
            $db->prepare("UPDATE descuentos_listas SET estado = 'carga' WHERE id = ?")->execute([$l['id']]);
        }
        registrar($db, (int)$l['id'], null, '—', 'etapa', null,
                  $l['estado'] === 'carga' ? 'Carga' : 'Revisión', $estado === 'carga' ? 'Carga' : 'Revisión');
        responder(['ok' => true]);

    case 'eliminar_lista':
        exigirPost();
        exigirAdmin('eliminar listas');
        $l = listaActiva($db, (int)($_POST['id'] ?? 0));
        $db->prepare("UPDATE descuentos_listas SET eliminado_en = ? WHERE id = ?")->execute([$ahora, $l['id']]);
        responder(['ok' => true]);

    case 'agregar_nombres':
        exigirPost();
        $l = listaActiva($db, (int)($_POST['id_lista'] ?? 0));
        exigirEdicion($l);
        $nuevos = [];
        foreach (preg_split('/\R/u', (string)($_POST['nombres'] ?? '')) as $n) {
            $n = texto($n, 150);
            if ($n !== '') $nuevos[clave($n)] = $n;            // sin repetir dentro de lo pegado
        }
        if (!$nuevos) responder(['error' => 'Escribí al menos un nombre'], 400);
        if (count($nuevos) > 500) responder(['error' => 'Máximo 500 nombres por vez'], 400);

        // No repetir vigiladores que ya estén en la lista (otro usuario pudo haberlo agregado recién)
        $st = $db->prepare("SELECT nombre FROM descuentos_items WHERE id_lista = ?");
        $st->execute([$l['id']]);
        $existentes = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) $existentes[clave($n)] = $n;
        $repetidos = array_values(array_intersect_key($existentes, $nuevos));   // como figuran en la lista
        $nuevos = array_diff_key($nuevos, $existentes);

        $items = [];
        if ($nuevos) {
            $db->beginTransaction();
            $ins = $db->prepare("INSERT INTO descuentos_items (id_lista, nombre, creado_por_id, creado_por, creado_en) VALUES (?,?,?,?,?)");
            foreach ($nuevos as $n) {
                $ins->execute([$l['id'], $n, $autorId, $autorNombre, $ahora]);
                $id = (int)$db->lastInsertId();
                registrar($db, (int)$l['id'], $id, $n, 'alta');
                $st = $db->prepare("SELECT * FROM descuentos_items WHERE id = ?"); $st->execute([$id]);
                $items[] = formatearItem($st->fetch());
            }
            $db->commit();
        }
        if (!$items) responder(['error' => (count($repetidos) === 1 ? "$repetidos[0] ya está en la lista" : 'Esos vigiladores ya están en la lista'), 'repetidos' => $repetidos], 400);
        responder(['ok' => true, 'items' => $items, 'repetidos' => $repetidos]);

    case 'actualizar':
        exigirPost();
        $it = itemConLista($db, (int)($_POST['id'] ?? 0));
        exigirEdicion(['estado' => $it['estado']]);
        $campo = $_POST['campo'] ?? '';
        if (!array_key_exists($campo, DESC_NOMBRES_CAMPO)) responder(['error' => 'Campo inválido'], 400);
        $valor = (string)($_POST['valor'] ?? '');
        $anterior = (string)($_POST['anterior'] ?? '');
        $actual = $it[$campo];

        // ¿Lo cambió otro usuario mientras éste lo tenía en pantalla?
        if ($campo === 'nombre') {
            $nuevo = texto($valor, 150);
            if ($nuevo === '') responder(['error' => 'El nombre no puede quedar vacío'], 400);
            $conflicto = texto($anterior, 150) !== $actual;
        } else {
            $nuevo = pagosParsearMonto($valor);
            if ($nuevo === false) responder(['error' => "\"$valor\" no es un monto válido. Usá por ejemplo 150000 o 150.000,50"], 400);
            $conflicto = $anterior === ''
                ? $actual !== null
                : ($actual === null || abs((float)$anterior - (float)$actual) >= 0.005);
        }
        if ($conflicto) {
            responder([
                'error' => sprintf('%s cambió "%s" de %s mientras lo editabas. Quedó el valor de %s; revisalo y volvé a cargarlo si hace falta.',
                    $it['actualizado_por'] ?: 'Otro usuario', DESC_NOMBRES_CAMPO[$campo], $it['nombre'], $it['actualizado_por'] ?: 'ese usuario'),
                'conflicto' => true,
                'item' => formatearItem($it),
            ], 409);
        }

        if ($campo === 'nombre' && clave($nuevo) !== clave($actual)) {
            $st = $db->prepare("SELECT nombre FROM descuentos_items WHERE id_lista = ? AND id <> ?");
            $st->execute([$it['lista_id'], $it['id']]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) {
                if (clave($n) === clave($nuevo)) responder(['error' => "$n ya está en la lista"], 400);
            }
        }

        $igual = $campo === 'nombre' ? $nuevo === $actual
               : (($nuevo === null && $actual === null) || ($nuevo !== null && $actual !== null && abs($nuevo - (float)$actual) < 0.005));
        if (!$igual) {
            $guardar = ($campo === 'nombre' || $nuevo === null) ? $nuevo : number_format($nuevo, 2, '.', '');
            $db->prepare("UPDATE descuentos_items SET $campo = ?, actualizado_por_id = ?, actualizado_por = ?, actualizado_en = ? WHERE id = ?")
               ->execute([$guardar, $autorId, $autorNombre, $ahora, $it['id']]);
            registrar($db, (int)$it['lista_id'], (int)$it['id'], $campo === 'nombre' ? $nuevo : $it['nombre'],
                      'cambio', $campo, $actual, $guardar);
        }
        $st = $db->prepare("SELECT * FROM descuentos_items WHERE id = ?"); $st->execute([$it['id']]);
        responder(['ok' => true, 'item' => formatearItem($st->fetch())]);

    case 'eliminar_item':
        exigirPost();
        $it = itemConLista($db, (int)($_POST['id'] ?? 0));
        exigirEdicion(['estado' => $it['estado']]);
        $total = (float)$it['adelantos'] + (float)$it['ropa_medico'] + (float)$it['otros'];
        $db->prepare("DELETE FROM descuentos_items WHERE id = ?")->execute([$it['id']]);
        registrar($db, (int)$it['lista_id'], (int)$it['id'], $it['nombre'], 'baja', null, $total ? number_format($total, 2, ',', '.') : null, null);
        responder(['ok' => true]);

    default:
        responder(['error' => 'Acción desconocida'], 400);
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[descuentos] ' . $e->getMessage());
    responder(['error' => 'Error interno del servidor'], 500);
}
