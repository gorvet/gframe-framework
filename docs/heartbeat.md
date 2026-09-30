# Heartbeat, sesión y canales periódicos

GFrame reúne las consultas periódicas del navegador en una sola petición. El módulo `heartbeat-client` coordina el cliente, publica la ruta protegida y permite que cada aplicación registre canales propios.

Normalmente se instala como dependencia de `auth-ui`. Incluye:

- `public/js/core/heartbeat.js`, cliente y coordinación entre pestañas;
- `public/js/core/session.js`, cierre y expiración sincronizados;
- `app/controllers/system/heartbeat/HeartbeatController.php`, registro de canales;
- `config/routes/routes_system_heartbeat.php`, endpoint del sistema.

## Funcionamiento

1. Una pestaña protegida asume el liderazgo.
2. La pestaña líder envía un tick cada 60 segundos a `ajax/heartbeat`.
3. `HeartbeatMaster` decide qué canales deben ejecutarse.
4. Los resultados se comparten con las demás pestañas.
5. El cliente emite eventos DOM para cada canal.

Cuando la pestaña está oculta, el cliente reduce la frecuencia. Al recuperar el foco puede solicitar una ejecución inmediata.

## Ruta y sesión

La ruta instalada es:

```php
Route::post('ajax/heartbeat', 'system/heartbeat/HeartbeatController@dispatch')
    ->middleware(['auth'])
    ->excludeMiddleware(['CSRF'])
    ->noRefreshSession()
    ->registerFinal();
```

`noRefreshSession()` es obligatorio. El polling no debe prolongar artificialmente la sesión. Cuando se supera `session.idle_timeout`, middleware devuelve `code=expired`, `session.js` informa al usuario y sincroniza el cierre entre pestañas.

El heartbeat no consulta la tabla de usuarios. PHP lee una sola vez la sesión del request y middleware comprueba únicamente la identidad guardada allí. Cuando una cuenta se suspende o desactiva, el servicio de administración revoca previamente sus sesiones en el almacenamiento; el siguiente tick encuentra la sesión ausente.

El tiempo predeterminado de inactividad es de 1800 segundos:

```php
'session' => [
    'idle_timeout' => 1800,
],
```

## Registrar un canal

Los canales se registran en el constructor del `HeartbeatController` publicado en la aplicación:

```php
$this->registerChannel('orders.pending', [
    'interval_ms' => 120000,
    'run_when_hidden' => false,
    'payload' => ['limit' => 20],
], 'admin/orders/OrderController@heartbeatPendingChannel');
```

El nombre admite letras minúsculas, números, punto, guion y guion bajo. El intervalo mínimo efectivo es de 60 segundos.

El handler recibe el payload declarado y el contexto del cliente:

```php
public function heartbeatPendingChannel(array $payload = [], array $context = []): array
{
    $limit = $this->hbInt($payload, 'limit', 20, 1, 50);
    $response = $this->orderModel->getPendingSummary($limit);

    if (($response['status'] ?? 'error') !== 'success') {
        return $response;
    }

    return $this->hbSuccess($response['data'] ?? []);
}
```

El controlador propietario del canal puede usar `HeartbeatChannelTrait`, que proporciona `hbInt`, `hbSuccess` y `hbError`.

## Contrato de errores

Los módulos deben devolver errores esperados como arreglos estables:

```php
return [
    'status' => 'error',
    'code' => 'not_available',
    'message' => 'No se pudo actualizar el resumen.',
];
```

El modelo devuelve el contrato y el controlador decide si lo conserva o añade datos. Router y JavaScript determinan después si el mensaje se presenta mediante una vista de error, `swalAlert` o `alertToast`.

No se debe usar `Throwable` como sustituto de este contrato en módulos, controladores o modelos. Las operaciones que puedan fallar deben capturar `Exception` en la capa correspondiente y devolver `status`, `code` y `message`.

## Consumir resultados en JavaScript

Cada canal genera el evento `gf:heartbeat:<nombre>`:

```js
document.addEventListener('gf:heartbeat:orders.pending', function (event) {
    const response = event.detail.payload || {};
    if (response.status !== 'success') {
        alertToast(response.message || 'No se pudo actualizar el resumen.', 'error');
        return;
    }

    updatePendingOrders(response.data || {});
});
```

El cliente también emite:

- `gf:heartbeat:tick`, después de procesar una respuesta;
- `gf:heartbeat:error`, cuando falla la petición;
- `gf:heartbeat:expired`, cuando la sesión expiró;
- `gf:heartbeat:logout`, cuando se detiene por cierre de sesión.

La API pública permite:

```js
window.GFHeartbeat.triggerNow();
window.GFHeartbeat.stop('logout');
window.GFHeartbeat.authInactive();
```

## Seguridad

- La ruta debe conservar el middleware `auth`.
- No se debe eliminar `noRefreshSession()`.
- Los canales se registran exclusivamente en el backend.
- Cada handler valida y limita su payload.
- Los canales deben ser de lectura o idempotentes.
- No deben ejecutarse pagos, eliminaciones ni cambios críticos desde heartbeat.
- No se debe consultar el estado del usuario en cada tick; la revocación ocurre al cambiar el estado de la cuenta.
- `run_when_hidden` debe habilitarse solo cuando el canal realmente lo necesite.

La ruta excluye CSRF porque realiza polling de infraestructura. Cualquier operación que modifique datos debe utilizar su propia ruta protegida con CSRF.

## Coordinación entre pestañas

El cliente usa `BroadcastChannel` cuando está disponible y `localStorage` como respaldo. Solo la pestaña líder consulta el servidor; las demás reciben el resultado y emiten los mismos eventos localmente.

El identificador se deriva de `site_url`, por lo que dos aplicaciones abiertas en el mismo dominio no comparten liderazgo ni eventos si utilizan rutas base distintas.

## Personalización

La aplicación puede editar su `HeartbeatController` para registrar canales. La lógica de cada canal debe permanecer en el controlador, servicio o modelo propietario del módulo correspondiente.

No se deben añadir canales específicos de notificaciones, multimedia u otro negocio al núcleo de GFrame. Cada módulo registra su integración desde la aplicación que lo instala.

## Verificación

```bash
php packages/bin/phpunit --filter HeartbeatTest
php packages/bin/phpunit --filter HeartbeatPublishes
node --check resources/modules/heartbeat-client/public/heartbeat.js
node --check resources/modules/heartbeat-client/public/session.js
```

Las pruebas verifican registro, normalización, intervalos, visibilidad, ejecución forzada, aislamiento de excepciones, contratos y publicación de archivos.
