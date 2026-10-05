# Elegir entre ejecución directa, Async, Cron y colas

GFrame ofrece varias formas de ejecutar trabajo. No son intercambiables.

La decisión principal es:

> ¿el usuario debe esperar esta operación, puede lanzarse y olvidarse, o debe quedar persistida para ejecutarse/reintentarse después?

## Resumen rápido

| Necesidad | Opción |
| --- | --- |
| el resultado es necesario para responder ahora | ejecución directa en controller/service |
| sacar una operación puntual del request, sin seguimiento persistente | `Async` |
| ejecutar en una fecha futura | `cron-runner` |
| ejecutar de forma recurrente | `cron-runner` |
| conservar estado `pending/processing/completed/error` | `cron-runner` |
| enviar un correo simple sin bloquear la página | `MailService::sendTemplateAsync()` / `sendHtmlAsync()` |
| procesar campañas o colas de notificaciones | cola correspondiente + `cron-runner` |
| miles de trabajos con necesidad de control/reintento | estrategia persistente; no lanzar miles de `Async` |

## 1. Ejecución directa

Utiliza ejecución normal cuando el resultado forma parte de la respuesta.

```text
petición
  → controller
  → service
  → operación
  → resultado
  → respuesta
```

Ejemplos:

- validar y guardar un producto;
- consultar una lista;
- comprobar permisos;
- crear una relación que debe existir antes de confirmar éxito.

No saques una escritura crítica a segundo plano solo para hacer que la interfaz responda antes. Si la respuesta afirma «guardado», la operación que define ese estado debe haberse confirmado o el contrato debe decir expresamente que quedó pendiente.

## 2. Async: otro proceso, sin cola persistente

`Async` serializa una closure y lanza un proceso PHP CLI separado.

```php
(new Async())->create(static function () use ($recordID): void {
    // Cargar servicios/modelos dentro del worker.
});
```

La petición confirma que el proceso pudo **lanzarse**. No confirma que la tarea haya terminado correctamente.

### Úsalo cuando

- el trabajo puede empezar inmediatamente;
- no necesitas programarlo para una hora futura;
- no necesitas un ID de job persistente;
- no necesitas reintentos automáticos;
- un fallo posterior puede registrarse o gestionarse dentro del propio proceso.

Ejemplos:

- generar un documento que luego se guarda en el proyecto;
- procesar una imagen pesada después de confirmar la operación principal;
- ejecutar una integración puntual que no requiere garantía de entrega;
- enviar correo mediante los métodos async de Mail.

### No lo uses como si fuera una cola

`Async` no incluye por sí solo:

- tabla de trabajos;
- estados persistentes;
- prioridad;
- reintentos;
- supervisión;
- garantía de entrega;
- límite global de concurrencia;
- ejecución programada.

Cada llamada puede lanzar un proceso independiente.

Consulta [Async](async.md) para requisitos de PHP CLI, serialización y manejo de fallos.

## 3. Cron: trabajo persistente y programado

`cron-runner` mantiene tareas en `cron_tasks` y las ejecuta desde CLI.

```text
cron del sistema
  → bin/gframe-cron.php
  → reserva tareas pendientes
  → handler
  → completa / reprograma / marca error
```

Ejemplo conceptual:

```php
$result = (new CronTaskService())->schedule(
    'catalog.sync.25',
    CatalogSyncCron::class,
    '2026-10-05 23:00:00',
    ['catalog_id' => 25]
);
```

### Úsalo cuando

- la operación debe ejecutarse después;
- la operación debe repetirse;
- necesitas que el trabajo siga registrado aunque no haya un navegador abierto;
- necesitas distinguir pendiente, procesando, completada o error;
- quieres recuperación de tareas que quedaron bloqueadas.

Ejemplos:

- sincronización nocturna;
- limpieza diaria;
- renovación periódica de datos;
- procesamiento de colas;
- campañas programadas.

Consulta [Cron runner](cron-runner.md).

## 4. Mail async

Para enviar correo desde una acción web no necesitas construir manualmente un `Async` alrededor de SMTP.

```php
$result = (new \GFrame\Mail\MailService())->sendTemplateAsync(
    'user@example.com',
    'Bienvenido',
    'welcome',
    ['title' => 'Bienvenido']
);
```

Mail utiliza el mecanismo Async para los métodos asíncronos.

La respuesta `mail_queued` significa que se aceptó el lanzamiento, no que el servidor SMTP ya entregó el mensaje.

Para workers o tareas Cron que ya están fuera del request, normalmente utiliza el método síncrono de Mail; no necesitas crear un segundo proceso solo para salir de un proceso que ya es background.

