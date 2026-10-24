# Checklist de despliegue seguro

Lista de comprobación antes de publicar una aplicación GFrame en producción. No sustituye una auditoría; reúne las opciones que el framework ya ofrece y que conviene verificar en el entorno real. Comprueba cada punto con las cabeceras y respuestas reales del servidor, no solo con la configuración.

## Entorno y errores

- [ ] `app.debug` en `false` (constante `DebugMode`). En depuración se muestran detalles técnicos y volcados; nunca en producción. Ver [Configuración](configuracion.md) y [Errores](errores.md).
- [ ] `app.environment` en `production`.
- [ ] `APP_URL` (`app.url`) configurada con la URL pública real. Los enlaces de verificación y recuperación la usan y no dependen del `Host` de la petición.
- [ ] `.env` fuera del árbol público y fuera de Git; sin secretos en `config/*.php`.

## Transporte y servidor web

- [ ] HTTPS en todo el sitio; redirección de HTTP a HTTPS en el servidor web.
- [ ] Solo `index.php` y los assets públicos permitidos se sirven directamente. `app/`, `packages/`, `storage/` y `.env` no son accesibles por el navegador. Ver [Servidores web](servidores-web.md).
- [ ] Si la aplicación está detrás de un proxy/CDN, declara `security.trusted_proxies`; no confíes en cabeceras `X-Forwarded-*` de clientes no verificados.

## Sesión y CSRF

- [ ] Cookie de sesión con `HttpOnly` (`session.cookie_httponly`), `SameSite` (`session.cookie_samesite`, `Lax` o `Strict`) y `Secure` activo bajo HTTPS (`session.cookie_secure`; automático por defecto). Ver [Sesiones](sesiones.md).
- [ ] `session.idle_timeout` acorde a la sensibilidad de la aplicación.
- [ ] Driver de sesión adecuado (`native`, `database` o `redis`) y, si se comparte Redis, `session.redis.prefix` propio por proyecto.
- [ ] Formularios públicos: considera `security.csrf_guest_max_age` para caducar el token de invitado.

## Cabeceras de seguridad

- [ ] `security.headers.enabled` activo. Revisa `referrer_policy`, `frame_options`, `content_security_policy` y `hsts_max_age` según el caso. Verifica las cabeceras reales de una respuesta.

## Acceso y credenciales

- [ ] Roles y permisos revisados; sin cuentas de prueba ni superadministradores olvidados. Ver [Roles y permisos](permisos.md).
- [ ] Límite de intentos de acceso activo para frenar fuerza bruta.
- [ ] Webhooks entrantes con secreto configurado (`WEBHOOK_DEFAULT_SECRET` o el secreto por ruta); nunca sin verificación. Ver [Acceso API](api-access.md).
- [ ] Claves de cifrado presentes y fuera de Git. Ver [Cifrado de datos](encryption.md).

## Datos y logging

- [ ] Entrada de usuario sanitizada/escapada en la salida. Ver [Sanitización y escape](sanitizacion.md).
- [ ] Columnas dinámicas de ordenación/filtrado resueltas con una lista blanca antes de llegar al ORM. Ver [ORM](orm.md).
- [ ] `logging.level` y `logging.max_bytes` ajustados; sin secretos registrados deliberadamente. Ver [Helpers PHP](helpers-php.md).
- [ ] Permisos del sistema de archivos: `storage/` y `logs/` escribibles por la aplicación, no accesibles públicamente.

## Mantenimiento

- [ ] Instalador bloqueado tras instalar (no responde con configuración presente).
- [ ] Dependencias actualizadas y sin vulnerabilidades conocidas (`composer audit`).
- [ ] Copias de seguridad y plan de rotación de claves/secretos.
