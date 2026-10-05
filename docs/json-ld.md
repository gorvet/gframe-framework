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

Para sitemap, robots, llms e indexación consulta [SEO](seo.md).

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

## Relación con `seo.enabled`

Cuando `SEO_ENABLED=false`, `Meta::renderSchema()` devuelve una cadena vacía y no genera el bloque JSON-LD.

En la configuración estructurada, `LegacyConfigBridge` deriva `SEO_ENABLED` de:

```text
seo.enabled = true
AND
app.debug = false
```

Por tanto, desactivar SEO o activar debug desactiva también el JSON-LD generado por GFrame. Esto no afecta a JSON-LD que una aplicación imprima manualmente fuera de `Meta::renderSchema()`.

## Valores que completa SchemaComposer

Después de aplicar los presets, el compositor puede completar:

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

GFrame **no inventa una ruta de búsqueda**. `SearchAction` solo se genera cuando declaras expresamente un `search.target` que contenga `{search_term_string}`:

```php
'schema' => [
    'preset' => 'webpage',
    'search' => [
        'target' => 'https://example.com/buscar?q={search_term_string}',
    ],
],
```

Decláralo únicamente si esa búsqueda existe realmente.

## Nodos base

Con SEO habilitado, el grafo parte de:

```text
Organization
WebSite
WebPage
```

Según los datos añade otros nodos y relaciones. Si existe imagen principal, puede crear `ImageObject`.

Los IDs base utilizan la URL del sitio o página:

```text
#organization
#website
#webpage
#primaryimage
```

## Presets directos

`src/seo/schema.presets.php` contiene presets directos:

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
| `faq` | `WebPage` + bloque `faq` |

## Moldes compuestos

También existen moldes de uso común:

```text
site_base
marketing_page
faq_page
contact_page
blog_article
product_page
saas_landing
service_page
course_page
event_page
local_business_page
job_posting_page
video_page
recipe_page
```

`SchemaComposer` resuelve recursivamente sus claves `preset` y `presets`. Los valores del molde se aplican después de sus presets padre y los valores declarados por la vista tienen la última prioridad.

Ejemplo:

```php
'schema' => [
    'preset' => 'product_page',
    'product' => [
        'name' => 'Cuaderno',
        'price' => 12.50,
        'currency' => 'EUR',
    ],
],
```

`product_page` resuelve primero `product` y conserva `type=Product`.

`saas_landing` combina FAQ con `SoftwareApplication`, dejando `SoftwareApplication` como tipo principal.

Si un preset referencia directa o indirectamente a sí mismo, el compositor lanza `LogicException` con la cadena de presets implicada, en lugar de entrar en recursión infinita.

## Varios presets

Puedes declarar:

```php
'schema' => [
    'presets' => ['software', 'faq'],
    'software' => [
        'name' => 'Agenda del equipo',
    ],
    'faq' => [
        ['q' => '¿Tiene prueba?', 'a' => 'Sí.'],
    ],
]
```

Los presets se fusionan en el orden indicado; un preset posterior puede sobrescribir campos del anterior. Después se aplican los valores explícitos de la vista.

Si aparecen simultáneamente `preset` y `presets`, el código toma `preset` primero mediante `schema['preset'] ?? schema['presets']`. No declares ambos a la vez.

## Article

Para `Article`, `BlogPosting`, `NewsArticle` y `TechArticle`, JsonLD puede utilizar:

```text
title / headline
image
datePublished
dateModified
author
publisher
```

Ejemplo:

```php
'schema' => [
    'preset' => 'blog',
    'author' => 'María Pérez',
    'datePublished' => '2026-09-15T10:00:00-04:00',
    'dateModified' => '2026-10-01T12:00:00-04:00',
],
```

## Product

```php
'schema' => [
    'preset' => 'product',
    'product' => [
        'name' => 'Cuaderno de trabajo',
        'description' => 'Cuaderno de 120 páginas.',
        'sku' => 'CUADERNO-120',
        'brand' => 'Mi marca',
        'images' => ['https://example.com/public/img/cuaderno.jpg'],
        'price' => 0,
        'currency' => 'EUR',
        'availability' => 'https://schema.org/InStock',
    ],
],
```

`brand` se transforma en `Brand`. Un precio `0` se conserva y genera `Offer`; solo `null` o `''` representan ausencia de precio.

## SoftwareApplication

```php
'schema' => [
    'preset' => 'software',
    'software' => [
        'name' => 'Agenda del equipo',
        'category' => 'BusinessApplication',
        'os' => 'Web',
        'offers' => [
            ['name' => 'Individual', 'price' => 0, 'currency' => 'EUR'],
            ['name' => 'Equipo', 'price' => 25, 'currency' => 'EUR'],
        ],
    ],
],
```

Con varios planes se genera `AggregateOffer`. `lowPrice` y `highPrice` se calculan únicamente con los planes que declaran precio; un plan sin precio ya no introduce un cero ficticio. Los planes con precio real `0` sí cuentan.

El renderer no valida que todos los planes utilicen la misma moneda; esa consistencia pertenece a la aplicación.

`aggregateRating` admite `ratingValue` y `reviewCount`. Los valores numéricos `0` no se eliminan automáticamente, aunque debes declarar valores válidos según Schema.org y el proveedor que vaya a consumirlos.

## Otros tipos soportados

### Service

Campos principales:

```text
name
description
provider
areaServed
offers
serviceType
```

### LocalBusiness

```text
type
name
description
url
image
telephone
address
geo
openingHoursSpecification
sameAs
```

### Course

```text
name
description
provider
url
```

### Event

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

### JobPosting

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

`directApply=false` se conserva en el JSON final.

### VideoObject

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

### Recipe

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

### CreativeWork

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

`isAccessibleForFree=false` se conserva y no se confunde con ausencia del campo.

## FAQ

Si `schema.faq` contiene elementos, GFrame añade un `FAQPage` separado enlazado con la WebPage.

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

Si falta `@id`, asigna `#entity-1`, `#entity-2`, etc. Una entidad custom con el mismo ID de un nodo generado sustituye ese nodo en el grafo final.

## Valores vacíos

Los nodos generados eliminan:

```text
null
''
[]
```

pero conservan valores semánticamente válidos como:

```text
0
false
```

Esto importa especialmente en precios, `directApply` e `isAccessibleForFree`.

## JSON y validación

El grafo se serializa con:

```text
JSON_UNESCAPED_SLASHES
JSON_UNESCAPED_UNICODE
```

GFrame no valida exhaustivamente los requisitos de Google ni la semántica de cada negocio. Declara únicamente datos que correspondan al contenido real y visible.

Para comprobar una página:

1. localiza `application/ld+json` en el código fuente;
2. revisa tipos, IDs, URLs, idioma, fechas, precios y relaciones;
3. valida el vocabulario con Schema.org;
4. utiliza la prueba de resultados enriquecidos del buscador cuando aplique;
5. confirma que el marcado coincide con el contenido visible.

## Verificación automatizada

La suite cubre específicamente:

- resolución recursiva de moldes compuestos;
- protección contra ciclos;
- tipo principal de `saas_landing`;
- SearchAction únicamente explícito;
- precios `0`;
- booleanos `false`;
- agregados de software con planes sin precio;
- ausencia de JSON-LD cuando `SEO_ENABLED=false`.

Consulta [SEO](seo.md) para la política de indexación y [Metadatos](meta.md) para la composición de metas por vista.
