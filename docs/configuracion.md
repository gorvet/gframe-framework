# Configuración

GFrame separa la configuración en tres niveles:

1. Los valores internos del framework se encuentran en `config/defaults.php` y no se modifican desde la aplicación.
2. Cada proyecto define su estructura estable en `config/app.php`.
3. Cada entorno guarda sus valores y secretos en `.env`, archivo que no se versiona.

El archivo `.env.example` documenta las variables necesarias sin contener secretos.

## Acceso

```php
$name = config('app.name');
$connection = config('database.connections.main');
```

Dentro de `config/app.php` pueden utilizarse los ayudantes `env()`, `env_bool()` y `env_int()`.

## Compatibilidad

Durante la serie `0.x`, GFrame mantiene las constantes históricas principales. Estas constantes se generan desde la configuración estructurada y permiten actualizar gradualmente las aplicaciones existentes.

Las aplicaciones con tenant pueden definir `tenancy.key` y `tenancy.table`. Ambas opciones son obligatorias entre sí. Si se omiten, los permisos funcionan en modo global.

Los roles que acceden al middleware `admin` se configuran en `auth.administrator_roles`. El superadministrador se identifica por separado en la sesión y siempre conserva una autoridad superior a esos roles.
