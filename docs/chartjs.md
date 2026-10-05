# Chart.js

`chartjs` es una dependencia externa opcional para gráficas. GFrame distribuye la versión 4.4.2, sin modificar su código ni crear gráficas automáticamente.

Seleccione el módulo en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public chartjs`. Se copian `chart.umd.min.js` y su licencia a `public/vendors/external/chartjs/`.

Incluya `public/vendors/external/chartjs/chart.umd.min.js` en el meta de la vista que necesita gráficas. La aplicación define sus datos, configuración e inicialización.


La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.

## Crear y retirar una gráfica

Cargue `gframe-chartjs.js` después de `chart.umd.min.js`. El ejemplo requiere `<canvas id="salesChart"></canvas>`:

```js
const chart = new Chart(document.querySelector('#salesChart'), {
  type: 'bar',
  data: { labels: ['Enero', 'Febrero'], datasets: [{ label: 'Ventas', data: [12, 18] }] }
});
const stopTheme = GFrameChartTheme.watch(chart);
// Antes de retirar el canvas:
// stopTheme();
// chart.destroy();
```

El puente adapta texto, ejes, leyenda y tooltip al tema; los colores de las series pertenecen a la configuración de la gráfica. Para nuevos datos, actualice `chart.data` y llame a `chart.update()` en lugar de crear instancias superpuestas.

[Proyecto oficial](https://www.chartjs.org/) y [documentación de la versión 4.4.2](https://www.chartjs.org/docs/4.4.2/).
