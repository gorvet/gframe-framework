# Chart.js

`chartjs` es una dependencia externa opcional para gráficas. GFrame distribuye la versión 4.4.2, sin modificar su código ni crear gráficas automáticamente.

Seleccione el módulo en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public chartjs`. Se copian `chart.umd.min.js` y su licencia a `public/vendors/external/chartjs/`.

Incluya `public/vendors/external/chartjs/chart.umd.min.js` en el meta de la vista que necesita gráficas. La aplicación define sus datos, configuración e inicialización.


La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.
