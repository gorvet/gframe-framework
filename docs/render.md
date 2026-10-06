# Render, vistas y templates

Render construye la respuesta HTML de las rutas web. Ejecuta la acción del controlador, entrega sus datos a la vista, prepara las metas y envuelve el contenido con el template, header y footer.

La organización de archivos se describe en [Vistas, templates y partes](vistas.md). Las responsabilidades de apertura y cierre del documento están en [Header](header.md) y [Footer](footer.md).

No necesitas llamar a Render desde cada acción. [Router](rutas.md) lo invoca después de resolver la ruta y ejecutar los middleware. Los canales AJAX, API, webhook y SSE tienen respuestas directas y no pasan por este montaje HTML.

## De la ruta a los archivos

En `config/routes/routes_web.php`:

```php
use RouteBuilder as Route;

Route::get('acerca', 'home/HomeController@about')
    ->template('home')
    ->view('homeAbout')
    ->registerFinal();
```

| Elemento | Archivo o método |
| --- | --- |
| Controlador | `app/controllers/home/HomeController.php` |
| Acción | `HomeController::about()` |
| Vista | `app/views/home/homeAbout.php` |
| Metas de grupo | `app/views/home/home.group.meta.php` |
| Metas de vista | `app/views/home/homeAbout.meta.php` |
| Template | `app/views/templates/homeTemplate.php` |
| Metas de template | `app/views/templates/home.meta.php` |

Las metas son opcionales; la vista y el template resueltos deben existir. Los nombres inferidos y las opciones para cambiarlos se explican en [Rutas](rutas.md).

## Datos que devuelve el controlador

La acción recibe `$routeParams` y devuelve un array para la vista. Añade este método al controlador del ejemplo:

```php
public function about(array $routeParams): array
{
    return [
        'title' => 'Acerca de nuestro proyecto',
        'description' => 'Una aplicación desarrollada con GFrame.',
        'lang' => $routeParams['lang'] ?? 'es',
    ];
}
```

En `app/views/home/homeAbout.php`:

```php
<h1><?= htmlspecialchars($data['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
<p><?= htmlspecialchars($data['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
```

Render conserva la estructura devuelta: no extrae las claves como variables sueltas ni desempaqueta automáticamente un campo `data`. Si devuelves `['data' => ['title' => 'Acerca']]`, debes leer `$data['data']['title']`.

Devuelve un array incluso si no hay datos: `return [];`. El montaje del template espera ese tipo. No imprimas contenido desde la acción para combinarlo con la vista y evita enviar cabeceras después de producir salida.

La vista puede usar `$data` y `$routeParams`. No conviertas parámetros de la petición en rutas de archivos para un `include`. Escapa los textos al incorporarlos a HTML; el contenido HTML enriquecido necesita un tratamiento distinto, explicado en [Sanitización](html-sanitizer.md).

### Constructores

Para páginas web, Render instancia sin argumentos los controladores cuyo constructor no tiene parámetros obligatorios. Si los tiene, pasa el array de `$routeParams` como único argumento. No hay autowiring general de servicios en esta operación.

Usa dependencias opcionales con valores predeterminados o compón los servicios según el contrato de tu controlador. Los canales directos instancian el controlador sin argumentos: no dependas de un constructor obligatorio con parámetros de ruta si el mismo controlador atiende AJAX o API.

## Montaje del HTML

```text
acción → $data
metas → Meta
vista → buffer de salida → $content
header.php → <template>Template.php → footer.php
```

Render captura la salida de la vista en `$content`. El template decide dónde colocar ese HTML. Por ejemplo, `app/views/templates/homeTemplate.php` puede contener:

```php
<main class="container py-4">
    <?= $content ?>
</main>
```

No escapes `$content`: es el HTML ya generado por la vista. Sí deben escaparse los datos usados para construirlo. Tampoco incluyas la vista de nuevo dentro del template.

El header y footer compartidos del proyecto abren y cierran el documento y cargan los recursos registrados. No repitas `<html>`, `<head>` y los scripts globales en cada vista. Si tu vista ya contiene un `<main>`, evita añadir otro alrededor desde el template.

El header y footer se incluyen directamente desde `app/views/templates/header.php` y `footer.php`; no tienen el mismo fallback automático de los templates de módulos.

## Metas y recursos de la página

Render reinicia Meta para la página y carga la configuración global. Después combina template, aportaciones al template, grupo y vista. Las metas de vista pueden utilizar `$data` y `$routeParams`, ya que la acción se ejecutó antes.

En `app/views/home/homeAbout.meta.php`:

```php
<?php

return [
    'metaTags' => [
        'title' => $data['title'] ?? 'Acerca',
        'description' => $data['description'] ?? '',
    ],
    'css' => ['public/css/app/home/about.css'],
    'js' => ['public/js/app/home/about.js'],
];
```

