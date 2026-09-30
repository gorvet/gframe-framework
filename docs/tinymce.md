# TinyMCE

Motor externo de edición enriquecida. GFrame distribuye la versión 8.6.0 con sus idiomas, complementos, estilos y temas en `public/vendors/external/tinymce/`.

El módulo `rich-text-editor` instala TinyMCE como dependencia y proporciona la integración reutilizable de GFrame. Consulte [Editor de texto enriquecido](rich-text-editor.md) para usar esa integración. Si necesita el motor por separado, publique `tinymce` con `php bin/modules.php publish /ruta/del/proyecto/public tinymce` y cargue `tinymce.min.js` en el meta de la vista. La aplicación debe configurar la instancia y respetar la licencia distribuida con el paquete.
