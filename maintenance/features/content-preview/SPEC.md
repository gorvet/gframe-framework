# Vista previa privada del sitio

**Estado:** alcance cerrado el 2026-10-19 por autorización del usuario.

La vista previa usa la plantilla pública real y la copia privada existente. Tras confirmar un autoguardado o guardado, una vista previa abierta se actualiza automáticamente. Sustituye el botón de preview nativo de TinyMCE; no publica cambios. La barra pública de administración queda aplazada expresamente.

La aplicación conserva su controlador público, modelo, rutas y autorización de registro/tenant. El framework aporta lectura privada autorizada, estado para actualización y componente frontend; no inventa tablas de artículos ni URLs de edición. La integración se activa por campo con preview_url y reutiliza content-autosave. No se actualizan instalaciones ni se publica una versión automáticamente.
