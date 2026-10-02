# Limpieza de HTML enriquecido

`GFrame\Security\HtmlSanitizer` pertenece al core y está disponible en todos los proyectos mediante Composer, sin instalar un módulo opcional. Se copió el sanitizador de BaseConfías y se retiró su nombre específico de procedimientos.

```php
$safeHtml = \GFrame\Security\HtmlSanitizer::sanitize($untrustedHtml);
```

Conserva párrafos, títulos, listas, tablas, enlaces e imágenes con una lista limitada de atributos. Elimina scripts, estilos, iframes, eventos y protocolos no autorizados. Los enlaces válidos reciben `rel="noopener noreferrer"`. Admite HTTP/HTTPS, rutas desde la raíz y `uploads/`; enlaces también admiten `mailto:` y `tel:`. La política no autoriza lecturas de archivos ni solicitudes HTTP desde el servidor.

DOM es un requisito de Composer. Sin DOM se lanza `RuntimeException`: no se conserva la alternativa original basada en `strip_tags` con etiquetas permitidas, porque dejaba atributos peligrosos. El parser utiliza `LIBXML_NONET`; se conserva el estado previo de errores de libxml. Las pruebas cubren HTML enriquecido, Unicode, protocolos y eventos peligrosos, elementos no permitidos y ausencia de DOM simulada.

El HTML devuelto solo debe usarse como contenido HTML del cuerpo, no dentro de atributos, JavaScript o CSS. Para esos contextos se necesita el escape correspondiente. `SanitizeHelper::sanitize()` sigue siendo la utilidad de texto plano; su contrato no cambia. La limpieza del editor en el navegador no sustituye esta validación del backend. El receptor debe limitar el tamaño del contenido y aplicar sus permisos; esta utilidad no verifica permisos, propiedad, privacidad de imágenes ni legitimidad de enlaces externos.

Esta extracción incluye cambios puntuales de seguridad, no una certificación de ausencia de todas las variantes de XSS. Mantenga pruebas de regresión al modificar etiquetas o atributos permitidos.
