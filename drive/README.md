# Drive – Facturas y Pagos

Galería web para subir y ver comprobantes. Entrada en el menú del backoffice: **Administración → Facturas y pagos** (`drive/index.php`).

## Puesta en marcha
1. Aplicar la migración `sql/migrations/2026-11-25_drive_archivos.sql` desde `admin/migraciones.php` (o a mano).
2. Dar permiso de escritura a PHP sobre `drive/storage/`.
3. Extensiones PHP: `pdo_mysql`, `fileinfo`, `gd` (miniaturas).
4. Para archivos de hasta 20 MB, en `php.ini`: `upload_max_filesize = 20M`, `post_max_size = 100M`.

Usa la conexión de `config/db.php` y el login/roles de `admin/auth.php`: entran administradores y oficinistas.

## Estructura
```
drive/
├── index.php        Interfaz (galería, arrastrar y soltar, visor)
├── api.php          Listar / subir / renombrar / eliminar (JSON)
├── ver.php          Sirve archivos y miniaturas (con control de sesión)
├── config.php       Carpetas, tipos permitidos y límites
├── auth.php         Enganche con admin/auth.php + CSRF
├── assets/          drive.css, drive.js
└── storage/         Bloqueado por .htaccess
    ├── facturas/    .gitignore → AAAA/MM/archivo-aleatorio.ext
    ├── pagos/       .gitignore
    └── _thumbs/     .gitignore → miniaturas
```

## Notas
- **Nueva carpeta:** agregarla en `CARPETAS` (config.php) y crear `storage/<clave>/` con su `.gitignore`.
- **Seguridad:** se valida el contenido real del archivo (no sólo la extensión), se guardan con nombre aleatorio y nunca se ejecutan.
- **Eliminar** es borrado lógico (`eliminado_en`); el archivo queda en disco y se recupera poniendo ese campo en `NULL`.
- Los `.gitignore` de `storage/` conservan las carpetas en el repo pero nunca suben los archivos.
