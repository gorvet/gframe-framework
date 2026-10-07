# Biblioteca multimedia

`media-library` instala una biblioteca de archivos con listado, búsqueda, carga, eliminación y campos reutilizables. Puede operar de forma global, por tenant o por usuario.

## Instalación

Incluye el módulo en el perfil del instalador o publícalo mediante el catálogo de módulos. El controlador y las vistas originales permanecen en `resources/modules/media-library/application/app/`, dentro del paquete. Se publican las rutas, los componentes de inclusión, los recursos públicos y el esquema de MySQL o SQLite; se crean carpetas vacías de personalización en `app/controllers/media-library` y `app/views/media-library`, las capas presentes en el módulo.

Las rutas declaran `->module('media-library')`. Primero se busca en `app` y después en el módulo, sin duplicar archivos. El controlador nativo es `GFrame\Modules\MediaLibrary\Controllers\MediaController`; puede extenderse desde un controlador del proyecto. Las vistas personalizadas usan el mismo nombre relativo que las nativas. Un archivo personalizado no se sobrescribe al actualizar.

El módulo depende de `self-account`, `alerts` y `frontend-core`.

## Configuración

```php
'media' => [
    'scope' => 'global',
    'quota_bytes' => 0,
],
```

`scope` admite `global`, `tenant` o `user`. En ámbitos no globales, `MediaScopeResolver` obtiene el identificador desde la sesión normalizada. `quota_bytes` limita el consumo total del ámbito; `0` lo deja sin límite. Estos dos valores describen la integración del proyecto, no el procesamiento de los archivos.

### Elegir la biblioteca y su propietario

| Ámbito | Biblioteca compartida por | Identidad utilizada |
| --- | --- | --- |
| `global` | Todos los usuarios autorizados del proyecto | Sin ID de propietario |
| `user` | El usuario conectado | `$_SESSION['auth']['id']` |
| `tenant` | Miembros autorizados del tenant activo | Clave de sesión indicada por `tenancy.key`, o `tenant_id` |

El ámbito describe propiedad del almacenamiento, no quién tiene permiso para subir o borrar. La aplicación establece el tenant activo en sesión después de comprobar su acceso; el resolver no comprueba la membresía ni selecciona un tenant desde POST. Si falta la identidad de un ámbito user o tenant, falla sin utilizar la biblioteca global como respaldo.

Las llamadas PHP al servicio aceptan un `MediaScope` explícito. Si lo omites utilizan `global`, aunque la configuración diga `tenant`: pasa el ámbito en todas las integraciones propias.

Los tipos, límites por archivo y tamaños pertenecen al módulo: `resources/modules/media-library/config/media.php`. No se modifica esa copia instalada para personalizar un proyecto. Sus valores predeterminados son 25 MB, los formatos seguros habituales, `small` de 150×150 recortado y `medium` de hasta 300×300 proporcional. Se conserva el original intacto; no se genera `optimized` ni se amplían imágenes pequeñas. Las claves de compatibilidad de las miniaturas pueden referirse a un mismo archivo, sin generar copias adicionales.

La personalización se realiza por herencia, por ejemplo en `app/services/media-library/ProjectMediaProcessor.php`:

```php
<?php
namespace App\Services\MediaLibrary;

class ProjectMediaProcessor extends \GFrame\Media\MediaProcessor
{
    protected function configuration(): array
    {
        $config = parent::configuration();
        $config['max_upload_bytes'] = 10 * 1024 * 1024;
        $config['allowed_extensions'] = ['images' => ['jpg', 'jpeg', 'png', 'webp']];
        $config['variants'] = [
            'small' => ['w' => 150, 'h' => 150, 'mode' => 'crop'],
            'banner' => ['w' => 1200, 'h' => 600, 'mode' => 'fit'],
        ];
        return $config;
    }
}
```

El controlador del proyecto, en `app/controllers/media-library/MediaController.php`, conecta el procesador sin cambiar las rutas ni el controlador nativo:

```php
<?php
namespace App\Controllers\MediaLibrary;

class MediaController extends \GFrame\Modules\MediaLibrary\Controllers\MediaController
{
    protected function createProcessor(): \GFrame\Media\MediaProcessor
    {
        return new \App\Services\MediaLibrary\ProjectMediaProcessor();
    }
}
```

La clase del servicio debe estar registrada en el autoload del proyecto o incluida explícitamente; no se carga automáticamente por estar en una carpeta. El mismo procesador se utiliza en biblioteca, selector y sincronización. Las integraciones PHP externas deben inyectar la misma subclase en su servicio. Omitir una variante la desactiva; `variants => []` conserva solo el original. Los formatos configurados se intersectan con los formatos seguros soportados; esta configuración no habilita PHP, HTML ni otros ejecutables. Añadir un formato realmente nuevo requiere ampliar la validación por herencia, no solo cambiar su extensión.

Los cambios afectan únicamente a archivos nuevos. No se borran ni regeneran los tamaños existentes. `media.max_upload_bytes` de la antigua configuración del proyecto ya no define el límite: ahora lo define el procesador del módulo, y el backend lo comunica a los selectores.

