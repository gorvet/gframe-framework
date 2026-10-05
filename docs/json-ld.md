# Datos estructurados JSON-LD

GFrame construye un grafo Schema.org desde las metas de la página y lo imprime mediante `Meta::renderSchema()`.

El recorrido actual es:

```text
metas global/template/grupo/vista
  ↓
Meta
  ↓
SchemaComposer
  ↓
JsonLD
  ↓
<script type="application/ld+json">...</script>
```

Esta página documenta **el comportamiento del runtime actual**. Para sitemap, robots, llms e indexación consulta [SEO](seo.md).

## Dónde declarar schema

Utiliza el bloque `schema` de las metas:

```php
<?php
return [
    'metaTags' => [
        'title' => 'Servicios',
        'description' => 'Servicios disponibles.',
    ],
    'schema' => [
        'preset' => 'webpage',
        'siteName' => 'Mi proyecto',
        'org' => [
            'name' => 'Mi organización',
            'url' => 'https://example.com',
            'logo' => 'https://example.com/public/img/logo.png',
        ],
    ],
];
```

La jerarquía de metas se explica en [Metadatos y recursos de vistas](meta.md).

## Importante: `seo.enabled` no desactiva actualmente JSON-LD

El header del esqueleto llama siempre a:

```php
$this->metasController->renderSchema()
```

Y el recorrido `Meta → SchemaComposer → JsonLD` no comprueba actualmente `SEO_ENABLED`.

Por tanto, **no existe en esta versión la garantía de que `seo.enabled=false` elimine JSON-LD del HTML**.

Además, `SchemaComposer` infiere `WebPage` cuando no encuentra otro tipo, por lo que puede generarse un grafo base incluso sin un bloque `schema` específico.

Si necesitas cambiar esta política, hazlo en el runtime/header de forma deliberada y actualiza esta guía junto al código.

## Valores que completa SchemaComposer

Después de aplicar el preset, el compositor puede completar:

| Campo | Fallback actual |
| --- | --- |
| `type` | inferencia por bloques; finalmente `WebPage` |
| `lang` | idioma de ruta → `oglocale` → `es` |
| `siteName` | `metaTags.ogsite_name` |
| `title` | `metaTags.title` |
| `description` | `metaTags.description` |
| `image` | `metaTags.ogimage` |
| `author` para Article | `metaTags.author` |
| `org.logo` | `metaTags.ogimage` |
| `search.target` | `<site_url>/buscar?q={search_term_string}` |

Si no existe realmente un buscador en `/buscar`, declara un target propio sin `{search_term_string}` para que `JsonLD` no genere `SearchAction`.

## Nodos base

`JsonLD` genera un `@graph` que parte de:

```text
Organization
WebSite
WebPage
```

Según los datos añade otros nodos y relaciones. Si existe imagen principal, puede crear `ImageObject`.

Los IDs base utilizan la URL del sitio/página, por ejemplo:

```text
#organization
#website
#webpage
#primaryimage
```

## Presets directos fiables

`src/seo/schema.presets.php` contiene estos presets simples que establecen directamente un tipo o bloque:

| Preset | Resultado base |
| --- | --- |
| `webpage` | `WebPage` |
| `collection`, `listing` | `CollectionPage` |
| `contact` | `ContactPage` |
| `article` | `Article` |
| `blog`, `blog_post` | `BlogPosting` |
| `news`, `news_article` | `NewsArticle` |
| `tech_article` | `TechArticle` |
| `product` | `Product` + bloque `product` |
| `software`, `app` | `SoftwareApplication` + bloque `software` |
| `service` | `Service` + bloque `service` |
| `course` | `Course` + bloque `course` |
| `event` | `Event` + bloque `event` |
| `local_business` | `LocalBusiness` + bloque `business` |
| `job`, `job_posting` | `JobPosting` + bloque `job` |
| `video` | `VideoObject` + bloque `video` |
| `recipe` | `Recipe` + bloque `recipe` |
| `creative_work` | `CreativeWork` + bloque `creativeWork` |
| `faq` | `WebPage` + bloque `faq` vacío |

Para código nuevo, utiliza estos presets directos mientras no necesites el comportamiento de los moldes compuestos descritos abajo.

## Limitación actual de los moldes compuestos

El catálogo también contiene nombres como:

```text
site_base
marketing_page
faq_page
contact_page
blog_article
product_page
saas_landing
service_page
...
```

Esos moldes incluyen internamente claves `preset` o `presets`.

Sin embargo, `SchemaComposer::applyPresetChain()` **no resuelve recursivamente un preset contenido dentro de otro preset**. Solo resuelve la lista solicitada inicialmente y fusiona el array resultante.

Consecuencia: no todos esos nombres compuestos producen necesariamente el mismo tipo que su `preset` interno sugiere. Algunos funcionan por inferencia porque incluyen un bloque como `product`, `software` o `service`; otros pueden caer finalmente en `WebPage`.

Hasta que el runtime implemente resolución recursiva, no documentes esos moldes como aliases totalmente equivalentes. Para un tipo concreto utiliza el preset directo:

```php
'preset' => 'contact'
'preset' => 'blog'
'preset' => 'product'
```

Tampoco existe actualmente detección de ciclos de presets, porque esa resolución recursiva no se ejecuta.

## Varios presets

Puedes declarar:

```php
'schema' => [
    'presets' => ['software', 'faq'],
    'software' => [
        // ...
    ],
    'faq' => [
        // ...
    ],
]
```

