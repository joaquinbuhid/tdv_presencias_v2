-- Módulo Facturas y pagos (admin/archivos.php).
-- Los archivos viven en archivos/<carpeta>/AAAA/MM/ con nombre aleatorio; acá queda el índice.
CREATE TABLE IF NOT EXISTS drive_archivos (
  id              int unsigned NOT NULL AUTO_INCREMENT,
  carpeta         varchar(30)  NOT NULL,
  nombre_original varchar(255) NOT NULL,
  nombre_disco    varchar(80)  NOT NULL,
  extension       varchar(10)  NOT NULL,
  mime            varchar(120) NOT NULL,
  tamanio         int unsigned NOT NULL,
  tiene_miniatura tinyint(1)   NOT NULL DEFAULT 0,
  descripcion     varchar(500) DEFAULT NULL,
  subido_por      varchar(100) DEFAULT NULL,
  creado_en       datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  eliminado_en    datetime     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_drive_nombre_disco (nombre_disco),
  KEY idx_drive_carpeta (carpeta, eliminado_en, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
