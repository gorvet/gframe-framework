# Chart.js

`chartjs` es una dependencia externa opcional para gráficas. GFrame distribuye la versión 4.4.2, sin modificar su código ni crear gráficas automáticamente.

Seleccione el módulo en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public chartjs`. Se copian `chart.umd.min.js` y su licencia a `public/vendors/external/chartjs/`.

Incluya `public/vendors/external/chartjs/chart.umd.min.js` en el meta de la vista que necesita gráficas. La aplicación define sus datos, configuración e inicialización.

El archivo distribuido coincide con los de Dane y Base Confías. Bebots conserva una variante diferente; no se trasladó al paquete común.
