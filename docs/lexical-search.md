# Búsqueda léxica

El módulo `lexical-search` proporciona búsqueda léxica en PHP y JavaScript. No necesita base de datos propia.

Utilícelo para ordenar un catálogo pequeño, buscar documentos ya cargados o filtrar elementos visibles en el navegador. La aplicación entrega los datos y decide cómo mostrar resultados; el módulo no crea una pantalla de búsqueda.

Instálelo mediante el catálogo de módulos. Su servicio original queda en `resources/modules/lexical-search/application/app/services/lexical-search/LexicalSearchEngine.php`; la carpeta `app/services/lexical-search/` queda disponible para personalizaciones por herencia. El cliente se publica en `public/vendors/internal/lexical-search/lexical-search.js` y se carga desde el meta de la vista que lo utilice.

```php
$engine = new \GFrame\Modules\LexicalSearch\Services\LexicalSearchEngine();
$results = $engine->rank($authorizedRows, $query, ['title' => 2, 'body' => 1], [
    'threshold' => 0.18,
    'snippet_fields' => ['body'],
]);
```

Normaliza mayúsculas, tildes y espacios; busca palabras, coincidencias parciales y pequeñas diferencias mediante distancia de edición. Pondera campos, añade coincidencia de frase y ordena por relevancia. Conserva los campos de cada fila y añade `_search_score` y, si se solicita, `snippet` como texto plano. Escape ese texto al imprimirlo en HTML.

## Ejemplo completo en PHP

```php
use GFrame\Modules\LexicalSearch\Services\LexicalSearchEngine;

$rows = [
    ['id' => 1, 'title' => 'Gestión de campañas', 'body' => 'Envía avisos a tus usuarios.'],
    ['id' => 2, 'title' => 'Biblioteca multimedia', 'body' => 'Organiza imágenes y documentos.'],
];
$query = 'campañs';
$engine = new LexicalSearchEngine();
$results = trim($query) === '' ? $rows : $engine->rank(
    $rows, $query, ['title' => 2, 'body' => 1], ['snippet_fields' => ['body']]
);
```

La búsqueda aproxima «campañs» a «campañas». Un peso mayor en `title` favorece coincidencias en ese campo. `_search_score` no es un porcentaje ni está limitado a 1: acumula pesos y bonificaciones. No dependa del orden entre resultados con idéntica puntuación.

Pagine después de ordenar si desea relevancia global dentro del conjunto autorizado. Si pagina primero desde el ORM, la búsqueda solo examina esa página. Para grandes volúmenes, limite el conjunto o use un índice especializado.

## Opciones del motor PHP

| Opción | Valor predeterminado y efecto |
| --- | --- |
| `stop_words` | `[]`; añade palabras a la lista común en español, no la sustituye |
| `max_tokens` | 10 palabras únicas, con un mínimo efectivo de 1 |
| `threshold` | 0.18; puntuación mínima incluida, con mínimo 0 |
| `phrase_weight` | 0.08; bonificación si aparece la consulta completa normalizada |
| `combined_weight` | 0.14; peso de coincidencias en los campos combinados |
| `filter` | Callable que recibe una fila y devuelve si se debe evaluar |
| `boost` | Callable que recibe una fila y añade una bonificación no negativa |
| `snippet_fields` | `[]`; campos de los que se extrae el resumen de texto |

Los tokens tienen al menos dos caracteres. La tolerancia a errores se aplica desde cuatro caracteres: una diferencia, o dos para términos de siete caracteres o más. No requiere que coincidan todos los tokens; el umbral decide qué resultados quedan. Una bonificación puede incluir una fila que no coincida con la búsqueda, por lo que debe usarse con cuidado.

Los campos deben ser claves directas de cada fila; `author.name` no recorre un objeto anidado. Arrays u objetos se convierten en JSON para buscar. El resumen elimina etiquetas HTML y toma hasta 240 caracteres, con elipsis si recorta; no resalta coincidencias ni sustituye el escape HTML.

Opciones originales: `stop_words`, `max_tokens`, `threshold`, `phrase_weight`, `combined_weight`, `filter`, `boost` y `snippet_fields`. Los callbacks `filter` y `boost` son opciones del algoritmo, no un registro de extensiones MVC. Para cambiar el servicio, herede con namespace `App\Services\LexicalSearch` e inyecte su instancia en el controlador del proyecto.

```js
window.GFrameLexicalSearch.matches(query, text);
window.GFrameLexicalSearch.score(query, text);
window.GFrameLexicalSearch.normalize(text);
```

## Filtrar en el navegador

Cargue `public/vendors/internal/lexical-search/lexical-search.js` antes del script propio de la vista mediante sus [metas](meta.md). Por ejemplo, para un buscador y elementos con `data-search-item`:

```js
const input = document.querySelector('[data-search-input]');
if (input) {
    input.addEventListener('input', function () {
        document.querySelectorAll('[data-search-item]').forEach(function (item) {
            item.hidden = !window.GFrameLexicalSearch.matches(input.value, item.textContent);
        });
    });
}
```

Este filtro trabaja únicamente sobre elementos ya cargados. Para buscar en registros que aún no llegaron al navegador, envíe la consulta por AJAX y utilice PHP sobre el conjunto autorizado. JS ofrece `normalize()`, `score()` y `matches()`, sin opciones de pesos, snippets ni callbacks.

El identificador público JS es `GFrameLexicalSearch`; instalaciones antiguas que usaban `KnowledgeLexicalSearch` deben ajustar sus llamadas. No existe un alias automático.

PHP calcula relevancia ponderada; JS filtra textos con umbral 0.62. Sus puntuaciones y normalización de otros alfabetos no son idénticas. Con las opciones predeterminadas, una consulta vacía en PHP devuelve una lista vacía; JS considera que cualquier texto coincide. En PHP, un `boost` o un umbral 0 pueden cambiar ese comportamiento. Muestre el listado normal antes de llamar al algoritmo cuando no haya búsqueda.

## Ampliar el servicio

Guarde, por ejemplo, `app/services/lexical-search/LexicalSearchEngine.php`:

```php
<?php
namespace App\Services\LexicalSearch;

class LexicalSearchEngine extends \GFrame\Modules\LexicalSearch\Services\LexicalSearchEngine
{
    public function searchDocuments(array $rows, string $query): array
    {
        return trim($query) === '' ? $rows : $this->rank(
            $rows, $query, ['title' => 3, 'body' => 1], ['snippet_fields' => ['body']]
        );
    }
}
```

Instancie `App\Services\LexicalSearch\LexicalSearchEngine` desde su controlador o inyéctelo en su servicio. Crear el archivo no sustituye instancias explícitas de la clase original. `normalize()` y `rank()` son públicos; los métodos internos del algoritmo son privados.

No es un índice SQL ni un buscador que consulte automáticamente el ORM: trabaja en memoria con las filas entregadas. Filtre permisos y ámbito antes de entregar esas filas y limite su cantidad. No envíe información privada al navegador para ocultarla después con el filtro JS.
