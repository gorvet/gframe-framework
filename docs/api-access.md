# Acceso a API entrante

GFrame protege automáticamente las rutas declaradas en `routes_api.php` mediante `allow_cors_with_token`. Este componente autentica sistemas que consumen la aplicación. No administra las credenciales usadas por la aplicación para llamar a WhatsApp, Telegram u otros servicios externos.

## Un consumidor

Declare el token y los orígenes autorizados en el contexto de la ruta:

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

## CORS y preflight

Las solicitudes `OPTIONS` resuelven la misma ruta API, validan el origen y terminan con `204`. CORS no sustituye la autenticación: las solicitudes reales también deben presentar una credencial válida.

## Webhooks e integraciones salientes

- Los webhooks usan rutas de `routes_webhook.php` y su propia validación de firma o secreto.
- Las llamadas salientes usan un cliente HTTP y credenciales de `.env`.
- Ninguna credencial de una integración saliente debe registrarse como token de una ruta API.
