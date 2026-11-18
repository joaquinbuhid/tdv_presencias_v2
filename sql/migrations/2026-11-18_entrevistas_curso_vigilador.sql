-- Agrega a entrevistas la verificación del curso de vigilador hecha por el entrevistador.
-- curso_vigilador_verificado: 'si'/'no'. Es independiente de curso_habilitante,
-- que es lo que el postulante declaró en el formulario público.
-- Ejecutar una sola vez sobre la base de produccion (admin/migraciones.php).

ALTER TABLE entrevistas
  ADD COLUMN IF NOT EXISTS curso_vigilador_verificado enum('si','no') DEFAULT NULL AFTER familiares;
