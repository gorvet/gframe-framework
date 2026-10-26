# Configuración

Para leer nombre del sitio, idioma, zona horaria, SEO y Metricool desde ajustes guardados por el panel de tu aplicación, consulta [ajustes del proyecto](project-settings.md). Para correo, consulta el [proveedor SMTP](smtp-provider.md).

## Proxies y cabeceras de seguridad

`security.trusted_proxies` es una lista de direcciones IP o redes CIDR del proxy inmediato; por defecto está vacía. Solo sus conexiones pueden aportar `X-Forwarded-Proto`, `X-Forwarded-Host` y la cadena `X-Forwarded-For` para cookies, origen y límites de autenticación. El proxy debe reescribir esas cabeceras. La cadena de direcciones se recorre desde el extremo confiable hasta el primer salto no confiable. No configures todas las direcciones como confiables. Los enlaces de acceso siguen requiriendo `APP_URL`.

Router emite `X-Content-Type-Options: nosniff` y `Referrer-Policy: strict-origin-when-cross-origin`, conservando cabeceras ya emitidas por la aplicación. Configura `security.headers` en `config/app.php`: `enabled=false` desactiva la emisión, `referrer_policy` cambia la política, `frame_options` permite, por ejemplo, `SAMEORIGIN`, y `content_security_policy` define la CSP del proyecto. Estas dos últimas están desactivadas por defecto para conservar embebidos y recursos existentes. Evita configurar políticas contradictorias en PHP y el servidor.

`hsts_max_age` vale 0 por defecto; un valor positivo emite HSTS únicamente bajo HTTPS confiable. `hsts_include_subdomains` está desactivado. Actívalos solo cuando el host y, si corresponde, todos sus subdominios dispongan de HTTPS permanente: el navegador recuerda HSTS durante el plazo indicado. Las cabeceras del servidor web o proxy deben comprobarse en el despliegue.

El instalador crea `config/app.php` y `.env`. Modifica esos archivos del proyecto, no `config/defaults.php` dentro del paquete.

## Dónde guardar cada dato

| Archivo | Contenido | ¿Se versiona? |
| --- | --- | --- |
| `config/app.php` | Estructura y opciones estables de la aplicación. | Sí. |
| `.env` | Credenciales, URL y valores particulares del servidor. | No. |
| `.env.example` | Nombres de variables y valores de ejemplo sin secretos. | Sí. |

Los valores del paquete actúan como predeterminados. `config/app.php` los sustituye o amplía y puede leer variables del entorno mediante `env()`, `env_bool()` y `env_int()`.

Añadir una variable a `.env` no crea por sí solo una opción de configuración: tu archivo PHP debe leerla.

## Nombre, URL y modo de ejecución

Ejemplo de `.env` para producción:

```dotenv
APP_NAME="Mi proyecto"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ejemplo.com
```

La configuración generada ya conecta esas variables:

```php
'app' => [
    'name' => env('APP_NAME', 'Mi proyecto'),
    'environment' => env('APP_ENV', 'production'),
    'debug' => env_bool('APP_DEBUG', false),
    'url' => env('APP_URL', null),
    'timezone' => 'UTC',
    'language' => 'es',
],
```

`APP_DEBUG=true` muestra información técnica de errores y bloquea la indexación. No lo habilites en producción.

`APP_ENV` identifica el entorno, por ejemplo `local`, `staging` o `production`. El núcleo no cambia automáticamente debug, credenciales ni indexación por escribir otro nombre: esas decisiones dependen de sus opciones específicas. `APP_ENV=production` con `APP_DEBUG=true` sigue exponiendo depuración.

### URL automática y URL explícita

`APP_URL` es opcional para renderizar páginas web. Sin un valor, el framework calcula `site_url` a partir de HTTPS, host, puerto y ubicación del script. Los correos de verificación y recuperación de `auth-ui` requieren una URL configurada: no utilizan el host de la petición. Indica una URL HTTP/HTTPS absoluta, con la subcarpeta si corresponde, sin credenciales, parámetros ni fragmento. Si falta o no es válida, no se inicia el envío y se registra un diagnóstico en el servidor. La recuperación conserva su respuesta genérica; esa respuesta no confirma la entrega del correo.

Antes de actualizar una aplicación con autenticación, configura `APP_URL` en su `.env` o `app.url` en `config/app.php`. El instalador ya genera este valor. Una aplicación que dependía del cálculo automático debe configurar su dirección pública para seguir enviando enlaces de acceso.

Una ejecución CLI independiente, como cron, no recibe el dominio público ni el protocolo de una petición HTTP. El código actual no recupera automáticamente ese contexto de una visita anterior. En esos procesos, configura `APP_URL` con la URL pública para obtener enlaces correctos. Lo mismo se aplica a una tarea asíncrona que arranca un proceso nuevo y construye sus enlaces dentro de él; un enlace construido antes y capturado como dato conserva su valor.

