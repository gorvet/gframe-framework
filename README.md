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
composer require gframe/framework
```

Mientras el paquete no esté publicado en Packagist, puede instalarse desde GitHub o mediante un repositorio local de tipo `path`.

GFrame crea un proyecto nuevo desde su estructura inicial con:

```bash
composer new -- ../mi-proyecto
```

Después se instalan las dependencias del proyecto y se abre `install.php`. Un único asistente permite elegir un sitio estático, una aplicación administrada o un SaaS multitenant.

## Desarrollo

```bash
composer install
composer check
```

Los proyectos existentes se actualizan con:

```bash
composer update gframe/framework
composer gframe:update
```

Las dependencias de desarrollo se instalan en `packages/`. Esta carpeta es generada por Composer y no se versiona.

## Estado

Esta primera etapa estabiliza el núcleo, la instalación mediante Composer y el funcionamiento tanto en aplicaciones globales como en aplicaciones con tenant.

Bootstrap, jQuery, SweetAlert2, `gframe-icons`, `alerts` y las utilidades frontend forman la base visual instalada automáticamente. Los demás recursos reutilizables se mantienen como módulos seleccionables. El catálogo puede consultarse con `composer modules:list`; sus dependencias se resuelven antes de publicar cada módulo.

Consulta [la arquitectura](docs/arquitectura.md), [la instalación](docs/instalacion.md), [las actualizaciones](docs/actualizaciones.md), [la configuración](docs/configuracion.md), [las sesiones](docs/sesiones.md), [los roles y permisos](docs/permisos.md), [Meta y los recursos de vistas](docs/meta.md), [SEO](docs/seo.md), [el footer](docs/footer.md), [el panel administrativo](docs/panel-administrativo.md), [el soporte Mail](docs/mail.md), [WordPress headless](docs/wordpress-headless.md), [los cambios futuros de BridgeFrame](docs/bridgeframe-pending.md), [la autenticación](docs/autenticacion.md), [el acceso a API entrante](docs/api-access.md), [la interfaz de autenticación](docs/auth-ui.md), [Mi cuenta](docs/self-account.md), [la administración de usuarios](docs/user-admin.md), [el editor de texto enriquecido](docs/rich-text-editor.md), [la gestión de errores](docs/errores.md), [heartbeat y sesión](docs/heartbeat.md), [el inventario de módulos](docs/inventario-modulos.md), [los módulos opcionales](docs/modulos-opcionales.md), [los skills oficiales](docs/skills.md), [las colas de notificaciones](docs/notificaciones.md), [el versionado](docs/versionado.md), [el plan de extracción](docs/plan-extraccion.md), [la hoja de ruta](docs/roadmap.md) y [la política de dependencias](docs/dependencias.md).

## Licencia

GFrame se distribuye bajo la licencia MIT.