Crea esos assets solo si la pantalla los necesita. Los arrays registran sus URLs; no crean ni publican archivos. El header carga CSS y JavaScript de cabecera, y el footer carga JavaScript de final de página.

Las etiquetas se sustituyen por clave; CSS y JavaScript se acumulan sin duplicar la misma ruta. No uses un array vacío en la meta de vista para intentar eliminar recursos heredados. La combinación completa, schema y SEO se desarrollan en [Metas](meta.md) y [SEO](seo.md).

Los archivos de metas son PHP ejecutable que devuelve un array, no una segunda vista. Mantén sus datos opcionales protegidos con valores predeterminados, especialmente cuando puedan consultarse fuera del render normal para generar SEO.

## Vistas y templates de módulos

Para una ruta asociada a un módulo runtime, la resolución de controlador, vista y metas busca primero en `app/` y después en el módulo correspondiente. No busca todas las vistas de todos los módulos por nombre.

Los templates se resuelven primero en la aplicación y en el módulo de origen. Si no están allí, pueden obtenerse de un módulo activo que declare ese template compartido en su manifiesto. Si varios proveedores declaran el mismo template, la resolución rechaza la ambigüedad.

Para sustituir una vista, coloca el archivo en la misma ruta relativa de `app/views/`. No necesitas una segunda URL ni modificar Render. La sustitución de un archivo es completa, no una combinación automática de su contenido con el original.

Para ampliar comportamiento PHP, utiliza la clase de proyecto y herencia o los contratos del módulo. La ubicación y los namespaces se explican en [Módulos runtime](modulos-runtime.md). No edites los originales dentro de `packages/`.

### Partes de una vista

Un `include` PHP directo no adquiere fallback por estar dentro de una vista de módulo. Usa el resolvedor cuando la parte también deba admitir una versión del proyecto y otra del módulo:

```php
<?php

$partial = \GFrame\Modules\ModuleRuntime::file(
    'views',
    'media-library/parts/example.php',
    $routeParams['sourceModule'] ?? null
);
if ($partial !== null) {
    include $partial;
}
```

`example.php` representa una parte que debes crear; no es un archivo distribuido. Al incluirla desde la vista conserva el acceso a sus variables. El resolvedor devuelve `null` si no encuentra el archivo.

## Footer por vista o grupo

El footer compartido solicita tres áreas: `content`, `copyright` y `credits`. Para cada área se utiliza el primer archivo existente:

1. `<vista>.footer.<área>.php` en la carpeta de la vista.
2. `<grupo>.footer.<área>.php` en esa misma carpeta.
3. `app/views/templates/footer/<área>.php`.

Por ejemplo, `app/views/home/homeAbout.footer.content.php` define el contenido del footer para esta pantalla. Cada candidato utiliza la resolución aplicación/módulo cuando hay un módulo de origen; las áreas no se concatenan entre niveles. Si no existe ninguna parte, no se imprime esa área.

Consulta [Footer](footer.md) para su marcado y personalización visual.

## Sin acción y respuestas de error

`noAction()` omite el método y entrega un array vacío a la vista. No evita resolver el controlador ni elimina los middleware de la ruta. Úsalo cuando el contenido no necesite una acción de negocio, no para saltar controles.

Si el resultado de la acción contiene `status` igual a `error` o `unauthorized`, Render interpreta el código mediante ErrorResponder y muestra la respuesta de error. No lo trata como datos normales para la vista solicitada.

Controlador, vista o template inexistentes también activan el manejo de errores. `DebugMode` determina si se incorporan detalles de diagnóstico; no debes activarlo en producción para explicar errores al visitante.

Evita nombres de vistas propios que comiencen por `error` o el template `error` para pantallas normales: Render los reconoce como rutas de error y puede omitir su acción.

## Límites y comprobación

- Las vistas son PHP, no Blade ni un motor con herencia automática de bloques.
- El idioma de la ruta llega a los datos de ejecución; Render no traduce los textos ni selecciona automáticamente otro archivo de vista por idioma.
- Render usa `require_once` para la vista. No lo utilices como motor para renderizar repetidamente el mismo archivo dentro de una sola petición.
- `getDatas()` puede llamar a un método del controlador activo desde el contexto de Render, pero no carga otro controlador. Para pantallas nuevas, prepara los datos en la acción y evita consultas de negocio repartidas por las vistas.

Si falta contenido, comprueba el resultado de la acción, la ruta relativa de la vista, `$content` en el template y la existencia de header/footer. Si faltan estilos o scripts, comprueba sus metas y que los archivos públicos realmente existan.
