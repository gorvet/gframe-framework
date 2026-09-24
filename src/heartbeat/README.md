# GF Heartbeat (PHP-first)

## Objetivo
Unificar polling en una sola peticion (`system/heartbeat`) para ejecutar multiples tareas periodicas desde backend, sin registrar timers por vista.

## Estructura
- `core/heartbeat/HeartbeatChannelRegistry.php`: registro interno de canales.
- `core/heartbeat/HeartbeatMaster.php`: motor maestro (scheduler backend + dispatch).
- `core/heartbeat/HeartbeatChannelTrait.php`: helpers para handlers (`hbInt`, `hbSuccess`, `hbError`).
- `app/controllers/system/heartbeat/HeartbeatController.php`: extension del proyecto para registrar canales del proyecto.
- `public/js/core/heartbeat.js`: cliente generico que envia ticks y emite eventos.
- `config/routes/routes_system.php`: ruta unica del heartbeat.

## Ruta
```php
Route::post('system/heartbeat', 'system/heartbeat/HeartbeatController@dispatch')
    ->middleware(['auth'])
    ->excludeMiddleware(['CSRF'])
    ->noRefreshSession()
    ->registerFinal();
```

Alias legacy mientras exista frontend antiguo:

```php
Route::post('ajax/heartbeat', 'system/heartbeat/HeartbeatController@dispatch')
    ->middleware(['auth'])
    ->excludeMiddleware(['CSRF'])
    ->noRefreshSession()
    ->registerFinal();
```

## Flujo (PHP-first)
1. Frontend envia tick a `system/heartbeat` con contexto minimo:
   - `hb_visible` (1/0)
   - `hb_force` (1/0)
2. Middleware valida sesion y seguridad.
3. `HeartbeatController` (extendido) registra canales.
4. `HeartbeatMaster` decide que canales corren segun:
   - `interval_ms`
   - `run_when_hidden`
   - `hb_force`
   - ultimo run guardado en sesion (`__gf_heartbeat_last_run`)
5. Backend responde con canales ejecutados.
6. `heartbeat.js` emite eventos `gf:heartbeat:<channel>`.

## Rol del HeartbeatController extendido
Si: su funcion principal es **registrar/orquestar canales** del proyecto.

No debe contener logica pesada. Declaras el canal y apuntas a un handler `Controller@method` del modulo duenio de la logica.

## Registrar canales (backend)
En `app/controllers/system/heartbeat/HeartbeatController.php`:

```php
$this->registerChannel('notifications.inbox', [
    'interval_ms' => 60000,
    'run_when_hidden' => false,
    'payload' => ['limit' => 18, 'render' => 'navbar'],
], 'admin/notification/NotificationController@heartbeatInboxChannel');
```

## Agregar un canal nuevo
Ejemplo `billing.sync` cada 5 minutos:

```php
$this->registerChannel('billing.sync', [
    'interval_ms' => 300000,
    'run_when_hidden' => true,
    'payload' => ['batch' => 50],
], 'admin/billing/BillingController@heartbeatBillingSyncChannel');
```

Y en el controlador de billing implementas:

```php
public function heartbeatBillingSyncChannel(array $payload = [], array $context = []): array {
    $batch = max(1, min(500, (int)($payload['batch'] ?? 50)));
    $result = $this->model->syncPending($batch);
    return ['status' => 'success', 'data' => ['synced' => (int)($result['synced'] ?? 0)]];
}
```

Opcional recomendado en el controlador con canales:

```php
use HeartbeatChannelTrait;
```

Helpers:
- `hbInt($payload, 'limit', 18, 1, 40)`: clamp de enteros.
- `hbSuccess($data, $extra)`: respuesta estandar success.
- `hbError('code', 'mensaje')`: respuesta estandar error.

## Cliente JS
`public/js/core/heartbeat.js`:
- No registra canales por vista.
- Hace tick global con esquema de frecuencia:
  - visible: cada 60s
  - oculta: cada 5 minutos (multiplicador)
  - force en focus/visibilitychange con anti-rafaga
- Coordinacion multi-tab:
  - una pestana lider hace polling al backend
  - las demas reciben resultados por `BroadcastChannel` (fallback `localStorage`)
- Expone `window.GFHeartbeat.triggerNow()`.
- Emite:
  - `gf:heartbeat:expired`
  - `gf:heartbeat:error`
  - `gf:heartbeat:tick`
  - `gf:heartbeat:<channel>`

Consumir en UI:

```js
document.addEventListener('gf:heartbeat:notifications.inbox', function (evt) {
  var payload = evt.detail.payload || {};
  if (payload.status !== 'success') return;
  // actualizar UI
});
```

## Seguridad recomendada
1. Mantener allowlist de canales solo en backend (`registerChannel`).
2. Validar/castear payload dentro de cada handler.
3. Mantener `noRefreshSession()` para no revivir sesion por polling.
4. Limitar frecuencia con `interval_ms` y scheduler backend (`interval_ms >= beat base`).
5. No ejecutar operaciones criticas solo por frontend.

## Contrato de respuesta (minimo)
Ejemplo:

```json
{
  "status": "success",
  "data": {
    "channels": {
      "session": { "status": "success", "code": "alive" },
      "notifications.inbox": { "status": "success", "data": { "unread": 3 }, "html": "..." }
    }
  }
}
```

## Notas operativas
- Si expira sesion, middleware retorna `code=expired`; el cliente emite `gf:heartbeat:expired`.
- El estado de ultimo run se guarda por sesion de usuario.
- Para nuevos proyectos: reutiliza `core/heartbeat/*` y define canales en el `HeartbeatController` extendido del proyecto.
