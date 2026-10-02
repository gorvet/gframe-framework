# Configuración

GFrame separa la configuración en tres niveles:

1. Los valores internos del framework se encuentran en `config/defaults.php` y no se modifican desde la aplicación.
2. Cada proyecto define su estructura estable en `config/app.php`.
3. Cada entorno guarda sus valores y secretos en `.env`, archivo que no se versiona.

El archivo `.env.example` documenta las variables necesarias sin contener secretos.

Los tres niveles se combinan. `defaults.php` aporta un valor seguro, `app.php` decide si el proyecto lo conserva o lo sustituye y `.env` aporta el dato específico del servidor. No es necesario definir `APP_URL` durante una petición web: GFrame detecta dinámicamente dominio, protocolo y subcarpeta. `APP_URL` queda disponible para correos, cron o consola, donde no existe una petición desde la que inferirla.

## Acceso

```php
$name = config('app.name');
$connection = config('database.connections.main');
```

Dentro de `config/app.php` pueden utilizarse los ayudantes `env()`, `env_bool()` y `env_int()`.

## Compatibilidad

En la versión `1.x`, GFrame mantiene las constantes históricas principales. Estas constantes se generan desde la configuración estructurada y permiten actualizar gradualmente las aplicaciones existentes.

Las aplicaciones con tenant pueden definir `tenancy.key` y `tenancy.table`. Ambas opciones son obligatorias entre sí. Si se omiten, los permisos funcionan en modo global.

`seo.enabled` controla sitemap, robots y `llms.txt`; cada recurso conserva además su activador individual. En modo debug se impiden la indexación, sitemap y `llms.txt`. `robots.txt` puede mantenerse para declarar el bloqueo.

Metricool se configura de forma independiente:

```php
'analytics' => [
    'enabled' => true,
    'metricool' => ['enabled' => true],
],
```

En modo debug no se carga Metricool aunque ambos activadores estén habilitados.

La renovación de contraseñas es opcional:

```php
'auth' => [
    'password_expiration' => [
        'enabled' => false,
        'days' => 90,
        'warning_days' => 7,
    ],
],
```

El middleware `admin` exige el permiso `admin.access`. El rol de sistema `superadministrator` lo omite mediante una regla interna.
