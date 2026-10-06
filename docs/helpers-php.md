# Helpers PHP del core

GFrame incluye varios helpers estáticos para tareas pequeñas y repetitivas. Esta guía distingue la **API actual** de las funciones globales conservadas por compatibilidad.

No todos los helpers tienen el mismo nivel de abstracción: algunos son utilidades generales y otros generan HTML específico de la interfaz de GFrame.

## API recomendada

| Helper | Responsabilidad | Observación |
| --- | --- | --- |
| `UrlHelper` | URL base, detección SSL y URLs versionadas de assets | Útil en código de aplicación y render. |
| `TextHelper` | nombres aleatorios con prefijo | No es un generador de tokens criptográficos. |
| `SanitizeHelper` | sanitización básica de texto | Solo `rtrim()` + `strip_tags()`; no equivale a `HtmlSanitizer`. |
| `ViewHelper` | render de un fragmento PHP a string | Recibe una ruta absoluta; no resuelve módulos automáticamente. |
| `MediaPathHelper` | derivar URL/ruta de thumbnail por sufijo | Solo transforma el string; no comprueba que el archivo exista. |
| `MenuHelper` | render de menús HTML | Acoplado a la estructura/clases de navegación de GFrame/Bootstrap. |
| `PaginationHelper` | render de paginación HTML | Acoplado a IDs/clases usados por el frontend de GFrame. |
| `LogHelper` | log plano a archivo | Utilidad simple; no sustituye un sistema de logging estructurado. |
| `CorsHelper` | emisión de cabeceras CORS de bajo nivel | En rutas normales usa el middleware/canal de GFrame antes de emitir CORS manualmente. |

Estas clases son globales y Composer las carga por classmap; no utilizan namespace `GFrame\`. **Ese mecanismo de carga no las convierte en legacy ni deprecated.** En esta guía, “legacy” se reserva para wrappers y contratos conservados expresamente por compatibilidad, como las funciones de `LegacyCompatibility.php` descritas más abajo.

## UrlHelper

### `guessUrl()`

```php
$base = UrlHelper::guessUrl();
```

Construye una URL base a partir de la petición actual y devuelve slash final.

Para configuración estable de producción utiliza la URL configurada del proyecto cuando corresponda. `guessUrl()` depende de variables del servidor y está pensado como resolución de entorno, no como fuente de una política de dominio.

### `isSsl()`

```php
if (UrlHelper::isSsl()) {
    // La petición actual se detectó como HTTPS.
}
```

Comprueba `$_SERVER['HTTPS']` y, como alternativa, puerto `443`.

No interpreta por sí mismo cabeceras de proxies inversos. Si el despliegue termina TLS antes de PHP, configura correctamente el servidor y la aplicación en vez de confiar ciegamente en una cabecera enviada por cualquier cliente.

### `assetUrl()`

```php
$css = UrlHelper::assetUrl('public/css/app.css');
```

Si el archivo local existe, añade `?v=<filemtime>` para cache-busting.

También puedes indicar expresamente la ruta local usada para obtener la versión:

```php
$css = UrlHelper::assetUrl(
    'public/css/app.css',
    ABSPATH . 'public/css/app.css'
);
```

URLs `http://`, `https://`, protocol-relative y `data:` se devuelven sin modificación.

## TextHelper

```php
$name = TextHelper::randomName('upload');
$name = TextHelper::randomName('upload', 12);
```

Genera:

```text
<prefijo>_<sufijo aleatorio alfanumérico>
```

El sufijo usa `random_int()`. El mínimo efectivo de longitud es 1.

Aunque utiliza aleatoriedad segura, esta función produce un **nombre**, no un contrato de autenticación. Para tokens de acceso, verificación o recuperación utiliza las APIs específicas de seguridad/autenticación.

## SanitizeHelper

Para elegir entre texto plano, HTML enriquecido y escape de salida, consulta [Sanitización y escape de datos](sanitizacion.md).

```php
$text = SanitizeHelper::sanitize($input);
```

La operación actual equivale conceptualmente a:

```php
strip_tags(rtrim((string)$input));
```

Eso sirve para campos que deben convertirse en texto sin etiquetas, pero no significa que el resultado sea automáticamente seguro en todos los contextos de salida.

Al imprimir HTML sigue escapando según el contexto:

```php
<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>
```

Si el caso necesita **permitir una lista controlada de HTML**, utiliza [HtmlSanitizer](html-sanitizer.md). `SanitizeHelper` y `HtmlSanitizer` resuelven problemas distintos.

## ViewHelper

```php
$html = ViewHelper::fragmentRender(
    ABSPATH . 'app/views/productos/_productList.php',
    $context
);
```

`fragmentRender()`:

- exige una ruta de archivo;
- devuelve `''` si la ruta está vacía o no existe;
- ejecuta el PHP dentro de un buffer de salida;
- devuelve el HTML como string;
- deja `$ctx` disponible dentro del fragmento.

No hace `extract()` de arrays y no aplica por sí mismo el fallback proyecto → módulo.

