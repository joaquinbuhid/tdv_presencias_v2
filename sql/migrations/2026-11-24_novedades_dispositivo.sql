-- Identificación del dispositivo desde el que se registró cada marca.
-- dispositivo_id: código que genera el navegador la primera vez y guarda (localStorage + cookie).
-- modelo_disp:    modelo informado por el navegador (Chrome/Edge en Android; vacío en iPhone).
-- user_agent:     navegador y sistema operativo.
ALTER TABLE novedades
  ADD COLUMN IF NOT EXISTS dispositivo_id varchar(64) DEFAULT NULL AFTER ip_dispositivo,
  ADD COLUMN IF NOT EXISTS modelo_disp varchar(100) DEFAULT NULL AFTER dispositivo_id,
  ADD COLUMN IF NOT EXISTS user_agent varchar(255) DEFAULT NULL AFTER modelo_disp;

CREATE INDEX IF NOT EXISTS idx_novedades_dispositivo ON novedades (dispositivo_id);
