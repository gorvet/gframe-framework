# Biblioteca multimedia

`media-library` instala una biblioteca de archivos con listado, búsqueda, carga, eliminación y campos reutilizables. Puede operar de forma global, por tenant o por usuario.

## Instalación

Incluye el módulo en el perfil del instalador o publícalo mediante el catálogo de módulos. El manifiesto instala las rutas, el controlador, las vistas, los componentes, los recursos y el esquema correspondiente a MySQL o SQLite.

El módulo depende de `self-account`, `alerts` y `frontend-core`.

## Configuración

```php
'media' => [
    'scope' => 'global',
    'max_upload_bytes' => 26214400,
    'quota_bytes' => 0,
],
```

`scope` admite `global`, `tenant` o `user`. En ámbitos no globales, `MediaScopeResolver` obtiene el identificador desde la sesión normalizada. `max_upload_bytes` establece el límite por archivo. `quota_bytes` limita el consumo total del ámbito; `0` lo deja sin límite.

## Permisos

- `media.view`: abrir y consultar la biblioteca.
- `media.add`: cargar archivos.
- `media.delete`: eliminar archivos.
- `media.edit`: editar nombre y texto alternativo.
- `media.sync`: incorporar archivos válidos existentes en disco.

El superadministrador conserva acceso por la jerarquía general de permisos. Los demás roles deben recibir los permisos necesarios.

## Uso administrativo

La página `admin/media` permite buscar, filtrar por tipo y mes, cargar, registrar enlaces externos, editar, eliminar y sincronizar archivos. El modal de detalles muestra vista previa, tipo, tamaño, fecha, URL copiable y navegación entre los archivos de la página. También muestra el consumo de la cuota. Los tipos canónicos son `images`, `videos`, `audios` y `docs`.

El campo reutilizable se incluye desde `app/views/components/media/mediaField.php`. El selector requiere también `mediaPicker.php` una sola vez en la vista o plantilla que aloje el modal. Admite selección simple o múltiple, filtro por tipo, búsqueda, paginación, carga desde el modal y conservación de la selección entre páginas. Las vistas previas se obtienen como HTML del servidor; nunca se construyen con rutas enviadas por el navegador.

En una vista que no sea la biblioteca, registra los recursos en su meta, después incluye los componentes:

```php
// En el meta de la vista, además de Bootstrap, jQuery y alertas:
'css' => ['public/css/modules/media-library/media-library.css'],
'js' => [
    'public/js/modules/media-library/media-picker.js',
    'public/js/modules/media-library/media-field.js',
],
```

```php
<?php
$mediaField = [
    'name' => 'gallery_ids',
    'label' => 'Galería',
    'value' => [12, 18],
    'multiple' => true,
    'max' => 12,
    'accept' => 'images',
    'behavior' => 'append',
    'save_source' => 'library',
    'fragment' => 'gthumb',
    'remove_one' => true,
];
include ABSPATH . 'app/views/components/media/mediaField.php';
include ABSPATH . 'app/views/components/media/mediaPicker.php';
?>
```

Un campo simple guarda un ID entero o una cadena vacía. Un campo múltiple guarda un arreglo JSON de IDs en su input; al recibirlo, el controlador de la aplicación debe validarlo y aplicar sus reglas de relación con el contenido. `max` admite de 1 a 50. `behavior` admite `append` o `replace`. `fragment` solo admite `sthumb` y `gthumb`. El ámbito `tenant` o `user` se toma de la sesión normalizada, no de un ID enviado por el navegador.

Para contenido insertado después de cargar la página, llama a `MediaField.bindAll(contenedor)`. Si se integra el selector directamente, `MediaPicker.open({selected, multiple, max, kind, saveSource})` devuelve una promesa con `{ids, items}` o `null` cuando se cancela. El componente necesita las rutas AJAX del módulo y el formulario global `#tokens`.

La biblioteca expone `window.MediaLibrary`. Al encontrar `[data-ml-mount]` se inicia automáticamente y actualiza solo `[data-ml-results]` con el fragmento PHP. Acepta filtros, paginación sincronizada con la URL, carga múltiple y `data-ml-save-source`; también puede iniciarse con `new MediaLibrary({mount, syncUrl, saveSource, kind, uploadMaxMB})` cuando el contenedor tiene `data-ml-noauto`.

## Uso desde PHP

