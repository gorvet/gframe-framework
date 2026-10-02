# GFTable

Componente básico obligatorio de búsqueda y ordenación local de tablas, instalado en todos los perfiles. No reemplaza la paginación ni las consultas del servidor: actúa solamente sobre las filas presentes en el navegador. Se utiliza donde la vista lo requiere.

## Uso

El instalador publica `gf-table` automáticamente. Para una publicación manual, use `php bin/modules.php publish /ruta/del/proyecto/public gf-table`. Cargue `public/vendors/internal/gf-table/gf-table.js` en el meta de la vista, después de jQuery.

Las tablas `.gf-table` se inicializan automáticamente. Use `th.sortable` en los encabezados ordenables y `data-type="text"`, `"number"` o `"date"` para indicar el tipo. La búsqueda utiliza el primer `.gf-search` encontrado dentro del contenedor más cercano.

Para inicializar expresamente, use `$('#miTabla').gfTable(options)`. Las opciones son `searchSelector`, `containerSelector`, `debounceDelay`, `animateSorting` y `decimalSeparator`. La API obtenida mediante `$('#miTabla').data('gfTable')` ofrece `sort(index)`, `filter(query)` y `reset()`. El índice de `sort` se refiere a la lista de encabezados ordenables, no a todas las columnas.

Se emiten los eventos `gfTable.sorted`, `gfTable.filtered` y `gfTable.reset`. El componente reutiliza los estilos de tabla del proyecto; no incluye otro sistema visual.

## Ordenación y reinicio

- Las fechas se comparan como valores numéricos. Use fechas ISO o `data-sort-value` en las celdas para evitar formatos locales ambiguos.
- Filtrar conserva la dirección de ordenación seleccionada.
- `reset()` cancela operaciones pendientes, muestra las filas y restaura el orden de inicialización. Las filas nuevas se conservan al final; las retiradas no se reinsertan.
- Las tablas insertadas directamente o dentro de un fragmento se inicializan automáticamente.
- Los números reconocen coma o punto decimal. Por defecto, el último separador se interpreta como decimal. Para valores ambiguos como `1,234`, configure `decimalSeparator: '.'` si la coma indica miles, o use `data-sort-value="1234"`.

Cada celda puede declarar `data-sort-value` para ordenar por un valor distinto del texto visible. El componente no consulta datos que todavía no hayan sido cargados en la tabla.

## Migración

El archivo dejó de cargarse desde `frontend-core`. Las futuras migraciones deben seleccionar `gf-table` y cargar su nueva ruta, en lugar de `public/js/core/utils/table.js`.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.
