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
