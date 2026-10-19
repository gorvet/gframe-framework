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
| `min_height` | 580 px, con mínimo 320; altura inicial y mínima de la instancia |
| `required` | Añade el atributo HTML `required` |
| `label_class` | Clases adicionales de la etiqueta |
| `help` | Ayuda opcional debajo del campo |
| `media_library` | `false`; habilita la integración opcional de biblioteca y galerías |
| `save_button` | Vacío; ID explícito del botón de guardar del mismo formulario, para acceso desde el editor |
| `protect_changes` | `true`; aviso nativo al abandonar contenido distinto del último guardado confirmado |
| `draft_key` | Vacío; activa copias locales con clave explícita por usuario/ámbito, documento y campo; máximo 512 bytes |
| `autosave` | Vacío; URL autorizada, revisión pública e intervalo para [autoguardado privado en servidor](content-autosave.md) |

Estas opciones configuran el campo PHP. Las opciones de TinyMCE se registran por separado en JavaScript.

## Protección y recuperación

Para guardar también una copia privada en el servidor, consulte [Autoguardado privado](content-autosave.md). Es optativo y conserva la publicación en el flujo de la aplicación.

El editor avisa al salir o recargar si su HTML difiere del último guardado confirmado. La comparación es independiente de `isDirty()`: sincronizar el textarea, `saveAll()`, submit o `setDirty(false)` no demuestran persistencia. El navegador decide el texto y la disponibilidad del aviso, requiere interacción previa y puede omitirlo en cierres forzados. `protect_changes => false` lo desactiva por campo.

La navegación que reemplaza vistas por AJAX no dispara `beforeunload`: el proyecto debe consultar `AdminRichTextEditor.hasUnsavedChanges(editorID)` o, sin argumentos, todos los editores vivos, y usar su flujo de confirmación antes de sustituir el formulario. Destruir una instancia conserva su copia local cuando está habilitada y retira sus listeners; no confirma guardado remoto.

Para habilitar borradores, configure `draft_key` desde el servidor con una clave opaca y estable por usuario/ámbito, documento y campo. Ejemplo construido con IDs autorizados: `tenant:7:user:23:article:42:content`. No use credenciales, tokens ni datos personales. Para documentos nuevos asigne una clave de creación estable y distinta por documento. No se infiere identidad de la URL; el framework añade la base de la aplicación y el ID del editor.

```php
$richTextEditor['draft_key'] = $draftScope; // Construida con el contexto autorizado.
```

Las copias se actualizan tras una pausa de 600 ms y al ocultar/abandonar la página. Se guardan en `localStorage` de este navegador, con una clave independiente por instancia para que dos pestañas no sobrescriban sus copias. Caducan a los siete días desde su última actualización y se eliminan al abrir ese ámbito. El límite por copia es aproximadamente 1 MiB, contando HTML y versión base en UTF-16. Si falla el almacenamiento, se informa junto al campo y se conserva el texto para seguir editando; la última copia válida puede ser anterior al fallo.

Al volver se ofrece la copia más reciente del ámbito. **Recuperar borrador** sustituye el texto solo al pulsarlo y admite Deshacer. Si cambió la versión del servidor, se advierte antes de recuperarla. **Descartar copia local** requiere confirmación, conserva el texto actual y ofrece la siguiente copia si existe. No se recupera HTML automáticamente. El editor filtra lo recuperado; el backend debe mantener su sanitización y autorización.

Son copias locales del campo, sin cifrar: no incluyen otros campos, archivos ni revisiones del servidor. La aplicación debe definir su política de equipos compartidos y limpieza al cerrar sesión. Cambiar de usuario exige cambiar la clave; separar claves no convierte el almacenamiento del navegador en una frontera de autorización. Borrar los datos del sitio, usar modo privado o agotar la cuota puede impedir la recuperación. No hay guardado remoto automático.

### Confirmar el guardado real

La confirmación de descarte usa `swalAlert` del módulo `alerts`, ya cargado por el panel estándar. En una plantilla propia, instale `alerts` y use `rich-text-editor/richTextEditorRecovery.meta.php` en lugar del meta básico (resuelto con `ModuleRuntime::file`, igual que el ejemplo de recursos). Si combina biblioteca y recuperación, fusione ambos metadatos evitando duplicados y cargue sus dependencias antes del JS del editor. Sin el helper de confirmación, la copia se conserva y se informa del recurso que falta.

