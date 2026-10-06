# Router y declaración de rutas

RouteBuilder registra las rutas y sus opciones. Router interpreta la petición HTTP, busca una declaración compatible, ejecuta los middleware y entrega el resultado a Render o al canal de respuesta.

Para situar el enrutamiento en el arranque del proyecto, consulta [Arquitectura](arquitectura.md).

## Dónde declarar las rutas

El arranque carga los archivos `config/routes/routes_*.php`, ordenados por nombre, directamente desde esa carpeta. Puedes dividir un canal en varios archivos, como `routes_ajax_account.php` y `routes_ajax_media.php`; no se recorren subcarpetas.

| Archivo | Tipo registrado | URL habitual |
| --- | --- | --- |
| `routes_web.php` | web | `/productos` |
| `routes_ajax.php` | ajax | `/ajax/productos` |
| `routes_api.php` | api | `/api/productos` |
| `routes_webhook.php` | webhook | `/webhook/proveedor` |
| `routes_sse.php` | sse | `/sse/eventos` |
| `routes_system.php` | system | Depende de la declaración |

Hay dos mecanismos distintos: RouteBuilder infiere el tipo por el nombre del archivo que llama a `get()`, `post()`, `put()` o `delete()`; Router decide el canal de ejecución por el primer segmento de la URL, después de reconocer el idioma.

El nombre del archivo no añade el prefijo a la URL. Debes declarar `ajax/...`, `api/...`, `webhook/...` o `sse/...` cuando corresponda. `system` es una clasificación de declaraciones internas, no un prefijo de transporte reconocido por Router. Una ruta interna puede ejecutarse como AJAX si su URL comienza por `ajax`.

Por ejemplo, declarar `productos` en `routes_ajax.php` registra ese patrón, pero una petición a `/productos` sigue entrando por el canal web. Para ejecutarla como AJAX, declara `ajax/productos` y solicita `/ajax/productos`. Una cabecera `X-Requested-With` tampoco selecciona el canal: sus controles se aplican después de reconocer el prefijo.

El tipo registrado sirve también a componentes que consultan el catálogo de rutas. Para las peticiones normales, Router selecciona el transporte por la URL y busca el método y patrón; en preflight `OPTIONS`, además exige una declaración de tipo API. Mantén coherentes el nombre del archivo y el prefijo, aunque alguno de esos mecanismos permita registrar combinaciones distintas.

Declara las rutas directamente en sus archivos de canal. Si las construyes desde otro archivo mediante un helper, la inferencia usa el archivo que llama al método de RouteBuilder, no necesariamente el archivo que carga el helper.

## Una ruta web completa

En `config/routes/routes_web.php`:

```php
use RouteBuilder as Route;

Route::get('productos', 'catalogo/ProductController@index')
    ->template('home')
    ->view('productIndex')
    ->registerFinal();
```

Esta declaración asocia `GET /productos` con:

| Elemento | Destino |
| --- | --- |
| Controlador | `app/controllers/catalogo/ProductController.php` |
| Acción | `ProductController::index()` |
| Vista | `app/views/catalogo/productIndex.php` |
| Template | `app/views/templates/homeTemplate.php` |

La URL no debe contener el dominio ni la carpeta de despliegue. Las barras iniciales y finales de la declaración se recortan. Para la raíz utiliza `''`.

`registerFinal()` incorpora la declaración al registro. Sin esa llamada, la ruta no existe para Router.

### Métodos y prioridad

Los métodos disponibles son `get()`, `post()`, `put()` y `delete()`. No existe un método público `patch()` ni `any()` en el RouteBuilder actual. Router compara el método HTTP recibido; no convierte automáticamente un campo de formulario `_method` en PUT o DELETE.

Las rutas se almacenan por método y patrón. Registrar de nuevo el mismo método y patrón reemplaza su declaración anterior. Entre patrones diferentes, gana el primero que coincida; declara las rutas específicas antes de las parametrizadas que puedan capturarlas.

## Parámetros de ruta y datos de entrada

```php
use RouteBuilder as Route;

Route::get('productos/{id}', 'catalogo/ProductController@show')
    ->template('home')
    ->view('productShow')
    ->registerFinal();
```

El controlador recibe el arreglo de ejecución, no una lista de argumentos posicionales:

```php
final class ProductController
{
    public function show(array $routeParams): array
    {
        $id = $routeParams['params']['id'] ?? '';
        return ['productId' => $id];
    }
}
```

Los nombres de parámetros admiten letras ASCII y guion bajo. Sus valores admiten letras ASCII, números, guion y guion bajo. `{id}` no exige que el valor sea numérico: valida el formato y la existencia del registro en tu aplicación.

