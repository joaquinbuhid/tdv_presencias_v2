ALTER TABLE novedades
  ADD COLUMN objetivo_id int(11) DEFAULT NULL,
  ADD KEY idx_novedades_objetivo (objetivo_id);
