# Arquitectura de GFrame

GFrame separa el núcleo del framework del código de tu aplicación. El núcleo coordina las peticiones, rutas, middleware, renderizado y acceso a datos. Tu proyecto desarrolla sus reglas de negocio sin modificar el paquete instalado por Composer.

Para generar el proyecto, empieza por [Instalación](instalacion.md).

## Las tres capas

1. **Núcleo PHP**: arranque, configuración, Router y RouteBuilder, middleware, Render y Meta, ORM y dialectos, errores, SEO y servicios internos.
2. **Módulos de ampliación**: funcionalidades PHP o híbridas PHP/JavaScript, como usuarios, multimedia, notificaciones y campañas. Los requisitos dependen del perfil: no todos son opcionales en todos los proyectos.
3. **Módulos de vista y frontend**: componentes y utilidades de interfaz, como GF Select, GF Table y alertas visuales. Las bibliotecas externas, como Bootstrap y SweetAlert2, se presentan por separado dentro de esta capa.

## MVC y lógica de la aplicación

GFrame organiza la aplicación mediante controladores, modelos y vistas. Los servicios agrupan los procesos que necesitan varias operaciones o que se reutilizan entre acciones.

| Pieza | Responsabilidad | Ejemplo |
| --- | --- | --- |
| Ruta | Asocia una URL y un método HTTP con una acción, middleware y presentación. | `GET /products` llama a `ProductController@index`. |
| Controlador | Recibe los parámetros de la petición y devuelve el resultado de la acción. | Solicita el listado de productos. |
| Servicio | Ejecuta las reglas y procesos del negocio. | Comprueba disponibilidad y registra una compra. |
| Modelo | Accede a los datos mediante el ORM y la conexión elegida. | Consulta los productos de un tenant. |
| Vista | Presenta los datos devueltos por la acción. | Genera las filas del listado. |
| Template | Proporciona la estructura compartida de las páginas. | Envuelve la vista con el panel administrativo. |

Una acción sencilla puede consultar directamente un modelo o devolver datos sin persistencia. Añade un servicio cuando el proceso lo requiera. Mantén las comprobaciones de permisos sobre registros en el servidor; ocultar un botón en la vista no autoriza una operación.

## Carpetas del proyecto instalado

Estas ubicaciones pertenecen al proyecto. Algunas carpetas aparecen solo cuando el perfil, los módulos o tu desarrollo las necesitan.

| Ubicación | Responsabilidad |
| --- | --- |
| `index.php` | Punto de entrada HTTP |
| `install.php` | Instalador, protegido por el estado de instalación |
| `core/Load.php` | Carga Composer y llama al arranque del framework |
| `config/app.php` | Configuración estructural del proyecto |
| `.env` | Valores del entorno y secretos; no se publica en Git |
| `config/routes/routes_*.php` | Declaraciones de rutas; las convenciones de nombres se explican en la guía de rutas |
| `app/controllers/` | Acciones que atienden peticiones |
| `app/services/` | Procesos y reglas reutilizables del negocio |
| `app/models/` | Modelos y acceso a datos del proyecto |
| `app/views/` | Vistas, metas y sustituciones de vistas de módulos |
| `app/views/templates/` | Templates, header, footer y sus partes |
| `public/` | CSS, JavaScript, imágenes y assets publicados |
| `packages/` | Dependencias de Composer, incluido `gorvet/gframe` |
| `storage/` | Estado interno y archivos de ejecución; no es público |
| `storage/gframe-installed.json` | Registro de instalación y módulos |
| `deployment/` | Ayudas de despliegue, no configuración PHP |
| `.htaccess` y `nginx.conf` | Reglas de integración con el servidor web |

`packages/` cumple la función del directorio de dependencias de Composer. `composer.lock` fija las versiones de paquetes; `storage/gframe-installed.json` registra el estado de instalación del proyecto. No son intercambiables.

No edites `packages/gorvet/gframe/` para personalizar la aplicación: Composer puede reemplazarlo. Algunos archivos publicados también están administrados por el actualizador. Consulta [Actualizaciones](actualizaciones.md) antes de modificar assets compartidos.

Los originales de los módulos permanecen en el paquete. Para rutas asociadas a módulos, las convenciones runtime permiten buscar primero en la aplicación y después en el módulo. Consulta [Módulos runtime](modulos-runtime.md) para las rutas relativas, namespaces y ampliación de clases.

Por ejemplo, una vista de `self-account` conserva su original en `packages/gorvet/gframe/resources/modules/self-account/application/app/views/self-account/`. Su sustitución se coloca en `app/views/self-account/`, con el mismo nombre relativo. Los controladores, modelos y servicios del proyecto amplían las clases del módulo mediante sus contratos; no se copian automáticamente al instalar.

## Carpetas del repositorio del framework

Estas ubicaciones pertenecen al paquete, no al negocio de una aplicación.

| Ubicación | Contenido |
| --- | --- |
| `src/routing/` | Router y RouteBuilder |
| `src/middleware/` | Ejecución de middleware |
| `src/render/` | Render y Meta |
| `src/database/` | ORM, conexiones y dialectos |
| `src/GFrame/` | Componentes con namespace, como configuración, sesiones y runtime de módulos |
| `src/async/`, `src/cron/`, `src/heartbeat/` | Ejecución asíncrona, tareas y heartbeat |
| `src/seo/`, `src/error/` | SEO y errores |
| `src/services/`, `src/utils/` | Servicios y utilidades compartidos |
| `config/defaults.php` | Valores predeterminados del framework |
| `resources/skeleton/` | Fuente del proyecto inicial |
| `resources/modules/<identificador>/` | Manifiesto, código y recursos del módulo |
| `docs/` | Documentación del paquete completo |
| `skills/` | Instrucciones versionadas para asistentes |
| `tests/` | Pruebas del framework |