El patrón no ofrece parámetros opcionales ni comodines para capturar varias carpetas. Tampoco dispone de un método `where()` para añadir restricciones. Evita signos de expresiones regulares en las partes literales: Router construye una expresión regular a partir del patrón y no escapa esos signos.

La query string no participa en la coincidencia. En `/productos/25?formato=breve`, `id` llega en `$routeParams['params']`; `formato` se consulta en `$_GET`. Los campos de formulario se leen de `$_POST`. Un cuerpo JSON necesita lectura y validación explícitas; no aparece automáticamente en `$_POST`.

`$routeParams['uri']` conserva el patrón declarado, por ejemplo `productos/{id}`. Se distingue de `relativePath`, que identifica la carpeta del controlador, y de `currentURL`, que contiene la URL de la petición. La política SEO utiliza el patrón para reconocer rutas privadas aunque su controlador pertenezca a otra carpeta.

## Inferencia de vista y template

Si omites `view()` y `template()`, se usa la última carpeta del controlador:

```php
use RouteBuilder as Route;

Route::get('catalogo', 'catalogo/ProductController@index')
    ->registerFinal();
```

En este caso, la vista inferida es `catalogoIndex`, dentro de `app/views/catalogo/`, y el template es `catalogo`, que corresponde a `catalogoTemplate.php`.

La vista se construye con la carpeta y la acción, no con el nombre de la URL ni con el nombre completo de la clase. Usa `view()` y `template()` cuando quieras otros nombres. En controladores anidados se conserva la carpeta relativa completa para encontrar la vista.

## Middleware y contexto

```php
use RouteBuilder as Route;

Route::get('admin/productos', 'catalogo/ProductController@index')
    ->template('admin')
    ->view('productIndex')
    ->middleware(['auth', 'admin', 'can:catalogo.read'])
    ->context(['section' => 'catalogo'])
    ->registerFinal();
```

Los middleware se ejecutan antes de la acción. El contexto se recibe en `$routeParams['context']`; Router incorpora allí el contexto adicional que produzcan los middleware.

El orden de ejecución, los controles disponibles y los límites de ampliación se explican en [Middleware](middleware.md).

`middleware()` y `context()` sustituyen el valor anterior si se llaman varias veces; no acumulan llamadas. La opción `permission()` guarda un dato de ruta, pero no añade por sí misma un middleware `can:*`. Para exigir permisos, declara el middleware correspondiente. Consulta [Permisos](permisos.md).

Los guardas automáticos de cada canal se añaden antes de los middleware propios, salvo que ya estén declarados o se hayan excluido. `excludeMiddleware()` evita la inyección automática indicada; no elimina un middleware que hayas escrito en `middleware()`.

No excluyas CSRF, autenticación o validación de credenciales para resolver un problema de configuración. Comprueba primero el contrato del canal.

## Rutas asociadas a módulos

```php
use RouteBuilder as Route;

Route::get('account', 'self-account/SelfAccountController@index')
    ->module('self-account')
    ->template('admin')
    ->middleware(['auth'])
    ->registerFinal();
```

`module()` registra el módulo de origen como `sourceModule`. El módulo debe estar instalado y activo para disponer de sus archivos runtime. La aplicación se busca primero; si no existe la personalización, se utiliza el original del módulo según su manifiesto.

El nombre acepta minúsculas, números y segmentos separados por guion, empezando por una letra. Esta opción no instala un módulo ni convierte una carpeta arbitraria en un módulo.

Si no declaras `module()`, Router puede inferirlo de la última carpeta del controlador cuando coincide con un módulo runtime activo. La declaración explícita evita depender de esa inferencia y permite conservar nombres de vista y template elegidos por el proyecto.

`sourceModule` no es lo mismo que el valor histórico `module` usado en la resolución de permisos. Consulta [Módulos runtime](modulos-runtime.md) para namespaces y ampliación; no dupliques rutas solo para sustituir una vista.

## AJAX

Los campos y su adaptación por canal se describen en [Contratos de respuesta](respuestas.md).

En `config/routes/routes_ajax.php`:

```php
use RouteBuilder as Route;

Route::post('ajax/catalogo/guardar', 'catalogo/ProductController@save')
    ->middleware(['auth', 'can:catalogo.write'])
    ->registerFinal();
```

Router añade `block_external_ajax` y `CSRF`. La petición debe seguir los contratos de AJAX y token del frontend del framework. Enviar una cabecera AJAX no sustituye la autenticación ni los permisos.

La acción devuelve el contrato de datos; no imprime una vista ni añade un template:

```php
public function save(array $routeParams): array
{
    // Validar y persistir antes de devolver el resultado.
    return ['status' => 'success', 'code' => 'saved', 'message' => 'Guardado correctamente.'];
}
```

