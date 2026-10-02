# SweetAlert2

SweetAlert2 es una biblioteca de terceros para diálogos y confirmaciones. El módulo `alerts` de GFrame la utiliza mediante `swalAlert`; los avisos breves se muestran con `alertToast`.

## Integración

Los archivos se publican en `public/vendors/external/sweetalert2/`. Carga el CSS original antes del puente `sweetTheme.css`; los metadatos globales ya declaran ambos. El puente adapta tipografía, colores y botones a las variables del framework. Las acciones de estos diálogos están centradas.

Usa los helpers de [Alerts](alerts.md) para mantener una presentación coherente. El puente no sustituye ni modifica la biblioteca original.

## Fuente oficial

[Proyecto y documentación de SweetAlert2](https://sweetalert2.github.io/). El código original y su licencia pertenecen a sus autores.
