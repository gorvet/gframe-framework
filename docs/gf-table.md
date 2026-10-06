# GF Table

Componente básico obligatorio de búsqueda y ordenación local de tablas, instalado en todos los perfiles. No reemplaza la paginación ni las consultas del servidor: actúa solamente sobre las filas presentes en el navegador. Se utiliza donde la vista lo requiere.

## Uso

El instalador publica `gf-table` automáticamente. Para una publicación manual, use `php bin/modules.php publish /ruta/del/proyecto/public gf-table`. Cargue `public/vendors/internal/gf-table/gf-table.js` en el meta de la vista, después de jQuery.

Las tablas `.gf-table` se inicializan automáticamente. Use `th.sortable` en los encabezados ordenables y `data-type="text"`, `"number"` o `"date"` para indicar el tipo. La búsqueda utiliza el primer `.gf-search` encontrado dentro del contenedor más cercano.

```html
<div class="table-responsive">
  <label for="productSearch">Buscar productos</label>
  <input id="productSearch" class="form-control gf-search" type="search">
  <table id="products" class="table gf-table">
    <thead>
      <tr>
        <th>Código</th>
        <th class="sortable" data-type="text">Producto</th>
        <th class="sortable" data-type="number">Precio</th>
        <th class="sortable" data-type="date">Fecha</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>P01</td>
        <td>Cuaderno</td>
        <td data-sort-value="1234.56">1.234,56 €</td>
        <td data-sort-value="2026-10-05">05/10/2026</td>
      </tr>
      <tr>
        <td>P02</td>
        <td>Lápiz</td>
        <td data-sort-value="2.50">2,50 €</td>
        <td data-sort-value="2026-09-01">01/09/2026</td>
      </tr>
    </tbody>
  </table>
</div>
```

El buscador y la tabla deben compartir el contenedor elegido. Con la configuración predeterminada se busca el `div` más cercano, no cualquier buscador de la página. Use un contenedor por tabla para evitar que varias tablas escuchen el mismo campo.

Para inicializar expresamente, use `$('#miTabla').gfTable(options)`. Las opciones son `searchSelector`, `containerSelector`, `debounceDelay`, `animateSorting` y `decimalSeparator`. La API obtenida mediante `$('#miTabla').data('gfTable')` ofrece `sort(index)`, `filter(query)` y `reset()`. El índice de `sort` se refiere a la lista de encabezados ordenables, no a todas las columnas.

Se emiten los eventos `gfTable.sorted`, `gfTable.filtered` y `gfTable.reset`. El componente reutiliza los estilos de tabla del proyecto; no incluye otro sistema visual.

## Configuración y API

| Opción | Predeterminado | Uso |
| --- | --- | --- |
| `searchSelector` | `.gf-search` | Selector del campo dentro del contenedor |
| `containerSelector` | `div` | Ancestro donde buscar el campo |
| `debounceDelay` | `300` | Espera del filtro en milisegundos |
| `animateSorting` | `true` | Aplicar temporalmente `gf-table-sorting` |
| `decimalSeparator` | `null` | Detectar el separador decimal; admite `'.'` o `','` explícitos |

Para opciones propias, inicialice una tabla sin la clase de autoarranque `.gf-table`, o inicialícela antes del arranque automático. Una segunda llamada no reemplaza la configuración existente.

El siguiente ejemplo supone una tabla `id="customTable"`, sin `.gf-table`, dentro de `.product-list`, con un buscador `.product-search` en ese mismo contenedor.

```js
$('#customTable').gfTable({
  containerSelector: '.product-list',
  searchSelector: '.product-search',
  debounceDelay: 150,
  decimalSeparator: '.'
});

const table = $('#customTable').data('gfTable');
table.filter('cuaderno');
table.sort(0);
// Para restaurar todas las filas y su orden inicial:
// table.reset();
```

En la tabla del ejemplo HTML, `sort(0)` ordena Producto y `sort(1)` Precio. Código no participa porque su encabezado no es `sortable`. La primera ordenación es ascendente; repetir la misma columna alterna la dirección. La ordenación se aplica a las filas visibles y se ejecuta con un temporizador, no inmediatamente al llamar al método.

