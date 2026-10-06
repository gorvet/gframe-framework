# Mapa canónico de capacidades y documentación

Fecha de contraste: 2026-10-05.

Este mapa relaciona el código vigente de `codex/reconstruccion-unificada` con la documentación pública. Sirve para detectar capacidades reales sin guía y para evitar que un inventario histórico se interprete como estado actual.

La fuente de verdad es el código. Una fila «documentada» significa que existe una entrada pública suficiente para descubrir la capacidad y entender su contrato principal; no significa que cada método interno tenga una página propia.

## Recorrido base de una aplicación

| Capacidad | Código principal | Entrada pública | Documentación | Estado |
| --- | --- | --- | --- | --- |
| Bootstrap y configuración | `src/GFrame/Foundation/Bootstrap.php`, `src/GFrame/Config/*` | `Bootstrap::boot()`, `config()` y bridge de compatibilidad | `docs/arquitectura.md`, `docs/configuracion.md` | Documentada |
| Creación de proyecto | `bin/new-project.php`, `resources/skeleton/` | comando de creación + instalador | `docs/instalacion.md`, `docs/comandos.md` | Documentada |
| Routing | `src/routing/RouteBuilder.php`, `src/routing/Router.php` | `RouteBuilder::get/post/put/delete`, modifiers y `registerFinal()` | `docs/rutas.md` | Documentada |
| Middleware | `src/middleware/Middleware.php` | `auth`, `guest`, `admin`, `role:*`, `can:*` y guardas de canal | `docs/middleware.md` | Documentada |
| Render web | `src/render/Render.php` | controller → data → view → template | `docs/render.md`, `docs/vistas.md` | Documentada |
| Metas y assets | `src/render/Meta.php` | meta global/template/grupo/vista | `docs/meta.md` | Documentada |
| Contratos de respuesta | Router/Render/ErrorResponder | `status`, `code`, `message`, `data`, `meta`, `html` | `docs/respuestas.md` | Documentada |
| Primera página | skeleton + routing/render | ruta + controlador + vista + meta | `docs/primer-proyecto.md` | Documentada |
| Funcionalidad vertical | routing + controller + service de proyecto + ORM + AJAX | ejemplo Productos | `docs/tutorial-productos.md` | Documentada |

## Datos y utilidades de aplicación

| Capacidad | Código principal | API/contrato | Documentación | Estado |
| --- | --- | --- | --- | --- |
| Base de datos | `src/database/DatabaseManager.php` | conexiones MySQL/SQLite | `docs/configuracion.md`, `docs/orm.md` | Documentada |
| ORM | `src/database/ORM.php` | consultas, agregados, escrituras, transacciones | `docs/orm.md` | Documentada |
| Helpers PHP | `src/utils/*` | `UrlHelper`, `MenuHelper`, `PaginationHelper`, `ViewHelper`, `LogHelper`, etc. | `docs/helpers-php.md` | Documentada |
| Wrappers legacy | `src/utils/LegacyCompatibility.php` | `guess_url()`, `buildMenu()`, `pagination()`, `logger()`, etc. | `docs/helpers-php.md` | Documentada como compatibilidad |
| Cliente HTTP saliente | `src/services/HttpClient.php` | `HttpClient::request()` | `docs/http-client.md` | Documentada |
| Async fire-and-forget | `src/async/Async.php`, worker CLI | `Async::create()` | `docs/async.md` | Documentada |
| Cron persistente/programado | `src/cron/*` + módulo `cron-runner` | tasks + runner | `docs/cron-runner.md` | Documentada |
| Heartbeat | `src/heartbeat/*` + `heartbeat-client` | heartbeat servidor/cliente | `docs/heartbeat.md`, `docs/heartbeat-client.md` | Documentada |

## Identidad, autorización y seguridad

| Capacidad | Código principal | API/contrato | Documentación | Estado |
| --- | --- | --- | --- | --- |
| Autenticación | `src/GFrame/Auth/AuthModel.php` y servicios relacionados | registro, verificación, login, recuperación | `docs/autenticacion.md`, `docs/auth-ui.md` | Documentada |
| Sesiones | `src/GFrame/Session/*`, `SessionManager` | identidad, drivers, revocación | `docs/sesiones.md` | Documentada |
| Roles y permisos | `src/GFrame/Auth/Role*`, `UserPermissionService` | roles globales, tenant, overrides | `docs/permisos.md` | Documentada |
| Cuenta propia | módulo `self-account` | perfil, contraseña, desactivación | `docs/self-account.md` | Documentada |
| Administración de usuarios | módulo `user-admin` | operaciones administrativas existentes | `docs/user-admin.md` | Documentada |
| Sanitización HTML | `src/GFrame/Security/HtmlSanitizer.php` | allowlist de HTML | `docs/html-sanitizer.md` | Documentada |
| Cifrado reversible | `src/GFrame/Security/Encryption.php` | `encrypt()`, `decrypt()` AES-256-GCM | `docs/encryption.md` | Documentada |
| CORS bajo nivel | `src/utils/CorsHelper.php` | `sendHeaders()` | `docs/helpers-php.md`; API normal en `docs/api-access.md` | Documentada |

