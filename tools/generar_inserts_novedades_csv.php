<?php
// Genera inserts de novedades desde un CSV legacy.
// Uso:
// php tools/generar_inserts_novedades_csv.php "C:\ruta\datos.csv" "C:\ruta\salida.sql"
// Si no hay conexion local a la base nueva:
// php tools/generar_inserts_novedades_csv.php "C:\ruta\datos.csv" "C:\ruta\salida.sql" --sin-db

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Este script se ejecuta por consola.\n";
    exit(1);
}

$csvPath = $argv[1] ?? '';
$outPath = $argv[2] ?? '';
$sinDb = in_array('--sin-db', $argv, true);

if ($csvPath === '' || $outPath === '') {
    fwrite(STDERR, "Uso: php tools/generar_inserts_novedades_csv.php \"datos.csv\" \"salida.sql\"\n");
    exit(1);
}

if (!is_file($csvPath)) {
    fwrite(STDERR, "No existe el CSV: {$csvPath}\n");
    exit(1);
}

if (!$sinDb) {
    require_once __DIR__ . '/../config/db.php';
}

function limpiarTexto(?string $valor): string {
    $valor = trim((string)$valor);
    // Corrige mojibake comun sin depender de acentos en este archivo.
    $reemplazos = [
        "\xC3\x83\xC2\xA1" => 'a',
        "\xC3\x83\xC2\xA9" => 'e',
        "\xC3\x83\xC2\xAD" => 'i',
        "\xC3\x83\xC2\xB3" => 'o',
        "\xC3\x83\xC2\xBA" => 'u',
        "\xC3\x83\xC2\x81" => 'A',
        "\xC3\x83\xC2\x89" => 'E',
        "\xC3\x83\xC2\x8D" => 'I',
        "\xC3\x83\xC2\x93" => 'O',
        "\xC3\x83\xC2\x9A" => 'U',
        "\xC3\x83\xC2\xB1" => 'n',
        "\xC3\x83\xC2\x91" => 'N',
        "\xC3\x83\xC2\xBC" => 'u',
        "\xC3\x83\xC2\x9C" => 'U',
        "\xC3\x82\xC2\xBF" => '',
        "\xC3\x82\xC2\xA1" => '',
        "\xC3\x82\xC2\xB0" => '',
        "\xC3\x82\xC2\xB7" => '-',
    ];
    return strtr($valor, $reemplazos);
}

function normalizar(string $valor): string {
    $valor = limpiarTexto($valor);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
    if ($ascii !== false) {
        $valor = $ascii;
    }
    $valor = strtolower($valor);
    $valor = preg_replace('/[^a-z0-9]+/', ' ', $valor);
    return trim(preg_replace('/\s+/', ' ', $valor));
}

function sqlQuote(?string $valor): string {
    if ($valor === null) {
        return 'NULL';
    }
    return "'" . str_replace(["\\", "'"], ["\\\\", "''"], $valor) . "'";
}

function validarFecha(string $fecha): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt && $dt->format('Y-m-d') === $fecha;
}

function validarHora(string $hora): bool {
    return (bool)preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora);
}

function tipoDesdeDescripcion(string $descripcion): string {
    $d = normalizar($descripcion);
    if (str_contains($d, 'inicio') || str_contains($d, 'entrada') || str_contains($d, 'ingreso')) {
        return 'Entrada';
    }
    if (str_contains($d, 'fin') || str_contains($d, 'salida') || str_contains($d, 'egreso')) {
        return 'Salida';
    }
    return 'Novedad';
}

