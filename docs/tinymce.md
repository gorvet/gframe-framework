# TinyMCE

Motor externo de edición enriquecida. GFrame distribuye la versión 8.6.0 con sus idiomas, complementos, estilos y temas en `public/vendors/external/tinymce/`.

Fuente: [TinyMCE](https://www.tiny.cloud/). Consulte su [documentación para TinyMCE 8](https://www.tiny.cloud/docs/tinymce/8/) para opciones, plugins y eventos del motor. La versión incluida se declara en el manifiesto del módulo y en su `package.json`; no implica que sea la última versión publicada por el proveedor.

El módulo `rich-text-editor` instala TinyMCE como dependencia y proporciona la integración reutilizable de GFrame. Consulte [Editor de texto enriquecido](rich-text-editor.md) para usar esa integración. Si necesita el motor por separado, publique `tinymce` con `php bin/modules.php publish /ruta/del/proyecto/public tinymce` y cargue `tinymce.min.js` en el meta de la vista. La aplicación debe configurar la instancia y respetar la licencia distribuida con el paquete.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.


## Uso directo frente a rich-text-editor

Use TinyMCE directamente solo cuando la integración estándar de [Editor de texto enriquecido](rich-text-editor.md) no cubra el caso. El componente de GFrame ya resuelve publicación, idioma, tema, pegado desde Word, sincronización de formularios y ciclo de vida; duplicar esas responsabilidades en cada pantalla aumenta el riesgo de inconsistencias.

Para una instancia directa, cargue `tinymce.min.js`, inicialice un selector propio y defina explícitamente plugins, toolbar, idioma y política de contenido. No presuponga que la configuración de `rich-text-editor` se aplica automáticamente a una instancia creada por `tinymce.init()`.

## Ciclo de vida

Una instancia debe retirarse antes de reemplazar su textarea o contenedor. TinyMCE mantiene estado fuera del textarea original; eliminar solo el nodo HTML puede dejar referencias y eventos activos.

Cuando la aplicación envía un formulario por AJAX, sincronice el contenido del editor con el textarea antes de serializar. El componente `rich-text-editor` ofrece `saveAll()` para este fin; en uso directo debe implementar el equivalente con la API del motor.

## Seguridad

TinyMCE es un editor, no una frontera de seguridad. La configuración del navegador puede limitar elementos, pero el backend debe sanear el HTML según la política del proyecto antes de persistir o mostrar contenido no confiable. Consulte [Sanitización de HTML](html-sanitizer.md).

No habilite plugins o elementos arbitrarios sin revisar qué HTML producen y qué recursos externos permiten incrustar.

## Tema y contenido del iframe

La interfaz del editor y el documento editado son contextos distintos. El puente de GFrame adapta la interfaz y `rich-text-editor` sincroniza variables dentro del iframe. En una integración directa, la aplicación debe decidir cómo aplicar tipografía, colores y tema al contenido editable.

## Actualización

Al cambiar de versión, revise plugins, opciones eliminadas, idioma, temas, licencia y HTML producido. No reemplace únicamente `tinymce.min.js`: el paquete incluye complementos, iconos, skins, modelos e idiomas que deben permanecer compatibles entre sí.
