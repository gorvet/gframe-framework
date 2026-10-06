# Editor de texto enriquecido

El módulo `rich-text-editor` publica un componente reutilizable basado en TinyMCE. Incluye idioma español, normalización del contenido pegado desde Word y soporte para enlaces, imágenes, tablas, listas, código y vista previa.

## Instalación

Selecciona `rich-text-editor` en la instalación o añádelo siguiendo [Instalación de módulos](modulos-opcionales.md#añadir-módulos-a-un-proyecto-instalado). Sus dependencias se resuelven automáticamente.

La publicación instala automáticamente jQuery y TinyMCE. Conserva las vistas originales en `resources/modules/rich-text-editor/application/app/views/rich-text-editor/` y crea carpetas vacías de personalización en `app`. Publica únicamente el JavaScript:

- `public/js/app/admin/components/rich-text-editor.js`.

Personalice el campo o sus metadatos creando `app/views/rich-text-editor/richTextEditor.php` o `richTextEditor.meta.php`. Si no existen, se usan los originales. Los archivos antiguos de `app/views/admin/components/` no se borran automáticamente: adapte sus includes o traslade expresamente sus personalizaciones.

La distribución incluida utiliza TinyMCE 8.6.0 bajo GPL-2.0-or-later.

TinyMCE es la biblioteca externa; `rich-text-editor` es la integración de GFrame. El componente no incluye almacenamiento de artículos ni carga automática de archivos. Consulte la [referencia de TinyMCE](tinymce.md) para su fuente y documentación.

## Incluir los recursos

La vista que use el editor debe incorporar los recursos declarados en `richTextEditor.meta.php`. Puede combinarlos con su archivo meta:

```php
$editorAssets = require \GFrame\Modules\ModuleRuntime::file(
    'views', 'rich-text-editor/richTextEditor.meta.php', 'rich-text-editor'
);

return [
    'css' => $editorAssets['css'],
    'hjs' => $editorAssets['hjs'],
    'js' => [
        ...$editorAssets['js'],
        'public/js/app/admin/articles/article-form.js',
    ],
];
```

El JavaScript específico del formulario debe cargarse después de `rich-text-editor.js` cuando registre opciones adicionales.

El meta incluye el puente visual `gframe-tinymce.css`. La interfaz y el contenido del iframe utilizan las variables del framework; cambiar `data-bs-theme` actualiza el contenido sin reiniciar el editor ni modificar el HTML guardado. El observador se desconecta al destruir la instancia. Si sustituye `init_instance_callback` mediante `register()`, debe conservar esa sincronización o gestionar su propio tema.

## Renderizar el campo

```php
<?php
$richTextEditor = [
    'id' => 'articleContent',
    'name' => 'content',
    'label' => 'Contenido',
    'value' => $article['content'] ?? '',
    'rows' => 24,
    'min_height' => 580,
    'required' => true,
    'help' => 'Organiza el contenido con títulos, listas y tablas.',
];

include \GFrame\Modules\ModuleRuntime::file(
    'views', 'rich-text-editor/richTextEditor.php', 'rich-text-editor'
);
?>
```

El identificador se normaliza para que sea válido en HTML. Cada editor de una página debe tener un identificador único.

| Opción PHP | Valor predeterminado / efecto |
| --- | --- |
| `id` | `richTextContent`; identifica textarea e instancia JS |
| `name` | `contenido`; clave recibida por el backend |
| `label` | `Contenido` |
| `value` | Texto inicial, vacío por defecto; se escapa dentro del textarea |
| `rows` | 24, con mínimo 8, para el textarea sin editor |
| `min_height` | 580 px, con mínimo 320, para la instancia |
| `required` | Añade el atributo HTML `required` |
| `label_class` | Clases adicionales de la etiqueta |
| `help` | Ayuda opcional debajo del campo |

Estas opciones configuran el campo PHP. Las opciones de TinyMCE se registran por separado en JavaScript.

## Personalizar una instancia

Registre las opciones antes de que el documento termine de cargar:

```javascript
window.AdminRichTextEditor.register('articleContent', {
  toolbar: 'undo redo | blocks | bold italic | bullist numlist | link image | customAction',
  setup: function (editor) {
    editor.ui.registry.addButton('customAction', {
      text: 'Insertar bloque',
      onAction: function () {
        editor.insertContent('<p>Nuevo bloque</p>');
      }
    });
  }
});
```

Las opciones registradas se combinan con la configuración base. El componente permite varias instancias en una misma página.

`register()` debe ejecutarse antes de `init()`. No reconfigura una instancia ya creada: si necesita cambiarla, guarde su contenido, destrúyala y vuelva a inicializarla. El componente evita inicializar dos veces el mismo ID; si TinyMCE no está cargado, `init()` no hace nada y no programa un reintento.

La configuración base activa listas, enlaces, imágenes, tablas, código, vista previa, altura automática y herramientas de selección. Oculta el menú, utiliza español y conserva las URLs del contenido con `convert_urls: false`. La base para cargar el idioma procede de `site_url`; revise esa variable cuando despliegue en una subcarpeta.

## Envío de formularios

Los formularios HTML ordinarios sincronizan TinyMCE automáticamente. Antes de leer o serializar un formulario por AJAX, ejecute:

```javascript
window.AdminRichTextEditor.saveAll();
const payload = $('#article-form').serialize();
```

Sin esta llamada, el `textarea` puede conservar el valor anterior.

Ejecute `saveAll()` antes de validar los campos y de crear `FormData` o llamar a `serialize()`. Mantenga el flujo de [formularios del frontend](frontend-core.md): validación, tokens CSRF, petición AJAX y feedback con los módulos de alertas.

`required` comprueba el textarea, no el significado del HTML. Un contenido como `<p><br></p>` puede superar una comprobación de cadena no vacía. Además, TinyMCE oculta el textarea: no dependa de que el navegador enfoque ese campo para mostrar un error. Valide el contenido de la instancia, muestre el mensaje junto al editor y vuelva a comprobarlo en backend. Para contenido que admite solo imágenes, defina expresamente si una imagen cuenta como contenido válido.

## Contenido dinámico

Después de insertar un fragmento que contenga editores:

```javascript
window.AdminRichTextEditor.initAll(fragmentElement);
```

Antes de reemplazar o retirar el fragmento:

```javascript
window.AdminRichTextEditor.destroyAll(fragmentElement);
```

Para retirar una sola instancia, use `destroy('articleContent')` o entregue el elemento `textarea`.

`root` debe ser un contenedor DOM; `initAll(root)` busca sus descendientes `textarea.js-rich-text-editor`, no el propio root. Para un textarea individual utilice `init(textarea)`. `destroyAll()` no guarda automáticamente el contenido: llame antes a `saveAll()` si lo necesita.

## Pegado desde Word

El componente transforma títulos y listas de Word en HTML semántico, conserva negritas, cursivas y subrayados, y elimina párrafos innecesarios dentro de las celdas de tablas. La configuración `valid_elements` limita los elementos y atributos que conserva el editor.

Se eliminan estilos visuales de origen para que el contenido use el diseño del proyecto. La lista permitida incluye párrafos, encabezados, listas, citas, tablas, enlaces e imágenes, pero no bloques `<pre>` o `<code>`: el botón «Código» edita el HTML fuente, no inserta automáticamente bloques de programación. La conversión de Word aplica heurísticas sobre el contenido pegado; revise documentos con estructuras complejas.

## Imágenes y multimedia

El complemento `image` permite insertar una URL, pero esta integración no configura `images_upload_handler`, un selector de biblioteca ni un endpoint de subida. Conecte esas opciones desde `register()` con el [módulo multimedia](media-library.md), respetando su ámbito, permisos, tipos y límites de archivo. Mantenga en backend la autorización de los IDs o recursos asociados al contenido.

## Seguridad

La validación del navegador no sustituye la validación del servidor. El proyecto debe sanear el HTML antes de almacenarlo o antes de mostrar contenido que no sea de confianza.

Como mínimo:

- utilice una lista permitida equivalente a `valid_elements`;
- rechace protocolos peligrosos en enlaces e imágenes;
- no permita scripts, eventos HTML, iframes ni estilos arbitrarios;
- aplique límites de tamaño;
- valide las imágenes mediante el módulo multimedia cuando permita cargas o selección de archivos.

El módulo no guarda contenido. El core incluye `GFrame\Security\HtmlSanitizer` para limpiar HTML enriquecido en backend. Su uso es explícito en el controlador o servicio receptor:

```php
$content = \GFrame\Security\HtmlSanitizer::sanitize((string)($_POST['content'] ?? ''));
```

No use `sanitize()` de texto plano para este campo si desea conservar su formato. Consulte [limpieza de HTML](html-sanitizer.md) para conocer la política, la dependencia DOM y sus límites.

Valide también longitud, contenido requerido y permisos antes de persistir. La política del sanitizador del servidor puede diferir de `valid_elements`: alinee ambas con el contenido permitido por el proyecto y pruebe el resultado después de guardar y volver a cargar. Los límites de PHP para el tamaño del POST siguen aplicándose.

## Personalización visual

El puente `gframe-tinymce.css` adapta los controles a las variables comunes. El contenido editado vive en un iframe y recibe una copia de las variables Bootstrap; cambiar de tema no modifica el HTML persistido. El CSS del iframe configura únicamente la edición: para mostrar ese contenido en una vista pública, aplique los estilos de esa vista. Si reemplaza callbacks o `content_style`, conserve las responsabilidades que todavía necesite.

## API del componente

| Método | Uso |
| --- | --- |
| `register(id, options)` | Personalizar una instancia antes de inicializarla |
| `init(element)` | Inicializar un `textarea` específico |
| `initAll(root)` | Inicializar todas las instancias dentro de un contenedor |
| `saveAll()` | Sincronizar el contenido con los `textarea` |
| `destroy(elementOrID)` | Destruir una instancia |
| `destroyAll(root)` | Destruir las instancias de un contenedor |