function insertSinDb(
    string $fecha,
    string $hora,
    string $nombreCompleto,
    string $objetivoCsv,
    string $tipoNombre,
    string $observaciones
): string {
    return
        "INSERT INTO novedades (fecha, hora, tipo_novedad, observaciones, empleado_id, ip_dispositivo)\n" .
        "SELECT " . sqlQuote($fecha) . ", " . sqlQuote($hora) . ", tn.id_tipo, " . sqlQuote($observaciones) . ", e.id_empleado, 'import_csv'\n" .
        "FROM empleados e\n" .
        "JOIN tipo_novedad tn ON LOWER(tn.nombre) = " . sqlQuote(strtolower($tipoNombre)) . "\n" .
        "LEFT JOIN objetivos o ON o.id_objetivo = e.objetivo_id\n" .
        "WHERE e.nombre = " . sqlQuote($nombreCompleto) . "\n" .
        "  AND COALESCE(e.tipo, 1) = 1\n" .
        "  AND o.nombre = " . sqlQuote($objetivoCsv) . "\n" .
        "  AND NOT EXISTS (\n" .
        "    SELECT 1 FROM novedades n2\n" .
        "    WHERE n2.fecha = " . sqlQuote($fecha) . "\n" .
        "      AND n2.hora = " . sqlQuote($hora) . "\n" .
        "      AND n2.tipo_novedad = tn.id_tipo\n" .
        "      AND n2.empleado_id = e.id_empleado\n" .
        "  );";
}

function cargarMapas(PDO $db): array {
    $objetivos = [];
    foreach ($db->query("SELECT id_objetivo, nombre FROM objetivos") as $row) {
        $objetivos[normalizar($row['nombre'] ?? '')] = [
            'id' => (int)$row['id_objetivo'],
            'nombre' => $row['nombre'] ?? '',
        ];
    }

    $empleados = [];
    $stmt = $db->query(
        "SELECT id_empleado, nombre, objetivo_id
         FROM empleados
         WHERE COALESCE(tipo, 1) = 1"
    );
    foreach ($stmt as $row) {
        $key = normalizar($row['nombre'] ?? '');
        $empleados[$key][] = [
            'id' => (int)$row['id_empleado'],
            'nombre' => $row['nombre'] ?? '',
            'objetivo_id' => $row['objetivo_id'] !== null ? (int)$row['objetivo_id'] : null,
        ];
    }

    $tipos = [];
    foreach ($db->query("SELECT id_tipo, nombre FROM tipo_novedad") as $row) {
        $tipos[normalizar($row['nombre'] ?? '')] = [
            'id' => (int)$row['id_tipo'],
            'nombre' => $row['nombre'] ?? '',
        ];
    }

    return [$empleados, $objetivos, $tipos];
}

function elegirEmpleado(array $candidatos, ?int $objetivoId): ?array {
    if (count($candidatos) === 1) {
        return $candidatos[0];
    }
    foreach ($candidatos as $empleado) {
        if ($objetivoId !== null && $empleado['objetivo_id'] === $objetivoId) {
            return $empleado;
        }
    }
    return null;
}

if (!$sinDb) {
    $db = getDB();
    [$empleadosMap, $objetivosMap, $tiposMap] = cargarMapas($db);
} else {
    $empleadosMap = [];
    $objetivosMap = [];
    $tiposMap = [];
}

$fh = fopen($csvPath, 'rb');
if (!$fh) {
    fwrite(STDERR, "No se pudo abrir el CSV.\n");
    exit(1);
}

$header = fgetcsv($fh);
if (!$header || count($header) < 6) {
    fwrite(STDERR, "El CSV debe tener columnas: fecha,hora,nombre,apellido,descripcion,nombre_objetivo\n");
    exit(1);
}

$inserts = [];
$errores = [];
$avisos = [];
$linea = 1;