El límite efectivo también depende de PHP (`upload_max_filesize`, `post_max_size`) y del servidor web. Aumentar el valor del procesador no aumenta esos límites.

### Organización del almacenamiento

El controlador utiliza `public` como raíz de MediaStorage:

```text
public/uploads/<source>/<año>/<mes>/                  global
public/uploads/user/<id>/<source>/<año>/<mes>/        usuario
public/uploads/tenant/<id>/<source>/<año>/<mes>/      tenant
```

`source` identifica el origen funcional, como `library` o `articles`; no crea otro ámbito de seguridad. Las relaciones con publicaciones se guardan por separado.

El aislamiento del listado no convierte los archivos en privados: están en `public` y pueden servirse directamente por URL. Para documentos confidenciales utiliza almacenamiento protegido y una descarga autorizada del proyecto; un enlace difícil de adivinar no sustituye esa protección.

## Permisos

- `media.view`: abrir y consultar la biblioteca.
- `media.add`: cargar archivos.
- `media.delete`: eliminar archivos.
- `media.edit`: editar nombre y texto alternativo.
- `media.sync`: incorporar archivos válidos existentes en disco.

El superadministrador conserva acceso por la jerarquía general de permisos. Los demás roles deben recibir los permisos necesarios.

## Uso administrativo

### Rutas y campos HTTP

La pantalla es `GET admin/media`. Estas acciones son POST bajo `ajax/admin/media/`, con autenticación, la capacidad correspondiente y CSRF:

| Acción | Campos principales | Capacidad |
| --- | --- | --- |
| `list` | `q`, `source`, `kind`, `ym`, `page`, `fragment` | `media.view` |
| `field` | `media_ids`, `variant`, `allow_remove_one` | `media.view` |
| `upload` | Archivo multipart `file`, `source` | `media.add` |
| `hotlink` | `media_url`, `name`, `source` | `media.add` |
| `base64` | `base64`, `name` | `media.add` |
| `details` | `media_id` | `media.view` |
| `save` | `media_id`, `original_name`, `alt_text` | `media.edit` |
| `delete` | `media_id` | `media.delete` |
| `quota` | Ámbito resuelto en el servidor | `media.view` |
| `sync` | Ámbito resuelto en el servidor | `media.sync` |

`fragment` del listado admite `library` o `picker`. No aceptes un nombre arbitrario de archivo PHP como fragmento.

`MediaLibrary` envía el fragmento según su modo: `manage` usa `library` y `picker` usa `picker`. El listado principal conserva sus IDs históricos; el selector utiliza el prefijo `mp-`, incluidos filtros, tarjetas y paginación. Los filtros se identifican también mediante `data-ml-filter` y las acciones mediante `data-ml-action`. La paginación de cada listado se controla desde su propia instancia, sin depender de cuál haya quedado activa globalmente. Mantenga esos atributos y el contenedor `data-ml-pagination` al personalizar `_mlist.php`; utilice `data-media-id` para obtener el ID del archivo, sin extraerlo del ID del nodo DOM. Incluya el modal del selector una sola vez por página.

La página `admin/media` incluye biblioteca, filtros por origen, tipo y mes, búsqueda automática, carga y modal de detalles con texto alternativo, URL copiable y eliminación. El selector conserva las pestañas Biblioteca, Subir y Desde URL. Los tipos canónicos son `images`, `videos`, `audios` y `docs`. La cuota y la sincronización siguen disponibles mediante el servicio y sus rutas; esta vista original no añade controles nuevos para ellas ni muestra navegación anterior/siguiente.

El campo reutilizable se incluye desde `app/views/components/media/mediaField.php`. El selector requiere también `mediaPicker.php` una sola vez en la vista o plantilla que aloje el modal. Admite selección simple o múltiple, filtro por tipo, búsqueda, paginación, carga desde el modal y conservación de la selección entre páginas. Las vistas previas se obtienen como HTML del servidor; nunca se construyen con rutas enviadas por el navegador.

En una vista que no sea la biblioteca, registra los recursos en su meta, después incluye los componentes:

```php
// En el meta de la vista, además de Bootstrap, jQuery y alertas:
'css' => ['public/css/modules/media-library/media-library.css'],
'js' => [
    'public/js/modules/media-library/media-library.js',
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

La biblioteca expone `window.MediaLibrary`. La pantalla administrativa la inicia mediante `media-admin.js`; otras integraciones pueden usar `new MediaLibrary({mount, uiRoot, mode, syncUrlEnabled, saveSource, kind, uploadMaxMB, endpoints})`. El fragmento PHP reemplaza el contenido del contenedor asignado; no se construyen tarjetas en JavaScript.

`MediaPicker.open({selected, multiple, max, kind, saveSource, endpoints})` admite `endpoints: {list: URL, upload: URL}`. Esto permite usar el mismo selector en otros formularios con rutas propias; no elige una vista ni cambia el propietario de la biblioteca. Las rutas predeterminadas son `ajax/admin/media/list` y `ajax/admin/media/upload`. El campo acepta `data-ml-field-endpoint` para su vista previa. Todas las rutas alternativas deben conservar permisos, tokens y resolución del ámbito en el servidor.

El listado acepta `q`, `source`, `kind`, `ym` y `page`; devuelve `status`, `code`, `data`, `meta` y `html`. La vista previa recibe `media_ids`, `variant` y `allow_remove_one`; recupera los archivos por ID bajo el ámbito activo. El backend construye las URL de los archivos y miniaturas; no acepta rutas de archivo aportadas por el navegador.

## Uso desde PHP

```php
use GFrame\Media\MediaLibraryService;
use GFrame\Media\MediaModel;
use GFrame\Media\MediaScope;
use GFrame\Media\MediaStorage;
use GFrame\Media\MediaScopeResolver;

