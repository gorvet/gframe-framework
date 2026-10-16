# Biblioteca dentro del editor

Esta integración conecta el [editor TinyMCE](rich-text-editor.md) con la [biblioteca multimedia](media-library.md). Permite insertar imágenes y componer/reeditar galerías dentro del texto. Se activa por campo; el editor sin activación conserva su comportamiento.

## Preparar el formulario

Instala los módulos `rich-text-editor` y `media-library`, con sus dependencias. Conserva el formulario global `#tokens` del header y el flujo de permisos del proyecto. En el meta de la vista, combina estos recursos con los de tu formulario:

```php
$assets = require \GFrame\Modules\ModuleRuntime::file(
    'views', 'rich-text-editor/richTextEditorMedia.meta.php', 'rich-text-editor'
);
return $assets;
```

En la vista:

```php
$richTextEditor = [
    'id' => 'articleContent', 'name' => 'content', 'label' => 'Contenido',
    'value' => $article['content'] ?? '', 'media_library' => true,
];
include \GFrame\Modules\ModuleRuntime::file('views', 'rich-text-editor/richTextEditor.php', 'rich-text-editor');
// Una sola vez por página y fuera del formulario.
include \GFrame\Modules\ModuleRuntime::file('views', 'media-library/mediaPickerModal.php', 'media-library');
```

Los IDs de las instancias deben ser únicos. Mantén `AdminRichTextEditor.saveAll()` antes de validar/serializar el formulario por AJAX.

## Insertar y reeditar

**Imagen de biblioteca** inserta una imagen en el cursor. Si seleccionas una imagen existente, la sustituye sin duplicarla. **Galería de biblioteca** permite seleccionar, subir y ordenar hasta 50 imágenes. Selecciona una imagen de la galería y vuelve a abrir cualquiera de estos botones para editar su composición. Quitar de la galería conserva el archivo en la biblioteca; para eliminar toda la galería, retira su figura del editor. El selector exige al menos una imagen al confirmar.

Cancelar conserva el texto. La inserción y sustitución admiten Deshacer/Rehacer. Se conserva el cursor y cada instancia se trata por separado. Si cambia el documento o se destruye/recrea el editor antes de recibir el HTML, se descarta la respuesta pendiente.

También puedes trabajar desde la barra contextual al seleccionar una imagen: **Cambiar imagen** o **Quitar imagen del texto**; en una galería, **Editar galería** o **Quitar galería del texto**. Quitar no elimina registros ni archivos de la biblioteca y puede deshacerse. Mientras la selección o el fragmento de inserción están pendientes, el acceso opcional a guardar del editor permanece deshabilitado para no enviar el contenido anterior.

El compositor administra imágenes de biblioteca. Si se añade texto o una imagen sin referencia dentro de la figura de una galería mediante edición manual, se detiene su recomposición y se pide revisarla para no eliminar contenido del autor.

Los botones se añaden automáticamente a una barra `toolbar` de texto. Si usas una barra como arreglo/objeto de TinyMCE, incluye `gframeImage` y `gframeGallery`. El callback `setup` del proyecto se conserva. El editor debe estar en un formulario de página: no se abre un segundo modal Bootstrap dentro de otro abierto. Si faltan los recursos o el modal, se informa de que la biblioteca no está disponible.

El HTML procede del endpoint `field` existente, mediante variantes internas `editor_image` y `editor_gallery`. No son variantes de miniatura de `MediaField`. El servidor obtiene originales y alt por IDs bajo el ámbito activo; rechaza selecciones parciales, archivos ajenos y tipos distintos de imagen. La subida usa el picker con sus permisos, CSRF y límites existentes.

## Guardar y mostrar

Las imágenes guardan `data-media-id`; las galerías usan `<figure data-media-gallery="1">` con figuras e imágenes en orden. El sanitizador de GFrame conserva únicamente estos marcadores acotados. El alt es una copia del valor al insertar; cambiar atributos en la biblioteca no reescribe artículos anteriores.

El proyecto sigue siendo responsable de autorizar el contenido, limitar su tamaño y llamar a `HtmlSanitizer::sanitize()` antes de guardarlo. Los marcadores no son credenciales. Valida cada ID contra `MediaLibraryService::details($id, $scope)` con el ámbito autorizado y contrasta la URL de la imagen con su registro. Un usuario puede modificar el HTML o enviarlo sin pasar por el editor. Si se admiten imágenes por URL sin ID, establece una política explícita para ellas; sanear HTML no comprueba propiedad ni dominios.

Para mostrar galerías en una vista pública, añade `public/css/modules/media-library/media-content.css` a su meta. Es independiente del CSS administrativo. Sin ese recurso se conservan las imágenes y su orden con la presentación HTML predeterminada. No se añade una tabla de relaciones ni se modifica cómo los CRUD guardan sus campos de imagen/galería.

## Actualización

Publica los recursos de ambos módulos mediante el actualizador del proyecto y revisa overrides del componente, meta y controlador: tienen prioridad sobre los originales. Usa el sanitizador actualizado al guardar y comprueba la recarga de una galería. Una política HTML propia debe preservar las figuras y estos marcadores seguros para permitir reedición.

No requiere migración de esquema. No incorpora edición de píxeles, metadatos nuevos, audio/vídeo dentro del texto ni almacenamiento de artículos.
