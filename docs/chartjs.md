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


## Datos, actualización y responsive

Mantenga la instancia en una variable del módulo de la vista para poder actualizarla y destruirla de forma explícita. Cuando cambien filtros o datos, modifique `chart.data` o las opciones necesarias y llame a `chart.update()`; crear una instancia nueva sobre el mismo canvas sin destruir la anterior produce errores y listeners duplicados.

```js
chart.data.labels = response.labels;
chart.data.datasets[0].data = response.values;
chart.update();
```

Chart.js adapta el canvas al contenedor. Defina el tamaño desde el layout y evite fijar simultáneamente dimensiones HTML y CSS contradictorias. Si utiliza `maintainAspectRatio: false`, el contenedor necesita una altura real.

## Carga por AJAX

Una gráfica insertada dentro de un fragmento AJAX debe inicializarse después de insertar el canvas. Antes de reemplazar el fragmento, desconecte el observador del tema y destruya la instancia:

```js
stopTheme();
chart.destroy();
```

No guarde referencias a canvas retirados del DOM. Si la pantalla puede recargarse varias veces, centralice la creación y destrucción para no acumular instancias.

## Accesibilidad y contenido alternativo

Un canvas no comunica por sí solo los datos a lectores de pantalla. Para información importante, acompañe la gráfica con un resumen textual, una tabla o una descripción equivalente. No dependa únicamente del color para diferenciar series; combine color con etiquetas, orden, patrones o texto según el caso.

Los tooltips son ayuda visual y no sustituyen una alternativa accesible. Los valores críticos deben poder consultarse sin hover.

## Datos y seguridad

Los datos que llegan desde el backend siguen siendo datos de aplicación. Valide permisos y ámbito antes de devolverlos y no incruste secretos en configuraciones JavaScript. Las etiquetas procedentes de usuarios deben tratarse como texto, no como HTML confiable.

## Diagnóstico

Si la gráfica no aparece, compruebe en este orden:

1. que `chart.umd.min.js` cargó antes del script de la vista;
2. que existe el canvas y no fue reemplazado después de inicializar;
3. que los arrays de etiquetas y datos tienen la estructura esperada;
4. que el contenedor tiene dimensiones visibles;
5. que no existe otra instancia sobre el mismo canvas;
6. que `gframe-chartjs.js` se carga después del motor cuando utiliza el puente de tema.

El puente de GFrame adapta presentación, no corrige datos inválidos ni configura escalas de negocio.
