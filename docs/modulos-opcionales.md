# Módulos y componentes de interfaz

GFrame conserva los módulos reutilizables en `resources/modules`. Cada módulo declara su nombre, tipo, versión cuando se conoce, dependencias, si forma parte de la instalación predeterminada y sus recursos publicables.

Las dependencias se resuelven automáticamente. Por ejemplo, `alerts` incorpora Bootstrap, jQuery, SweetAlert2 y `gframe-icons` antes de publicar `alertToast`.

## Base instalada automáticamente

- Bootstrap.
- jQuery.
- SweetAlert2.
- `gframe-icons`.
- `alerts`: `alertToast`, `swalAlert`, estados de carga y estilos de toast.
- `frontend-core`: formularios, errores, paginación, tablas, Markdown y utilidades comunes.

Estos componentes no se presentan como elecciones del instalador. Forman la interfaz mínima de GFrame y sus dependencias se publican automáticamente.

## Módulos internos seleccionables

- `gfselect`: selector enriquecido propio.
- `heartbeat-client`: cliente web del heartbeat.
- `password-utils`: utilidades e indicador de contraseña.
- `rich-text-editor`: integración reutilizable de TinyMCE y normalización del contenido pegado desde Word.
- `media-library`: biblioteca global, por tenant o por usuario, con relaciones polimórficas hacia contenidos.
- `notifications`: cola persistente y transporte de correo.
- `user-admin`: administración de usuarios reservada al superadministrador.
- `wordpress-headless`: acceso seguro a contenido de WordPress mediante BridgeFrame.

## Integraciones y componentes externos

- `aos`
- `bootstrap`
- `chartjs`
- `coloris`
- `flatpickr`
- `html2canvas`
- `intl-tel-input`
- `jquery`
- `jquery-ui`
- `luxon`
- `owl-carousel`
- `purecounter`
- `sweetalert2`
- `swiper`
- `tinymce`
- `venobox`
- `wordpress-styles`

Owl Carousel y Swiper son opciones independientes. Instalar una no obliga a publicar la otra.

## Consulta y publicación

```bash
php bin/modules.php list
php bin/modules.php publish /ruta/del/proyecto/public
php bin/modules.php publish /ruta/del/proyecto/public alerts gfselect
```

Cuando no se indica ningún módulo, se publica automáticamente la base visual completa. La publicación conserva los archivos existentes por defecto. El instalador utilizará este catálogo y permitirá seleccionar los demás módulos sin exigir que el usuario recuerde estos comandos.

## Multimedia

El mismo módulo funciona en tres ámbitos:

- `MediaScope::global()` para una biblioteca compartida;
- `MediaScope::tenant($tenantID)` para aislar empresas o clientes;
- `MediaScope::user($userID)` para bibliotecas personales.

Las asociaciones con artículos, productos o procedimientos se almacenan en `media_relations`; no crean ámbitos artificiales.

## Esquemas y archivos

Los módulos pueden declarar esquemas MySQL y SQLite, activos públicos y archivos de aplicación. El instalador resuelve dependencias, ejecuta los esquemas y publica los recursos seleccionados.
