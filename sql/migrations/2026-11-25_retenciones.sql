-- Retenciones (admin/retenciones.php).
-- Cada ingreso (fecha, período, monto) puede tener una o más facturas asociadas.
-- Los adjuntos de las facturas viven en retenciones/AAAA/MM/ con nombre aleatorio.
CREATE TABLE IF NOT EXISTS retenciones_ingresos (
  id            int unsigned  NOT NULL AUTO_INCREMENT,
  fecha         date          NOT NULL,
  periodo       varchar(20)   NOT NULL,              -- mes en letras: 'Noviembre'
  monto         decimal(14,2) NOT NULL,
  creado_por_id int           DEFAULT NULL,          -- empleado que registró el ingreso
  creado_por    varchar(100)  DEFAULT NULL,          -- nombre al momento de registrarlo
  creado_en     datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  eliminado_en  datetime      DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_ret_ingresos_fecha (eliminado_en, fecha),
  CONSTRAINT fk_ret_ingresos_creado_por FOREIGN KEY (creado_por_id) REFERENCES empleados (id_empleado) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS retenciones_facturas (
  id               int unsigned  NOT NULL AUTO_INCREMENT,
  id_ingreso       int unsigned  NOT NULL,
  numero           varchar(50)   NOT NULL,
  objetivo         varchar(150)  NOT NULL,
  observaciones    text          DEFAULT NULL,
  adjunto_nombre   varchar(255)  DEFAULT NULL,       -- nombre original del archivo
  adjunto_disco    varchar(80)   DEFAULT NULL,       -- AAAA/MM/aleatorio.ext
  adjunto_mime     varchar(120)  DEFAULT NULL,
  adjunto_tamanio  int unsigned  DEFAULT NULL,
  creado_por_id    int           DEFAULT NULL,       -- empleado que cargó la factura
  creado_por       varchar(100)  DEFAULT NULL,       -- nombre al momento de cargarla
  creado_en        datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  eliminado_en     datetime      DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_ret_facturas_ingreso (id_ingreso, eliminado_en),
  CONSTRAINT fk_ret_facturas_ingreso FOREIGN KEY (id_ingreso) REFERENCES retenciones_ingresos (id) ON DELETE CASCADE,
  CONSTRAINT fk_ret_facturas_creado_por FOREIGN KEY (creado_por_id) REFERENCES empleados (id_empleado) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
