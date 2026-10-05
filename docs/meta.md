# Metadatos y recursos de vistas

`Meta` reúne las etiquetas HTML, los recursos CSS y JavaScript y los datos estructurados que utiliza una página. El contenido del footer se escribe en plantillas PHP.

## Capas

La configuración se aplica en este orden:

1. `config/meta/global.meta.php`: información y recursos comunes de toda la aplicación.
2. `app/views/templates/<plantilla>.meta.php`: recursos y metadatos comunes a una plantilla, como `admin`.
3. `app/views/templates/meta/<plantilla>/*.meta.php`: aportaciones de módulos a esa plantilla, ordenadas por nombre de archivo.
4. `app/views/<grupo>/<grupo>.group.meta.php`: configuración compartida por un grupo de vistas.
5. `app/views/<grupo>/<vista>.meta.php`: configuración propia de una vista.

Las capas posteriores amplían o sustituyen los valores anteriores. Los recursos repetidos se cargan una sola vez.

## Configuración global

Distribuye los recursos según su alcance: global para toda la aplicación, template para un layout, grupo para una familia de pantallas y vista para una pantalla concreta. Declara dependencias antes de los scripts que las utilizan.

Para `app/views/catalogo/productShow.php`, los archivos son `catalogo.group.meta.php` y `productShow.meta.php` en esa carpeta. En grupos anidados se usa el nombre de la última carpeta. Las metas de carpetas superiores no se heredan automáticamente; comparte recursos del panel mediante su meta de template.

Incluya aquí solamente lo que realmente usa toda la aplicación, como Bootstrap, las utilidades comunes y la identidad general del sitio:

```php
<?php

return [
    'metaTags' => [
        'title' => site_name,
        'description' => 'Descripción general del sitio.',
    ],
    'css' => [
        'public/vendors/external/bootstrap/css/bootstrap.min.css',
        'public/css/common.css',
    ],
    'js' => [
        'public/vendors/external/jquery/jquery.min.js',
        'public/vendors/external/bootstrap/js/bootstrap.bundle.min.js',
    ],
    'hjs' => [],
    'schema' => [
        'preset' => 'site_base',
        'org' => ['name' => site_name],
    ],
];
```

## Meta de grupo

Un grupo puede añadir recursos usados por todas sus vistas:

```php
<?php

return [
    'css' => ['public/css/app/admin/users.css'],
    'js' => ['public/js/app/admin/users.js'],
];
```

## Meta de vista

El ejemplo de grupo anterior corresponde a `app/views/admin/users/users.group.meta.php`. Sus recursos se aplican a ese grupo, no a todos los grupos administrativos.

Para compartir recursos del layout, utiliza `app/views/templates/admin.meta.php`, aunque la plantilla se llame `adminTemplate.php`. Las aportaciones adicionales viven en `app/views/templates/meta/admin/*.meta.php` y se combinan por nombre de archivo.

La vista declara únicamente lo adicional o lo que necesita sustituir:

```php
<?php

return [
    'metaTags' => [
        'title' => 'Detalle del servicio',
        'description' => 'Información pública del servicio.',
    ],
    'css' => ['public/css/app/services/detail.css'],
    'js' => ['public/js/app/services/detail.js'],
    'schema' => ['preset' => 'webpage'],
];
```

`css` se imprime en el encabezado, `hjs` contiene JavaScript del encabezado y `js` se imprime al final de la página. No es necesario excluir recursos: los archivos específicos deben declararse solamente en el grupo o la vista que los utiliza.

## Datos dinámicos y prioridad

Las metas evaluadas durante el render pueden consultar `$data` y `$routeParams`. La meta global se carga antes de ejecutar la acción y debe utilizar valores independientes de ella. Render conserva la estructura devuelta por el controlador.

En `app/views/catalogo/productShow.meta.php`:

```php
<?php

return [
    'metaTags' => [
        'title' => $data['title'] ?? 'Producto',
        'description' => $data['description'] ?? '',
    ],
    'css' => ['public/css/app/catalogo/detail.css'],
    'js' => ['public/js/app/catalogo/detail.js'],
];
```

Crea los assets indicados si los necesitas; registrarlos no crea archivos. Protege los datos opcionales cuando las metas se consulten durante la generación de SEO fuera de una petición normal.

| Clave | Combinación entre capas |
| --- | --- |
| `metaTags` | Los valores posteriores sustituyen las claves de igual nombre |
| `css`, `js`, `hjs` | Se acumulan en orden, conservando la primera aparición de cada ruta idéntica |
| `schema` | Sustitución recursiva de valores, incluidos índices numéricos |

Un array vacío no elimina recursos heredados. Dos URLs distintas al mismo archivo cuentan como recursos diferentes. La deduplicación es independiente para `hjs` y `js`: declarar el mismo script en ambas listas puede ejecutarlo dos veces.

## Etiquetas y salida HTML

`metaTags` registra valores; el header del proyecto determina cuáles se imprimen. El header inicial utiliza título, descripción, robots, canonical e idioma. Una clave arbitraria no genera automáticamente otra etiqueta HTML.