GFrame combina componentes con namespace `GFrame\` y clases globales, como `Router`, `Render` y `RouteBuilder`. Composer carga ambas mediante PSR-4 y su mapa de clases. No necesitas incluir los archivos del núcleo en cada controlador.

## Recorrido de una petición

```text
Petición HTTP
  → servidor web → index.php → core/Load.php → arranque
  → Router: canal, idioma y ruta → middleware
  ├─ web → Render → acción del controlador → vista y template → HTML
  └─ AJAX / API / webhook / SSE → acción del controlador → respuesta del canal

Dentro de una acción, cuando el caso lo necesita:
  controlador → servicio → modelo → ORM → base de datos
```

Servicios y modelos no son pasos obligatorios. Una acción puede devolver datos sin consultar una base de datos.

En un listado administrado, la primera petición web construye la página. Los filtros, la búsqueda y la paginación utilizan peticiones AJAX que devuelven datos o fragmentos HTML; el JavaScript actualiza el contenedor correspondiente, sin reconstruir el template completo. Esas peticiones siguen pasando por Router, middleware y controlador. Consulta [Frontend core](frontend-core.md) para el contrato de actualización de fragmentos.

### Entrada y preparación

Apache o Nginx entrega las rutas de aplicación a `index.php`. Los archivos públicos permitidos pueden servirse directamente sin ejecutar Router. No abras `app/`, `packages/` o `storage/` al navegador; consulta [Servidores web](servidores-web.md).

`core/Load.php` carga `packages/autoload.php`. Una aplicación sin configuración ni registro de instalación puede redirigir al instalador antes de arrancar.

`GFrame\Foundation\Bootstrap::boot()` carga `.env`, combina los valores del framework con `config/app.php`, prepara utilidades y autoload de la aplicación, inicializa los módulos registrados y carga `config/routes/routes_*.php`.

Después, `index.php` registra el manejo de errores, inicia la sesión y crea Render y Router. La combinación de configuración se explica en [Configuración](configuracion.md).

### Resolución y middleware

Router detecta el canal por el prefijo de la URL: `ajax`, `api`, `webhook` o `sse`; las demás peticiones usan el canal web. Puede reconocer un idioma admitido al inicio de la ruta. Reconocer el idioma no traduce automáticamente los textos.

La resolución compara el método HTTP y la ruta, extrae parámetros y prepara controlador, acción, vista, template, módulo de origen y middleware. El archivo de declaración y el canal de entrada deben ser coherentes.

Antes de ejecutar la acción se aplican los middleware declarados y los guardas automáticos del canal. Pueden autorizar, rechazar, aportar contexto o finalizar un preflight CORS.

La autorización de ruta no sustituye las reglas de negocio sobre registros concretos. Esas reglas deben comprobarse en los servicios o políticas de tu aplicación.

### Acción y respuesta

En el canal web, Router delega en Render. Render instancia el controlador, ejecuta la acción salvo que se haya omitido, interpreta sus datos, carga metas y vista y la envuelve con template, header y footer.

Para AJAX, API, webhook y SSE, Router ejecuta directamente la acción. AJAX y API serializan resultados a JSON con sus respectivos contratos; webhook tiene su tratamiento de respuesta y SSE permite emitir eventos. Estos canales no reciben automáticamente un template HTML.

`noAction()` permite omitir la ejecución del método. Los errores pueden desviar el recorrido a una vista o respuesta de error.

## Ejemplo de la portada

El proyecto inicial declara su portada en `config/routes/routes_web.php`:

```php
use RouteBuilder as Route;

Route::get('', 'home/HomeController@index')
    ->template('home')
    ->view('homeIndex')
    ->registerFinal();
```

La raíz ejecuta `HomeController::index()` en `app/controllers/home/HomeController.php`. Su resultado llega a `app/views/home/homeIndex.php` como `$data`. El template es `app/views/templates/homeTemplate.php`; las metas comunes están en `app/views/home/home.group.meta.php`.

Por ejemplo, puedes devolver un texto desde la acción:

```php
final class HomeController
{
    public function index(): array
    {
        return ['welcome' => 'Bienvenido a mi proyecto'];
    }
}
```

Y utilizarlo en la vista, escapándolo antes de incorporarlo al HTML:

```php
<h1><?= htmlspecialchars($data['welcome'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
```

Este ejemplo no necesita un modelo ni un servicio. Cuando necesites persistencia, añade esas piezas en la aplicación; no introduzcas la lógica del negocio en Router.

## Guías relacionadas

- [Router y rutas](rutas.md): declaraciones, canales y parámetros.
- [Render](render.md): datos, vistas y templates.
- [Metas](meta.md): etiquetas y assets por grupo y vista.
- [Módulos runtime](modulos-runtime.md): resolución entre aplicación y módulo.
- [Permisos](permisos.md): autorización global y por tenant.
- [SEO](seo.md): metadatos y respuestas automáticas.
