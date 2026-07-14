<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();
try {
    // Check if column already exists
    $stmt = $db->query("SHOW COLUMNS FROM empleados LIKE 'genero'");
    $column = $stmt->fetch();
    
    if (!$column) {
        $db->exec("ALTER TABLE empleados ADD COLUMN genero VARCHAR(50) DEFAULT NULL");
        echo "SUCCESS: Columna 'genero' agregada exitosamente a la tabla 'empleados'.";
    } else {
        echo "INFO: La columna 'genero' ya existe en la tabla 'empleados'.";
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
}
