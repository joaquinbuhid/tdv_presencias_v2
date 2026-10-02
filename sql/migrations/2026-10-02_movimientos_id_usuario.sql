-- Alinea las tablas de movimientos con sql/schema.sql.
-- Necesario para que admin/api/guardar_vigilador.php e insertar_usuario.php
-- puedan registrar la asignacion inicial / quita de objetivo y empresa.
-- Ejecutar una sola vez sobre la base de produccion (phpMyAdmin o CLI).

ALTER TABLE movimientos_objetivos
  MODIFY objetivo_ant_id int(11) DEFAULT NULL,
  MODIFY objetivo_nuevo_id int(11) DEFAULT NULL,
  ADD COLUMN id_usuario int(11) DEFAULT NULL,
  ADD KEY idx_mov_obj_usuario (id_usuario),
  ADD CONSTRAINT fk_mov_obj_usuario FOREIGN KEY (id_usuario) REFERENCES empleados (id_empleado);

ALTER TABLE movimientos_empresas
  MODIFY empresa_ant_id int(11) DEFAULT NULL,
  MODIFY empresa_nuevo_id int(11) DEFAULT NULL,
  ADD COLUMN id_usuario int(11) DEFAULT NULL,
  ADD KEY idx_mov_emp_usuario (id_usuario),
  ADD CONSTRAINT fk_mov_emp_usuario FOREIGN KEY (id_usuario) REFERENCES empleados (id_empleado);