`Meta::getMetaTag()` escapa el valor en la salida. Entrega texto normal para evitar entidades escapadas dos veces. Conserva las llamadas de CSS, JavaScript y schema cuando personalices header y footer.

## Metas de módulos

En rutas de módulos runtime, Render busca cada meta de grupo o vista primero en la aplicación y después en el módulo. Un archivo del proyecto sustituye al original completo de esa capa. Conserva los assets necesarios; no se combinan automáticamente los arrays de ambos archivos sustituidos.

Las demás capas siguen combinándose. Consulta [Render](render.md) y [Módulos runtime](modulos-runtime.md) para la resolución y los templates compartidos.

## Valores de la ruta

El renderizador añade automáticamente el idioma, la URL canónica, la URL de Open Graph y el contexto necesario para los datos estructurados. La indexación se decide globalmente y en la ruta mediante `context.seo.indexable`; `metaTags.robots` se ignora. El framework genera `index,follow` o `noindex,nofollow,noarchive` según esa política. Consulta [SEO](seo.md).


## API de Meta

`Meta` mantiene el estado de metadatos y recursos durante el render de una petición. La instancia puede obtenerse con `Meta::getInstance()`, pero el ciclo normal lo gestiona Render.

| Método | Contrato |
| --- | --- |
| `reset()` | Limpia ruta, etiquetas, CSS, JS, scripts de cabecera y schema; después vuelve a cargar la meta global |
| `applyMetaConfig($config)` | Aplica `metaTags`, `css`, `js`, `hjs` y `schema` si existen |
| `setMetaTags($tags)` | Sustituye claves repetidas mediante `array_merge` |
| `setCssLinks($links)` | Acumula rutas y elimina duplicados exactos |
| `setJsScripts($scripts)` | Acumula scripts de pie y elimina duplicados exactos |
| `setHeaderJsScripts($scripts)` | Acumula scripts de cabecera y elimina duplicados exactos |
| `setSchema($schema)` | Combina el schema recursivamente |
| `setRouteParams($routeParams)` | Entrega contexto de ruta a robots y JSON-LD |
| `getMetaTag($name)` | Devuelve el valor escapado; `robots` se calcula por política de indexación |
| `renderSchema()` | Compone y renderiza JSON-LD si SEO está habilitado |

`reset()` no deja la instancia completamente vacía: vuelve a ejecutar `initializeConfig()` y carga `config/meta/global.meta.php`. Esto evita que una petición reutilice recursos de otra y conserva al mismo tiempo la base global.

## Deduplicación de recursos

La deduplicación de CSS y JavaScript es por cadena exacta. Estas dos rutas se consideran distintas aunque apunten al mismo archivo físico:

```text
public/js/app/list.js
/public/js/app/list.js
```

Normalice las rutas y no registre variantes equivalentes en capas diferentes. `hjs` y `js` tienen colecciones separadas: un mismo archivo declarado una vez en cada una puede ejecutarse dos veces.

El orden conserva la primera aparición. Una capa posterior no mueve un recurso ya registrado hacia el final; simplemente se descarta el duplicado exacto.

## Robots y autoridad de la ruta

El valor final de `robots` no se toma de `metaTags.robots`. `Meta::getMetaTag('robots')` calcula el resultado con dos autoridades:

1. `SEO_ALLOW_INDEXING`; si es falso, devuelve `noindex,nofollow,noarchive`;
2. `routeParams.context.seo.indexable`; si es `false`, también bloquea la indexación.

En cualquier otro caso devuelve `index,follow`.

Esto impide que una meta de vista vuelva indexable una ruta bloqueada globalmente o por su contrato de ruta. La meta puede describir título, descripción y canonical, pero no sobreescribe esa política.

## JSON-LD y schema

`setSchema()` usa combinación recursiva. Las capas pueden ampliar objetos ya definidos en lugar de sustituir siempre el bloque completo. Revise especialmente arrays numéricos, porque la combinación recursiva trabaja por índices.

`renderSchema()` devuelve una cadena vacía cuando `SEO_ENABLED` está definido como falso. En caso contrario:

```text
schema acumulado
-> SchemaComposer::compose()
-> JsonLD::renderSchema()
-> <script type="application/ld+json">...</script>
```

Si el compositor o el renderizador no producen un grafo válido, no se imprime una etiqueta vacía. Los presets, ciclos, entidades y restricciones se documentan en [JSON-LD](json-ld.md).

## Meta dinámica y ejecución fuera de una vista normal

Las metas de vista pueden usar `$data` y `$routeParams` durante un render web normal. Sin embargo, SEO, sitemap u otras herramientas pueden inspeccionar rutas sin ejecutar exactamente el mismo recorrido de una petición interactiva.

Por eso una meta dinámica debe:

- utilizar valores predeterminados para datos opcionales;
- no asumir sesión, `$_POST` ni efectos secundarios de un controlador;
- no ejecutar escrituras ni consultas costosas solo para calcular una etiqueta;
- producir valores válidos aunque falte un registro opcional.

El objetivo es que describir una página siga siendo una operación segura y reproducible.
