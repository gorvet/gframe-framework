# jQuery

jQuery es una biblioteca de terceros utilizada por los formularios AJAX y algunas integraciones de GFrame. Es una dependencia predeterminada, no una biblioteca desarrollada por GFrame.

## Integración

El archivo se publica en `public/vendors/external/jquery/jquery.min.js`. Los metadatos globales lo cargan antes de los scripts que dependen de `$` o `jQuery`.

Mantén los textos y fragmentos HTML en PHP. En flujos AJAX, utiliza los contratos `status`, `code`, `message`, `data`, `meta` y `html` del framework; no construyas otro contrato en JavaScript.

## Fuente oficial

[Proyecto jQuery](https://jquery.com/). Consulta su documentación para eventos, selectores y AJAX. Los archivos originales conservan la licencia de sus autores.
