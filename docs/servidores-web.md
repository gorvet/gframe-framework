# Apache y Nginx

El servidor entrega los recursos públicos y envía las peticiones de aplicación a `index.php`. El router mantiene sus rutas declarativas y el motor de vistas existente. El directorio raíz del sitio es la raíz del proyecto, no `public`.

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

Para comunicar los errores del servidor, añade esta línea dentro del `location` PHP existente del panel, junto a sus parámetros FastCGI:

```nginx
fastcgi_param GFRAME_SERVER_ERROR $gframe_server_error;
```

Mantén allí `fastcgi_intercept_errors off;` para conservar las respuestas de la aplicación. No basta con declarar el parámetro en `server`: los parámetros definidos dentro del manejador PHP sustituyen la herencia de ese nivel. No retires sus parámetros existentes ni crees otro manejador PHP.

El fragmento queda junto a `.htaccess` y bloquea el acceso web a `/nginx.conf`. Si una instalación anterior usa `deployment/nginx.conf` o `config/server/nginx.conf`, cambia su `include` a la raíz después de actualizar. Las copias antiguas se conservan para respetar personalizaciones.

Conserva fuera del fragmento los certificados, redirección HTTPS, dominio, listeners, HTTP/2 o HTTP/3, registros, monitorización y límites del servidor. Ajusta `client_max_body_size` a los límites de Multimedia y los de PHP. El fragmento no habilita CORS indiscriminadamente; las API conservan sus middleware y los recursos que requieran CORS necesitan una política explícita del sitio.

Incluye GFrame antes del manejador PHP y de los bloques estáticos del panel, para que sus bloqueos se evalúen primero. El archivo de rewrite del panel debe quedar vacío si GFrame ya define `location /`. Conserva el manejador PHP del panel.

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

Fuentes multimedia adicionales requieren una autorización explícita en ambos servidores, situada después de los bloqueos de scripts y archivos sensibles. El SDK de WebChat de referencia está bajo `public/wcapi/` y no necesita otra excepción. No abras `storage/exports/` para servir ZIP: deben descargarse mediante el controlador. Archivos arbitrarios en la raíz, como copias ZIP o JSON, ya no se entregan directamente.

El fragmento suministrado está preparado para un proyecto en la raíz del dominio, como el sitio Códice de referencia. No debe usarse sin adaptar las ubicaciones y `SCRIPT_NAME` en una instalación bajo un prefijo de URL. No modifica el sitio Códice ni sus certificados.

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

Comprobado con Nginx 1.28.3 y PHP 8.1.5: home, login, recursos, SDK público, biblioteca global/usuario/tenant, bloqueo de documentos privados y archivos ZIP/JSON fuera de las ubicaciones autorizadas, bloqueo de internos incluso con cabeceras falsificadas, errores 403/404/500/503 con CSS, JSON AJAX/API, SSE y POST de recuperación sin usuario existente. No se enviaron correos ni se cambiaron demos. Apache se comprueba aquí mediante aserciones de configuración, no mediante una prueba HTTP real. PHP 8.4 y el servidor remoto no se han probado aquí.

Referencias oficiales: [error_page y ubicaciones internas de Nginx](https://nginx.org/en/docs/http/ngx_http_core_module.html#error_page), [parámetros FastCGI e interceptación](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_param), [errores personalizados de Apache](https://httpd.apache.org/docs/2.4/custom-error.html).
