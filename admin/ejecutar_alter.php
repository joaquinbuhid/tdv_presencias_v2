<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();
try {
    // 1. empleados table
    $stmt = $db->query("SHOW COLUMNS FROM empleados LIKE 'genero'");
    $column = $stmt->fetch();
    if (!$column) {
        $db->exec("ALTER TABLE empleados ADD COLUMN genero VARCHAR(50) DEFAULT NULL");
        echo "SUCCESS: Columna 'genero' agregada exitosamente a la tabla 'empleados'.<br>";
    } else {
        echo "INFO: La columna 'genero' ya existe en la tabla 'empleados'.<br>";
    }

    // 2. postulantes table (check if table exists first)
    $stmtTable = $db->query("SHOW TABLES LIKE 'postulantes'");
    if ($stmtTable->fetch()) {
        $stmtCol = $db->query("SHOW COLUMNS FROM postulantes LIKE 'genero'");
        $columnCol = $stmtCol->fetch();
        if (!$columnCol) {
            $db->exec("ALTER TABLE postulantes ADD COLUMN genero VARCHAR(50) DEFAULT NULL");
            echo "SUCCESS: Columna 'genero' agregada exitosamente a la tabla 'postulantes'.<br>";
        } else {
            echo "INFO: La columna 'genero' ya existe en la tabla 'postulantes'.<br>";
        }
    } else {
        echo "INFO: La tabla 'postulantes' no existe en esta base de datos.<br>";
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
}
