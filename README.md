# GFrame

GFrame es un framework PHP ligero para aplicaciones web, paneles administrativos y servicios HTTP. Integra routing, middleware, render de vistas, ORM, autenticación, permisos, sesiones, módulos reutilizables y capacidades como multimedia, correo, notificaciones, procesos en segundo plano, cron, SEO e integraciones HTTP.

La serie `1.0` estabiliza el núcleo, la instalación mediante Composer y el funcionamiento tanto en aplicaciones globales como multitenant.

## Requisitos

- PHP 8.1.9 o superior.
- Composer 2.
- Extensiones DOM, JSON, Mbstring y OpenSSL.
- MySQL o SQLite cuando la aplicación utilice base de datos.
- Apache con `mod_rewrite` o Nginx con PHP-FPM para servir una aplicación web.

Algunas capacidades tienen requisitos adicionales. Por ejemplo, el cliente HTTP saliente necesita cURL y Async necesita PHP CLI con `exec()` disponible.

## Crear una aplicación nueva

El generador actual se ejecuta desde una copia del repositorio de GFrame:

```bash
git clone https://github.com/gorvet/gframe-framework.git
cd gframe-framework
composer install
composer new -- ../mi-proyecto
cd ../mi-proyecto
composer install
```

**`composer new` no es un comando nativo de Composer.** Es el script `new` definido por este repositorio y debe ejecutarse desde la copia de `gframe-framework`.

El proyecto generado instala `gorvet/gframe` mediante Composer y después se configura desde `install.php`.

Consulta [Instalación y perfiles](docs/instalacion.md) para Apache/Nginx, base de datos, perfiles y módulos.

## Añadir GFrame a un proyecto existente

Si ya tienes un proyecto Composer y necesitas incorporar el paquete directamente:

```bash
composer require gorvet/gframe
```

Eso instala la librería; **no ejecuta el generador de proyectos ni añade el script `composer new` al proyecto consumidor**.

Mientras el paquete no esté disponible desde el repositorio Composer utilizado por tu entorno, configura GitHub o un repositorio local `path` según tu flujo de desarrollo.

## Empieza a desarrollar

La documentación principal ya no está organizada solo como referencia de clases. Empieza por:

1. [Documentación de GFrame](docs/index.md).
2. [Desarrollar una aplicación con GFrame](docs/guia-desarrollo.md).
3. [Tutorial: Productos de extremo a extremo](docs/tutorial-productos.md).
4. [Arquitectura](docs/arquitectura.md) cuando necesites profundizar en el runtime.

El tutorial muestra en una sola funcionalidad:

```text
Route
  → Middleware
  → Controller
  → Service
  → Model / ORM
  → View / parcial
  → respuesta web o AJAX
```

## Desarrollo del framework

Desde el repositorio de GFrame:

```bash
composer install
composer check
```

`composer check` ejecuta lint, tests y validación de skills.

El catálogo de módulos puede consultarse con:

```bash
composer modules:list
```

o directamente:

```bash
php bin/modules.php list
```

Las dependencias de desarrollo se instalan en `packages/`. Esa carpeta es generada por Composer y no se versiona.

## Actualizar una aplicación

Los proyectos existentes actualizan primero el paquete y después sincronizan los archivos/migraciones administrados:

```bash
composer update gorvet/gframe
composer gframe:update
```

Consulta [Actualizaciones](docs/actualizaciones.md) antes de modificar archivos publicados por módulos o el esqueleto.

## Módulos y personalización

GFrame diferencia entre:

- funcionalidades propias de una aplicación;
- capacidades instalables del catálogo;
- módulos runtime MVC;
- componentes frontend y bibliotecas empaquetadas.

Una funcionalidad normal no necesita convertirse en módulo. Puede vivir simplemente en:

```text
app/controllers/
app/services/
app/models/
app/views/
```

Los originales de los módulos runtime permanecen dentro del paquete. El proyecto crea únicamente las personalizaciones que necesita, con prioridad sobre el original.

Consulta [Usar módulos en una aplicación](docs/modulos-en-aplicacion.md), [Módulos runtime](docs/modulos-runtime.md) y [Extensibilidad](docs/extensibilidad.md).

## Capacidades

Entre las áreas cubiertas por el framework están:

- routing web, AJAX, API, webhook y SSE;
- ORM con MySQL y SQLite;
- autenticación, permisos, tenants y sesiones administradas;
- multimedia;
- correo y plantillas;
- notificaciones, transportes y campañas;
- Async y tareas Cron;
- cliente HTTP saliente;
- cifrado y sanitización HTML;
- SEO, sitemap, robots y JSON-LD;
- WordPress headless;
- módulos y componentes frontend reutilizables.

Consulta [el índice de documentación](docs/index.md) para elegir la guía según la tarea.

## Reconstrucción documental

La rama de reconstrucción mantiene un inventario explícito de documentación cubierta, parcial, ausente u obsoleta en [docs/reconstruccion-documentacion.md](docs/reconstruccion-documentacion.md). El código es la fuente de verdad para resolver contradicciones.

## Licencia

GFrame se distribuye bajo la licencia MIT.
