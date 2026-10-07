# Actualizar a GFrame 2.0.0

Esta versión reúne las correcciones verificadas de seguridad y runtime, las mejoras de reservas y migraciones, las catorce skills y su distribución, y el aviso administrativo opcional de registro. El detalle está en el changelog del paquete.

## Compatibilidad desde 1.x

- `whereIn([])` y `orWhereIn([])` agregan una condición falsa. Si una lista vacía significaba «sin filtro» en su aplicación, omita explícitamente esa llamada. Revise también grupos y condiciones OR.
- Cron considera fallidos los resultados `status=error` o `status=failed`; no los reprograma como éxitos. Los handlers sin estado mantienen el comportamiento anterior.
- Webhooks y SSE requieren credenciales verificables según [Rutas](rutas.md); complete configuraciones que antes aceptaban tokens sin verificar.
- Antes de reemplazar el paquete, finalice los workers de notificaciones antiguos. Revise repositorios personalizados que heredan modelos nativos y adapten reservas. El SMTP conserva el riesgo de duplicados tras interrupciones; consulte [Notificaciones por correo](notifications-email.md).
- Las migraciones MySQL incorporan registro por sentencia. Un resultado incierto requiere inspección y resolución explícita; no repita SQL arbitrario. Consulte [Actualizaciones](actualizaciones.md#migraciones-y-recuperación).

Cambie la restricción Composer de la aplicación para permitir `^2.0`, resuelva su lock en desarrollo y pruebe la aplicación antes de desplegarlo. La etiqueta Git `v2.0.0` identifica la versión Composer `2.0.0`; no añada un campo `version` al paquete.

## Sitio de documentación

En la aplicación `gframe-docs`, `APP_ENV=local` y `DOCS_REPOSITORY=C:/xampp/htdocs/gframe-framework` permiten leer los Markdown del checkout al recargar. El runtime sigue siendo el paquete instalado; actualizar la fuente documental no actualiza ese runtime.

Para llevar el runtime de documentación a esta versión, ejecute en una copia de desarrollo del sitio:

```bash
composer require gorvet/gframe:^2.0 --no-update
composer update gorvet/gframe --with-dependencies
composer gframe:update -- --dry-run --no-database --preserve-custom
composer gframe:update -- --no-database --preserve-custom
php tests/check.php
```

Revise los conflictos del actualizador y conserve las personalizaciones del sitio. Versione `composer.json`, `composer.lock`, el registro de instalación y los archivos administrados actualizados que corresponda desplegar.

En producción despliegue esos archivos probados, mantenga `APP_ENV=production` y ejecute:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

La web lee `docs/` del paquete instalado y muestra su versión. No necesita reinstalar el sitio. Si publica desde un checkout Git del sitio, actualice ese checkout al commit probado antes de instalar el lock. Los cambios propios de navegación, vistas y estilos del sitio se despliegan desde su repositorio, además de actualizar el paquete.
