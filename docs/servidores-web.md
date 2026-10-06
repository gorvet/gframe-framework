# Apache y Nginx

El servidor entrega los recursos públicos y envía las peticiones de aplicación a `index.php`. El router mantiene sus rutas declarativas y el motor de vistas existente. El directorio raíz del sitio es la raíz del proyecto, no `public`.

En Apache, el proyecto ya incluye `.htaccess`: no necesitas editarlo para instalar o declarar rutas. El servidor debe permitir sus reglas. En Nginx, que no interpreta `.htaccess`, integra el fragmento `nginx.conf` de GFrame en la configuración del sitio siguiendo el apartado siguiente.

## Nginx

El instalador y `composer gframe:update` publican `nginx.conf`. Es un fragmento para incluir dentro del bloque `server` del sitio, no un virtual host completo. No instala ni recarga Nginx automáticamente.

```nginx
server {
    listen 80;
    server_name ejemplo.com;
    root /var/www/mi-proyecto;

    include /var/www/mi-proyecto/nginx.conf;
    include enable-php-81.conf;
}
```

Conserva una sola inclusión PHP del panel, con la versión correspondiente al sitio. El fragmento de GFrame no configura sockets, FastCGI ni manejadores PHP.

Si el manejador PHP del panel intercepta errores, se puede conservar intacto y definir un manejador exacto para el controlador frontal dentro del `server` de este proyecto, después de incluir `nginx.conf`:

```nginx
location = /index.php {
    try_files /index.php =404;
    include fastcgi.conf;
    fastcgi_pass unix:/tmp/php-cgi-81.sock;
    fastcgi_intercept_errors off;
    fastcgi_param GFRAME_SERVER_ERROR $gframe_server_error;
}
```

El socket del ejemplo corresponde a la instalación habitual de PHP 8.1 en aaPanel; usa el socket o dirección real de PHP-FPM en tu servidor. `fastcgi.conf` debe existir en el directorio de configuración de Nginx y aportar los parámetros FastCGI normales, incluido `SCRIPT_FILENAME`. El bloque exacto tiene prioridad sobre el manejador PHP genérico del panel. No se edita ni se copia `enable-php-81.conf`, y su inclusión se conserva para el instalador.

`fastcgi_intercept_errors off` conserva las respuestas de error generadas por PHP. `GFRAME_SERVER_ERROR` comunica al framework los errores originados en Nginx. Ambas directivas pertenecen al manejador que realmente ejecuta `index.php`; declararlas únicamente en `server` no garantiza su efecto si el manejador PHP define sus propios parámetros u opciones. No dupliques otro `location = /index.php` existente.

El fragmento queda junto a `.htaccess` y bloquea el acceso web a `/nginx.conf`. Si una instalación anterior usa `deployment/nginx.conf` o `config/server/nginx.conf`, cambia su `include` a la raíz después de actualizar. Las copias antiguas se conservan para respetar personalizaciones.

Conserva fuera del fragmento los certificados, redirección HTTPS, dominio, listeners, HTTP/2 o HTTP/3, registros, monitorización y límites del servidor. Ajusta `client_max_body_size` a los límites de Multimedia y los de PHP. El fragmento no habilita CORS indiscriminadamente; las API conservan sus middleware y los recursos que requieran CORS necesitan una política explícita del sitio.

Incluye GFrame antes del manejador PHP y de los bloques estáticos del panel, para que sus bloqueos se evalúen primero. Quita del `server` la inclusión de rewrite del sitio, o conserva ese archivo vacío: GFrame ya define `location /`. Conserva las inclusiones nativas de PHP y de extensiones del panel. No dupliques las reglas de protección que ya aporta el fragmento.

Antes de aplicar la configuración:

```bash
nginx -t
```

Solo tras una validación satisfactoria, recarga Nginx mediante el mecanismo del sistema o del panel.

### Enrutamiento y protección

- Solo las ubicaciones públicas autorizadas entregan archivos existentes; las demás peticiones pasan por `/index.php`, sin añadir segmentos a `SCRIPT_NAME` ni a la URL.
- Solo `/index.php` e `/install.php` se ejecutan como PHP. El instalador se bloquea al completar la instalación; puede denegarse explícitamente en producción.
- Los directorios internos, archivos ocultos, Composer, bases de datos, logs, copias de seguridad y otros scripts PHP no son públicos.
- Una cabecera AJAX o de webhook no elimina esos bloqueos.
- Los tokens ACME se permiten únicamente bajo `/.well-known/acme-challenge/`, con nombres de token válidos.
- `public` sirve archivos, no PHP. Si un proyecto expone scripts adicionales deliberadamente, necesita una regla y una revisión propias; no se habilitan todos los scripts por defecto.

La biblioteca estándar permite acceso directo a `uploads/library/`, `uploads/user/<id>/library/` y `uploads/tenant/<id>/library/`. Son archivos públicos: separar carpetas por usuario o tenant no protege su descarga. Otras fuentes de `uploads/`, así como `download/` y `downloads/`, quedan bloqueadas al acceso directo. Las rutas de descarga con otros nombres siguen llegando al controlador, que valida permisos o tokens y entrega el archivo. Para una biblioteca privada, retira también su autorización pública y usa una ruta controlada. No guardes archivos privados en `public/` ni en las ubicaciones autorizadas.

