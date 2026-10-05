# Módulos y componentes de interfaz

GFrame conserva los módulos reutilizables en `resources/modules`. Cada módulo declara su nombre, tipo, versión cuando se conoce, dependencias, si forma parte de la instalación predeterminada y sus recursos publicables.

Las dependencias se resuelven automáticamente. Por ejemplo, `alerts` incorpora Bootstrap, jQuery, SweetAlert2 y `gframe-icons` antes de publicar `alertToast`.

## Base instalada automáticamente

- Bootstrap.
- jQuery.
- SweetAlert2.
- [`gframe-icons`](gframe-icons.md).
- `alerts`: `alertToast`, `swalAlert`, estados de carga y estilos de toast.
- `frontend-core`: formularios, errores, paginación y utilidades comunes. Véase [uso y funcionamiento](frontend-core.md). Markdown tiene su propio módulo opcional.
- [`gfselect`](gfselect.md): selector enriquecido propio, con búsqueda y selección simple o múltiple.
- [`gf-table`](gf-table.md): búsqueda y ordenación local de tablas.
- [`error-pages`](errores.md): gestión, plantilla y vistas 403, 404, 500 y 503.

Estos componentes no se presentan como elecciones del instalador. Forman la interfaz mínima de GFrame y sus dependencias se publican automáticamente.

## Módulos propios y requisitos del perfil

No todos estos módulos son opcionales en todos los perfiles. `managed` e `intranet` incluyen Auth, Cuenta y seguridad, Panel administrativo, Gestión de usuarios y Multimedia; `saas` añade Notificaciones y tareas programadas. El instalador muestra únicamente opciones compatibles que no estén ya incluidas. Consulte [los perfiles](instalacion.md).

- [`markdown`](markdown.md): conversión de Markdown y HTML en PHP y JavaScript, sin dependencia de bots.

- `admin-panel`: estructura visual compartida del panel, con navbar, sidebar, tema y puntos de inserción. Los perfiles administrados lo incluyen automáticamente. Consulte [Panel administrativo](panel-administrativo.md).

- `auth-ui`: flujo MVC de autenticación, recuperación y verificación. Consulte [Interfaz de autenticación](auth-ui.md).
- `self-account`: pantalla Mi cuenta y acciones sobre la cuenta propia. Consulte [Mi cuenta](self-account.md).
- [`heartbeat-client`](heartbeat.md): cliente web, sesión, controlador y canales periódicos.
- [`password-utils`](password-utils.md): política PHP de contraseñas y utilidades de interfaz. Se incluye con `auth-ui`; las vistas cargan sus JS/CSS solo si los usan.
- `rich-text-editor`: integración reutilizable de TinyMCE y normalización del contenido pegado desde Word. Consulte [Editor de texto enriquecido](rich-text-editor.md).
- `media-library`: biblioteca global, por tenant o por usuario, con relaciones polimórficas hacia contenidos. Consulta [Biblioteca multimedia](media-library.md).
- `notifications`: inbox por usuario y tenant con contratos para transportes acoplables.
- `notifications-email`: adaptador de correo para `notifications`; utiliza el soporte Mail del núcleo y sus plantillas.
- `notification-campaigns`: campañas masivas desacopladas, con audiencias extensibles y programación mediante `cron-runner`.
- `cron-runner`: esquema de tareas programadas y punto de entrada CLI.
- `user-admin`: consulta y administración de usuarios mediante permisos. Consulte [Administración de usuarios](user-admin.md).
- `wordpress-headless`: acceso seguro a contenido, taxonomías, menús y esquema de WordPress mediante BridgeFrame, incluidos los estilos de bloques. Consulte [WordPress headless](wordpress-headless.md).

## Integraciones y componentes externos

- [`aos`](aos.md)
- `bootstrap`
- [`chartjs`](chartjs.md)
- [`coloris`](coloris.md)
- [`flatpickr`](flatpickr.md)
- [`html2canvas`](html2canvas.md)
- [`intl-tel-input`](intl-tel-input.md)
- `jquery`
- [`jquery-ui`](jquery-ui.md)
- [`luxon`](luxon.md)
- [`owl-carousel`](owl-carousel.md)
- [`purecounter`](purecounter.md)
- `sweetalert2`
- [`swiper`](swiper.md)
- [`tinymce`](tinymce.md)
- [`venobox`](venobox.md)

Owl Carousel y Swiper son opciones independientes. Instalar una no obliga a publicar la otra.

## Consulta y publicación

```bash
php bin/modules.php list
php bin/modules.php publish /ruta/del/proyecto/public
php bin/modules.php publish /ruta/del/proyecto/public alerts gfselect
```

Cuando no se indica ningún módulo, se publica automáticamente la base visual completa. La publicación conserva los archivos existentes por defecto. Para añadir módulos funcionales a un proyecto instalado, use [el actualizador](actualizaciones.md#añadir-módulos-después-de-instalar); publicar recursos por sí solo no instala tablas ni registra el módulo.

## Multimedia

El mismo módulo funciona en tres ámbitos:

- `MediaScope::global()` para una biblioteca compartida;
- `MediaScope::tenant($tenantID)` para aislar empresas o clientes;
- `MediaScope::user($userID)` para bibliotecas personales.

Las asociaciones con artículos, productos o procedimientos se almacenan en `media_relations`; no crean ámbitos artificiales.

El ámbito predeterminado se define con `media.scope`: `global`, `tenant` o `user`.

## Sesión e inactividad

Las aplicaciones con autenticación incorporan `heartbeat-client`. La ruta heartbeat no renueva la actividad de la sesión; cuando se alcanza `session.idle_timeout`, el cliente cierra la sesión y sincroniza el estado entre pestañas. El valor predeterminado es 1800 segundos.

## Esquemas y archivos

Los módulos pueden declarar esquemas MySQL y SQLite, activos públicos y archivos de aplicación. El instalador resuelve dependencias, ejecuta los esquemas y publica los recursos seleccionados.
