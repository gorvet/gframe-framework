# WordPress headless

`wordpress-headless` permite que una aplicación GFrame consuma contenido, taxonomías, menús y esquema de un WordPress que tenga instalado BridgeFrame. El módulo incluye los estilos necesarios para presentar el HTML de WordPress; no existe un módulo de estilos separado. El contrato canónico es BridgeFrame `2.0`, bajo `/wp-json/bridgeframe/v2`.

## Estilos del contenido

Cuando una vista muestra HTML recibido de WordPress, declare estos archivos en su meta, en este orden:

```php
'css' => [
    'public/vendors/internal/wp/style.min.css',
    'public/vendors/internal/wp/wordpress-theme.css',
],
```

Coloque el HTML dentro de un contenedor `gf-wordpress-content`. La primera hoja conserva la estructura visual de los bloques de WordPress. La segunda conecta colores, bordes y tipografía con las variables de Bootstrap del proyecto (`--bs-primary`, `--bs-body-color`, `--bs-body-bg`, `--bs-border-color` y `--bs-link-color`). El proyecto puede sobrescribir `--gf-wp-primary`, `--gf-wp-text`, `--gf-wp-background`, `--gf-wp-border`, `--gf-wp-link` y `--gf-wp-on-primary` en ese contenedor. Los colores definidos expresamente en el contenido se conservan.

La hoja base contiene selectores globales de WordPress; cárguela solo en vistas que muestran ese contenido. GFrame no la añade a todas las páginas ni modifica la respuesta HTML de BridgeFrame.

## Configuración

