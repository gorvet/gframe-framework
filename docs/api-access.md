# Proveedor de credenciales API

La declaración de endpoints, consumidores y respuestas se explica en [Rutas: API](rutas.md#api). Esta referencia describe el proveedor de credenciales utilizado por el middleware `allow_cors_with_token` para autenticar sistemas que consumen la aplicación.

## Un consumidor

Declara el token y los orígenes autorizados en el contexto de la ruta:

```php
Route::get('api/orders', 'api/OrderController@index')
    ->context([
        'api_token' => $_ENV['ORDERS_API_TOKEN'],
        'api_consumer' => 'orders-client',
        'allowed_origins' => ['https://client.example.com'],
    ])
    ->registerFinal();
```

El consumidor envía la credencial sin cambiar mayúsculas ni minúsculas:

```http
Authorization: Bearer Secret-With-Case
```

Una llamada servidor a servidor puede omitir `Origin`. Si la petición procede de un navegador, su origen debe estar autorizado.

## Varios consumidores

```php
->context([
    'api_consumers' => [
        [
            'name' => 'store-a',
            'token' => $_ENV['STORE_A_API_TOKEN'],
            'tenant_id' => 10,
            'scopes' => ['orders.read'],
            'origins' => ['store-a.example.com'],
        ],
        [
            'name' => 'store-b',
            'token' => $_ENV['STORE_B_API_TOKEN'],
            'tenant_id' => 20,
            'scopes' => ['orders.read', 'orders.write'],
            'origins' => ['store-b.example.com'],
        ],
    ],
])
```

Después de autenticar, el Router incorpora al contexto:

- `api_consumer`
- `api_tenant_id`
- `api_scopes`

## Proveedor personalizado

Una aplicación que registre credenciales en base de datos puede implementar:

```php
GFrame\Http\Contracts\ApiCredentialProvider
```

El proveedor resuelve el token, el consumidor, el tenant, sus alcances y los orígenes permitidos. Se inyecta como segundo argumento de `Middleware`. La tabla y el esquema pertenecen a la aplicación, no al framework.

| Método del contrato | Resultado esperado |
| --- | --- |
| `authenticate(string $bearer, array $context): array` | En éxito, `status => success` y `data` con `name`, `tenant_id`, `scopes`, `origins`; no devuelve el token |
| `allowsPreflightOrigin(string $origin, array $context): bool` | Indica si el origen puede realizar preflight para la ruta, sin exigir Bearer |

En una denegación, devuelve `status => unauthorized`, un `code` y `http_code => 401`. El middleware comprueba después los orígenes del consumidor. Recibe hosts normalizados en las comprobaciones CORS; conserva ese formato en `origins`. La inyección debe realizarse en la construcción del middleware utilizado por el arranque del proyecto; instanciar otro middleware dentro de una acción no sustituye el que ya protegió la ruta.

## CORS y preflight

Las solicitudes `OPTIONS` resuelven la misma ruta API, validan el origen y terminan con `204`. CORS no sustituye la autenticación: las solicitudes reales también deben presentar una credencial válida.

## Webhooks e integraciones salientes

- Los webhooks usan rutas de `routes_webhook.php` y su propia validación de firma o secreto.
- Las llamadas salientes usan un cliente HTTP y credenciales de `.env`.
- Ninguna credencial de una integración saliente debe registrarse como token de una ruta API.


## Alcances y autorización

Autenticar un consumidor no autoriza automáticamente todas las operaciones. `api_scopes` identifica capacidades declaradas para ese consumidor, pero la acción o servicio debe comprobar expresamente el alcance requerido.

```php
$context = $routeParams['context'] ?? [];
if (!in_array('orders.write', $context['api_scopes'] ?? [], true)) {
    return [
        'status' => 'unauthorized',
        'code' => 'scope_required',
        'http_code' => 403,
    ];
}
```

Del mismo modo, `api_tenant_id` identifica el ámbito autenticado; no filtra consultas por sí mismo. Pase ese tenant al servicio o repositorio y no acepte como sustituto un `tenant_id` enviado por el cliente.

## Gestión de secretos

Mantenga tokens en `.env`, un almacén seguro o el proveedor de credenciales del proyecto. No los escriba en JavaScript público, logs, URLs, ejemplos reales ni repositorio.

Para rotación, permita una transición controlada si su proveedor lo necesita, pero evite conservar indefinidamente credenciales antiguas. Registre eventos de autenticación con identificadores del consumidor, nunca con el token completo.

## Orígenes y CORS

CORS es una política del navegador, no una barrera para llamadas servidor a servidor. Un origen permitido solo determina qué frontend puede leer la respuesta desde un navegador; el Bearer sigue siendo necesario para la solicitud real.

Mantenga la lista de orígenes exacta. No utilice `*` con credenciales sensibles ni convierta automáticamente cualquier host recibido en permitido.

## Errores esperados

| Situación | Resultado esperado |
| --- | --- |
| No hay token/proveedor configurado | Error de configuración del servidor |
| Bearer ausente o inválido | `unauthorized`, normalmente 401 |
| Token válido sin scope requerido | 403 desde la autorización de la acción |
| Tenant no válido para la operación | 403 o código de negocio equivalente |
| Origin de navegador no permitido | Rechazo CORS/preflight |

No devuelva detalles que permitan distinguir secretos parcialmente correctos ni información interna del proveedor.

## Pruebas de una integración API

Cubra al menos:

1. token válido e inválido;
2. sensibilidad a mayúsculas del secreto;
3. preflight permitido y bloqueado;
4. llamada servidor a servidor sin `Origin`;
5. scope permitido y faltante;
6. aislamiento por tenant;
7. respuesta sin exposición del token;
8. rotación o proveedor personalizado cuando exista.

Las pruebas del middleware no sustituyen las pruebas del servicio de negocio: una credencial válida puede seguir intentando acceder a un recurso que no pertenece a su tenant.