Para vistas propias de un módulo runtime utiliza `ModuleRuntime::file()` para resolver primero la personalización del proyecto y luego el original del módulo, y renderiza después el archivo resuelto.

## MediaPathHelper

```php
$thumb = MediaPathHelper::thumbnailUrl(
    '/uploads/foto.jpg',
    'small'
);
```

Resultado:

```text
/uploads/foto-small.jpg
```

Sin extensión:

```text
/uploads/foto + small → /uploads/foto-small
```

Este helper solo aplica la convención de nombre. No crea la miniatura, no consulta `MediaLibraryService` y no verifica que exista el archivo derivado.

Para procesamiento, scopes y relaciones utiliza [Biblioteca multimedia](media-library.md).

## MenuHelper

La guía [Menús con MenuHelper](menus.md) detalla el formato posicional, submenús, contenedores, enlace actual y límites de los destinos.

```php
MenuHelper::build($items, $currentUrl, 'nav');
```

La estructura histórica de cada item es posicional. Los tipos soportados por el código actual son:

- `heading`;
- `divider`;
- `item`;
- `menu`;
- `dropdown`.

Ejemplo simple:

```php
$items = [
    ['heading', '', '', 'Catálogo'],
    ['item', '/productos', 'gicon-box', 'Productos'],
    ['item', '/categorias', 'gicon-folder', 'Categorías'],
];

MenuHelper::build($items, $_SERVER['REQUEST_URI'] ?? '');
```

El helper escapa títulos, href, icon classes e IDs antes de imprimirlos. También marca el enlace actual y puede renderizar submenús.

No lo uses como modelo de autorización. Filtra previamente los items que el usuario puede ver y protege además sus rutas con middleware.

## PaginationHelper

```php
PaginationHelper::render($totalPages, $page);
```

Genera el bloque HTML de paginación usado por el frontend actual, con:

- `Anterior` y `Siguiente`;
- página activa;
- hasta cinco páginas visibles antes de compactar con `...`;
- `#pagination` y `#all_items_pagination`;
- clases `prev`, `next` y `linkeable` utilizadas por el frontend.

El helper **renderiza HTML**; no consulta datos, no calcula `totalPages` y no cambia la URL.

Para listados AJAX consulta [Frontend core](frontend-core.md).

## LogHelper

```php
LogHelper::write('Sincronización iniciada', 'sync');
LogHelper::write($payload, 'debug_payload');
```

Cuando `ABSPATH` existe, escribe en:

```text
<proyecto>/logs/<filename>.txt
```

El nombre se reduce a caracteres alfanuméricos, `_`, `-` y `.`. Los datos no string se convierten con `print_r()`.

El helper usa append y bloqueo de archivo. Las operaciones de creación/escritura están silenciadas con `@`, por lo que no ofrece un contrato explícito para saber si el log pudo escribirse.

Úsalo para diagnóstico sencillo, no como sustituto de una estrategia de auditoría, observabilidad o logging estructurado cuando el proyecto necesite garantías mayores.

No registres contraseñas, claves privadas, tokens completos ni otros secretos.

## CorsHelper

```php
CorsHelper::sendHeaders(true);
```

Cuando existe `HTTP_ORIGIN` y `$allow` es `true`, refleja ese origin y emite credenciales, headers y métodos permitidos.

Esta es una utilidad de bajo nivel. Para endpoints de GFrame utiliza primero los canales y middleware documentados en [Router](rutas.md), [Middleware](middleware.md) y [Acceso API](api-access.md). No conviertas una comprobación de autenticación en `sendHeaders(true)`: emitir CORS no autoriza la petición.

## Funciones globales legacy

`src/utils/LegacyCompatibility.php` conserva estas funciones globales:

| Función histórica | API actual relacionada |
| --- | --- |
| `guess_url()` | `UrlHelper::guessUrl()` |
| `is_ssl()` | `UrlHelper::isSsl()` |
| `sanitize()` | `SanitizeHelper::sanitize()` |
| `randomNameGen()` | `TextHelper::randomName()` para código nuevo |
| `buildMenu()` | `MenuHelper::build()` |
| `pagination()` | `PaginationHelper::render()` |
| `send_cors_headers()` | `CorsHelper::sendHeaders()` / middleware de ruta |
| `markdown2html()` | `MarkdownHelper::channelMarkdownToHtml()` cuando la capacidad Markdown está disponible |
| `logger()` | `LogHelper::write()` |

Estas funciones existen para evitar romper aplicaciones anteriores. **No deben ser el estilo enseñado en ejemplos nuevos** cuando ya existe un helper explícito.

La excepción conceptual es CORS: incluso la clase `CorsHelper` es más baja de nivel que el middleware. En código de rutas nuevo, la API de routing/middleware sigue siendo la primera opción.

## Helper no significa validación de negocio

Los helpers resuelven operaciones pequeñas. No deben absorber reglas como:

- si un usuario puede modificar un registro;
- si un producto pertenece al tenant actual;
- si un precio es válido para un plan;
- si un archivo puede asociarse a una entidad concreta.

Esas decisiones pertenecen a middleware, services y modelos según el caso.
