# Cliente HTTP saliente

`HttpClient` permite que una aplicación GFrame haga peticiones HTTP hacia servicios externos. Es una clase global del core y utiliza cURL.

No debe confundirse con [Acceso API](api-access.md):

```text
otro sistema → tu aplicación GFrame   = API entrante / Router

tu aplicación GFrame → otro sistema   = HttpClient
```

## Petición básica

```php
$response = HttpClient::request([
    'url' => 'https://api.example.com/products',
    'method' => 'GET',
]);

if (!$response['ok']) {
    error_log('HTTP externo falló: ' . ($response['error'] ?: $response['status']));
}
```

`request()` siempre devuelve un array con estas claves:

| Clave | Contenido |
| --- | --- |
| `ok` | `true` únicamente cuando cURL no reporta error y el código HTTP está entre 200 y 299. |
| `status` | Código HTTP recibido; `0` si no hubo respuesta HTTP válida. |
| `headers` | Cabeceras de respuesta indexadas por nombre. |
| `body` | Cuerpo crudo como string. |
| `json` | Resultado de `json_decode()` cuando el cuerpo es JSON válido; en otro caso `null`. |
| `error` | Mensaje de cURL o string vacío. |

Una respuesta `404`, `422` o `500` puede tener cuerpo y JSON válidos, pero `ok` será `false`.

## Argumentos

```php
$response = HttpClient::request([
    'url' => 'https://api.example.com/items',
    'method' => 'POST',
    'headers' => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ],
    'query' => [
        'lang' => 'es',
    ],
    'body' => [
        'name' => 'Producto',
        'price' => '25.00',
    ],
    'timeout' => 30,
    'follow_location' => true,
    'max_redirects' => 5,
    'verify_peer' => true,
    'verify_host' => true,
    'user_agent' => 'MiAplicacion/1.0',
]);
```

Opciones actuales:

| Argumento | Valor predeterminado | Comportamiento |
| --- | --- | --- |
| `url` | obligatorio | URL completa. Una URL vacía devuelve error sin iniciar cURL. |
| `method` | `GET` | Se normaliza a mayúsculas. |
| `headers` | `[]` | Lista de strings `Nombre: valor`. |
| `query` | `null` | Si es un array no vacío, se añade mediante query string. |
| `body` | `null` | Payload según el tipo explicado abajo. |
| `timeout` | `300` | Segundos para `CURLOPT_TIMEOUT`; valores negativos se convierten en `0`. |
| `follow_location` | `true` | Sigue redirecciones. |
| `max_redirects` | `10` | Máximo de redirecciones. |
| `verify_peer` | `true` | Verificación TLS del certificado. |
| `verify_host` | `true` | Verificación TLS del hostname. |
| `user_agent` | vacío | User-Agent opcional. |

## Query string

Para parámetros de consulta utiliza `query`:

```php
$response = HttpClient::request([
    'url' => 'https://api.example.com/search',
    'query' => [
        'q' => 'miel',
        'page' => 2,
    ],
]);
```

El cliente utiliza `http_build_query()` y respeta una query string que ya exista en la URL.

En `GET`, si `body` es un array también se convierte en query string. Para evitar ambigüedad, usa `query` explícitamente para parámetros de URL.

## Enviar formularios

Un `body` de tipo array en métodos distintos de GET y DELETE se envía como `application/x-www-form-urlencoded` si no proporcionaste `Content-Type`:

```php
$response = HttpClient::request([
    'url' => 'https://api.example.com/login',
    'method' => 'POST',
    'body' => [
        'email' => $email,
        'password' => $password,
    ],
]);
```

## Enviar JSON

La implementación actual codifica automáticamente como JSON cuando `body` es un **objeto**:

```php
$payload = (object)[
    'name' => 'Producto',
    'price' => 25.00,
];

$response = HttpClient::request([
    'url' => 'https://api.example.com/products',
    'method' => 'POST',
    'body' => $payload,
]);
```

Si `body` es un string, se envía tal cual y, salvo que especifiques otro `Content-Type`, el cliente añade `Content-Type: application/json`.

También puedes controlar expresamente la serialización:

```php
$response = HttpClient::request([
    'url' => 'https://api.example.com/products',
    'method' => 'POST',
    'headers' => ['Content-Type: application/json'],
    'body' => json_encode([
        'name' => 'Producto',
        'price' => 25.00,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);
```

