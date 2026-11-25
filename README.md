# TDV Presencias v2

Sistema de presencias adaptado a la estructura nueva de base de datos basada en `empleados`, `objetivos`, `empresas` y `novedades`.

## Configuracion de base de datos

No se versionan credenciales reales. Para configurar el hosting:

1. Copiar `config/db.local.example.php` como `config/db.local.php`.
2. Completar `DB_HOST`, `DB_USER`, `DB_PASS` y `DB_NAME`.
3. Verificar que `config/db.local.php` no se suba al repositorio.

Tambien se pueden usar variables de entorno:

- `DB_HOST`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

## Roles

El sistema usa `empleados.tipo` para diferenciar roles:

- `1`: vigilador
- `2`: supervisor
- `3`: oficinista
- `4`: administrador

## Base

El archivo `sql/schema.sql` contiene el esquema esperado y datos semilla minimos para `tipo_novedad` y un administrador inicial de desarrollo.

Las migraciones incrementales viven en `sql/migrations/`. Si la base de produccion fue creada con una version anterior del esquema, aplicarlos desde `admin/migraciones.php` (solo administrador real; registra las aplicadas en la tabla `migraciones`) o ejecutarlos a mano en orden.

Cambiar o eliminar el administrador inicial antes de produccion.

## Postulantes

La tabla `postulantes` es compartida con el formulario publico de postulacion. Los usuarios de backoffice, incluyendo `tipo=3` oficinista, pueden consultar y filtrar esos registros desde `admin/postulantes.php`.

## Retenciones

`admin/retenciones.php` (menú **Administración → Retenciones**). Acceden los usuarios de backoffice (admin y oficinista).

1. Se registra un **ingreso**: fecha, período (mes) y monto. Queda guardado quién lo registró.
2. Con el botón **+** de la fila se agregan una o más **facturas** a ese ingreso: número, objetivo, observaciones y adjunto opcional. Se apilan en la misma fila y queda guardado quién subió cada una.
3. Al tocar una factura se ve el detalle y el adjunto. Eliminar ingresos o facturas es sólo para administradores (borrado lógico).

- Tablas `retenciones_ingresos` y `retenciones_facturas` (migración `2026-11-25_retenciones.sql`), con `creado_por_id` (empleado) y `creado_por` (nombre).
- Adjuntos en `retenciones/AAAA/MM/` con nombre aleatorio. `retenciones/.gitignore` evita que se suban al repo y `retenciones/.htaccess` bloquea el acceso directo; se sirven por `admin/api/ver_retencion.php` con sesión.
- PHP necesita permiso de escritura sobre `retenciones/` y la extensión `fileinfo`. Límite: 20 MB por adjunto.

## Pagos a vigiladores

`admin/pagos.php` (menú **Administración → Pagos vigiladores**). Acceden los usuarios de backoffice (admin y oficinista). Hay una lista por cada pago (ej. "Noviembre 2026"), con historial.

1. **Nombres y montos:** se agregan los nombres a mano (de a uno o pegando varios, uno por línea). Monto y observaciones se completan en la misma tabla y se guardan solos; abajo está el total y cuántos montos faltan.
2. **Separar por banco:** se asigna el banco de cada vigilador (o de varios marcados a la vez). Se muestran agrupados por banco con subtotal y el total general.
3. **Resumen por banco:** tabla Banco | Monto con el total, generada sola.

- Tablas `pagos_listas` y `pagos_items` (migración `2026-11-25_pagos_vigiladores.sql`). Se guarda quién agregó cada nombre, quién cargó cada monto y quién asignó cada banco.
- Exportar a Excel (`admin/api/export_pagos_excel.php`): .xlsx sin formato con hojas Lista, Por banco y Resumen.
- Eliminar listas es sólo para administradores (borrado lógico).

## Descuentos

`admin/descuentos.php` (menú **Administración → Descuentos**). Una lista por período (ej. "Descuentos Noviembre 2026").

1. **Carga:** los usuarios de backoffice arman la tabla **Vigilador | Adelantos | Desc. Ropa/médico | Otros | Total**, varios a la vez. Cada celda se guarda sola y la pantalla se actualiza cada 8 segundos con lo que cargan los demás. Si dos personas cambian el mismo dato, el segundo recibe un aviso con el valor actual en vez de pisarlo. No se puede repetir un vigilador en la misma lista.
2. **Revisión:** un administrador pasa la lista a revisión; desde ahí sólo los administradores pueden editar (se puede reabrir la carga). El administrador exporta el Excel **Vigilador | Total** (sólo vigiladores con descuento).

- Tablas `descuentos_listas`, `descuentos_items` y `descuentos_historial` (migración `2026-11-25_descuentos.sql`). El historial registra cada alta, cambio (valor anterior y nuevo), baja y cambio de etapa, con usuario y fecha.
- Eliminar listas es sólo para administradores (borrado lógico).
