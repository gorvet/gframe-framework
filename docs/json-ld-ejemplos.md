# Ejemplos completos de JSON-LD

Estas metas completas complementan la referencia de [Datos estructurados JSON-LD](json-ld.md). Sustituye los dominios, rutas y datos por contenido real del proyecto. Cada bloque devuelve un arreglo para un archivo de meta; coloca los datos comunes en la meta global y los particulares en la meta de la vista.

Declara search.target solo cuando el proyecto disponga de ese buscador. No se inventa un destino si falta. Los ejemplos se comprueban contra SchemaComposer y JsonLD.

## Configuración global y página

```php
<?php
return [
    'schema' => [
        'preset' => 'webpage',
        'siteName' => 'Mi proyecto',
        'org' => [
            'name' => 'Mi organización',
            'url' => 'https://example.com',
            'logo' => 'https://example.com/public/img/logo.png',
            'sameAs' => ['https://example.org/perfil-oficial'],
        ],
        'search' => [
            'target' => 'https://example.com/buscar?q={search_term_string}',
        ],
    ],
];
```

## Artículo

```php
<?php
return [
    'metaTags' => [
        'title' => 'Cómo organizar una biblioteca',
        'description' => 'Una guía práctica para clasificar libros.',
        'ogimage' => 'https://example.com/public/img/biblioteca.jpg',
    ],
    'schema' => [
        'preset' => 'blog',
        'author' => 'María Pérez',
        'datePublished' => '2026-09-15T10:00:00-04:00',
        'dateModified' => '2026-10-01T12:00:00-04:00',
    ],
];
```

## Producto

```php
<?php
return ['schema' => [
    'preset' => 'product_page',
    'product' => [
        'name' => 'Cuaderno de trabajo',
        'description' => 'Cuaderno de 120 páginas.',
        'sku' => 'CUADERNO-120',
        'brand' => 'Mi marca',
        'images' => ['https://example.com/public/img/cuaderno.jpg'],
        'price' => '12.50',
        'currency' => 'EUR',
        'availability' => 'https://schema.org/InStock',
    ],
]];
```

## Aplicación SaaS, planes y preguntas

```php
<?php
return ['schema' => [
    'preset' => 'software',
    'software' => [
        'name' => 'Agenda del equipo',
        'category' => 'BusinessApplication',
        'os' => 'Web',
        'offers' => [
            ['name' => 'Individual', 'price' => '9.00', 'currency' => 'EUR'],
            ['name' => 'Equipo', 'price' => '25.00', 'currency' => 'EUR'],
        ],
    ],
    'faq' => [
        ['q' => '¿Puedo cambiar de plan?', 'a' => 'Sí, desde la configuración de tu cuenta.'],
    ],
]];
```

## Servicio

```php
<?php
return ['schema' => [
    'preset' => 'service',
    'service' => [
        'name' => 'Consultoría de procesos',
        'serviceType' => 'Consultoría empresarial',
        'areaServed' => 'España',
        'offers' => [
            '@type' => 'Offer',
            'price' => '150.00',
            'priceCurrency' => 'EUR',
        ],
    ],
]];
```

## Entidad personalizada

```php
<?php
return ['schema' => [
    'preset' => 'webpage',
    'breadcrumbs' => [
        ['name' => 'Inicio', 'url' => 'https://example.com'],
        ['name' => 'Libros', 'url' => 'https://example.com/libros'],
    ],
    'entities' => [[
        '@type' => 'Book',
        '@id' => 'https://example.com/libros/manual#book',
        'name' => 'Manual de organización',
        'author' => ['@type' => 'Person', 'name' => 'María Pérez'],
        'isbn' => '9780000000002',
    ]],
]];
```

Para entender la prioridad de metas, presets, salida y límites consulta la referencia principal y [SEO](seo.md).
