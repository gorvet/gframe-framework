# TinyMCE

Motor externo de edición enriquecida. GFrame distribuye la versión 8.6.0 con sus idiomas, complementos, estilos y temas en `public/vendors/external/tinymce/`.

Fuente: [TinyMCE](https://www.tiny.cloud/). Consulte su [documentación para TinyMCE 8](https://www.tiny.cloud/docs/tinymce/8/) para opciones, plugins y eventos del motor. La versión incluida se declara en el manifiesto del módulo y en su `package.json`; no implica que sea la última versión publicada por el proveedor.

El módulo `rich-text-editor` instala TinyMCE como dependencia y proporciona la integración reutilizable de GFrame. Consulte [Editor de texto enriquecido](rich-text-editor.md) para usar esa integración. Si necesita el motor por separado, publique `tinymce` con `php bin/modules.php publish /ruta/del/proyecto/public tinymce` y cargue `tinymce.min.js` en el meta de la vista. La aplicación debe configurar la instancia y respetar la licencia distribuida con el paquete.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.
