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
