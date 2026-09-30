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

Incluya aquí solamente lo que realmente usa toda la aplicación, como Bootstrap, las utilidades comunes y la identidad general del sitio:

```php
<?php

return [
    'metaTags' => [
        'title' => site_name,
        'description' => 'Descripción general del sitio.',
    ],
    'css' => [
        'public/vendors/bootstrap/css/bootstrap.min.css',
        'public/css/common.css',
    ],
    'js' => [
        'public/vendors/jquery/jquery.min.js',
        'public/vendors/bootstrap/js/bootstrap.bundle.js',
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
    'metaTags' => ['robots' => 'noindex,nofollow'],
];
```

## Meta de vista

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

## Valores automáticos

El renderizador añade automáticamente el idioma, la URL canónica, la URL de Open Graph y el contexto necesario para los datos estructurados. Cuando la indexación está desactivada, el valor predeterminado de `robots` es `noindex,nofollow,noarchive`.
