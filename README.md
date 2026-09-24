# GFrame

GFrame es un framework PHP ligero orientado a aplicaciones web, paneles administrativos y servicios HTTP. Reúne en un mismo núcleo el enrutamiento, los middleware, el acceso a datos, el renderizado de vistas, los metadatos, la gestión de errores y otras utilidades compartidas.

El proyecto se está extrayendo de aplicaciones reales en producción. La serie `0.x` mantiene compatibilidad con la arquitectura histórica mientras se incorporan namespaces, contratos estables, pruebas y herramientas de instalación.

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

Esta primera etapa centraliza el núcleo compartido por Baseconfías, Bebots, DANE, Libros, RAG y AIPrint MVP. Baseconfías y Bebots son los proyectos de referencia para validar, respectivamente, permisos globales y permisos por tenant.

Consulta [la arquitectura](docs/arquitectura.md), [el plan de extracción](docs/plan-extraccion.md) y [la política de dependencias](docs/dependencias.md).

## Licencia

GFrame se distribuye bajo la licencia MIT.