Los presets solicitados directamente se fusionan en orden y después tus valores explícitos tienen prioridad.

Si aparecen simultáneamente `preset` y `presets`, el código toma `preset` primero mediante:

```text
schema['preset'] ?? schema['presets']
```

## Article

Ejemplo:

```php
return [
    'metaTags' => [
        'title' => 'Cómo organizar una biblioteca',
        'description' => 'Una guía práctica.',
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

Para tipos:

```text
Article
BlogPosting
NewsArticle
TechArticle
```

`JsonLD` crea un nodo de artículo enlazado con WebPage, organización e imagen cuando existen esos datos.

## Product

```php
return [
    'schema' => [
        'preset' => 'product',
        'product' => [
            'name' => 'Cuaderno de trabajo',
            'description' => 'Cuaderno de 120 páginas.',
            'sku' => 'CUADERNO-120',
            'brand' => 'Mi marca',
            'images' => [
                'https://example.com/public/img/cuaderno.jpg',
            ],
            'price' => '12.50',
            'currency' => 'EUR',
            'availability' => 'https://schema.org/InStock',
        ],
    ],
];
```

El renderer transforma `brand` en `Brand` y, cuando `price` es no vacío, crea un `Offer`.

La comprobación actual usa `!empty($p['price'])`; por eso un precio numérico `0` se omite. Comprueba el JSON final si cero tiene significado en tu caso.

## SoftwareApplication

```php
return [
    'schema' => [
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
    ],
];
```

Con varios offers crea `AggregateOffer`, calcula low/high price y utiliza la moneda del primer offer como `priceCurrency` del agregado.

El renderer no verifica que todos los planes utilicen la misma moneda: esa consistencia pertenece a tu aplicación.

## Service, LocalBusiness y Course

Bloques soportados:

```text
service
business
course
```

Campos consumidos directamente por el renderer:

| Bloque | Campos |
| --- | --- |
| `service` | `name`, `description`, `provider`, `areaServed`, `offers`, `serviceType` |
| `business` | `type`, `name`, `description`, `url`, `image`, `telephone`, `address`, `geo`, `openingHoursSpecification`, `sameAs` |
| `course` | `name`, `description`, `provider`, `url` |

Los objetos anidados se entregan como estructuras Schema.org; GFrame no valida exhaustivamente su vocabulario.

## Event

`event` admite actualmente:

```text
name
description
startDate
endDate
eventAttendanceMode
eventStatus
images
location
organizer
offers
```

Usa fechas ISO 8601 y estructuras Schema.org válidas para location/offers.

## JobPosting

`job` consume:

```text
title
description
datePosted
validThrough
employmentType
hiringOrganization
jobLocation
baseSalary
applicantLocationRequirements
directApply
```

## VideoObject

`video` consume:

```text
name
description
thumbnailUrl
uploadDate
duration
contentUrl
embedUrl
publisher
```

## Recipe

`recipe` consume:

```text
name
description
image
author
recipeYield
prepTime
cookTime
totalTime
recipeCategory
recipeCuisine
keywords
recipeIngredient
recipeInstructions
nutrition
```

## CreativeWork

`creativeWork` consume:

```text
name
description
url
inLanguage
dateCreated
datePublished
dateModified
isAccessibleForFree
author
publisher
image
text
mainEntityOfPage
```

## FAQ

Si `schema.faq` es no vacío, GFrame añade un `FAQPage` separado enlazado con la WebPage.

Formato esperado:

```php
'faq' => [
    [
        'q' => '¿Puedo cambiar de plan?',
        'a' => 'Sí.',
    ],
]
```

El renderer accede directamente a `q` y `a`; valida la estructura antes de pasar datos dinámicos.

## Breadcrumbs

```php
'breadcrumbs' => [
    ['name' => 'Inicio', 'url' => 'https://example.com'],
    ['name' => 'Libros', 'url' => 'https://example.com/libros'],
]
```

GFrame crea un `BreadcrumbList` y asigna posiciones consecutivas.

## Entidades personalizadas

`entities` permite añadir nodos que el renderer no conoce específicamente:

```php
'entities' => [[
    '@type' => 'Book',
    '@id' => 'https://example.com/libros/manual#book',
    'name' => 'Manual de organización',
]]
```

Si falta `@id`, asigna:

```text
#entity-1
#entity-2
...
```

El grafo se indexa internamente por `@id`; una entidad custom con el mismo ID de un nodo generado lo sustituye en el array final.

## `array_filter()` y valores vacíos

Muchos nodos se construyen con `array_filter()` sin callback.

Eso elimina valores evaluados como falsos, incluidos en varios casos:

```text
null
''
0
false
[]
```

Por eso debes revisar especialmente campos donde `0` o `false` sean datos válidos.

## JSON y escaping

El grafo se serializa con:

```text
JSON_UNESCAPED_SLASHES
JSON_UNESCAPED_UNICODE
```

El renderer no valida requisitos de Google ni corrige semántica de negocio. Declara únicamente datos que correspondan al contenido real y visible.

## Comprobar el resultado

1. abre el código fuente de la página;
2. localiza `application/ld+json`;
3. revisa tipos, IDs, URLs, idioma, fechas, precios y relaciones;
4. valida el vocabulario con Schema.org;
5. usa la prueba de resultados enriquecidos del buscador cuando el tipo aplique;
6. comprueba que el marcado coincide con el contenido visible.

La referencia de [SEO](seo.md) explica por separado indexación, sitemap, robots y llms. Ninguno de esos mecanismos sustituye autenticación o autorización.