Capture un snapshot **antes de enviar** y confirme el mismo objeto únicamente cuando la respuesta de negocio indique éxito. Envíe su `content` para representar exactamente el contenido capturado:

```javascript
function saveArticle(form) {
  window.AdminRichTextEditor.saveAll();
  if (!form.checkValidity()) {
    form.classList.add('was-validated');
    return;
  }
  const snapshot = window.AdminRichTextEditor.getSaveSnapshot('articleContent');
  if (!snapshot) return; // El editor aún no está listo.
  const data = new FormData(form);
  data.set('content', snapshot.content);
  $('#tokens').serializeArray().forEach(token => data.set(token.name, token.value));
  $.ajax({url: form.action, type: 'POST', data, processData: false, contentType: false})
    .done(function (response) {
      if (response.status === 'success') {
        window.AdminRichTextEditor.markSaved(snapshot);
      }
      // Conservar el tratamiento de errores y feedback de la aplicación.
    });
  // Mantener también el manejador de fallos de transporte del proyecto.
}
```

`markSaved(snapshot)` devuelve `false` para objetos inventados, instancias destruidas o confirmaciones anteriores a otra ya aceptada. El snapshot es inmutable y pertenece a esa instancia: no lo reconstruya desde JSON. Si el usuario escribió durante la petición, esos cambios siguen pendientes y recuperables. Confirmar elimina la copia propia que ya coincide y la copia recuperada que representa ese guardado, sin borrar otras copias.

Serialice escrituras al servidor o use el control de versiones de la aplicación para resolver peticiones concurrentes: esta API no impone el orden de persistencia remota. Si el servidor transforma el contenido, reconcilie su respuesta sin reemplazar ediciones posteriores. En formularios POST tradicionales puede aparecer el aviso durante el envío, porque submit no confirma persistencia. Una copia idéntica a la versión cargada no se ofrece como cambio recuperable.

Para actualizar una integración existente, añada la clave por campo y capture/confirme el snapshot en su flujo de guardado. Los overrides de la vista deben incorporar los nuevos atributos y el panel PHP o usar el original mediante `ModuleRuntime`. `setDirty(false)` por sí solo ya no desactiva la protección.

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

La configuración base activa listas, enlaces, imágenes, tablas, código y herramientas de selección. Oculta el menú, utiliza español y conserva las URLs del contenido con `convert_urls: false`. La base para cargar el idioma procede de `site_url`; revise esa variable cuando despliegue en una subcarpeta.

### Herramientas de escritura

**Pantalla completa** permite concentrarse en el editor y volver al formulario. **Esquema del artículo** enumera los títulos existentes y sitúa el cursor en el elegido, sin añadir identificadores al HTML. Ambos controles usan la interfaz nativa de TinyMCE; las barras personalizadas pueden incluir `fullscreen` y `gframeOutline`.

Al abrir la biblioteca desde concentración se vuelve temporalmente al formulario para que el modal conserve visibilidad y foco; al cerrarlo se recupera la concentración. Si un proyecto activa `fullscreen_native`, deberá volver a pantalla completa mediante una acción del usuario, por la restricción del navegador.

Al seleccionar texto aparece una barra contextual con formatos de párrafo y títulos, negrita, cursiva, subrayado, listas, cita y enlace. El contador de palabras se muestra en la barra de estado.

Escribe `/` al inicio de un párrafo para elegir párrafo, título 2 o 3, lista con viñetas, lista numerada, cita o separador. Puedes filtrar escribiendo, desplazarte con las flechas y confirmar con Intro. Escape cierra el menú y conserva el texto escrito. No se abre dentro de una frase o ruta. Las operaciones admiten Deshacer y mantienen el contenido HTML existente; no requieren la biblioteca de medios.

La barra principal reúne formato básico, listas, enlaces, imágenes y tablas. **Más herramientas** contiene subrayado, cita, separador, quitar formato y código; con `preview_url`, también la [vista previa privada del sitio](content-preview.md). Usa un grupo nativo de TinyMCE en modo `floating`, accesible también con teclado. Si un proyecto registra otro `toolbar_mode`, el grupo propio se despliega en botones en línea; no se pierden esas funciones. Una barra personalizada sigue el orden que registre el proyecto.

Con la biblioteca activada, seleccionar una imagen muestra **Cambiar imagen** y **Quitar imagen del texto**. En una galería aparecen **Editar galería** y **Quitar galería del texto**. Quitar modifica solo el documento, admite Deshacer y conserva los archivos de la biblioteca. Las acciones de alineación predeterminadas de Quickbars se sustituyen por este contexto, salvo configuración explícita del proyecto.

