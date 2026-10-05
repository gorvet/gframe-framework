# Datos estructurados JSON-LD

JSON-LD describe las entidades de una página: la organización, un artículo, un producto, un servicio o una aplicación SaaS. GFrame construye un grafo Schema.org desde el bloque `schema` de las metas y lo imprime en el HTML mediante `Meta::renderSchema()`.

Declara datos que correspondan al contenido visible y real. El marcado puede ayudar a los buscadores a interpretar la página, pero no garantiza resultados enriquecidos. Sus requisitos dependen del tipo y del buscador; consulta la [documentación de Google Search Central](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data).

## Dónde declarar los datos

Organiza las declaraciones con la misma jerarquía de [Metas](meta.md): datos del sitio en `config/meta/global.meta.php`, datos compartidos en la meta del template o del grupo y datos del contenido en la meta de la vista.

Ejemplo global, sustituyendo el dominio y los datos por los del proyecto:

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

`search.target` debe corresponder a un buscador existente. El compositor añade por defecto `/buscar?q={search_term_string}` si falta el valor. Si el sitio no tiene búsqueda, puedes usar una URL real sin ese placeholder, por ejemplo la portada; el renderer omitirá `SearchAction`. Un valor vacío no desactiva el valor por defecto.

La ruta decide la indexación y la meta describe las entidades. Con SEO desactivado se omite JSON-LD; con SEO activo puede emitirse aunque la ruta tenga `seo.indexable = false`. Consulta [SEO](seo.md) para la política global y por ruta.

## Flujo y valores automáticos

Render carga las metas; Meta combina sus bloques `schema`; SchemaComposer aplica los presets y completa valores; JsonLD genera `@context` y `@graph`; el header imprime el script. Mantén `Meta::renderSchema()` al personalizar el header.

| Dato | Fuente si no lo declaras en `schema` |
| --- | --- |
| Título y descripción | `metaTags.title` y `metaTags.description` |
| Imagen | `metaTags.ogimage` |
| Nombre del sitio | `metaTags.ogsite_name`, título o host |
| Idioma | Idioma de la ruta, `oglocale` o `es` |
| URL de la página | `currentURL` de la ruta; después `site_url` |
| Organización | Nombre del sitio y `site_url`; logo desde `ogimage` |
| Autor de artículos | `schema.author` o `metaTags.author` |

El grafo incluye `Organization`, `WebSite` y `WebPage`; añade `ImageObject` si hay imagen y las entidades específicas según los datos. Sus referencias `@id` enlazan nodos como `#organization`, `#website` y `#webpage`. Usa un logo explícito para evitar que una imagen social del contenido se utilice como logo de la organización.

Las metas se combinan mediante `array_replace_recursive()`: una declaración posterior sustituye claves anteriores, pero una lista más corta puede conservar índices anteriores. No declares preguntas, planes o entidades de una sola página en las metas globales.

## Presets básicos

Un preset es un molde de configuración. Los siguientes seleccionan directamente el tipo:

| Preset | Tipo o entidad |
| --- | --- |
| `webpage` | `WebPage` |
| `collection`, `listing` | `CollectionPage`, junto a `WebPage` |
| `contact` | `ContactPage`, junto a `WebPage` |
| `article` | `Article` |
| `blog`, `blog_post` | `BlogPosting` |
| `news` | `NewsArticle` |
| `product` | `Product` |
| `software`, `app` | `SoftwareApplication` |
| `service` | `Service` |
| `course` | `Course` |
| `event` | `Event` |
| `local_business` | `LocalBusiness` |
| `job`, `job_posting` | `JobPosting` |
| `video` | `VideoObject` |
| `recipe` | `Recipe` |
| `creative_work` | `CreativeWork` |
| `faq` | `WebPage`; añade `FAQPage` cuando hay preguntas |

El catálogo contiene también estos moldes con campos preparados:

| Molde | Tipo generado |
| --- | --- |
| `site_base`, `marketing_page`, `faq_page` | `WebPage` |
| `contact_page` | `ContactPage` |
| `blog_article` | `BlogPosting` |
| `news_article` | `NewsArticle` |
| `tech_article` | `TechArticle` |
| `product_page` | `Product` |
| `saas_landing` | `SoftwareApplication` |
| `service_page` | `Service` |
| `course_page` | `Course` |
| `event_page` | `Event` |
| `local_business_page` | `LocalBusiness` |
| `job_posting_page` | `JobPosting` |
| `video_page` | `VideoObject` |
| `recipe_page` | `Recipe` |

El compositor resuelve las referencias internas de cada molde de forma recursiva. Tus campos explícitos tienen prioridad sobre los valores del molde; `type` permite sustituir el tipo generado, pero no es necesario repetirlo. Una referencia circular lanza `LogicException` con la cadena de presets implicada. Un nombre desconocido cae en `WebPage`, sin error de validación.

