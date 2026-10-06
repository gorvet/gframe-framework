# Cliente Heartbeat

El módulo `heartbeat-client` reúne las consultas periódicas del navegador, coordina las pestañas abiertas y sincroniza la expiración y el cierre de sesión. Se instala como dependencia de `auth-ui`.

## Integración

Publica `public/js/core/heartbeat.js`, `public/js/core/session.js` y la ruta `ajax/heartbeat`. El controlador original permanece en el paquete; las personalizaciones van en `app/controllers/heartbeat-client/HeartbeatController.php`.

La ruta requiere autenticación y utiliza `noRefreshSession()`: las consultas periódicas no prolongan la sesión. La pestaña líder consulta los canales registrados y comparte los resultados con las demás pestañas.

### Recursos de la plantilla

Si la plantilla no los carga ya, declare los recursos en su meta, después de jQuery y de las utilidades de alertas:

```php
<?php

return [
    'js' => [
        'public/js/core/heartbeat.js',
        'public/js/core/session.js',
        'public/js/app/dashboard/summary.js',
    ],
];
```

El render debe proporcionar `site_url` y `is_protected`, con los valores de la aplicación y de la ruta. El cliente solo comienza el polling si `is_protected` es verdadero. `site_url` debe conservar la ruta base y su barra final, también cuando el proyecto se instala en una subcarpeta. No cree otro temporizador en cada vista.

`heartbeat.js` envía los tokens encontrados en `#tokens`, además de `hb_visible` y `hb_force`. La ruta nativa excluye CSRF porque sus canales son consultas de infraestructura; enviar esos campos no convierte Heartbeat en un endpoint para operaciones de escritura.

## Consumir un canal