Fuentes multimedia adicionales requieren una autorización explícita en ambos servidores, situada después de los bloqueos de scripts y archivos sensibles. Un SDK publicado bajo `public/wcapi/` no necesita otra excepción. No abras `storage/exports/` para servir ZIP: deben descargarse mediante el controlador. Archivos arbitrarios en la raíz, como copias ZIP o JSON, no se entregan directamente.

El fragmento suministrado está preparado para un proyecto en la raíz del dominio. Una instalación bajo un prefijo de URL requiere adaptar las ubicaciones y `SCRIPT_NAME`.

### Errores del servidor y errores de la aplicación

Los `error_page` de 403, 404, 500 y 503 dirigen a ubicaciones internas con nombre. Cada una fija `$gframe_server_error` y redirige internamente a `/index.php`; el manejador PHP del panel envía el código en `GFRAME_SERVER_ERROR`. La petición conserva URL y método originales. El router reconoce el código antes de ejecutar rutas o normalizar la URL y utiliza `ErrorResponder` y las vistas existentes. Nunca acepta ese código desde `?error_code=`, el cuerpo de un formulario ni una cabecera HTTP.

El resultado depende del canal original: web usa las vistas y estados HTTP correspondientes; AJAX conserva JSON con estado 200; API conserva JSON y el estado HTTP; webhook usa texto y SSE conserva su formato de eventos.

`fastcgi_intercept_errors off` conserva los errores ya procesados por GFrame, incluidos JSON y vistas. No se interceptan y ejecutan de nuevo las operaciones PHP. `recursive_error_pages off` evita encadenar manejadores de error.

Un 502/504 por PHP-FPM caído no puede renderizar una vista PHP. Debe conservar una respuesta nativa o una página estática del servidor; enviar otra vez esa petición al mismo PHP caído no lo resuelve. Tampoco se promete una vista de GFrame cuando el bootstrap o sus dependencias no pueden cargar.

## Apache

El proyecto publica `.htaccess`, con `mod_rewrite` y permisos `AllowOverride` apropiados. Mantiene el controlador frontal y bloquea archivos internos, ocultos y scripts PHP que no sean los puntos de entrada. No autoriza acceso mediante cabeceras.

Apache comunica los errores internos a PHP mediante `REDIRECT_STATUS`; el router acepta los mismos cuatro estados. El esqueleto conserva los `ErrorDocument` de dominio raíz y el caso local de proyecto en una carpeta bajo `localhost`, incluidos hosts con puerto. Para prefijos distintos o Alias personalizados, ajusta los `ErrorDocument` al punto de entrada real del proyecto. No se deduce arbitrariamente ese prefijo a partir del nombre del dominio.

## Prueba reproducible

`ServerRoutingTest` comprueba el contrato de parámetros confiables y la ausencia de configuración específica del panel en el fragmento. Su prueba HTTP optativa crea un proyecto y procesos temporales de Nginx y PHP-CGI, sin modificar servidores existentes.

```powershell
$env:GFRAME_TEST_NGINX = 'C:/ruta/nginx.exe'
$env:GFRAME_TEST_PHP_CGI = 'C:/xampp/php/php-cgi.exe'
php packages/phpunit/phpunit/phpunit tests/ServerRoutingTest.php
```

Después de configurar el servidor, comprueba una URL válida, `robots.txt`, `sitemap.xml`, una URL inexistente y un recurso público inexistente. Las URL inexistentes deben conservar el estado HTTP 404 y mostrar la vista del framework, no la página nativa de Nginx. Estas comprobaciones del despliegue no se sustituyen por la suite del paquete.

Referencias oficiales: [error_page y ubicaciones internas de Nginx](https://nginx.org/en/docs/http/ngx_http_core_module.html#error_page), [parámetros FastCGI e interceptación](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_param), [errores personalizados de Apache](https://httpd.apache.org/docs/2.4/custom-error.html).


## Lista de comprobación de despliegue

Después de configurar Apache o Nginx, comprueba de forma explícita:

| Petición | Resultado esperado |
| --- | --- |
| Ruta válida de la aplicación | Respuesta del controlador correspondiente |
| Archivo existente bajo `public/` | Entrega directa, sin pasar por Router |
| URL inexistente | Vista de error GFrame con HTTP 404 |
| Recurso inexistente bajo `public/` | HTTP 404, sin revelar rutas internas |
| `/.env`, `/composer.json` o archivo interno | Acceso bloqueado |
| Script PHP distinto de `index.php` o `install.php` | Acceso bloqueado |
| `/robots.txt` | Respuesta dinámica cuando corresponde |
| `/sitemap.xml` | Respuesta dinámica solo si SEO e indexación lo permiten |
| Descarga protegida | Pasa por controlador y conserva autorización |
| Archivo de biblioteca pública autorizada | Entrega directa únicamente en las rutas permitidas |

Comprueba también un error 403/500/503 cuando sea reproducible en el entorno. El objetivo es distinguir errores que GFrame puede renderizar de fallos donde PHP-FPM o el bootstrap completo ya no están disponibles.

En Nginx ejecuta `nginx -t` antes de recargar. En Apache revisa que `AllowOverride` permita las directivas usadas por `.htaccess`. Una configuración que funciona para la portada pero expone archivos internos no se considera válida.
