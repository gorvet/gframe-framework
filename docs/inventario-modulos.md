# Mapa de capacidades y módulos

Este documento responde una pregunta práctica:

> Antes de implementar algo desde cero, ¿GFrame ya tiene una capacidad, servicio o módulo para resolverlo?

La lista separa **núcleo**, **base visual**, **módulos funcionales** y **bibliotecas empaquetadas**. No todas las entradas son módulos runtime MVC.

## Cómo leer el mapa

- **Núcleo**: forma parte del paquete PHP y no se selecciona como módulo.
- **Base visual predeterminada**: módulos marcados para formar la base común de los proyectos.
- **Módulo funcional**: capacidad instalable que puede añadir runtime, esquema, rutas, assets o integración.
- **Biblioteca empaquetada**: wrapper/publicación de una dependencia frontend; no debe confundirse con una capa de negocio.

Para entender instalación y personalización consulta [Usar módulos en una aplicación](modulos-en-aplicacion.md).

## Núcleo de GFrame

| Necesidad | Capacidad real | Dónde empezar |
| --- | --- | --- |
| arrancar/configurar una aplicación | Bootstrap, `.env`, `config/app.php`, defaults y bridge legacy | [Arquitectura](arquitectura.md), [Configuración](configuracion.md) |
| crear URLs web | Router + RouteBuilder | [Rutas](rutas.md) |
| acciones AJAX | canal AJAX + middleware automático + respuestas JSON | [Tutorial Productos](tutorial-productos.md), [Frontend core](frontend-core.md) |
| publicar una API | rutas API + credenciales por ruta + CORS | [Acceso API](api-access.md) |
| recibir webhooks | canal webhook + guard específico | [Rutas](rutas.md) |
| Server-Sent Events | canal SSE + guard específico | [Rutas](rutas.md) |
| renderizar HTML | Render + views + templates + metas | [Render](render.md) |
| lógica de aplicación | controllers/services/models del proyecto | [Guía de desarrollo](guia-desarrollo.md) |
| consultar/persistir datos | ORM + DatabaseManager + dialectos MySQL/SQLite | [ORM](orm.md) |
| autenticar usuarios | AuthModel + SessionManager + módulo `auth-ui` | [Identidad y autorización](identidad-autorizacion.md) |
| roles/permisos | RoleModel, RolePermissionService, UserPermissionService | [Permisos](permisos.md) |
| sesiones revocables | SessionRuntime: database / redis / native | [Sesiones](sesiones.md) |
| enviar correo | MailService + templates + SMTP | [Correo](mail.md) |
| llamar una API externa | `HttpClient::request()` | [Cliente HTTP saliente](http-client.md) |
| ejecutar trabajo fuera del request | `Async` | [Procesos en segundo plano](procesos-segundo-plano.md) |
| programar trabajo persistente | core Cron + módulo `cron-runner` | [Cron](cron-runner.md) |
| cifrar datos recuperables | `GFrame\Security\Encryption` | [Cifrado](encryption.md) |
| sanear HTML permitido | `HtmlSanitizer` | [Sanitización HTML](html-sanitizer.md) |
| utilidades pequeñas PHP | UrlHelper, ViewHelper, MenuHelper, PaginationHelper, etc. | [Helpers PHP](helpers-php.md) |
| SEO técnico | Meta + Sitemap + Robots + Llms + SchemaComposer + JsonLD | [SEO](seo.md), [JSON-LD](json-ld.md) |
| WordPress headless | `GFrame\Headless\WordPressClient` + módulo `wordpress-headless` | [WordPress headless](wordpress-headless.md) |
| módulos reutilizables | ModuleCatalog + ModuleRuntime + ModuleAssetPublisher | [Módulos](modulos-en-aplicacion.md) |
| instalar/actualizar proyectos | perfiles, schemas, migrations, scaffolder y updater | [Perfiles](perfiles-instalacion.md), [Actualizaciones](actualizaciones.md) |
| errores | ErrorHandler / respuestas del canal + módulo `error-pages` | [Errores](errores.md) |
| heartbeat | core servidor + módulo `heartbeat-client` | [Heartbeat](heartbeat.md) |
| footer extensible | áreas de footer por template/grupo/vista | [Footer](footer.md) |

### Nota sobre SEO

SEO no es una única capacidad activada/desactivada de forma homogénea. Sitemap, robots, llms, meta robots y JSON-LD siguen caminos distintos en el runtime actual. Consulta [SEO](seo.md) antes de asumir el efecto de `seo.enabled`.

## Los 37 módulos del catálogo

`resources/modules` contiene actualmente **37 módulos**. La lista siguiente cubre todos los directorios del catálogo auditado.

### Base visual predeterminada — 9

| Módulo | Papel |
| --- | --- |
| `bootstrap` | Bootstrap publicado para la interfaz |
| `jquery` | jQuery publicado para componentes heredados/actuales |
| `sweetalert2` | motor de diálogos/alertas |
| `gframe-icons` | iconografía propia |
| `alerts` | `alertToast`, `swalAlert` y estados de carga |
| `frontend-core` | formularios, errores, paginación y helpers JS comunes |
| `gf-select` | selector enriquecido propio |
| `gf-table` | ordenación/búsqueda local de tablas |
| `error-pages` | template y vistas runtime para 403/404/500/503 |