No supongas que un array se convierte a JSON: actualmente los arrays se convierten a formulario URL-encoded.

## DELETE y cuerpo

La implementación actual no prepara `body` para `GET` ni `DELETE`. Si una API exige un cuerpo en DELETE, `HttpClient::request()` no cubre actualmente ese contrato de forma directa. Usa la forma que soporte el proveedor —por ejemplo parámetros en la URL— o adapta el cliente en el framework antes de depender de un body DELETE.

Documentar esta limitación evita asumir un comportamiento que el código actual no implementa.

## Cabeceras y autenticación

La autenticación de un servicio externo pertenece a la llamada saliente:

```php
$response = HttpClient::request([
    'url' => 'https://api.example.com/me',
    'headers' => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ],
]);
```

No reutilices automáticamente las credenciales de las rutas API de GFrame. `RouteApiCredentialProvider` protege tráfico **entrante**; `HttpClient` no conoce ese contexto.

## Manejo de respuesta

```php
$response = HttpClient::request([
    'url' => 'https://api.example.com/products/25',
]);

if ($response['ok']) {
    $payload = $response['json'];
    // json_decode() devuelve objetos por defecto.
    return ['status' => 'success', 'data' => $payload];
}

error_log(sprintf(
    '[Products API] HTTP %d - %s',
    $response['status'],
    $response['error'] !== '' ? $response['error'] : $response['body']
));

return ['status' => 'error', 'code' => 'external_service_failed'];
```

No expongas sin filtrar el cuerpo o el error de un proveedor al usuario final. Registra el detalle interno y devuelve un contrato propio de la aplicación.

## Uso desde un service

El cliente HTTP suele pertenecer a un service, no directamente a la vista:

```php
final class InventorySyncService
{
    public function fetchRemoteProduct(int $externalID): array
    {
        $response = HttpClient::request([
            'url' => 'https://inventory.example.com/products/' . $externalID,
            'headers' => ['Accept: application/json'],
            'timeout' => 15,
        ]);

        if (!$response['ok'] || $response['json'] === null) {
            return ['status' => 'error', 'code' => 'inventory_unavailable'];
        }

        return ['status' => 'success', 'data' => $response['json']];
    }
}
```

Así la misma integración puede utilizarse desde una ruta web, AJAX, API, Cron o Async.

## Async y Cron

Una llamada HTTP puede tardar o depender de un proveedor externo.

- Si el usuario no necesita esperar y la operación puede ejecutarse sin persistencia ni reintento, evalúa [Async](async.md).
- Si la sincronización debe reintentarse, programarse o ejecutarse recurrentemente, utiliza una estrategia persistente con [Cron](cron-runner.md).

No confundas «HTTP saliente» con «ejecución en segundo plano»: son decisiones independientes.

## Seguridad

### Mantén TLS verificado

`verify_peer` y `verify_host` son `true` por defecto. No los desactives en producción para «resolver» errores de certificados. Corrige la cadena de confianza o la configuración del servidor.

### No aceptes destinos arbitrarios

Si la URL o el hostname pueden venir de entrada del usuario, existe riesgo de SSRF. Para integraciones normales:

- define el host en configuración del servidor;
- permite únicamente dominios conocidos cuando el destino sea variable;
- no permitas que un formulario elija libremente URLs internas o de infraestructura;
- valida redirecciones si el caso exige restricciones adicionales.

### Mantén secretos fuera del cliente

Tokens y credenciales de proveedores deben vivir en `.env` o configuración de servidor. No los envíes a JavaScript para que el navegador llame al proveedor si esa credencial debe permanecer privada.

## Compatibilidad: `requestCompat()`

Existe también:

```php
HttpClient::requestCompat($args);
```

Devuelve el contrato histórico:

```php
[
    'response' => $response['json'],
    'httpCode' => $response['status'],
    'error' => $response['error'],
    'body' => $response['body'],
    'ok' => $response['ok'],
]
```

Para código nuevo utiliza `request()`. `requestCompat()` debe entenderse como una capa de compatibilidad para consumidores antiguos, no como una segunda API preferida.

## Requisitos

El servidor necesita la extensión cURL de PHP. El cliente usa HTTP/1.1 actualmente.

Para publicar endpoints propios de la aplicación, consulta [Acceso API](api-access.md) y [Router](rutas.md).