```dotenv
# Proyecto en la raíz del dominio:
APP_URL=https://ejemplo.com
# Para un proyecto en una subcarpeta, utiliza en su lugar:
# APP_URL=https://ejemplo.com/mi-proyecto
```

La detección utiliza los datos del servidor; no interpreta automáticamente todas las cabeceras de un proxy. Si el despliegue termina HTTPS en otro servidor o necesita una URL canónica fija, utiliza la opción explícita y revisa la configuración del proxy.

## Leer una opción

Después del arranque, utiliza claves separadas por puntos:

```php
$name = config('app.name');
$idleSeconds = config('session.idle_timeout', 1800);
```

El segundo argumento es el valor de respaldo cuando la clave no existe.

## Conexión de base de datos

El instalador configura el motor seleccionado. Para cambiar los datos de MySQL, edita `.env`:

```dotenv
DB_HOST=localhost
DB_PORT=3306
DB_NAME=mi_proyecto
DB_USER=mi_usuario
DB_PASSWORD="contraseña-del-servidor"
```

En SQLite, la configuración generada utiliza `DB_SQLITE_PATH`, normalmente `storage/database.sqlite`. La ruta se resuelve respecto a la raíz del proyecto.

Cambiar `DB_DRIVER` por sí solo no convierte una configuración MySQL en SQLite ni migra los datos: debes adaptar también `database.connections` y preparar la base de destino.

## Dos conexiones de base de datos

`database.default` elige la conexión usada por los modelos sin una conexión propia. `database.connections` permite declarar varias conexiones con nombres distintos. Conserva la conexión `main` del instalador y añade, por ejemplo, `catalog` dentro del mismo bloque:

```php
'database' => [
    'default' => 'main',
    'connections' => [
        'main' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env_int('DB_PORT', 3306),
            'database' => env('DB_NAME', ''),
            'username' => env('DB_USER', ''),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],
        'catalog' => [
            'driver' => 'mysql',
            'host' => env('CATALOG_DB_HOST', 'localhost'),
            'port' => env_int('CATALOG_DB_PORT', 3306),
            'database' => env('CATALOG_DB_NAME', ''),
            'username' => env('CATALOG_DB_USER', ''),
            'password' => env('CATALOG_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],
    ],
],
```

Añade las variables `CATALOG_DB_*` a `.env` con los datos de la segunda base. Declarar la conexión no crea sus tablas ni copia datos desde `main`.

Un modelo puede elegirla explícitamente:

```php
<?php

class CatalogProduct extends ORM
{
    protected $table = 'products';
    protected $connection = 'catalog';
}
```

También puedes usar `onConnection('catalog')` en una consulta o solicitar `DatabaseManager::connection('catalog')`. Cada nombre mantiene su conexión y dialecto. Las transacciones de una conexión no coordinan automáticamente operaciones en otra base; consulta [ORM](orm.md) para consultas y transacciones.

## Opciones por función

`config/app.php` devuelve un único array con secciones como `app`, `database` y `session`. Modifica la sección existente de la función que quieras configurar; no añadas una segunda clave del mismo nombre, porque PHP conservaría la última y perderías los otros valores de esa sección.

```php
// Ejemplos de valores dentro de las secciones existentes:
'session' => ['idle_timeout' => 1800],
'media' => ['scope' => 'global', 'quota_bytes' => 0],
```

- [Sesiones](sesiones.md): tiempo de inactividad y almacenamiento.
- [Cron real o virtual](cron-runner.md#seleccionar-cron-real-o-virtual): selección directa desde `.env`, intervalo y límite por pasada.
- [Multimedia](media-library.md): ámbito `global`, `tenant` o `user` y cuota.
- [Autenticación](auth-ui.md): redirecciones y caducidad opcional de contraseñas.
- [Correo](mail.md): credenciales SMTP y envío asíncrono.
- [SEO](seo.md): activación global e indexación por ruta; los datos descriptivos pertenecen a las metas.

Para una aplicación multitenant, `tenancy.key` y `tenancy.table` se definen conjuntamente. Sin ambas, la autorización opera en modo global. Consulta [permisos](permisos.md).

## Añadir configuración propia

Por ejemplo, para limitar una importación:

```php
// Dentro del arreglo de config/app.php:
'import' => ['max_rows' => env_int('IMPORT_MAX_ROWS', 1000)],
```

```dotenv
# En .env:
IMPORT_MAX_ROWS=500
```

```php
// En el servicio del proyecto:
$limit = (int)config('import.max_rows', 1000);
```

Al cambiar `IMPORT_MAX_ROWS` en `.env`, la siguiente petición web leerá el nuevo límite. Si una importación se ejecuta en un worker que permanece activo, reinícialo para que vuelva a cargar la configuración; un proceso ya iniciado conserva los valores que leyó al arrancar.
