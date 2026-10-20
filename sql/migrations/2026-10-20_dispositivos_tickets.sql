-- Inventario de dispositivos y tickets de soporte.
-- Los usuarios (dueño, pedido_por, resuelto_por) son filas de empleados.

CREATE TABLE IF NOT EXISTS tipos_dispositivo (
  id_tipo_disp int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(60) NOT NULL,
  PRIMARY KEY (id_tipo_disp),
  UNIQUE KEY uq_tipos_dispositivo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dispositivos (
  id_disp int(11) NOT NULL AUTO_INCREMENT,
  nombre_disp varchar(100) NOT NULL,
  usuario_disp varchar(100) DEFAULT NULL,
  clave_disp varchar(255) DEFAULT NULL,
  id_empleado int(11) DEFAULT NULL,
  observaciones_disp text DEFAULT NULL,
  id_tipo_disp int(11) NOT NULL,
  mother_disp varchar(150) DEFAULT NULL,
  micro_disp varchar(150) DEFAULT NULL,
  ram varchar(100) DEFAULT NULL,
  fuente varchar(100) DEFAULT NULL,
  PRIMARY KEY (id_disp),
  KEY idx_dispositivos_empleado (id_empleado),
  KEY idx_dispositivos_tipo (id_tipo_disp),
  CONSTRAINT fk_dispositivos_empleado FOREIGN KEY (id_empleado) REFERENCES empleados (id_empleado) ON DELETE SET NULL,
  CONSTRAINT fk_dispositivos_tipo FOREIGN KEY (id_tipo_disp) REFERENCES tipos_dispositivo (id_tipo_disp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
  id_ticket int(11) NOT NULL AUTO_INCREMENT,
  fecha_hora_creacion datetime NOT NULL DEFAULT current_timestamp(),
  fecha_hora_resuelto datetime DEFAULT NULL,
  id_dispositivo int(11) NOT NULL,
  resuelto_por int(11) DEFAULT NULL,
  pedido_por int(11) DEFAULT NULL,
  problema text NOT NULL,
  solucion text DEFAULT NULL,
  PRIMARY KEY (id_ticket),
  KEY idx_tickets_dispositivo (id_dispositivo),
  KEY idx_tickets_resuelto (fecha_hora_resuelto),
  KEY idx_tickets_pedido_por (pedido_por),
  KEY idx_tickets_resuelto_por (resuelto_por),
  CONSTRAINT fk_tickets_dispositivo FOREIGN KEY (id_dispositivo) REFERENCES dispositivos (id_disp),
  CONSTRAINT fk_tickets_pedido_por FOREIGN KEY (pedido_por) REFERENCES empleados (id_empleado) ON DELETE SET NULL,
  CONSTRAINT fk_tickets_resuelto_por FOREIGN KEY (resuelto_por) REFERENCES empleados (id_empleado) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tipos_dispositivo (nombre) VALUES
  ('PC'), ('Notebook'), ('Celular'), ('Tablet'), ('Impresora'), ('Router'), ('Monitor'), ('Otro');