Instale y active el [plugin BridgeFrame](https://github.com/gorvet/bridgeframe) en WordPress. En `Ajustes > Bridgeframe`, cree una credencial de consumidor con `content.read` y copie el token mostrado. Añada `private.read` únicamente si necesita contenido privado. Consulte el [contrato oficial](https://github.com/gorvet/bridgeframe/blob/main/docs/CONTRATO-BRIDGEFRAME.md) para permisos y parámetros del plugin.

La versión del contrato es `2.0`; no es la versión del plugin. El cliente GFrame utiliza ese contrato y los cinco endpoints de lectura. Los endpoints de comentarios del plugin no tienen métodos específicos en este cliente.

Al seleccionar el módulo, el instalador añade estas variables a `.env`:

```dotenv
WORDPRESS_HEADLESS_URL="https://cms.example.com"
WORDPRESS_HEADLESS_TOKEN="token-generado-en-bridgeframe"
```

La URL debe usar HTTPS. El token nunca se coloca en rutas, vistas o JavaScript.

Configure la URL base del sitio WordPress, incluida su subcarpeta si existe, sin añadir `/wp-json`. El cliente construye la ruta de la API. La conexión se realiza desde PHP: CORS del navegador no es necesario para estas llamadas servidor a servidor. La autenticación de usuarios y los permisos del proyecto GFrame siguen siendo responsabilidad de la aplicación.

## Uso

```php
use GFrame\Headless\WordPressClient;

$wordpress = WordPressClient::fromEnvironment();
$result = $wordpress->content('mi-articulo', 'post');
```

Operaciones disponibles:

- `content($slug, $type, $private, $options)` consulta `/html` por slug.
- `contentById($id, $type, $private, $options)` consulta `/html` por ID.
- `contents($filters)` consulta `/list`.
- `terms($taxonomy, $withTotal, $options)` consulta `/terms`.
- `menu($filters)` consulta `/menu` por `location`, `slug` o `id`.
- `schema()` consulta `/schema`.

Los argumentos `$options` y `$filters` aceptan claves alfanuméricas con guion bajo y valores escalares o `null`; los arrays u objetos se descartan. Envíe listas de campos como cadenas separadas por comas. Los parámetros principales de `content()`, `contentById()` y `terms()` prevalecen sobre valores duplicados en `$options`.

`schema()` consulta la descripción de tipos, taxonomías y campos disponibles en WordPress; no genera el JSON-LD de la vista GFrame. Ese JSON-LD se configura mediante sus [metas](meta.md) y [presets](json-ld.md).

Los filtros y campos admitidos forman parte del contrato BridgeFrame 2.0. Puede solicitarse, por ejemplo:

```php
$result = $wordpress->contents([
    'type' => 'post',
    'limit' => 10,
    'page' => 1,
    'fields' => 'id,title,slug,excerpt,image,date',
    'taxonomy' => 'category',
    'term' => 'noticias',
]);
```

## Contratos de respuesta

BridgeFrame debe responder siempre con este sobre:

```php
[
    'status' => 'success',
    'code' => 'content_loaded',
    'data' => [...],
    'meta' => ['contract_version' => '2.0', 'request_id' => '...'],
]
```

El cliente valida el sobre y lo transforma al contrato local, por ejemplo `wordpress_content_loaded`. Una respuesta sin versión o con la estructura antigua se rechaza con `wordpress_contract_mismatch`.

Los códigos de error estables incluyen `wordpress_url_not_configured`, `wordpress_token_not_configured`, `insecure_wordpress_url`, `wordpress_unauthorized`, `wordpress_not_found`, `wordpress_rate_limited`, `wordpress_unavailable`, `wordpress_connection_failed` y `wordpress_invalid_response`.

La capa que llama decide si presenta una vista de error, `swalAlert` o `alertToast`. Los detalles internos de conexión se registran, pero no se entregan a la vista.

## Entregar contenido a una vista

Dentro de la acción del controlador, consulte el artículo y compruebe el resultado antes de leer sus datos:

```php
$result = $wordpress->content('mi-articulo', 'post');
if (($result['status'] ?? '') !== 'success') {
    return $result;
}
return [
    'status' => 'success',
    'code' => 'article_loaded',
    'data' => ['article' => $result['data']],
];
```

El `Render` entrega `data` a la vista según su [contrato](render.md). La vista puede mostrar el HTML del artículo dentro del contenedor de estilos:

```php
<?php $article = (array)($data['article'] ?? []); ?>
<div class="gf-wordpress-content">
    <?php echo (string)($article['html'] ?? ''); ?>
</div>
```

Este ejemplo imprime HTML confiable procedente del CMS configurado, no una entrada arbitraria del visitante. Defina quién puede editar ese contenido y si necesita aplicar [sanitización HTML](html-sanitizer.md); una política más restrictiva puede eliminar bloques o atributos de WordPress. Escape los textos simples, como títulos, cuando los imprima por separado.

Los estilos incluidos no ejecutan scripts de bloques, shortcodes interactivos o formularios de plugins. Un bloque que dependa de JavaScript necesita su integración específica. Las imágenes mantienen sus URLs del CMS; asegure que sean accesibles desde el navegador.

## Menús y taxonomías

```php
$categories = $wordpress->terms('category', true);
$navigation = $wordpress->menu(['location' => 'primary']);
$schema = $wordpress->schema();
```

La aplicación interpreta `data` y construye sus vistas. Un menú devuelto por WordPress no cambia automáticamente la barra de GFrame ni convierte URLs del CMS en rutas del frontend: aplique su propio mapeo y valide los enlaces que muestre. Consulte la respuesta y el contrato del plugin para los campos específicos de cada endpoint.

## Fallos y límites de la conexión

Las peticiones son síncronas: cada llamada espera la respuesta HTTP, con timeout de 30 segundos y hasta tres redirecciones. El cliente no añade caché, reintentos ni sincronización local de contenidos. Para varias consultas en una página, gestione caché o precarga desde un servicio del proyecto y separe los datos por consumidor y permisos.

Ante un 429, devuelve `wordpress_rate_limited`; no programa el siguiente intento ni expone `Retry-After` en su contrato. Ante 401/403, compruebe credencial, scopes y su vigencia en WordPress. Un 404 puede indicar contenido ausente o un endpoint no disponible; no convierta indiscriminadamente todos los fallos remotos en un 404 de su aplicación.

Una respuesta 2xx con sobre incorrecto o versión incompatible devuelve `wordpress_contract_mismatch`. El código remoto de éxito se sustituye por el código local de la operación; `meta` conserva los metadatos remotos y añade el estado HTTP si no venía ya en el sobre.

## Contenido público y privado

El argumento `$private` solamente solicita que BridgeFrame incluya contenido privado. El plugin debe exigir el scope `private.read`; `private=true` nunca constituye autorización por sí mismo. Para lectura normal se requiere `content.read`. GFrame no expone el token al navegador.

Una credencial backend puede tener acceso a más contenido que el usuario de GFrame. Autorice al usuario antes de mostrar datos privados o devolverlos por AJAX. El cliente no incorpora contexto tenant automáticamente; en una aplicación multitenant, seleccione una configuración confiable por tenant y no acepte una URL CMS arbitraria desde el formulario.

## Integración y pruebas

`WordPressClient` es final. Use composición en un servicio del proyecto para añadir caché, mapeo de URLs o reglas de negocio. Puede construir otra instancia con URL y token propios o inyectar un callable de transporte como tercer argumento para pruebas:

```php
use GFrame\Headless\WordPressClient;

$wordpress = new WordPressClient(
    'https://cms.example.com',
    'test-token',
    static function (array $request): array {
        return [
            'ok' => true, 'status' => 200,
            'json' => [
                'status' => 'success', 'code' => 'content_loaded',
                'data' => ['id' => 7, 'html' => '<p>Contenido de prueba</p>'],
                'meta' => ['contract_version' => '2.0'],
            ],
        ];
    }
);
$result = $wordpress->content('mi-articulo');
```

El callable recibe URL, método, query, headers y opciones TLS; devuelve el contrato HTTP mostrado. Esta prueba sustituye la red y no demuestra conectividad HTTPS real. No utilice ese transporte de prueba en producción.

## TLS

Todas las solicitudes verifican certificado y host. No se admite desactivar TLS desde el módulo. Para desarrollo local debe usarse un certificado confiable o un entorno de prueba HTTPS correctamente configurado.
