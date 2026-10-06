# jQuery

jQuery es una biblioteca de terceros utilizada por los formularios AJAX y algunas integraciones de GFrame. Es una dependencia predeterminada, no una biblioteca desarrollada por GFrame.

## Integración

El archivo se publica en `public/vendors/external/jquery/jquery.min.js`. Los metadatos globales lo cargan antes de los scripts que dependen de `$` o `jQuery`.

Mantén los textos y fragmentos HTML en PHP. En flujos AJAX, utiliza los contratos `status`, `code`, `message`, `data`, `meta` y `html` del framework; no construyas otro contrato en JavaScript.

## Versión y uso en GFrame

La versión distribuida es **3.5.1**, declarada en el manifiesto y en el archivo original. No cargue una segunda copia desde CDN: los plugins se registran sobre la instancia de jQuery cargada previamente.

Para elementos insertados por AJAX, use eventos delegados y con namespace:

```js
$(document).off('click.products', '[data-product-details]')
  .on('click.products', '[data-product-details]', function () {
    const id = $(this).data('product-details');
    // Envíe el identificador al endpoint de lectura del proyecto.
  });
```

La validación, los tokens CSRF, el envío y el feedback se describen en [Frontend core](frontend-core.md). Use `.text()` para datos de usuario; reserve `.html()` para fragmentos confiables renderizados por el backend. La biblioteca no necesita un puente de colores porque no aporta controles visuales propios.

## Documentación

[Proyecto jQuery](https://jquery.com/). Consulta su documentación para eventos, selectores y AJAX. Los archivos originales conservan la licencia de sus autores.

[API oficial](https://api.jquery.com/). La documentación del proveedor puede mostrar funciones de versiones posteriores; compruebe su disponibilidad en la versión incluida.


## AJAX, eventos y ciclo de vida

jQuery sigue siendo la base de varios componentes heredados y de utilidades de formulario de GFrame. Para código nuevo, úselo donde ya forma parte del contrato de la pantalla; no añada una segunda capa de abstracción solo para envolver selectores o eventos simples.

En fragmentos que pueden reemplazarse por AJAX, prefiera eventos delegados sobre un contenedor estable. Use namespaces para poder retirar únicamente los listeners de su módulo:

```js
$(document)
  .off('submit.products', '[data-product-form]')
  .on('submit.products', '[data-product-form]', function (event) {
    event.preventDefault();
    // Validar y enviar según el contrato del proyecto.
  });
```

No registre el mismo listener cada vez que se inserta un fragmento sin retirar el anterior.

## Peticiones y contratos

Para acciones AJAX del framework, envíe los tokens CSRF y compruebe `response.status` antes de interpretar el resultado. Un HTTP 200 puede contener un error funcional por compatibilidad del canal AJAX.

Use `.done()`, `.fail()` o `always()` para restaurar estados de botones y loaders tanto en éxito como en fallo. No deje un botón deshabilitado si la petición termina por error de transporte.

Los ejemplos completos están en [Frontend core](frontend-core.md) y [Contratos de respuesta](respuestas.md).

## Seguridad de DOM

Use `.text()` para imprimir texto no confiable. `.html()` debe recibir únicamente fragmentos controlados, normalmente HTML ya renderizado por el backend para ese contenedor. Nunca concatene entrada de usuario dentro de una cadena HTML y la inserte directamente.

Atributos, URLs y selectores construidos a partir de datos externos también necesitan validación; escapar HTML no convierte una URL en un destino autorizado.

## Plugins y orden de carga

Plugins como jQuery UI u Owl Carousel se registran sobre la instancia global de jQuery existente. Cargar otra copia después puede hacer que `$.fn` pierda esos plugins. El orden habitual es:

```text
jquery.min.js
-> plugin
-> utilidades de GFrame
-> script de la vista
```

No utilice `noConflict()` en código compartido de GFrame sin revisar todos los plugins que esperan `$`.

## Diagnóstico

Si aparece `$ is not defined`, el script de la vista cargó antes de jQuery. Si `$(...).plugin is not a function`, revise que el plugin cargó después de la misma instancia de jQuery y que no existe otra copia posterior. Si una acción se ejecuta varias veces tras recargas AJAX, busque listeners duplicados y utilice namespaces/delegación.
