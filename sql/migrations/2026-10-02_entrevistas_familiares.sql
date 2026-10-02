-- Agrega a entrevistas la pregunta sobre familiares trabajando en la empresa.
-- tiene_familiares: 'si'/'no'. familiares: descripcion de quien/es (si aplica).
-- Ejecutar una sola vez sobre la base de produccion (admin/migraciones.php).

ALTER TABLE entrevistas
  ADD COLUMN tiene_familiares enum('si','no') DEFAULT NULL,
  ADD COLUMN familiares varchar(255) DEFAULT NULL;
