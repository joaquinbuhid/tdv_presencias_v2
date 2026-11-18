-- Agrega el estado intermedio "convocado" entre entrevistado y contratado.
-- Flujo: entrevista (convocado=0, contratado=0) -> convocado (convocado=1) -> contratado (contratado=1).
-- Ejecutar una sola vez sobre la base de produccion (admin/migraciones.php).

ALTER TABLE entrevistas
  ADD COLUMN IF NOT EXISTS convocado tinyint(1) NOT NULL DEFAULT 0;

-- Los ya contratados se consideran convocados (al devolverlos vuelven a Convocados).
UPDATE entrevistas SET convocado = 1 WHERE contratado = 1;
