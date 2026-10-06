# Limpieza de HTML enriquecido

`GFrame\Security\HtmlSanitizer` pertenece al core y está disponible en todos los proyectos mediante Composer, sin instalar un módulo opcional.

```php
$safeHtml = \GFrame\Security\HtmlSanitizer::sanitize($untrustedHtml);
```

Conserva párrafos, títulos, listas, tablas, enlaces e imágenes con una lista limitada de atributos. Elimina scripts, estilos, iframes, eventos y protocolos no autorizados. Los enlaces válidos reciben `rel="noopener noreferrer"`. Admite HTTP/HTTPS, rutas desde la raíz y `uploads/`; enlaces también admiten `mailto:` y `tel:`. La política no autoriza lecturas de archivos ni solicitudes HTTP desde el servidor.

DOM es un requisito de Composer. Sin DOM se lanza `RuntimeException`: no se conserva la alternativa original basada en `strip_tags` con etiquetas permitidas, porque dejaba atributos peligrosos. El parser utiliza `LIBXML_NONET`; se conserva el estado previo de errores de libxml. Las pruebas cubren HTML enriquecido, Unicode, protocolos y eventos peligrosos, elementos no permitidos y ausencia de DOM simulada.

El HTML devuelto solo debe usarse como contenido HTML del cuerpo, no dentro de atributos, JavaScript o CSS. Para esos contextos se necesita el escape correspondiente. `SanitizeHelper::sanitize()` sigue siendo la utilidad de texto plano; su contrato no cambia. La limpieza del editor en el navegador no sustituye esta validación del backend. El receptor debe limitar el tamaño del contenido y aplicar sus permisos; esta utilidad no verifica permisos, propiedad, privacidad de imágenes ni legitimidad de enlaces externos.

La sanitización no certifica la ausencia de todas las variantes de XSS. Mantenga pruebas de regresión al modificar etiquetas o atributos permitidos.

## Integración al guardar contenido

Valida el tamaño y los permisos antes de persistir contenido de un editor:

```php
<?php
use GFrame\Security\HtmlSanitizer;

$html = (string)($_POST['content'] ?? '');
if (strlen($html) > 100000) {
    return ['status' => 'error', 'code' => 'content_too_large'];
}
$safeHtml = HtmlSanitizer::sanitize($html);
if ($safeHtml === '') {
    return ['status' => 'error', 'code' => 'content_required'];
}
// Guarda $safeHtml mediante el modelo autorizado del proyecto.
return ['status' => 'success', 'data' => ['content' => $safeHtml]];
```

El límite del ejemplo se expresa en bytes y pertenece al proyecto. Una salida no vacía puede contener solamente marcado sin texto; si necesitas contenido textual mínimo, valida también `strip_tags($safeHtml)`.

## Etiquetas y atributos admitidos

Se conservan `p`, `br`, `strong`, `b`, `em`, `i`, `u`, `s`, `ul`, `ol`, `li`, `a`, `h1` a `h6`, `blockquote`, `table`, `thead`, `tbody`, `tr`, `td`, `th`, `img` y `hr`.

| Elemento | Atributos conservados |
| --- | --- |
| Enlace | `href`, `target`, `rel`; se normalizan los valores de seguridad |
| Imagen | `src`, `alt`, `title`, `width`, `height` |
| Lista ordenada / elemento | `start`, `type` / `value` |
| Celda `td` o `th` | `colspan`, `rowspan` |

No se conservan clases, IDs ni estilos inline. Los elementos no admitidos se eliminan conservando sus hijos, salvo scripts, estilos, iframes, object y embed, cuyo contenido también se retira. Una imagen sin URL admitida se elimina; un enlace con URL no admitida conserva su texto sin `href`.

Las rutas que empiezan por `/` incluyen URLs relativas al protocolo como `//example.com/imagen.jpg`; la política actual no restringe el host. Si el proyecto necesita imágenes privadas o dominios permitidos, valida esa política adicional antes de guardar.

## Texto plano, salida y ampliación

`SanitizeHelper::sanitize()` elimina etiquetas y espacios finales; no valida correo, longitud, permisos ni protocolos. Para mostrar texto plano utiliza además `htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` en el HTML.

El HTML enriquecido saneado se imprime como fragmento del cuerpo. Escaparlo como texto mostraría sus etiquetas; usarlo en un atributo o script sería un contexto diferente y necesita otra protección. No envíes este HTML a una plantilla de correo que trate sus variables como texto esperando conservar el formato.

HtmlSanitizer es final y no expone listas configurables ni presets. Para otra política, compón un servicio de seguridad del proyecto con un sanitizador adecuado y pruebas propias; no añadas etiquetas mediante reemplazos de cadenas después de sanear. Revisa contenido antiguo si cambias la política de seguridad, sin asumir que el filtro del editor del navegador ya lo protegió.
