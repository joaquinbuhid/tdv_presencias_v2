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

Cambiar o eliminar el administrador inicial antes de produccion.