## Publicación, SEO y canales externos

| Capacidad | Código principal | API/contrato | Documentación | Estado |
| --- | --- | --- | --- | --- |
| API entrante | Router + `src/GFrame/Http/*` | Bearer, consumers, scopes, CORS | `docs/rutas.md#api`, `docs/api-access.md` | Documentada |
| Webhooks | Router/middleware | canal `/webhook/...` | `docs/rutas.md` | Documentada |
| SSE | Router/middleware | canal `/sse/...` | `docs/rutas.md` | Documentada |
| SEO global/por ruta | `src/seo/Sitemap.php`, `Robots.php`, `Llms.php`, `src/render/Meta.php` | `seo.*`, `context.seo.indexable` | `docs/seo.md` | Documentada |
| JSON-LD | `src/seo/SchemaComposer.php`, `JsonLD.php`, presets | presets + entidades | `docs/json-ld.md` | Documentada |
| Multilenguaje | Router + utilidades del proyecto | prefijos, idioma, vistas/metas | `docs/multilenguaje.md` | Documentada |
| WordPress headless | `src/GFrame/Headless/WordPressClient.php` + módulo | cliente BridgeFrame y vistas | `docs/wordpress-headless.md` | Documentada |

## Comunicación y contenido

| Capacidad | Código/módulo | Documentación | Estado |
| --- | --- | --- | --- |
| Correo | `src/GFrame/Mail/*` | `docs/mail.md` | Documentada |
| Inbox de notificaciones | `src/GFrame/Notifications/*`, módulo `notifications` | `docs/notificaciones.md` | Documentada |
| Transporte email de notificaciones | módulo `notifications-email` | `docs/notifications-email.md` | Documentada |
| Campañas | `src/GFrame/Notifications/Campaigns/*`, módulo `notification-campaigns` | `docs/notification-campaigns.md` | Documentada |
| Media Library | `src/GFrame/Media/*`, módulo `media-library` | `docs/media-library.md` | Documentada |
| Markdown | módulo `markdown` | `docs/markdown.md` | Documentada |
| Búsqueda léxica | módulo `lexical-search` | `docs/lexical-search.md` | Documentada |
| Editor enriquecido | módulo `rich-text-editor` | `docs/rich-text-editor.md` | Documentada |

## Frontend y módulos distribuidos

El inventario de módulos instalables vive en `resources/modules/*/module.php` y su catálogo público está en `docs/inventario-modulos.md` y `docs/modulos-opcionales.md`.

| Grupo | Documentación de entrada | Estado |
| --- | --- | --- |
| Base frontend propia | `docs/frontend-core.md`, `docs/estilos-comunes.md` | Documentada |
| Alertas y errores | `docs/alerts.md`, `docs/errores.md` | Documentada |
| GFSelect / GFTable / iconos | `docs/gfselect.md`, `docs/gf-table.md`, `docs/gframe-icons.md` | Documentada |
| Bibliotecas externas | `docs/dependencias-frontend.md` + guía de cada biblioteca | Documentada; licencias/versiones requieren mantenimiento curado |
| Publicación e instalación de módulos | `docs/modulos-opcionales.md` | Documentada |
| Runtime y overrides | `docs/modulos-runtime.md`, `docs/extensibilidad.md` | Documentada |

## Límites de este mapa

No se crea una página por cada clase interna. Repositorios, contratos o procesadores que solo son piezas de implementación se documentan dentro de la capacidad pública que sostienen.

Tampoco se marcan como capacidades vigentes propuestas que solo aparecen en `maintenance/`. Por ejemplo, una operación futura de administración o un cambio de BridgeFrame sigue siendo roadmap hasta existir en código.

Los nombres históricos `PHPAsync` y wrappers globales del core no son la API recomendada para código nuevo. El estado vigente de la auditoría está en `maintenance/auditoria-reconstruccion-final.md`.

## Regla para futuras incorporaciones

Cuando se añada una capacidad pública al framework:

1. identificar su punto de entrada real en código;
2. decidir si pertenece a una guía existente o necesita una nueva;
3. añadirla a `docs/index.md` en un recorrido práctico cuando un desarrollador deba descubrirla;
4. actualizar este mapa;
5. añadir pruebas de contrato o documentación cuando el comportamiento sea verificable automáticamente.

Una clase nueva que no cambia la superficie pública no obliga a crear una guía nueva.