Consulta [Correo y plantillas](mail.md).

## 5. Colas de notificaciones

Notificaciones introduce otro concepto: **persistir el mensaje/trabajo antes de procesarlo**.

```text
negocio
  → enqueue
  → notification_queue
  → worker/procesador
  → transporte
```

`NotificationQueueService::enqueue()` crea un registro en la cola. El procesador reserva trabajos, ejecuta el transporte y marca enviados o fallidos.

El cron puede mantener ese procesador activo:

```text
cron del servidor
  → cron-runner
  → procesador de cola
  → transporte
```

Esto es distinto de `Async`: primero existe un registro persistente del trabajo.

Consulta [Notificaciones](notificaciones.md), [Transporte de correo](notifications-email.md) y [Campañas](notification-campaigns.md).

## 6. Cómo elegir

### Caso A — guardar y redimensionar una imagen

Si crear el registro depende de que exista la imagen original:

```text
request
  → valida
  → guarda original
  → confirma registro
```

Si generar variantes pesadas puede hacerse después:

```text
request confirma original
  → Async genera variantes
```

Si las variantes deben reintentarse hasta quedar procesadas, utiliza una cola persistente o una tarea registrada en vez de confiar solo en Async.

### Caso B — sincronizar un proveedor externo cada noche

No uses Async desde una visita web para iniciar una tarea nocturna.

Usa:

```text
cron-runner
  → servicio de sincronización
  → HttpClient
```

El service puede ser el mismo que usarías para una sincronización manual.

### Caso C — «enviar correo de bienvenida»

Desde el registro web:

```text
registro confirmado
  → Mail async
  → respuesta al usuario
```

Si necesitas garantía, reintentos e historial de cada envío:

```text
registro confirmado
  → cola
  → cron/procesador
  → transporte de correo
```

Son contratos distintos.

### Caso D — campaña de 50 000 destinatarios

No hagas:

```text
for 50 000 usuarios
  → new Async()
```

Una campaña necesita persistencia, procesamiento por lotes y control del estado. Utiliza el sistema de campañas/colas y Cron.

## 7. Dónde vive la lógica

La forma de ejecución no debería duplicar el negocio.

Preferible:

```text
ProductSyncService
   ↑        ↑        ↑
 web      Async     Cron
```

El controller, closure Async o handler Cron son adaptadores que invocan el mismo service.

Ejemplo:

```php
final class CatalogSyncService
{
    public function sync(int $catalogID): array
    {
        // Regla de negocio e integración.
    }
}
```

Desde una acción manual:

```php
$result = (new CatalogSyncService())->sync($catalogID);
```

Desde Async:

```php
(new Async())->create(static function () use ($catalogID): void {
    (new CatalogSyncService())->sync($catalogID);
});
```

Desde Cron:

```php
final class CatalogSyncCron extends Cron
{
    public function handle(array $task = []): array
    {
        return (new CatalogSyncService())->sync((int)($task['catalog_id'] ?? 0));
    }
}
```

Eso evita que «modo web», «modo async» y «modo cron» se conviertan en tres implementaciones diferentes.

## 8. Transacciones y background

Si una tarea depende de datos creados en la petición, confirma primero la transacción y después lanza o encola el trabajo.

Evita:

```text
begin transaction
  → crea datos
  → lanza background
  → rollback
```

El worker podría ejecutarse antes del rollback o leer un estado que nunca llegará a confirmarse.

Preferible:

```text
begin transaction
  → crea datos
commit
  → lanza / encola trabajo dependiente
```

Si el lanzamiento posterior al commit falla y el trabajo es crítico, necesitas una estrategia persistente/reconciliable; Async por sí solo no resuelve esa garantía.

## 9. Seguridad

Un worker no hereda automáticamente la autorización HTTP que ya pasó una ruta.

Antes de lanzar una tarea:

- valida actor y parámetros en el request;
- captura solo IDs y valores necesarios;
- no captures conexiones PDO, recursos o el controller completo.

Dentro del worker:

- vuelve a consultar el estado actual cuando sea relevante;
- aplica las reglas de negocio que protegen el recurso;
- no confíes en `$_POST`, cabeceras del navegador o sesión como contexto implícito.

## Regla práctica

Puedes empezar con esta secuencia:

```text
¿Necesito el resultado para responder?
  sí → ejecución directa
  no ↓

¿Debe quedar persistido/programado/reintentable?
  sí → Cron / cola persistente
  no ↓

¿Solo quiero sacar un trabajo puntual del request?
  sí → Async
```

Después consulta la referencia específica para los detalles de cada mecanismo.
