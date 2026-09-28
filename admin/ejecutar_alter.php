<?php
require_once __DIR__ . '/auth.php';
requireAdminRealPage();
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

    // 3. entrevistas table
    $stmtTable = $db->query("SHOW TABLES LIKE 'entrevistas'");
    if (!$stmtTable->fetch()) {
        $db->exec("CREATE TABLE entrevistas (
          id_entrevista int(11) NOT NULL AUTO_INCREMENT,
          postulante_id int(11) DEFAULT NULL,
          empleado_id int(11) DEFAULT NULL,
          fecha_entrevista datetime NOT NULL DEFAULT current_timestamp(),
          nombre_completo varchar(255) DEFAULT NULL,
          dni varchar(20) DEFAULT NULL,
          fecha_nacimiento date DEFAULT NULL,
          telefono varchar(50) DEFAULT NULL,
          email varchar(255) DEFAULT NULL,
          localidad_residencia varchar(255) DEFAULT NULL,
          puesto_postula varchar(255) DEFAULT NULL,
          disponibilidad_horaria varchar(50) DEFAULT NULL,
          experiencia_seguridad varchar(10) DEFAULT NULL,
          curso_habilitante varchar(10) DEFAULT NULL,
          credencial_vigente varchar(10) DEFAULT NULL,
          parte_track_seguridad varchar(10) DEFAULT NULL,
          monotributista varchar(10) DEFAULT NULL,
          genero varchar(50) DEFAULT NULL,
          peso decimal(5,1) DEFAULT NULL,
          altura decimal(5,1) DEFAULT NULL,
          relacion_peso_altura tinyint(4) DEFAULT NULL,
          apariencia_vestimenta tinyint(4) DEFAULT NULL,
          modulacion_habla tinyint(4) DEFAULT NULL,
          estado_civil varchar(50) DEFAULT NULL,
          hijos int(11) DEFAULT NULL,
          domicilio varchar(255) DEFAULT NULL,
          tiene_vehiculo enum('si','no') DEFAULT NULL,
          vehiculo varchar(255) DEFAULT NULL,
          fecha_ultimo_trabajo date DEFAULT NULL,
          punto_ultimo_trabajo tinyint(4) NOT NULL DEFAULT 0,
          valoracion_personal tinyint(4) DEFAULT NULL,
          valoracion_texto text DEFAULT NULL,
          puntaje_sin_valoracion int(11) DEFAULT NULL,
          puntaje_total int(11) DEFAULT NULL,
          fecha_registro timestamp NULL DEFAULT current_timestamp(),
          PRIMARY KEY (id_entrevista),
          KEY idx_entrevistas_postulante (postulante_id),
          KEY idx_entrevistas_empleado (empleado_id),
          KEY idx_entrevistas_fecha (fecha_entrevista),
          CONSTRAINT fk_entrevistas_postulante FOREIGN KEY (postulante_id) REFERENCES postulantes (id) ON DELETE SET NULL,
          CONSTRAINT fk_entrevistas_empleado FOREIGN KEY (empleado_id) REFERENCES empleados (id_empleado) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "SUCCESS: Tabla 'entrevistas' creada exitosamente.<br>";
    } else {
        echo "INFO: La tabla 'entrevistas' ya existe en esta base de datos.<br>";
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
}