El fragmento pertenece al controlador; no implementa la persistencia. En éxito, Router serializa el resultado a JSON. Las respuestas de error se procesan por el sistema de errores antes de la serialización normal. Consulta [Frontend core](frontend-core.md) para envío y feedback.

## API

Una ruta API publica datos u operaciones para un consumidor externo. Usa el mismo RouteBuilder y los mismos controladores que los otros canales; cambia el prefijo, el contrato de respuesta y el guarda de acceso. No requiere vista ni template.

### Declarar el endpoint y su credencial

En `config/routes/routes_api.php`:

```php
use RouteBuilder as Route;

Route::get('api/catalogo', 'catalogo/ProductController@listApi')
    ->context([
        'api_token' => (string)env('CATALOG_API_TOKEN', ''),
        'api_consumer' => 'catalog-client',
        'allowed_origins' => ['https://cliente.example.com'],
    ])
    ->registerFinal();
```

Router añade `allow_cors_with_token`. El consumidor envía `Authorization: Bearer <token>`. Una llamada servidor a servidor puede omitir Origin; un navegador necesita un origen permitido. Un token vacío no configura una credencial válida.

La respuesta API se serializa a JSON. Puedes devolver `http_code` para indicar el estado HTTP; los errores con `status` igual a `error` o `unauthorized` también se interpretan para determinarlo.

Los preflight `OPTIONS` se resuelven sobre declaraciones API y pueden terminar con 204 desde middleware, sin ejecutar la acción. No necesitas inventar una ruta OPTIONS mediante un método inexistente de RouteBuilder.

### Varios consumidores y aislamiento por tenant

En el contexto de la misma declaración puedes utilizar `api_consumers` en lugar de un único `api_token`:

```php
use RouteBuilder as Route;

Route::get('api/pedidos', 'orders/OrderController@listApi')
    ->context(['api_consumers' => [
        [
            'name' => 'tienda-a',
            'token' => (string)env('STORE_A_API_TOKEN', ''),
            'tenant_id' => 10,
            'scopes' => ['orders.read'],
            'origins' => ['cliente.example.com'],
        ],
    ]])
    ->registerFinal();
```

El guarda compara el token respetando mayúsculas y minúsculas, y añade `api_consumer`, `api_tenant_id` y `api_scopes` al contexto de ejecución. Un token válido identifica al consumidor; **los scopes no se comprueban automáticamente** y el tenant no filtra consultas por sí mismo.

En la acción, exige el alcance y toma el tenant del contexto autenticado, no de un campo enviado por el cliente:

```php
public function listApi(array $routeParams): array
{
    $context = $routeParams['context'] ?? [];
    if (!in_array('orders.read', $context['api_scopes'] ?? [], true)) {
        return ['status' => 'unauthorized', 'code' => 'scope_required', 'http_code' => 403];
    }
    $tenantID = (int)($context['api_tenant_id'] ?? 0);
    if ($tenantID <= 0) {
        return ['status' => 'unauthorized', 'code' => 'tenant_required', 'http_code' => 403];
    }
    // El servicio del proyecto consulta únicamente pedidos de $tenantID.
    return ['status' => 'success', 'data' => ['tenant_id' => $tenantID]];
}
```

El ejemplo comprueba acceso; sustituye el resultado por los datos del servicio. Para una API global puedes omitir el tenant y comprobar solamente sus alcances. `auth`, `admin` y `can:*` trabajan con la sesión de usuario: el Bearer de API no crea esa sesión ni convierte los scopes en permisos de rol. Si necesitas autorización por usuario, implementa esa integración explícitamente.

### Solicitud y respuesta

Envía el token por cabecera sobre HTTPS; no lo incluyas en la URL ni en JavaScript público si es un secreto de servidor:

```http
GET /api/pedidos HTTP/1.1
Host: example.com
Authorization: Bearer TOKEN_DEL_CONSUMIDOR
Accept: application/json
```

Para POST o PUT con JSON, lee `php://input`, decodifica y valida el contenido en la acción. El canal no lo transforma automáticamente en `$_POST`. Mantén la respuesta como array con `status`, `code` y `data` según la operación, sin imprimir HTML.

| Situación del guarda | Resultado |
| --- | --- |
| No hay credenciales configuradas | `api_token_not_configured`, HTTP 500 |
| Token ausente o incorrecto | `invalid_token`, HTTP 401 |
| Origen no permitido para el consumidor | `cors_denied`, HTTP 403 |
| Preflight autorizado | HTTP 204, sin acción |

Los orígenes actuales se comparan por host normalizado: no distinguen puerto ni esquema y eliminan `www.`. Una llamada sin Origin ni Referer se autentica por token. Evita `*` salvo que quieras aceptar cualquier origen; CORS no limita clientes servidor a servidor.

