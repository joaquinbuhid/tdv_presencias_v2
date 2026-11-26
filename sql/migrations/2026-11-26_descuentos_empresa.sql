-- Descuentos: empresa de cada vigilador (Track Seguridad, TDV SRL, CCM o Limpieza).
ALTER TABLE descuentos_items ADD COLUMN empresa varchar(30) DEFAULT NULL AFTER nombre;
