-- Agrega a entrevistas si el postulante tiene título secundario (lo indica el entrevistador, sin adjunto).
-- titulo_secundario: 'si'/'no'.
-- Ejecutar una sola vez sobre la base de produccion (admin/migraciones.php).

ALTER TABLE entrevistas
  ADD COLUMN IF NOT EXISTS titulo_secundario enum('si','no') DEFAULT NULL;
