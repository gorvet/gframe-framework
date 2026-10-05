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

`APP_DEBUG=true` muestra información técnica de errores y desactiva la indexación. No lo habilites en producción.

Sin `APP_URL`, las peticiones web pueden deducir protocolo, dominio y subcarpeta. Define una URL completa para que correo, cron y consola construyan enlaces correctos.

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

## Opciones por función

Añade o modifica estos bloques dentro del arreglo que devuelve `config/app.php`:

```php
'session' => ['idle_timeout' => 1800],
'media' => ['scope' => 'global', 'quota_bytes' => 0],
```

- [Sesiones](sesiones.md): tiempo de inactividad y almacenamiento.
- [Multimedia](media-library.md): ámbito `global`, `tenant` o `user` y cuota.
- [Autenticación](auth-ui.md): redirecciones y caducidad opcional de contraseñas.
- [Correo](mail.md): credenciales SMTP y envío asíncrono.
- [SEO](seo.md): indexación, sitemap, robots y datos de las vistas.

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
