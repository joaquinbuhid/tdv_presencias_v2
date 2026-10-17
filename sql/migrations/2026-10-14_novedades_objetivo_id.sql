ALTER TABLE novedades
  ADD COLUMN IF NOT EXISTS objetivo_id int(11) DEFAULT NULL,
  ADD KEY IF NOT EXISTS idx_novedades_objetivo (objetivo_id);
