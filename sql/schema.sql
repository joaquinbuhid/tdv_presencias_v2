-- ============================================================
-- TDV Presencias v2 - Esquema base nueva
-- ============================================================

CREATE TABLE IF NOT EXISTS empresas (
  id_empresa int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(50) DEFAULT NULL,
  PRIMARY KEY (id_empresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS objetivos (
  id_objetivo int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(100) DEFAULT NULL,
  descripcion text DEFAULT NULL,
  coord_lat decimal(10,8) DEFAULT NULL,
  coord_long decimal(11,8) DEFAULT NULL,
  rad_metros int(11) DEFAULT NULL,
  supervisor_id int(11) DEFAULT NULL,
  PRIMARY KEY (id_objetivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipo_novedad (
  id_tipo int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(40) NOT NULL,
  descripcion text DEFAULT NULL,
  PRIMARY KEY (id_tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS empleados (
  id_empleado int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(100) NOT NULL,
  fecha_nac date NOT NULL,
  est_civil varchar(50) NOT NULL,
  empresa_id int(11) DEFAULT NULL,
  domicilio text NOT NULL,
  CUIL varchar(20) NOT NULL,
  DNI varchar(20) DEFAULT NULL,
  telefono varchar(20) NOT NULL,
  nro_legajo varchar(20) DEFAULT NULL,
  nro_credencial varchar(20) DEFAULT NULL,
  fecha_venc_cred date DEFAULT NULL,
  activo tinyint(1) NOT NULL DEFAULT 1,
  objetivo_id int(11) DEFAULT NULL,
  hora_entrada time DEFAULT NULL,
  hora_salida time DEFAULT NULL,
  pendiente tinyint(1) NOT NULL DEFAULT 0,
  email varchar(100) NOT NULL,
  contrasena varchar(250) NOT NULL,
  fecha_alta date DEFAULT (curdate()),
  tipo int(11) DEFAULT NULL,
  url_leg text DEFAULT NULL,
  nacionalidad varchar(100) DEFAULT NULL,
  PRIMARY KEY (id_empleado),
  KEY idx_empleados_email (email),
  KEY idx_empleados_cuil (CUIL),
  KEY idx_empleados_dni (DNI),
  KEY idx_empleados_tipo (tipo),
  KEY idx_empleados_objetivo (objetivo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimientos_empresas (
  id_movimiento_emp int(11) NOT NULL AUTO_INCREMENT,
  empresa_ant_id int(11) NOT NULL,
  empresa_nuevo_id int(11) NOT NULL,
  fecha date NOT NULL,
  empleado_id int(11) NOT NULL,
  PRIMARY KEY (id_movimiento_emp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimientos_objetivos (
  id_movimiento_obj int(11) NOT NULL AUTO_INCREMENT,
  objetivo_ant_id int(11) NOT NULL,
  objetivo_nuevo_id int(11) NOT NULL,
  fecha date NOT NULL,
  empleado_id int(11) NOT NULL,
  PRIMARY KEY (id_movimiento_obj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS novedades (
  id_novedad int(11) NOT NULL AUTO_INCREMENT,
  fecha date NOT NULL,
  hora time NOT NULL,
  tipo_novedad int(11) NOT NULL,
  observaciones text DEFAULT NULL,
  empleado_id int(11) NOT NULL,
  ip_dispositivo varchar(50) DEFAULT NULL,
  coord_lat decimal(10,8) DEFAULT NULL,
  coord_long decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (id_novedad),
  KEY idx_novedades_empleado_fecha (empleado_id, fecha),
  KEY idx_novedades_tipo (tipo_novedad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS postulantes (
  id int(11) NOT NULL AUTO_INCREMENT,
  nombre_completo varchar(255) NOT NULL,
  dni varchar(20) NOT NULL,
  fecha_nacimiento date NOT NULL,
  telefono varchar(50) NOT NULL,
  email varchar(255) NOT NULL,
  localidad_residencia varchar(255) NOT NULL,
  experiencia_seguridad enum('si','no') NOT NULL,
  curso_habilitante enum('si','no') NOT NULL,
  credencial_vigente enum('si','no') NOT NULL,
  disponibilidad_horaria enum('Full Time','Turno Diurno','Turno Nocturno','Rotativos') NOT NULL,
  puesto_postula varchar(255) NOT NULL,
  parte_track_seguridad enum('si','no') NOT NULL,
  archivo_adjunto varchar(255) DEFAULT NULL,
  fecha_registro timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_postulantes_fecha (fecha_registro),
  KEY idx_postulantes_dni (dni),
  KEY idx_postulantes_puesto (puesto_postula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tipo_novedad (nombre, descripcion)
SELECT 'Entrada', 'Registro de inicio de turno'
WHERE NOT EXISTS (SELECT 1 FROM tipo_novedad WHERE nombre = 'Entrada');

INSERT INTO tipo_novedad (nombre, descripcion)
SELECT 'Salida', 'Registro de fin de turno'
WHERE NOT EXISTS (SELECT 1 FROM tipo_novedad WHERE nombre = 'Salida');

INSERT INTO tipo_novedad (nombre, descripcion)
SELECT 'Novedad', 'Situacion especial durante el turno'
WHERE NOT EXISTS (SELECT 1 FROM tipo_novedad WHERE nombre = 'Novedad');

INSERT INTO tipo_novedad (nombre, descripcion)
SELECT 'Incidente', 'Reporte de incidente o irregularidad'
WHERE NOT EXISTS (SELECT 1 FROM tipo_novedad WHERE nombre = 'Incidente');

-- Roles usados por el sistema en empleados.tipo:
-- 1 = vigilador, 2 = supervisor, 3 = oficinista, 4 = administrador.
-- Admin inicial de desarrollo. Usuario: admin@tdv.local / clave: password
-- Cambiar o eliminar antes de produccion.
INSERT INTO empleados
  (nombre, fecha_nac, est_civil, domicilio, CUIL, telefono, email, contrasena, activo, pendiente, tipo)
SELECT
  'Administrador Sistema', '1900-01-01', 'No informado', 'No informado', '00000000000', '0000000000',
  'admin@tdv.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 0, 4
WHERE NOT EXISTS (SELECT 1 FROM empleados WHERE email = 'admin@tdv.local');