### Ampliar la resolución de credenciales

El proveedor incluido lee las credenciales del contexto. Para almacenarlas en una base de datos, revocarlas o aplicar caducidad propia, implementa `ApiCredentialProvider`; su contrato e inyección se detallan en [Proveedor de credenciales API](api-access.md). El proveedor integrado no añade caducidad, rotación automática ni límite de solicitudes.

## Webhooks

En `config/routes/routes_webhook.php`:

```php
use RouteBuilder as Route;

Route::post('webhook/proveedor', 'integraciones/ProviderController@receive')
    ->registerFinal();
```

Sin contexto, `webhook_guard` exige POST, contenido JSON y `X-Webhook-Secret` coincidente con `WEBHOOK_DEFAULT_SECRET`. También bloquea peticiones con Origin o Referer y limita el tamaño declarado del cuerpo a 2 MiB. Configura el secreto en el entorno y su puente de configuración; no lo incluyas en el repositorio.

El contexto conserva la autenticación predeterminada mientras no declare una credencial verificable: cabecera con `expected_value`, parámetros con valores esperados o HMAC con cabecera y `hmac_secret`. Declarar únicamente `methods` o exigir la presencia de un campo no sustituye el secreto. El guarda admite HMAC SHA-256; comprueba el formato que utiliza cada proveedor.

La acción debe validar el evento y decidir su procesamiento. Los arrays y objetos se responden como JSON; los escalares como texto. El canal no interpreta `http_code` del resultado como lo hace API: fija el estado HTTP explícitamente cuando sea necesario.

## SSE

En `config/routes/routes_sse.php`:

```php
use RouteBuilder as Route;

Route::get('sse/eventos', 'eventos/EventController@stream')
    ->middleware(['auth'])
    ->noRefreshSession()
    ->registerFinal();
```

`sse_guard` exige GET. La autenticación declarada se sigue comprobando; el guarda de SSE no la sustituye. Router prepara `text/event-stream`, desactiva buffering PHP cuando puede y libera el bloqueo de sesión antes de ejecutar la acción.

Si usas `context.require_token`, configura un secreto `expected` o un callable `verify`. Una configuración sin verificador devuelve `sse_token_configuration`; tener un token arbitrario no autentica el stream.

La acción emite los eventos, no devuelve un array para convertirlo en JSON:

```php
public function stream(array $routeParams): void
{
    echo "event: ready\n";
    echo 'data: ' . json_encode(['status' => 'ready'], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}
```

Este ejemplo emite un evento y termina. El navegador puede reconectar; la aplicación debe decidir duración, repetición e identificadores de eventos. También necesita una configuración de proxy y servidor que permita streaming.

`noRefreshSession()` evita que peticiones automáticas mantengan activa indefinidamente una sesión autenticada. No desactiva los controles de expiración.

## Otras opciones

| Opción | Uso |
| --- | --- |
| `autoSlug()` | Copia la URI declarada en `context['slug']` cuando no hay placeholders ni un slug explícito |
| `noAction()` | Omite el método de acción; en web permite renderizar sin lógica de controlador |
| `noRefreshSession()` | Marca que la petición no debe renovar la actividad de sesión |
| `RouteBuilder::all()` | Consulta el registro de declaraciones; no ejecuta peticiones |

`noAction()` no suprime la resolución del controlador ni los middleware. En canales directos, produce una respuesta mínima del canal; no es una forma de permitir acceso sin controles.

## Idioma, errores y diagnóstico

Un prefijo de idioma incluido en `SUPPORTED_LANGS` se separa antes de buscar la ruta; el idioma llega como `$routeParams['lang']`. Sin prefijo se utiliza el primero de la lista. En web se normalizan barras duplicadas y se elimina el prefijo redundante del idioma predeterminado mediante redirección 301. Los canales directos evitan esa normalización.

Consulta [Multilenguaje](multilenguaje.md) para preparar textos, vistas, metas y enlaces según ese idioma.

Una ruta no encontrada produce el tratamiento de error del canal: vista de error para web o respuesta directa para los demás. No se busca automáticamente otro método HTTP ni una ruta de otro módulo para compensar una declaración inexistente.

Si una ruta no responde, comprueba en este orden:

1. El servidor entrega la URL a `index.php`.
2. El archivo se llama `routes_*.php` y está en `config/routes/`.
3. Método, prefijo y patrón coinciden con la petición.
4. Se llamó a `registerFinal()` y no hay otra declaración que la reemplace o capture antes.
5. Los middleware reciben la sesión, token o credenciales necesarios.
6. Existen controlador y acción pública; para web, también la vista y el template resueltos.
