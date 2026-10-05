# Contratos de respuesta

Una acción devuelve el resultado de la operación; Router o Render lo adapta al canal de la petición. Los servicios pueden devolver ese mismo contrato, mientras que los modelos conservan los tipos de resultado de sus métodos. El controlador prepara la respuesta pública y evita exponer credenciales, SQL o detalles internos.

## Campos del resultado

| Campo | Uso |
| --- | --- |
| `status` | Resultado de la operación. Usa `success` para éxito; `error` y `unauthorized` activan el tratamiento de errores. |
| `code` | Identificador estable de la situación, tanto en éxito como en error. No equivale necesariamente a un estado HTTP. |
| `message` | Texto de feedback para el usuario. |
| `data` | Datos de la operación: registro, identificador, lista u otra estructura acordada. |
| `meta` | Información auxiliar, por ejemplo página y total de resultados. No son las metas SEO de la vista. |
| `html` | Fragmento renderizado en el servidor para sustituir un contenedor de la interfaz. |
| `http_code` | Estado HTTP explícito interpretado por el canal API. |

Los campos se incluyen cuando la operación los necesita. Router no añade automáticamente `message`, `data`, `meta` ni `html`, ni exige todos los campos. Mantén una estructura estable entre el controlador y su consumidor.

## Éxito y error de una operación

```php
return [
    'status' => 'success',
    'code' => 'saved',
    'message' => 'Cambios guardados.',
    'data' => ['id' => 25],
];
```

`saved` es un código del ejemplo: tu aplicación define cómo interpreta sus códigos de éxito. La presencia de `code` no convierte una respuesta en error.

```php
return [
    'status' => 'error',
    'code' => 'invalid_param',
    'message' => 'El título debe tener al menos cinco caracteres.',
];
```

En AJAX, un rechazo de validación puede llegar con HTTP 200 y `status: error`. El consumidor debe comprobar `status`, no solo que la solicitud haya completado su transporte.

Los errores de autorización pueden usar `unauthorized`. En web, tanto `error` como `unauthorized` se convierten en una página de error: no sirven para presentar un mensaje de éxito ni un formulario con errores en línea.

## Modelos, servicios y controladores

Los métodos del ORM no producen todos un sobre HTTP con `status` y `data`. Una consulta puede devolver registros; una escritura tiene su propio resultado y las operaciones pueden lanzar excepciones. Consulta [ORM](orm.md) antes de interpretar un retorno como éxito, fallo o ausencia de datos.

Un servicio combina esos resultados con las reglas del negocio. El controlador selecciona los datos públicos y los convierte en el contrato esperado por la vista o el consumidor externo. Devolver un array desde un servicio no lo envía automáticamente al navegador.

## Listados y fragmentos AJAX

Un listado puede devolver esta estructura:

```php
return [
    'status' => 'success',
    'html' => $html,
    'meta' => [
        'page' => 1,
        'total_pages' => 3,
        'total' => 25,
    ],
];
```

`$html` debe contener un parcial PHP ya renderizado. Escapa los valores al generar ese parcial. JavaScript sustituye el contenedor indicado; no vuelve a construir la página ni recibe automáticamente su template. El ejemplo completo de carga, filtros y paginación está en [Frontend core](frontend-core.md).

Devuelve el fragmento dentro del JSON. El manejador global de utilidades puede sustituir el documento completo si recibe directamente HTML con HTTP 200.

## Adaptación por canal

| Canal | Tratamiento del resultado de la acción |
| --- | --- |
| Web | Render entrega los datos a la vista como `$data` y compone la página. `error` o `unauthorized` desvían a una vista de error. |
| AJAX | Router serializa arrays u objetos como JSON; un escalar se envuelve en `data`. Los errores del contrato también se envían con HTTP 200. |
| API | Router serializa el resultado a JSON. Usa `http_code` cuando se proporciona; para errores sin él, calcula el estado a partir de `code`, con respaldo 400. |
| Webhook | Arrays y objetos se serializan como JSON; los escalares se envían como texto. La acción fija el estado mediante `http_response_code()` cuando lo necesita. |
| SSE | La acción emite eventos. Su valor de retorno no se serializa como un resultado JSON. |

Esta tabla describe los resultados de las acciones. Los errores previos, como una ruta inexistente o un rechazo de middleware, pasan por el tratamiento del canal y pueden terminar la petición sin ejecutar el controlador. Algunos códigos de middleware, como `login_required` o `to_reload`, también provocan redirecciones en canales distintos de AJAX. Consulta [Rutas](rutas.md) y [Errores](errores.md).

## Códigos y feedback

`ErrorResponder` reconoce códigos como `permission`, `forbidden`, `not_found`, `invalid_param`, `invalid_token`, `internal_error` y `service_unavailable`, además de sus alias documentados. API puede convertirlos en estados HTTP; Render los relaciona con páginas de error.

En la interfaz, comprueba primero `status`. Para errores de operación utiliza `successError(message, code)`; para fallos de transporte utiliza `ajaxError(status, error)`. Estos helpers preparan feedback, pero no asignan su icono. Presenta el resultado mediante `alertToast` o `swalAlert` según el flujo del formulario.

Algunos códigos hacen que `successError` navegue a una página de error o recargue la página y devuelva un objeto vacío. Comprueba que haya un título antes de abrir otra alerta. No pases códigos de éxito a ese helper. Los ejemplos de integración están en [Frontend core](frontend-core.md) y [Alertas](alerts.md).

Un mensaje de éxito debe describir el efecto confirmado: «Solicitud recibida» si quedó pendiente una tarea asíncrona, y «Enviado» solamente cuando se conoce ese resultado. La información técnica de excepciones pertenece al tratamiento de errores, no al mensaje público del formulario.
