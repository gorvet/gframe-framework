# GFrame

GFrame es un framework PHP ligero orientado a aplicaciones web, paneles administrativos y servicios HTTP. Reúne en un mismo núcleo el enrutamiento, los middleware, el acceso a datos, el renderizado de vistas, los metadatos, la gestión de errores y otras utilidades compartidas.

GFrame se encuentra en una etapa de estabilización. La serie `0.x` mantiene compatibilidad con la arquitectura existente mientras se incorporan namespaces, contratos estables, pruebas y herramientas de instalación.

Incluye autenticación adaptable, ejecución asíncrona, tareas programadas y procesamiento de colas de notificaciones mediante contratos definidos por cada proyecto.

La primera versión pública será `0.9.0`.

## Requisitos

- PHP 8.1 o superior.
- Composer 2.
- Extensiones JSON y Mbstring.
- MySQL o SQLite según la aplicación.

## Instalación

```bash
composer require gorvet/gframe
```

Mientras el paquete no esté publicado en Packagist, puede instalarse desde GitHub o mediante un repositorio local de tipo `path`.

## Desarrollo

```bash
composer install
composer check
```

## Estado

Esta primera etapa estabiliza el núcleo, la instalación mediante Composer y el funcionamiento tanto en aplicaciones globales como en aplicaciones con tenant.

Consulta [la arquitectura](docs/arquitectura.md), [la configuración](docs/configuracion.md), [la autenticación](docs/autenticacion.md), [las colas de notificaciones](docs/notificaciones.md), [el versionado](docs/versionado.md), [el plan de extracción](docs/plan-extraccion.md), [la hoja de ruta](docs/roadmap.md) y [la política de dependencias](docs/dependencias.md).

## Licencia

GFrame se distribuye bajo la licencia MIT.
