# Markdown

Componente reutilizable de conversión entre Markdown y HTML, independiente de la lógica de aplicación. El código PHP se encuentra en `resources/modules/markdown/src/` y Composer carga `MarkdownHelper`. No contiene transportes ni lógica de bots.

## Backend

```php
$html = MarkdownHelper::channelMarkdownToHtml('*Texto en negrita*');
$html = MarkdownHelper::chatMarkdownToHtml('## Título');
$texto = MarkdownHelper::htmlToChannelMarkdown('<strong>Texto</strong>');
$texto = MarkdownHelper::htmlToChatMarkdown('<h2>Título</h2>');
```

El perfil `channel` usa formato simple (`*negrita*`, `_cursiva_`, `~tachado~` y código). El perfil `chat` permite una sintaxis más amplia, con encabezados, listas, citas, enlaces y tablas. Los nombres describen formatos, no garantizan compatibilidad con una API externa específica. No es una implementación completa de CommonMark.

## Frontend

Seleccione `markdown` en el instalador o publique sus recursos con `php bin/modules.php publish /ruta/del/proyecto/public markdown`. Declare `public/vendors/internal/markdown/markdown.js` en el meta de las vistas que lo necesiten. No se carga desde `frontend-core`.

`markdown2Html(texto)` convierte negrita, cursiva, tachado y saltos de línea. Escapa el HTML de entrada antes de aplicar el formato. `html2Markdown(html)` realiza la conversión inversa de esas etiquetas básicas; no es un sanitizador ni un conversor general de documentos HTML.

El JavaScript no implementa todo el perfil PHP `chat`. Para una representación completa y consistente, convierta en el backend y entregue el HTML a la vista. Si amplía la sintaxis, añada pruebas para PHP y JS y documente expresamente qué perfil soporta cada uno.

## Migración

Las futuras migraciones de proyectos deben sustituir la antigua ruta `public/js/core/utils/markdown.js` por la ruta del componente. El backend conserva el nombre `MarkdownHelper`; no se añade una fachada nueva de compatibilidad.
