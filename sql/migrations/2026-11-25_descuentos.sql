-- Descuentos a vigiladores (admin/descuentos.php).
-- Una lista por período. Etapa 'carga': editan todos los usuarios de backoffice a la vez.
-- Etapa 'revision': sólo administradores editan y exportan Vigilador | Total.
CREATE TABLE IF NOT EXISTS descuentos_listas (
  id             int unsigned NOT NULL AUTO_INCREMENT,
  nombre         varchar(120) NOT NULL,
  estado         varchar(10)  NOT NULL DEFAULT 'carga',   -- 'carga' | 'revision'
  creado_por_id  int          DEFAULT NULL,
  creado_por     varchar(100) DEFAULT NULL,
  creado_en      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revision_por_id int         DEFAULT NULL,               -- quién la pasó a revisión
  revision_por   varchar(100) DEFAULT NULL,
  revision_en    datetime     DEFAULT NULL,
  eliminado_en   datetime     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_desc_listas (eliminado_en, creado_en),
  CONSTRAINT fk_desc_listas_creado_por  FOREIGN KEY (creado_por_id)   REFERENCES empleados (id_empleado) ON DELETE SET NULL,
  CONSTRAINT fk_desc_listas_revision_por FOREIGN KEY (revision_por_id) REFERENCES empleados (id_empleado) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS descuentos_items (
  id                int unsigned  NOT NULL AUTO_INCREMENT,
  id_lista          int unsigned  NOT NULL,
  nombre            varchar(150)  NOT NULL,             -- vigilador (nombre a mano)
  adelantos         decimal(14,2) DEFAULT NULL,
  ropa_medico       decimal(14,2) DEFAULT NULL,         -- Desc. Ropa/médico
  otros             decimal(14,2) DEFAULT NULL,
  creado_por_id     int           DEFAULT NULL,
  creado_por        varchar(100)  DEFAULT NULL,
  creado_en         datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_por_id int          DEFAULT NULL,         -- último cambio
  actualizado_por   varchar(100)  DEFAULT NULL,
  actualizado_en    datetime      DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_desc_items_lista (id_lista),
  CONSTRAINT fk_desc_items_lista FOREIGN KEY (id_lista) REFERENCES descuentos_listas (id) ON DELETE CASCADE,
  CONSTRAINT fk_desc_items_creado_por FOREIGN KEY (creado_por_id) REFERENCES empleados (id_empleado) ON DELETE SET NULL,
  CONSTRAINT fk_desc_items_actualizado_por FOREIGN KEY (actualizado_por_id) REFERENCES empleados (id_empleado) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de cada cambio (alta, edición y baja de filas)
CREATE TABLE IF NOT EXISTS descuentos_historial (
  id               int unsigned NOT NULL AUTO_INCREMENT,
  id_lista         int unsigned NOT NULL,
  id_item          int unsigned DEFAULT NULL,          -- sin FK: se conserva aunque se borre la fila
  vigilador        varchar(150) NOT NULL,
  accion           varchar(20)  NOT NULL,              -- 'alta' | 'cambio' | 'baja' | 'etapa'
  campo            varchar(20)  DEFAULT NULL,          -- nombre | adelantos | ropa_medico | otros
  valor_anterior   varchar(150) DEFAULT NULL,
  valor_nuevo      varchar(150) DEFAULT NULL,
  usuario_id       int          DEFAULT NULL,
  usuario          varchar(100) DEFAULT NULL,
  fecha            datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_desc_hist_lista (id_lista, fecha),
  KEY idx_desc_hist_item (id_item),
  CONSTRAINT fk_desc_hist_lista FOREIGN KEY (id_lista) REFERENCES descuentos_listas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
