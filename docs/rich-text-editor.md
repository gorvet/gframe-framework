# Editor de texto enriquecido

El módulo `rich-text-editor` publica un componente reutilizable basado en TinyMCE. Incluye idioma español, normalización del contenido pegado desde Word y soporte para enlaces, imágenes, tablas, listas, código y vista previa.

## Instalación

```powershell
php bin/modules.php publish-project rich-text-editor C:\ruta\del\proyecto
```

La publicación instala automáticamente jQuery y TinyMCE. Conserva las vistas originales en `resources/modules/rich-text-editor/application/app/views/rich-text-editor/` y crea carpetas vacías de personalización en `app`. Publica únicamente el JavaScript:

- `public/js/app/admin/components/rich-text-editor.js`.

Personalice el campo o sus metadatos creando `app/views/rich-text-editor/richTextEditor.php` o `richTextEditor.meta.php`. Si no existen, se usan los originales. Los archivos antiguos de `app/views/admin/components/` no se borran automáticamente: adapte sus includes o traslade expresamente sus personalizaciones. La estructura del campo, el JavaScript y el puente visual no se han rediseñado.

La distribución incluida utiliza TinyMCE 8.6.0 bajo GPL-2.0-or-later.

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

## Personalizar una instancia

Registre las opciones antes de que el documento termine de cargar:

```javascript
window.AdminRichTextEditor.register('articleContent', {
  toolbar: 'undo redo | blocks | bold italic | bullist numlist | link image',
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

## Envío de formularios

Los formularios HTML ordinarios sincronizan TinyMCE automáticamente. Antes de leer o serializar un formulario por AJAX, ejecute:

```javascript
window.AdminRichTextEditor.saveAll();
const payload = $('#article-form').serialize();
```

Sin esta llamada, el `textarea` puede conservar el valor anterior.

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

## Pegado desde Word

El componente transforma títulos y listas de Word en HTML semántico, conserva negritas, cursivas y subrayados, y elimina párrafos innecesarios dentro de las celdas de tablas. La configuración `valid_elements` limita los elementos y atributos que conserva el editor.

## Seguridad

La validación del navegador no sustituye la validación del servidor. El proyecto debe sanear el HTML antes de almacenarlo o antes de mostrar contenido que no sea de confianza.

Como mínimo:

- utilice una lista permitida equivalente a `valid_elements`;
- rechace protocolos peligrosos en enlaces e imágenes;
- no permita scripts, eventos HTML, iframes ni estilos arbitrarios;
- aplique límites de tamaño;
- valide las imágenes mediante el módulo multimedia cuando permita cargas o selección de archivos.

El módulo no guarda contenido. El core incluye `GFrame\Security\HtmlSanitizer`, extraído de BaseConfías, para limpiar HTML enriquecido en backend. Su uso es explícito en el controlador o servicio receptor:

```php
$content = \GFrame\Security\HtmlSanitizer::sanitize((string)($_POST['content'] ?? ''));
```

No use `sanitize()` de texto plano para este campo si desea conservar su formato. Consulte [limpieza de HTML](html-sanitizer.md) para conocer la política, la dependencia DOM y sus límites.

## API del componente

| Método | Uso |
| --- | --- |
| `register(id, options)` | Personalizar una instancia antes de inicializarla |
| `init(element)` | Inicializar un `textarea` específico |
| `initAll(root)` | Inicializar todas las instancias dentro de un contenedor |
| `saveAll()` | Sincronizar el contenido con los `textarea` |
| `destroy(elementOrID)` | Destruir una instancia |
| `destroyAll(root)` | Destruir las instancias de un contenedor |