Estos módulos forman la base común resuelta por el catálogo. No significa que todos tengan MVC, tablas o configuración propia.

## Módulos funcionales — 15

### Aplicación y administración

| Módulo | Para qué sirve | Perfil/uso |
| --- | --- | --- |
| `admin-panel` | shell administrativo: navbar, sidebar, template y puntos de inserción | requerido por managed/intranet/saas |
| `auth-ui` | registro, login, verificación, recuperación y logout | requerido por perfiles con Auth |
| `self-account` | cuenta propia, contraseña y desactivación | requerido por perfiles administrados |
| `user-admin` | administración de otras cuentas y roles | requerido por perfiles administrados |
| `password-utils` | política/generación/feedback de contraseña | dependencia de Auth UI |
| `heartbeat-client` | cliente, controller y ruta heartbeat | dependencia de flujos autenticados |

### Contenido y archivos

| Módulo | Para qué sirve | Guía |
| --- | --- | --- |
| `media-library` | biblioteca global/tenant/user, procesamiento y relaciones | [Media Library](media-library.md) |
| `rich-text-editor` | integración reutilizable de TinyMCE y normalización de contenido | [Editor enriquecido](rich-text-editor.md) |
| `markdown` | Markdown ↔ HTML en PHP/JS | [Markdown](markdown.md) |
| `lexical-search` | búsqueda léxica | [Búsqueda léxica](lexical-search.md) |

### Comunicación y procesos

| Módulo | Para qué sirve | Guía |
| --- | --- | --- |
| `notifications` | inbox por usuario/tenant + transportes/cola base | [Notificaciones](notificaciones.md) |
| `notifications-email` | transporte de correo sobre Mail/colas | [Notifications Email](notifications-email.md) |
| `notification-campaigns` | audiencias y campañas masivas/programables | [Campañas](notification-campaigns.md) |
| `cron-runner` | persistencia y ejecución CLI de tareas programadas | [Cron](cron-runner.md) |

### Integración

| Módulo | Para qué sirve | Guía |
| --- | --- | --- |
| `wordpress-headless` | contenido/taxonomías/menús de WordPress mediante el cliente headless | [WordPress headless](wordpress-headless.md) |

## Bibliotecas frontend empaquetadas — 13

| Módulo | Uso |
| --- | --- |
| `aos` | animaciones al hacer scroll |
| `chartjs` | gráficos |
| `coloris` | selector de color |
| `flatpickr` | fechas y horas |
| `html2canvas` | captura de nodos HTML |
| `intl-tel-input` | teléfonos internacionales |
| `jquery-ui` | interacciones jQuery UI |
| `luxon` | fechas, zonas horarias y duraciones |
| `owl-carousel` | carruseles |
| `purecounter` | contadores animados |
| `swiper` | sliders/carruseles táctiles |
| `tinymce` | motor del editor enriquecido |
| `venobox` | lightbox/visor |

Estas entradas no deben presentarse como funcionalidades backend de GFrame. Principalmente resuelven publicación y disponibilidad de recursos frontend.

## Qué instala cada perfil

### `static`

No exige módulos funcionales adicionales sobre la base predeterminada y no admite opciones que dependan de esquema de base de datos.

### `managed`

Exige:

```text
auth-ui
self-account
admin-panel
user-admin
media-library
```

### `intranet`

Exige exactamente el mismo bloque funcional que `managed`; la diferencia principal del perfil es `public=false` y la configuración derivada.

### `saas`

Exige el bloque administrado y añade:

```text
notifications
cron-runner
```

Además activa el esquema genérico de tenancy.

Consulta [Qué instala cada perfil](perfiles-instalacion.md) para el comportamiento exacto.

## Dependencias automáticas

No elijas manualmente una dependencia solo porque otro módulo la necesita. `ModuleCatalog::resolve()` resuelve transitivamente el árbol.

Ejemplo conceptual:

```text
rich-text-editor
  → tinymce
```

O:

```text
auth-ui
  → alerts
  → frontend-core
  → heartbeat-client
  → password-utils
  → dependencias de cada uno
```

La instalación final registra la lista resuelta, no solo las opciones visibles que marcó el usuario.

## Módulo no significa runtime MVC

Comprueba el manifiesto:

```text
runtime     → originales MVC cargables desde el paquete
schemas     → tablas propias
migrations  → evolución de esquema
assets      → recursos publicables
application → rutas/archivos que deben copiarse al proyecto
environment → variables de entorno requeridas
```

Un módulo como `alerts` puede tener únicamente dependencias + assets. Uno como `media-library` puede combinar runtime, esquema, migraciones, assets y archivos de aplicación.

Consulta [Usar módulos en una aplicación](modulos-en-aplicacion.md).

## Antes de crear una solución nueva

Usa este orden:

```text
¿Existe en el núcleo?
  ↓ no
¿Existe como módulo funcional?
  ↓ no
¿Existe como componente frontend empaquetado?
  ↓ no
¿Es realmente lógica específica de mi aplicación?
  → créala en app/
```

No conviertas automáticamente la lógica específica de un proyecto en un módulo de GFrame.

## Inventario vs documentación especializada

Este archivo es el **mapa**. Los contratos exactos siguen viviendo en las guías enlazadas. Si una capacidad aparece aquí pero no tiene todavía una guía especializada, se registra como deuda en [Reconstrucción documental](reconstruccion-documentacion.md).
