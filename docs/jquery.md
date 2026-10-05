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
