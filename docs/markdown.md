# Markdown

`markdown` convierte texto con formato a HTML y transforma HTML en Markdown mediante perfiles PHP. Incluye una conversión básica para el navegador. Puede utilizarlo para vistas de documentación, mensajes y exportaciones de contenido.

## Instalación y perfiles

Instale `markdown` mediante el [catálogo de módulos](modulos-opcionales.md). Composer carga la clase global `MarkdownHelper`; no necesita construir una instancia. El módulo no crea tablas ni rutas propias.

| Conversión | Sintaxis principal |
| --- | --- |
| PHP, perfil `channel` | `*negrita*`, `_cursiva_`, `~tachado~`, código inline y bloques de código |
| PHP, perfil `chat` | `**negrita**`, `*cursiva*`, `~~tachado~~`, encabezados, listas, citas, enlaces, imágenes y tablas |
| JavaScript | Formato inline de `channel` y saltos de línea; sin bloques de código |

Seleccione el perfil según la sintaxis del contenido. El texto `*Hola*` produce negrita en `channel` y cursiva en `chat`.

## Backend

```php
$html = MarkdownHelper::channelMarkdownToHtml('*Texto en negrita*');
$html = MarkdownHelper::chatMarkdownToHtml('## Título');
$texto = MarkdownHelper::htmlToChannelMarkdown('<strong>Texto</strong>');
$texto = MarkdownHelper::htmlToChatMarkdown('<h2>Título</h2>');
```

El perfil `channel` usa formato simple (`*negrita*`, `_cursiva_`, `~tachado~` y código). El perfil `chat` permite una sintaxis más amplia, con encabezados, listas, citas, enlaces y tablas. Los nombres describen formatos, no garantizan compatibilidad con una API externa específica. No es una implementación completa de CommonMark.

### Renderizar una vista

Convierta el contenido en el controlador o servicio y entregue el resultado a la vista:

```php
$markdown = "## Primeros pasos\n\n**Instala** el proyecto.\n\n- Configura la aplicación\n- Crea tu primera ruta";
$contentHtml = \MarkdownHelper::chatMarkdownToHtml($markdown);
```

La vista imprime el HTML generado:

```php
<article class="documentation-content"><?php echo $contentHtml; ?></article>
```

No escape de nuevo `$contentHtml`, porque mostraría las etiquetas como texto. Esta salida debe proceder del conversor, no de HTML arbitrario recibido del usuario. El módulo genera etiquetas semánticas; sus colores, espaciado y estilos se definen en el CSS de la vista.

### HTML a Markdown

`htmlToChatMarkdown()` conserva encabezados, listas, enlaces, imágenes y tablas en la sintaxis del perfil. `htmlToChannelMarkdown()` simplifica encabezados a texto, enlaces a texto con URL y tablas a filas separadas por barras. No conserva estilos, scripts de interacción ni un diseño HTML completo. La conversión de ida y vuelta puede perder formato.

La conversión PHP de HTML utiliza `DOMDocument`. Sin esa extensión, devuelve texto obtenido con `strip_tags()` y pierde la estructura. No utilice estas funciones como sanitizador de HTML: si necesita conservar HTML de entrada, aplique el [contrato de sanitización](html-sanitizer.md).

## Frontend

Seleccione `markdown` en el instalador o publique sus recursos con `php bin/modules.php publish /ruta/del/proyecto/public markdown`. Declare `public/vendors/internal/markdown/markdown.js` en el meta de las vistas que lo necesiten. No se carga desde `frontend-core`.

`markdown2Html(texto)` convierte negrita, cursiva, tachado y saltos de línea. Escapa el HTML de entrada antes de aplicar el formato. `html2Markdown(html)` realiza la conversión inversa de esas etiquetas básicas; no es un sanitizador ni un conversor general de documentos HTML.

Declare el recurso antes del JavaScript de su vista, siguiendo las [metas](meta.md):

```php
<?php
return ['js' => [
    'public/vendors/internal/markdown/markdown.js',
    'public/js/messages/preview.js',
]];
```

En `preview.js`, una conversión básica puede utilizarse así:

```js
const formatted = markdown2Html('*Hola*\n_Revisa tu cuenta_');
const plainMarkdown = html2Markdown('<strong>Hola</strong><br><em>Revisa tu cuenta</em>');
```

`html2Markdown()` conserva las etiquetas desconocidas: su resultado sigue siendo texto y no debe insertarse mediante `innerHTML`. Para mostrar ese texto, utilice `textContent`. No carga contenido, guarda archivos ni conecta un editor por sí mismo.

El JavaScript no implementa todo el perfil PHP `chat`. Para una representación completa y consistente, convierta en el backend y entregue el HTML a la vista. Si amplía la sintaxis, añada pruebas para PHP y JS y documente expresamente qué perfil soporta cada uno.

## Seguridad y ampliación

Los conversores Markdown a HTML escapan HTML de entrada. En PHP, los enlaces e imágenes comprueban sus esquemas y descartan `javascript:`, `vbscript:` y `data:`. No existe una lista de dominios permitidos: el proyecto debe decidir si admite enlaces externos o imágenes remotas, que pueden comunicar datos al servidor de la imagen.

`MarkdownHelper` es final y sus métodos son estáticos. Añada reglas de negocio mediante un servicio del proyecto que llame al perfil correspondiente; no herede esta clase. Para otra sintaxis, integre un conversor específico sin afirmar compatibilidad automática con estos perfiles.

## Compatibilidad de rutas

Las futuras migraciones de proyectos deben sustituir la antigua ruta `public/js/core/utils/markdown.js` por la ruta del componente. El backend conserva el nombre `MarkdownHelper`; no se añade una fachada nueva de compatibilidad.
