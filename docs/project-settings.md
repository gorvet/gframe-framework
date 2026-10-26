# Ajustes del proyecto desde base de datos

GFrame permite que cada aplicación guarde desde su panel administrativo los ajustes generales, SEO y Metricool. La aplicación implementa el formulario, los permisos y el almacenamiento. El framework proporciona el contrato de lectura y aplica los valores durante el arranque web y CLI, sin sesión.

## Registro

En `config/app.php`, añade a la sección `app`:

```php
'settings_provider' => DatabaseProjectSettings::class,
```

La clase debe implementar `GFrame\Config\Contracts\ProjectSettingsProvider`, estar disponible por autoload y no requerir argumentos en el constructor. Una clase global puede vivir en `app/services/DatabaseProjectSettings.php`.

Sin proveedor se conserva el comportamiento actual de `.env` y `config/app.php`. Con proveedor, sus valores tienen prioridad. Las opciones que omita mantienen su configuración previa.

## Ejemplo de lectura

Este ejemplo requiere una tabla de la aplicación `project_settings` con columnas `id` y `settings_json`. No se instala una tabla ni una pantalla automáticamente.

```php
<?php
use GFrame\Config\Contracts\ProjectSettingsProvider;

final class DatabaseProjectSettings implements ProjectSettingsProvider
{
    public function resolve(): array
    {
        $pdo = DatabaseManager::connection();
        if (!$pdo instanceof PDO) throw new RuntimeException('Configuración no disponible.');
        $json = $pdo->query('SELECT settings_json FROM project_settings WHERE id = 1')->fetchColumn();
        return $json === false ? [] : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
```

Contenido de ejemplo de `settings_json`:

```json
{
  "app": {
    "name": "Mi sitio",
    "language": "es",
    "timezone": "America/Havana"
  },
  "seo": {
    "enabled": true,
    "allow_indexing": true,
    "sitemap": true,
    "robots": true,
    "llms": true
  },
  "analytics": {
    "enabled": true,
    "metricool": {"enabled": true, "hash": "identificador_metricool"}
  }
}
```

Solo se admiten esas claves. Los indicadores deben ser booleanos; nombre, idioma y zona horaria deben ser cadenas no vacías. La zona debe ser un identificador PHP válido y el idioma debe estar en `app.supported_languages`, definido por el desarrollador según las traducciones disponibles. El idioma seleccionado pasa a ser el predeterminado sin eliminar los otros idiomas. El identificador de Metricool admite letras, números, guiones y guiones bajos.

No se admiten límites, cuotas, políticas de usuarios, credenciales de base de datos, depuración ni configuración del servidor. La conexión de base de datos queda disponible antes de consultar el proveedor. Este no debe depender de rutas, módulos, sesión o constantes del sitio, que se preparan después.

Los ajustes se leen una vez por arranque del proyecto. Los cambios guardados se aplican en la siguiente petición o worker nuevo; los procesos persistentes deben reiniciarse. `ConfigRepository::merge()` después del arranque no actualiza las constantes ya definidas. Si falla el proveedor o sus datos son inválidos, el arranque falla con `project_settings_failed`, sin incluir la excepción original. La aplicación debe validar al guardar y conservar una configuración válida.

Se conserva la protección SEO de páginas privadas y la desactivación de SEO/analítica en depuración. Para correo utiliza el [proveedor SMTP](smtp-provider.md), que resuelve los ajustes en cada envío.
