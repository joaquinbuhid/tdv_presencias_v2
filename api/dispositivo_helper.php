<?php
// Datos del dispositivo desde el que se registra una marca.

/**
 * Devuelve [dispositivo_id, modelo_disp, user_agent] validados.
 * El ID llega en el JSON; si falta, se toma de la cookie tdv_disp que setea js/app.js.
 */
function datosDispositivo(?array $data): array {
    $id = $data['dispositivo_id'] ?? ($_COOKIE['tdv_disp'] ?? '');
    $id = is_string($id) && preg_match('/^[A-Za-z0-9-]{8,64}$/', $id) ? $id : null;

    $modelo = is_string($data['modelo_disp'] ?? null) ? trim($data['modelo_disp']) : '';
    $modelo = preg_replace('/[^\p{L}\p{N} ._()\/+-]/u', '', $modelo);
    $modelo = mb_substr($modelo, 0, 100) ?: null;

    $ua = mb_substr(trim($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;

    return [$id, $modelo, $ua];
}

/**
 * INSERT en novedades tolerante a migraciones pendientes: si una columna opcional
 * todavía no existe (error 42S22), reintenta sin ese grupo de columnas.
 *
 * @param array $campos      columna => valor (obligatorias)
 * @param array $opcionales  lista de grupos [columna => valor], del más nuevo al más viejo
 */
function insertarNovedad(PDO $db, array $campos, array $opcionales = []): void {
    while (true) {
        $todos = $campos;
        foreach ($opcionales as $grupo) {
            $todos += $grupo;
        }
        $cols = implode(', ', array_keys($todos));
        $marks = implode(', ', array_fill(0, count($todos), '?'));
        try {
            $db->prepare("INSERT INTO novedades ($cols) VALUES ($marks)")->execute(array_values($todos));
            return;
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S22' || empty($opcionales)) {
                throw $e;
            }
            array_shift($opcionales);
        }
    }
}