El registro PHP y el callback del servidor se explican en [Heartbeat](heartbeat.md#registrar-un-canal). Para el canal `project.summary`, el archivo `summary.js` puede actualizar un contador de la vista:

```html
<span id="pendingCount" aria-live="polite">0</span>
```

```js
document.addEventListener('gf:heartbeat:project.summary', function (event) {
  const response = event.detail.payload || {};
  if (response.status !== 'success') return;

  const counter = document.querySelector('#pendingCount');
  if (counter) counter.textContent = String(response.data?.pending ?? 0);
});
```

El handler PHP del proyecto debe devolver `data.pending` para este ejemplo. `event.detail` incluye `channel`, `payload` y la respuesta general en `response`. Compruebe el estado del canal, no solo el éxito de la petición general. Inserte texto mediante `textContent`; un fragmento HTML recibido requiere el contrato de contenido confiable de su módulo.

Las pestañas seguidoras reciben los mismos resultados, aunque tengan otra vista abierta. Compruebe que el nodo que quiere actualizar existe. Los resultados compartidos pueden recibirse más de una vez, por lo que los listeners deben asignar el estado recibido, no incrementar contadores ni ejecutar acciones irreversibles.

## Solicitar una actualización

```js
// Después de una operación AJAX completada correctamente:
window.GFHeartbeat?.triggerNow();
```

`triggerNow()` solicita un tick a la pestaña líder; no devuelve una promesa ni el resultado del canal. Espere el evento del canal para actualizar la interfaz. La llamada puede omitirse si hay una petición en curso o si han pasado menos de 300 ms desde el último tick. La ejecución forzada omite el intervalo del canal en el servidor, pero conserva sus reglas de visibilidad.

| Comportamiento | Valor del cliente |
| --- | --- |
| Tick con pestaña líder visible | Cada 60 segundos |
| Tick con líder oculta | Cada cinco ticks, aproximadamente cinco minutos |
| Renovación del liderazgo | Cada 10 segundos |
| Caducidad del liderazgo | 30 segundos sin renovación |
| Protección entre ticks normales | Al menos 15 segundos |

Estos tiempos son internos, no opciones públicas por canal. El intervalo PHP de un canal determina si este se ejecuta al llegar una petición, no crea un temporizador propio. Los navegadores pueden retrasar temporizadores en segundo plano; utilice tareas programadas para trabajos que deban ejecutarse sin pestañas abiertas.

## Sesión y cierre

```html
<button type="button" class="btn btn-outline-secondary" data-gf-logout>
  Cerrar sesión
</button>
```

`session.js` registra el botón mediante delegación, solicita confirmación con `swalAlert` y envía `ajax/logout`. Incluya los tokens del formulario en uno de los contenedores nativos (`#tokens`, `#media-tokens`, `#self-account-tokens` o `#user-admin-tokens`). Sin contenedor, intenta serializar los inputs de tokens presentes.

Tras el cierre voluntario, la pestaña solicitante va a `login` y las demás pestañas protegidas muestran el aviso. Cuando expira la sesión, el aviso lleva a `login?rd=...`, conservando el destino para volver después de acceder.

`GFHeartbeat.stop(reason)` y `GFHeartbeat.authInactive()` detienen el cliente, abortan la petición pendiente y cancelan sus temporizadores. No cierran la sesión del servidor ni ofrecen un método de reinicio; para un cierre real utilice el flujo de logout. Una sesión nueva inicia el cliente al cargar otra página.

## Fallos de conexión

`gf:heartbeat:error` incluye `xhr`, `status` y `error` de jQuery. No transforma automáticamente un fallo de red en un aviso visual ni lo retransmite a las demás pestañas. El siguiente tick normal vuelve a intentar la consulta.

Una respuesta `code=expired`, o un fallo HTTP 401/403, activa el flujo de expiración y detiene el polling. Por ello, los errores de permisos propios de un canal deben devolverse dentro de su payload, sin convertir toda la petición en un 403.

La coordinación usa `localStorage` y, cuando existe, `BroadcastChannel`. El acceso a almacenamiento debe estar disponible: el cliente de polling no incorpora una alternativa completa para navegadores que lo bloqueen. No guarde secretos en respuestas compartidas entre pestañas.

## Personalización

Extienda `GFrame\Modules\HeartbeatClient\Controllers\HeartbeatController`, conserve `parent::__construct()` y registre los canales del proyecto. No modifique el dispatcher ni consulte el estado de la cuenta en cada tick. Las operaciones que cambian datos necesitan rutas propias y protección CSRF.

La [guía de Heartbeat](heartbeat.md) documenta la ruta, los canales, sus respuestas, los eventos JavaScript, la coordinación entre pestañas y las reglas de seguridad.


## Elección de pestaña líder

Heartbeat evita que cada pestaña abierta consulte el servidor por separado. Las pestañas compiten por una clave de liderazgo compartida en almacenamiento local.

El modelo es:

```text
pestañas abiertas
   |
   +-> una líder
   |     -> ejecuta polling HTTP
   |     -> publica resultado
   |
   +-> seguidoras
         -> reciben resultado compartido
         -> no duplican polling
```

La líder renueva periódicamente su presencia. Si deja de hacerlo durante el plazo de caducidad, otra pestaña puede asumir el liderazgo.

Este sistema reduce tráfico, pero no es un mecanismo de exclusión distribuida para operaciones de negocio. Dos pestañas pueden observar una transición de liderazgo muy cercana; los handlers del servidor deben seguir siendo idempotentes cuando su canal lo requiera.

## Distribución de resultados

Los resultados pueden viajar mediante BroadcastChannel y almacenamiento compartido. Cada pestaña transforma el resultado de un canal en el evento:

```text
gf:heartbeat:<nombre-del-canal>
```

El evento transporta el payload del canal y la respuesta general del heartbeat.

Un consumidor debe tratar el evento como **estado observado**, no como una orden que debe ejecutarse exactamente una vez. Por ejemplo:

```js
document.addEventListener('gf:heartbeat:project.summary', event => {
  const pending = Number(event.detail.payload?.data?.pending ?? 0);
  document.querySelector('#pendingCount')?.replaceChildren(String(pending));
});
```

No utilice un heartbeat para efectuar pagos, borrar registros o incrementar contadores. Esas operaciones deben tener endpoints explícitos y contratos idempotentes.

## Visibilidad y frecuencia

La pestaña líder reduce actividad cuando está oculta. Esto ahorra tráfico y batería, pero significa que Heartbeat no garantiza precisión temporal.

Un canal que necesita ejecutar una tarea «cada minuto aunque nadie tenga la aplicación abierta» debe usar Cron, no Heartbeat.

El intervalo PHP de cada canal sirve para decidir si ese canal participa en una petición heartbeat concreta. No crea un temporizador independiente en el navegador.

## Forzado tras una mutación

Después de una escritura AJAX, `GFHeartbeat.triggerNow()` puede pedir una actualización temprana:

```text
POST guardar
  -> success
  -> triggerNow()
  -> líder solicita heartbeat
  -> evento del canal
  -> UI actualiza estado
```

Esto evita duplicar lógica de actualización cuando varias zonas de la interfaz consumen el mismo canal.

El forzado no debe interpretarse como confirmación del trabajo anterior. La mutación y el heartbeat son contratos separados.

## Diseño de canales de cliente

Un buen payload de Heartbeat debe ser pequeño, estable y suficiente para actualizar la UI. Prefiera:

- contadores;
- estados;
- timestamps;
- pequeños resúmenes;
- identificadores necesarios para refrescar otra ruta.

Evite transportar grandes listados, HTML de páginas completas, secretos o datos que todas las pestañas no deban conocer.

Si la información puede crecer mucho, use Heartbeat solo para señalar que hubo cambios y consulte el detalle mediante un endpoint normal.

## Diagnóstico de liderazgo

Cuando varias pestañas parecen consultar simultáneamente, revise:

1. disponibilidad de `localStorage`;
2. si el navegador bloquea almacenamiento;
3. si las pestañas usan el mismo origen;
4. eventos de `storage` y BroadcastChannel;
5. si una navegación cambia el scope de aplicación;
6. si un error JavaScript detuvo la renovación de la líder.

No «solucione» el problema añadiendo otro `setInterval` en cada vista: eso elimina precisamente la coordinación que ofrece este módulo.