Puedes usar `presets` con una lista; se aplican en orden y tus valores explícitos tienen prioridad. Si existen simultáneamente `preset` y `presets`, gana `preset`. Evita heredar un `preset` global y añadir únicamente `presets` en una vista; declara en esa vista el selector que vas a utilizar.

## Artículo

En una meta como `app/views/articles/articleDetail.meta.php`:

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

Para contenido dinámico, obtén esos valores de `$data`, igual que las otras metas de la vista. El autor del artículo se recibe como nombre de persona, no como un objeto libre.

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

`images` contiene URLs; `brand` es un nombre. El renderer construye `Offer` con el precio y la URL de la página. El molde usa USD y disponibilidad en stock por defecto: sustituye ambos por los datos reales. Actualmente un precio numérico cero se omite por los filtros del renderer; no utilices este ejemplo para representar un producto gratuito.

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

La lista de planes genera `AggregateOffer` y sus ofertas. Todos deben utilizar la misma moneda. `saas_landing` combina software y FAQ sin cambiar el tipo principal de la aplicación; su molde contiene un bloque de valoraciones vacío. Para evitar emitir una valoración incompleta, usa `software` como en el ejemplo o declara `aggregateRating => null` cuando no existan valoraciones reales.

Las preguntas utilizan claves `q` y `a`. El bloque FAQ se puede añadir junto a otro tipo; debe corresponder a preguntas y respuestas visibles en la página.

## Campos de los otros tipos

Los nombres siguientes son las claves que acepta GFrame, no una lista de requisitos para resultados enriquecidos. Las propiedades fuera de esta selección se añaden mediante `entities`.

| Bloque | Campos específicos admitidos |
| --- | --- |
| `service` | `name`, `description`, `serviceType`, `provider`, `areaServed`, `offers` |
| `business` | `type`, `name`, `description`, `url`, `image`, `telephone`, `address`, `geo`, `openingHoursSpecification`, `sameAs` |
| `course` | `name`, `description`, `url`, `provider` |
| `event` | `name`, `description`, `startDate`, `endDate`, `eventAttendanceMode`, `eventStatus`, `images`, `location`, `organizer`, `offers` |
| `job` | `title`, `description`, `datePosted`, `validThrough`, `employmentType`, `hiringOrganization`, `jobLocation`, `baseSalary`, `applicantLocationRequirements`, `directApply` |
| `video` | `name`, `description`, `thumbnailUrl`, `uploadDate`, `duration`, `contentUrl`, `embedUrl`, `publisher` |
| `recipe` | `name`, `description`, `image`, `author`, `recipeYield`, `prepTime`, `cookTime`, `totalTime`, `recipeCategory`, `recipeCuisine`, `keywords`, `recipeIngredient`, `recipeInstructions`, `nutrition` |
| `creativeWork` | `name`, `description`, `url`, `inLanguage`, `dateCreated`, `datePublished`, `dateModified`, `isAccessibleForFree`, `author`, `publisher`, `image`, `text`, `mainEntityOfPage` |

Los objetos anidados, como `PostalAddress`, `Place`, `Person` u `Offer`, deben incluir sus claves Schema.org (`@type`, `priceCurrency`, etc.). Solo las ofertas de `product` y `software` reciben la transformación específica mostrada arriba. Usa fechas ISO 8601 y duraciones como `PT5M`.

Ejemplo de servicio:

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

Los bloques de servicio, negocio, curso, evento, empleo, vídeo, receta y obra pueden generar entidades adicionales aunque el tipo principal sea otro. Producto y software requieren su `type` correspondiente. No basta cambiar `type` por cualquier nombre Schema.org para obtener un nodo de ese tipo.

## Navegación y tipos adicionales

`breadcrumbs` recibe una lista consecutiva de elementos con `name` y `url`; las posiciones se generan automáticamente. `entities` admite objetos Schema.org completos, incluidos tipos que no tienen preset:

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

Sin `@id`, GFrame asigna `#entity-1`, `#entity-2`, etc. Una entidad con el mismo `@id` de un nodo generado lo sustituye completamente, no fusiona propiedades. Usa identificadores distintos salvo que quieras reemplazar ese nodo.

## Comprobar el resultado

1. Abre el código fuente de la página y localiza `application/ld+json`.
2. Comprueba tipos, URLs absolutas, idioma, fechas y relaciones del grafo.
3. Valida el vocabulario con el [validador Schema.org](https://validator.schema.org/) y la elegibilidad con la [prueba de resultados enriquecidos](https://search.google.com/test/rich-results).
4. Contrasta cada dato con el contenido visible, incluidos precios, disponibilidad, preguntas y valoraciones.

El renderer serializa los datos; no valida requisitos por tipo. Sus filtros pueden omitir valores `null`, vacíos, cero o `false`. Para propiedades donde esos valores tengan significado, comprueba el JSON generado o utiliza una entidad completa. Mantén el contenido editorial controlado y no insertes HTML arbitrario de usuarios en estos bloques.