$media = new MediaLibraryService(
    new MediaModel(),
    new MediaStorage(ABSPATH . 'public')
);

$scope = (new MediaScopeResolver())->resolve();
$identity = (array)($_SESSION['auth'] ?? []);
$uploader = ['id' => (int)($identity['id'] ?? 0), 'name' => (string)($identity['name'] ?? $identity['email'] ?? '')];

$result = $media->registerLocalFile(
    $temporaryPath,
    $originalName,
    'library',
    $scope,
    $uploader
);

$generated = $media->ingestBase64($base64, 'imagen-generada.png', $scope, $uploader);
$remote = $media->registerRemoteUrl('https://ejemplo.com/imagen.jpg', 'Imagen externa', 'library', $scope, $uploader);
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

`relatedType` identifica una entidad del proyecto y `relatedID` su registro. `field` distingue portada, galería u otro uso; `sortOrder` conserva el orden. El proyecto debe comprobar que ese contenido exista y que el actor pueda modificarlo: el servicio valida el archivo, no la entidad de negocio relacionada.

Guardar el JSON del campo no crea relaciones automáticamente. Al retirar una imagen de una galería, utiliza `detach()` si debe conservarse en la biblioteca. `delete()` elimina el registro y sus archivos locales y variantes; no es una simple desvinculación. Los enlaces remotos se retiran de la biblioteca sin borrar el recurso del servidor externo.

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

## Autoría, búsqueda y archivos remotos

La URL remota se persiste separada de la clave interna. Se rechazan HTTP y redirecciones remotas para proteger las consultas del servidor. El controlador toma el autor de la sesión y el servicio lo guarda como `metadata.uploader` (ID y nombre al subir); no confía en `uploaded_by` enviado desde el navegador. Las cargas, enlaces y archivos base64 registran ese dato. Integraciones PHP pasan el autor explícitamente; una sincronización o archivo antiguo sin autor muestra «—». No se inventa el autor de archivos anteriores. «Subido a» muestra el origen. La búsqueda incluye nombre, nombre original y `alt_text`, que corresponde al «Título descriptivo».

Las instalaciones antiguas pueden conservar vistas o controladores publicados en `app`; al tener prioridad sobre el módulo, deben revisarse explícitamente antes de retirarlos. La actualización no elimina personalizaciones ni modifica automáticamente archivos antiguos.

## Actualización del esquema

Las instalaciones nuevas reciben todas las columnas desde los esquemas del módulo. Las actualizaciones aplican las migraciones de metadatos y de `remote_url` mediante el actualizador del proyecto. Revisa los cambios con `composer gframe:update -- --dry-run` y verifica la aplicación después de aplicarlos.

## Validar el campo al guardar un formulario

El navegador envía IDs, no archivos completos ni rutas. Para un campo múltiple:

```php
<?php
$raw = (string)($_POST['gallery_ids'] ?? '[]');
$ids = json_decode($raw, true);
if (!is_array($ids) || !array_is_list($ids) || count($ids) > 12) {
    return ['status' => 'error', 'code' => 'invalid_gallery'];
}
foreach ($ids as $id) {
    if (!is_int($id) || $id <= 0) {
        return ['status' => 'error', 'code' => 'invalid_gallery'];
    }
}
$ids = array_values(array_unique($ids));
// Recupera cada archivo con details($id, $scope) antes de relacionarlo.
return ['status' => 'success', 'data' => ['ids' => $ids]];
```

La aceptación del selector en el navegador no autoriza esos IDs. En el backend comprueba también el ámbito, tipo permitido y acceso al contenido que recibe la galería. Un campo simple requiere validar un ID positivo o un valor vacío cuando sea opcional.

## Comprobación de una integración

1. Prueba búsqueda por nombre y título descriptivo y los filtros del listado.
2. Sube archivos permitidos y rechazados; comprueba límite, variantes y autor.
3. Prueba campos simples y múltiples fuera de la biblioteca, incluyendo paginación, cancelación y contenido añadido por AJAX.
4. Valida los IDs antes de crear relaciones con el contenido.
5. En ámbitos user o tenant, comprueba que otro ámbito no obtiene detalles ni acciones sobre esos IDs.
6. Comprueba por separado si las URLs físicas deben ser públicas o necesitan una descarga privada.