while (($cols = fgetcsv($fh)) !== false) {
    $linea++;
    if (count($cols) < 6) {
        $errores[] = "Linea {$linea}: columnas insuficientes";
        continue;
    }

    $fecha = limpiarTexto($cols[0] ?? '');
    $hora = limpiarTexto($cols[1] ?? '');
    $nombre = limpiarTexto($cols[2] ?? '');
    $apellido = limpiarTexto($cols[3] ?? '');
    $descripcion = limpiarTexto($cols[4] ?? '');
    $objetivoCsv = limpiarTexto($cols[5] ?? '');
    $nombreCompleto = trim($nombre . ' ' . $apellido);

    if (!validarFecha($fecha)) {
        $errores[] = "Linea {$linea}: fecha invalida ({$fecha})";
        continue;
    }
    if (!validarHora($hora)) {
        $errores[] = "Linea {$linea}: hora invalida ({$hora})";
        continue;
    }
    if (strlen($hora) === 5) {
        $hora .= ':00';
    }

    $tipoNombre = tipoDesdeDescripcion($descripcion);
    $observaciones = 'Importado desde CSV. Objetivo CSV: ' . $objetivoCsv . '. Descripcion CSV: ' . $descripcion;

    if ($sinDb) {
        $inserts[] = insertSinDb($fecha, $hora, $nombreCompleto, $objetivoCsv, $tipoNombre, $observaciones);
        continue;
    }

    $objetivo = $objetivosMap[normalizar($objetivoCsv)] ?? null;
    $objetivoId = $objetivo['id'] ?? null;
    if (!$objetivo) {
        $avisos[] = "Linea {$linea}: objetivo no encontrado en base nueva ({$objetivoCsv})";
    }

    $candidatos = $empleadosMap[normalizar($nombreCompleto)] ?? [];
    if (!$candidatos) {
        $errores[] = "Linea {$linea}: empleado no encontrado ({$nombreCompleto})";
        continue;
    }

    $empleado = elegirEmpleado($candidatos, $objetivoId);
    if (!$empleado) {
        $errores[] = "Linea {$linea}: empleado ambiguo para {$nombreCompleto}; revise objetivo {$objetivoCsv}";
        continue;
    }

    if ($objetivoId !== null && $empleado['objetivo_id'] !== null && $empleado['objetivo_id'] !== $objetivoId) {
        $avisos[] = "Linea {$linea}: {$nombreCompleto} esta asignado a otro objetivo en base nueva";
    }

    $tipo = $tiposMap[normalizar($tipoNombre)] ?? null;
    if (!$tipo) {
        $errores[] = "Linea {$linea}: tipo de novedad no existe en base nueva ({$tipoNombre})";
        continue;
    }
    $inserts[] =
        "INSERT INTO novedades (fecha, hora, tipo_novedad, observaciones, empleado_id, ip_dispositivo)\n" .
        "SELECT " . sqlQuote($fecha) . ", " . sqlQuote($hora) . ", " . (int)$tipo['id'] . ", " . sqlQuote($observaciones) . ", " . (int)$empleado['id'] . ", 'import_csv'\n" .
        "WHERE NOT EXISTS (\n" .
        "  SELECT 1 FROM novedades\n" .
        "  WHERE fecha = " . sqlQuote($fecha) . "\n" .
        "    AND hora = " . sqlQuote($hora) . "\n" .
        "    AND tipo_novedad = " . (int)$tipo['id'] . "\n" .
        "    AND empleado_id = " . (int)$empleado['id'] . "\n" .
        ");";
}

fclose($fh);

$sql = [];
$sql[] = "-- Inserts generados desde CSV legacy";
$sql[] = "-- Archivo origen: " . basename($csvPath);
$sql[] = "-- Generado: " . date('Y-m-d H:i:s');
$sql[] = "-- Filas listas para insertar: " . count($inserts);
$sql[] = "-- Avisos: " . count($avisos);
$sql[] = "-- Errores omitidos: " . count($errores);
$sql[] = "START TRANSACTION;";
$sql[] = "";
$sql = array_merge($sql, $inserts);
$sql[] = "";
$sql[] = "COMMIT;";

if ($avisos) {
    $sql[] = "";
    $sql[] = "-- AVISOS";
    foreach ($avisos as $aviso) {
        $sql[] = "-- " . $aviso;
    }
}

if ($errores) {
    $sql[] = "";
    $sql[] = "-- ERRORES / FILAS OMITIDAS";
    foreach ($errores as $error) {
        $sql[] = "-- " . $error;
    }
}

$dir = dirname($outPath);
if (!is_dir($dir)) {
    fwrite(STDERR, "No existe la carpeta destino: {$dir}\n");
    exit(1);
}

file_put_contents($outPath, implode("\n", $sql) . "\n");

echo "SQL generado: {$outPath}\n";
echo "Inserts: " . count($inserts) . "\n";
echo "Avisos: " . count($avisos) . "\n";
echo "Errores omitidos: " . count($errores) . "\n";

if ($errores) {
    echo "Revise los comentarios al final del SQL antes de ejecutarlo.\n";
}
