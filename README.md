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