### Altura y desplazamiento

El editor comienza con la altura indicada por `min_height` (580 px por defecto) y mantiene su tamaño aunque el artículo sea largo. El texto se desplaza dentro del editor; la barra de herramientas permanece fuera de ese desplazamiento. La rueda en los extremos del contenido no arrastra el formulario exterior. Esto permite seguir accediendo a un aside o a las acciones del formulario sin recorrer todo el artículo. El layout del aside pertenece a cada vista; no se modifica automáticamente.

El tirador inferior permite aumentar manualmente la altura. Para otro tamaño inicial y mínimo, usa la opción PHP existente `min_height`, o registra opciones TinyMCE como `height: 480, min_height: 320`. No se activa `autoresize` de forma predeterminada. Si un proyecto lo necesita, puede añadirlo expresamente a su lista de `plugins` mediante `register()`, asumiendo entonces el crecimiento del editor con el contenido.

El aislamiento del desplazamiento forma parte del `content_style` base. Si sustituyes ese estilo, conserva `html, body { overscroll-behavior-y: contain; }` cuando quieras mantenerlo. Las vistas o scripts personalizados que ya registren `autoresize` deben revisarse para obtener el nuevo comportamiento; actualizar el paquete no elimina esa elección del proyecto.

## Envío de formularios

### Guardar desde el editor

Para añadir un acceso al botón existente del formulario, configura `save_button` en el campo PHP:

```php
$richTextEditor = [
    'id' => 'articleContent', 'name' => 'content', 'label' => 'Contenido',
    'value' => $article['content'] ?? '', 'save_button' => 'article-save',
];
include \GFrame\Modules\ModuleRuntime::file('views', 'rich-text-editor/richTextEditor.php', 'rich-text-editor');
// Dentro del mismo formulario, o asociado mediante el atributo HTML form.
```

```html
<button type="submit" id="article-save" class="btn btn-primary">Guardar borrador</button>
```

El editor incorpora un botón con el nombre de ese botón y el atajo **Ctrl+S** (Windows/Linux) o **Cmd+S** (macOS). Antes del clic sincroniza los editores del mismo formulario; después ejecuta el clic existente. Se conserva la validación HTML y el manejador AJAX o submit del proyecto. No envía peticiones propias, no elige entre guardar/publicar y no declara éxito de persistencia. Si apuntas a un botón de publicar, esa seguirá siendo su acción: utiliza un nombre explícito en el botón del formulario.

El acceso queda deshabilitado si falta el botón, pertenece a otro formulario, no es un botón de acción, está disabled/aria-disabled o con clase disabled, el editor está en modo lectura o espera una selección/inserción de medios. No se registra el atajo si no se configura `save_button`. En barras personalizadas como arreglo/objeto, incluye `gframeSave` donde corresponda.

Para este campo, la sincronización automática al hacer submit se realiza sin limpiar la marca interna de cambios de TinyMCE. Para confirmar un guardado use el snapshot descrito en [Protección y recuperación](#protección-y-recuperación); `setDirty(false)` por sí solo no confirma persistencia. Las llamadas explícitas a `saveAll()` o `save()` mantienen su comportamiento de sincronización; esta integración no sustituye el manejo de errores de la aplicación.

### Formularios y AJAX existentes

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

El complemento `image` conserva la inserción por URL. Para insertar y subir desde la biblioteca, sustituir imágenes o componer y reeditar galerías ordenadas, activa `media_library` por campo y carga los recursos del picker. Consulta [Biblioteca dentro del editor](rich-text-media.md) para la integración, guardado seguro, estilos públicos y actualización. No se añade un almacenamiento de artículos ni un `images_upload_handler`: se usa la carga existente del picker.

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
| `getSaveSnapshot(editorID)` | Capturar HTML y referencia inmutable para el próximo guardado; `null` antes de inicializar |
| `hasUnsavedChanges(editorID)` | Consultar cambios sin confirmar por editor; sin argumento consulta todos los editores vivos |
| `markSaved(snapshot)` | Reconocer el guardado confirmado de ese snapshot sin perder ediciones posteriores |
| `markSaved(snapshot, baseVersion)` | Con autoguardado remoto, reconocer publicación y adoptar la nueva revisión pública |
| `destroy(elementOrID)` | Destruir una instancia |
| `destroyAll(root)` | Destruir las instancias de un contenedor |
