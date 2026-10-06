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


## Diálogos, confirmaciones y resultados

Para los flujos normales de GFrame, prefiera `swalAlert` y las funciones de [Alerts](alerts.md). De este modo se conserva el tema, la semántica de botones y la integración con las respuestas del framework.

Una confirmación solo decide si el frontend continúa con una acción. El backend debe volver a comprobar permisos, CSRF, identidad del recurso y reglas de negocio. Nunca utilice el resultado del diálogo como prueba de autorización.

## Contenido seguro

Para mensajes procedentes del usuario o del backend, utilice texto. Si una integración necesita HTML, asegúrese de que provenga de contenido confiable o saneado. No construya fragmentos HTML con valores sin escapar para mostrarlos dentro del diálogo.

Los mensajes técnicos de excepciones, SQL o rutas internas no deben llegar al diálogo en producción.

## Accesibilidad y foco

SweetAlert2 gestiona un diálogo modal, pero la aplicación debe usar títulos y botones comprensibles. Evite diálogos innecesarios para información que puede mostrarse con un toast o junto al formulario. Una cadena de modales obliga al usuario a interrumpir repetidamente su tarea.

Después de cerrar una confirmación, compruebe que el flujo devuelve el foco a un lugar razonable, especialmente cuando la acción elimina el elemento que lo originó.

## Diagnóstico

Si el diálogo aparece sin el tema de GFrame, revise el orden `sweetalert2.min.css` -> `sweetTheme.css`. Si el helper no existe, compruebe la carga del módulo `alerts`. Si una confirmación se ejecuta dos veces, revise listeners duplicados en fragmentos AJAX antes de culpar a SweetAlert2.
