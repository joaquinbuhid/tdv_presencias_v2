-- Precisión (en metros) que informó el GPS del teléfono al registrar la marca.
ALTER TABLE novedades
  ADD COLUMN IF NOT EXISTS precision_m int(11) DEFAULT NULL;