```php
use GFrame\Media\MediaLibraryService;
use GFrame\Media\MediaModel;
use GFrame\Media\MediaScope;
use GFrame\Media\MediaStorage;

$media = new MediaLibraryService(
    new MediaModel(),
    new MediaStorage(ABSPATH . 'public')
);

$result = $media->registerLocalFile(
    $temporaryPath,
    $originalName,
    'library',
    MediaScope::tenant($tenantID)
);

$generated = $media->ingestBase64($base64, 'imagen-generada.png', $scope);
$remote = $media->registerRemoteUrl('https://ejemplo.com/imagen.jpg', 'Imagen externa', 'library', $scope);
$details = $media->details($mediaID, $scope);
$media->updateMetadata($mediaID, [
    'original_name' => 'Portada principal',
    'alt_text' => 'Descripción accesible de la portada',
], $scope);
$quota = $media->quota($scope);
```

Las operaciones devuelven un arreglo estable con `status` y `code`. Cuando corresponde, incluyen `message`, `data` o `meta`. La capa que llama decide si muestra una vista de error, `swalAlert` o `alertToast`.

```php
if (($result['status'] ?? 'error') === 'success') {
    $mediaID = (int)$result['data']['media_id'];
}
```

## Relaciones con contenido

```php
$media->attach($mediaID, 'post', $postID, 'cover', 0, $scope);
$items = $media->related('post', $postID, 'gallery', $scope);
$media->detach($mediaID, 'post', $postID, 'cover', $scope);
```

El ámbito debe acompañar todas las operaciones. El servicio impide relacionar o separar un archivo que no pertenezca al ámbito solicitado.

## Extensión

Para usar otro origen de datos, implementa `GFrame\Media\Contracts\MediaRepository` e inyéctalo en `MediaLibraryService`. El adaptador debe aplicar siempre `MediaScope` al buscar, listar, eliminar y consultar relaciones.

Los orígenes se normalizan a un identificador seguro y forman parte de la ruta de almacenamiento. No deben usarse rutas proporcionadas directamente por el usuario.

`MediaSyncService` recorre únicamente la carpeta del ámbito indicado, ignora variantes y extensiones bloqueadas, valida el contenido y registra originales ausentes en la base de datos.

## Validación y seguridad

- Se bloquean extensiones ejecutables y de marcado activo.
- La extensión debe estar en la lista permitida de su tipo.
- Las imágenes se validan por su contenido y dimensiones.
- Los demás archivos se validan por MIME; audio y video genéricos `application/octet-stream` se rechazan.
- El tamaño máximo se comprueba antes de copiar el archivo.
- La cuota se comprueba con el original y las variantes generadas.
- Las imágenes generan variantes optimizadas cuando GD ofrece el formato necesario.
- Las rutas se generan dentro del almacenamiento configurado.
- Las rutas administrativas exigen autenticación y permiso específico.
- Los enlaces externos solo admiten HTTPS con DNS público, certificado válido, puerto 443, respuesta directa 2xx y MIME/extensión permitidos. No se siguen redirecciones. La URL queda en `remote_url`; `path` es una clave interna única y no se usa como ruta física. El archivo externo no consume cuota local ni se borra del servidor remoto al quitar su registro.

## Códigos principales

Éxito: `media_created`, `media_loaded`, `media_deleted`, `media_updated`, `media_details_loaded`, `media_quota_loaded`, `media_synchronized`, `media_attached`, `media_detached` y `media_related_loaded`.

Errores esperados: `file_not_found`, `file_size_not_allowed`, `media_quota_exceeded`, `extension_not_allowed`, `media_not_allowed`, `mime_not_allowed`, `not_image`, `extension_mime_mismatch`, `invalid_base64_media`, `media_not_found`, `invalid_media_relation` y `media_scope_invalid`.

Fallos internos: `media_create_failed`, `media_list_failed`, `media_details_failed`, `media_update_failed`, `media_delete_failed`, `media_quota_failed`, `media_sync_failed`, `media_attach_failed`, `media_detach_failed` y `media_related_failed`.

Los fragmentos rechazan variantes fuera de la lista permitida con `invalid_media_fragment` y selecciones excesivas con `invalid_media_selection`. Cada vista previa se obtiene por ID bajo el ámbito activo; los archivos de otro tenant o usuario no se devuelven.

## Cotejo con los proyectos de origen

El módulo incluye campo reutilizable, selector, vistas previas, carga, listado, filtros, enlaces externos y controles de detalles del modal. La URL remota se persiste separada de la clave interna. A diferencia de los proyectos de origen, los enlaces HTTP y las redirecciones remotas se rechazan para evitar consultas del servidor a destinos no verificados. Los datos de negocio como «subido por» o «subido a» no se muestran en el modal genérico porque dependen del modelo de cada aplicación.

## Actualización del esquema

Las instalaciones nuevas reciben todas las columnas desde los esquemas del módulo. Las actualizaciones aplican las migraciones de metadatos y de `remote_url`. No se han ejecutado estas migraciones en los proyectos de origen.
