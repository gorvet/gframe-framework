# Configuración

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

`APP_URL` es opcional para peticiones web. Sin un valor, el framework calcula `site_url` a partir de HTTPS, host, puerto y ubicación del script. Incluye la subcarpeta y normaliza la barra final. El correo construido durante esa petición puede utilizar la URL calculada.

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

La configuración se carga al arrancar cada proceso. Recarga la página o reinicia los trabajadores persistentes después de cambiarla. No imprimas credenciales ni el arreglo completo de configuración en respuestas públicas.
