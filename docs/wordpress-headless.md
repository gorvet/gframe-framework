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

Al seleccionar el módulo, el instalador añade estas variables a `.env`:

```dotenv
WORDPRESS_HEADLESS_URL="https://cms.example.com"
WORDPRESS_HEADLESS_TOKEN="token-generado-en-bridgeframe"
```

La URL debe usar HTTPS. El token nunca se coloca en rutas, vistas o JavaScript.

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

## Contenido público y privado

El argumento `$private` solamente solicita que BridgeFrame incluya contenido privado. El plugin debe exigir el scope `private.read`; `private=true` nunca constituye autorización por sí mismo. Para lectura normal se requiere `content.read`. GFrame no expone el token al navegador.

## TLS

Todas las solicitudes verifican certificado y host. No se admite desactivar TLS desde el módulo. Para desarrollo local debe usarse un certificado confiable o un entorno de prueba HTTPS correctamente configurado.
