# SweetAlert2

SweetAlert2 es una biblioteca de terceros para diálogos y confirmaciones. El módulo `alerts` de GFrame la utiliza mediante `swalAlert`; los avisos breves se muestran con `alertToast`.

## Integración

Los archivos se publican en `public/vendors/external/sweetalert2/`. Carga el CSS original antes del puente `sweetTheme.css`; los metadatos globales ya declaran ambos. El puente adapta tipografía, colores y botones a las variables del framework. Las acciones de estos diálogos están centradas.

Usa los helpers de [Alerts](alerts.md) para mantener una presentación coherente. El puente no sustituye ni modifica la biblioteca original.

## Versión y contrato de uso

La versión distribuida es **11.10.0**. Los archivos principales son `sweetalert2.min.css` y `sweetalert2.all.min.js`; el puente `sweetTheme.css` se carga después del CSS original. La versión declarada corresponde a la copia del paquete, no a la última del proveedor.

```js
swalAlert({
  icon: 'success',
  title: 'Cambios guardados',
  confirmButtonText: 'Aceptar'
});
```

Use `swalAlert` para conservar los botones y el tema comunes; no replique el feedback con banners propios ni inicialice otro tema de SweetAlert2. Las confirmaciones y el manejo de respuestas AJAX se explican en [Alerts](alerts.md). Un diálogo de confirmación no sustituye autorización ni CSRF.

## Documentación

[Proyecto y documentación de SweetAlert2](https://sweetalert2.github.io/). El código original y su licencia pertenecen a sus autores.
