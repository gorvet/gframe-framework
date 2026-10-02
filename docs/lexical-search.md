# Búsqueda léxica

Módulo `lexical-search`, copiado de BaseConfías sin trasladar controladores, tablas ni reglas del módulo de conocimiento. Conserva el algoritmo original en PHP y JavaScript. No necesita base de datos propia.

Instálelo mediante el catálogo de módulos. Su servicio original queda en `resources/modules/lexical-search/application/app/services/lexical-search/LexicalSearchEngine.php`; la carpeta `app/services/lexical-search/` queda disponible para personalizaciones por herencia. El cliente se publica en `public/vendors/internal/lexical-search/lexical-search.js` y se carga desde el meta de la vista que lo utilice.

```php
$engine = new \GFrame\Modules\LexicalSearch\Services\LexicalSearchEngine();
$results = $engine->rank($authorizedRows, $query, ['title' => 2, 'body' => 1], [
    'threshold' => 0.18,
    'snippet_fields' => ['body'],
]);
```

Normaliza mayúsculas, tildes y espacios; busca palabras, coincidencias parciales y pequeñas diferencias mediante distancia de edición. Pondera campos, añade coincidencia de frase y ordena por relevancia. Conserva los campos de cada fila y añade `_search_score` y, si se solicita, `snippet` como texto plano. Escape ese texto al imprimirlo en HTML.

Opciones originales: `stop_words`, `max_tokens`, `threshold`, `phrase_weight`, `combined_weight`, `filter`, `boost` y `snippet_fields`. Los callbacks `filter` y `boost` son opciones del algoritmo, no un registro de extensiones MVC. Para cambiar el servicio, herede con namespace `App\Services\LexicalSearch` e inyecte su instancia en el controlador del proyecto.

```js
window.GFrameLexicalSearch.matches(query, text);
window.GFrameLexicalSearch.score(query, text);
window.GFrameLexicalSearch.normalize(text);
```

Cambio de nombre documentado: el global original `KnowledgeLexicalSearch` pasa a `GFrameLexicalSearch`; no existe un alias automático. PHP pasa a `GFrame\Modules\LexicalSearch\Services\LexicalSearchEngine`. No se modifican los proyectos de origen.

PHP calcula relevancia de filas ponderadas; JS permite filtrar textos ya cargados y conserva su umbral original de 0.62. No prometen puntuaciones idénticas. Una consulta vacía en PHP devuelve una lista vacía; JS considera que cualquier texto coincide. El consumidor decide mostrar su listado normal antes de llamar al algoritmo cuando no hay búsqueda.

No es un índice SQL ni un buscador que consulte automáticamente el ORM: trabaja en memoria con las filas entregadas. Filtre permisos y ámbito antes de entregar esas filas y limite su cantidad. No envíe información privada al navegador para ocultarla después con el filtro JS.
