# Sanitización y escape de datos

El núcleo ofrece dos métodos públicos de sanitización: `SanitizeHelper::sanitize()` para texto plano y `GFrame\Security\HtmlSanitizer::sanitize()` para HTML enriquecido. Elige según el contenido que deba conservarse y escapa la salida según dónde se imprima.

| Necesidad | API | Resultado |
| --- | --- | --- |
| Eliminar etiquetas de un campo de texto | `SanitizeHelper::sanitize($value): string` | Texto sin etiquetas y sin espacios finales |
| Conservar HTML de un editor con una política limitada | `HtmlSanitizer::sanitize(string $html): string` | Fragmento HTML saneado |
| Imprimir texto en HTML o en un atributo | `htmlspecialchars()` de PHP | Texto escapado para ese contexto |

## Texto plano

En el controlador, valida el tipo antes de sanear:

```php
$name = $_POST['name'] ?? '';
if (!is_string($name)) {
    return ['status' => 'error', 'code' => 'invalid_name'];
}
$name = SanitizeHelper::sanitize($name);
if (trim($name) === '') {
    return ['status' => 'error', 'code' => 'name_required'];
}
```

El método equivale a `strip_tags(rtrim((string)$value))`: elimina espacios finales, conserva los iniciales y no valida correo, longitud, URL, permisos ni reglas de negocio. No sanea arreglos de forma recursiva ni ofrece métodos separados para enteros, correo o SQL.

En la vista sigue escapando el texto:

```php
<?= htmlspecialchars($data['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
```

El ejemplo presupone que el controlador entrega `name` dentro de los datos de la vista. La función global `sanitize()` delega en el mismo helper cuando el arranque ha cargado la compatibilidad; para código nuevo utiliza `SanitizeHelper::sanitize()`.

## HTML enriquecido

```php
use GFrame\Security\HtmlSanitizer;

$safeHtml = HtmlSanitizer::sanitize('<p>Hola <strong>mundo</strong></p>');
```

El resultado conserva los párrafos y el énfasis admitidos. Para el listado de etiquetas, atributos, protocolos, requisitos DOM y un ejemplo al guardar contenido consulta [Limpieza de HTML enriquecido](html-sanitizer.md).

Imprime ese resultado como fragmento del cuerpo HTML. No lo reutilices directamente dentro de atributos, JavaScript o CSS: son contextos diferentes. La limpieza del editor en el navegador no sustituye el saneamiento del backend.

## Validación, consultas y contratos

Sanear no demuestra que un valor cumpla el contrato del formulario. Comprueba tipos, campos obligatorios, tamaños, formatos, permisos y pertenencia al tenant antes de ejecutar la operación. No conviertas un arreglo enviado por el cliente en el texto `Array` ni des por válido un entero solo porque puede convertirse con un cast.

La sanitización tampoco sustituye los parámetros del ORM o de una consulta preparada. No construyas SQL concatenando un texto porque haya pasado por alguno de estos métodos. Consulta [ORM](orm.md) y [Helpers PHP](helpers-php.md#sanitizehelper) para sus contratos específicos.
