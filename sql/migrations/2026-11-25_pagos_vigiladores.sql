-- Pagos a vigiladores (admin/pagos.php).
-- Una lista por pago (ej. "Noviembre 2026"). Cada fila es un vigilador (nombre a mano),
-- con monto, observaciones y banco. Se guarda quién hizo cada paso.
CREATE TABLE IF NOT EXISTS pagos_listas (
  id             int unsigned NOT NULL AUTO_INCREMENT,
  nombre         varchar(120) NOT NULL,
  creado_por_id  int          DEFAULT NULL,
  creado_por     varchar(100) DEFAULT NULL,
  creado_en      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  eliminado_en   datetime     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_pagos_listas (eliminado_en, creado_en),
  CONSTRAINT fk_pagos_listas_creado_por FOREIGN KEY (creado_por_id) REFERENCES empleados (id_empleado) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagos_items (
  id             int unsigned  NOT NULL AUTO_INCREMENT,
  id_lista       int unsigned  NOT NULL,
  nombre         varchar(150)  NOT NULL,              -- nombre completo del vigilador
  monto          decimal(14,2) DEFAULT NULL,          -- NULL = todavía sin cargar
  observaciones  varchar(500)  DEFAULT NULL,
  banco          varchar(80)   DEFAULT NULL,          -- NULL = sin banco asignado
  -- Paso 1: quién agregó el nombre
  creado_por_id  int           DEFAULT NULL,
  creado_por     varchar(100)  DEFAULT NULL,
  creado_en      datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Paso 2: quién cargó el monto (último cambio)
  monto_por_id   int           DEFAULT NULL,
  monto_por      varchar(100)  DEFAULT NULL,
  monto_en       datetime      DEFAULT NULL,
  -- Paso 3: quién asignó el banco (último cambio)
  banco_por_id   int           DEFAULT NULL,
  banco_por      varchar(100)  DEFAULT NULL,
  banco_en       datetime      DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_pagos_items_lista (id_lista),
  CONSTRAINT fk_pagos_items_lista FOREIGN KEY (id_lista) REFERENCES pagos_listas (id) ON DELETE CASCADE,
  CONSTRAINT fk_pagos_items_creado_por FOREIGN KEY (creado_por_id) REFERENCES empleados (id_empleado) ON DELETE SET NULL,
  CONSTRAINT fk_pagos_items_monto_por  FOREIGN KEY (monto_por_id)  REFERENCES empleados (id_empleado) ON DELETE SET NULL,
  CONSTRAINT fk_pagos_items_banco_por  FOREIGN KEY (banco_por_id)  REFERENCES empleados (id_empleado) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
