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

## Facturas y pagos

`admin/archivos.php` (menú **Administración → Facturas y pagos**) es una galería para subir y ver comprobantes. Acceden los usuarios de backoffice (admin y oficinista).

- Archivos en `archivos/facturas/` y `archivos/pagos/` (subcarpetas `AAAA/MM/`, nombre aleatorio). Cada carpeta tiene su `.gitignore`, así que nunca se suben al repo; `archivos/.htaccess` bloquea el acceso directo y se sirven por `admin/api/ver_archivo.php` con sesión.
- Índice en la tabla `drive_archivos` (migración `2026-11-25_drive_archivos.sql`). Eliminar es borrado lógico (`eliminado_en`).
- PHP necesita permiso de escritura sobre `archivos/` y las extensiones `fileinfo` y `gd`. Límite: 20 MB por archivo (revisar `upload_max_filesize` / `post_max_size`).
- Para sumar una carpeta: agregarla en `ARCHIVOS_CARPETAS` (`admin/api/archivos_helper.php`) y crear `archivos/<clave>/.gitignore`.