```js
$('#products')
  .on('gfTable.sorted', function (event, index, direction, type) {
    console.log(index, direction, type);
  })
  .on('gfTable.filtered', function (event, query) {
    console.log(query);
  })
  .on('gfTable.reset', function () {
    console.log('Tabla restaurada');
  });
```

El filtro emite su evento después de mostrar u ocultar filas; si hay una ordenación activa, su evento llega después, cuando termina de ordenar. Los encabezados también responden a Enter y espacio. La dirección se refleja en `aria-sort`, `sorted-asc` y `sorted-desc`.

## Búsqueda local y listados del servidor

La búsqueda compara el texto completo de cada fila, sin distinguir mayúsculas. No elimina tildes, interpreta términos por separado ni utiliza el [buscador léxico](lexical-search.md). `data-sort-value` afecta únicamente a la ordenación, no a la búsqueda. Al ordenar una celda con un `select`, se usa la etiqueta seleccionada, salvo que exista un valor explícito.

GF Table añade después de la tabla el contador «Mostrando X de Y registros». Esas cantidades corresponden a las filas cargadas, no al total de registros de la base de datos.

En un listado paginado por AJAX, la búsqueda y la ordenación globales deben enviarse al controlador para consultar el conjunto completo. Use GF Table para tablas locales o para operaciones limitadas conscientemente a la página actual; no conecte un mismo buscador simultáneamente al filtro local y a una consulta remota.

## Ordenación y reinicio

- Las fechas se comparan como valores numéricos. Use fechas ISO o `data-sort-value` en las celdas para evitar formatos locales ambiguos.
- Filtrar conserva la dirección de ordenación seleccionada.
- `reset()` cancela operaciones pendientes, muestra las filas y restaura el orden de inicialización. Las filas nuevas se conservan al final; las retiradas no se reinsertan.
- Las tablas insertadas directamente o dentro de un fragmento se inicializan automáticamente.
- Los números reconocen coma o punto decimal. Por defecto, el último separador se interpreta como decimal. Para valores ambiguos como `1,234`, configure `decimalSeparator: '.'` si la coma indica miles, o use `data-sort-value="1234"`.

Cada celda puede declarar `data-sort-value` para ordenar por un valor distinto del texto visible. El componente no consulta datos que todavía no hayan sido cargados en la tabla.

Las fechas y los números que no se pueden interpretar se comparan como `0`. Para importes sin separadores de miles use, por ejemplo, `data-sort-value="1234.56"` y `decimalSeparator: '.'`. La correspondencia entre encabezado y celda utiliza la posición de columna; evite `colspan` y encabezados multinivel en tablas ordenables.

## Actualización por AJAX y personalización

El autoarranque observa tablas nuevas, tanto insertadas directamente como dentro de un fragmento. Reemplazar las filas del mismo `tbody` mantiene la instancia existente; vuelva a aplicar `filter()` si necesita conservar el filtro sobre las filas nuevas. Los encabezados y el campo de búsqueda se capturan al inicializar, por lo que para cambiarlos conviene reemplazar la tabla completa y su contenedor.

La API no expone `destroy()` ni una operación para cambiar opciones después de inicializar. `reset()` restaura las filas originales que siguen presentes y añade al final las nuevas, sin recuperar filas eliminadas.

Personalice la presentación con las clases Bootstrap de la tabla y el CSS de la vista, manteniendo las variables del framework. Para acciones de fila, use los botones de acciones comunes del proyecto. La búsqueda, el filtrado y la ocultación de filas son comportamientos de interfaz; no sustituyen permisos ni restricciones de datos del backend.

Consulte [Puentes visuales](paquetes-visuales.md) para las convenciones de variables y orden de carga. GF Table publica JavaScript y utiliza el CSS del proyecto; no distribuye una hoja CSS propia.


## Migración y personalización visual

GF Table dejó de cargarse desde `frontend-core`. Los proyectos que todavía referencien `public/js/core/utils/table.js` deben seleccionar el módulo `gf-table` y cargar `public/vendors/internal/gf-table/gf-table.js` desde la meta de la vista. Mantener ambas rutas durante una migración puede inicializar la misma tabla dos veces.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para revisar rutas, orden de carga, variables del tema y personalización. Los estilos propios de una pantalla deben cargarse después del puente y conservar los estados de búsqueda, ordenación, foco y tema oscuro.
