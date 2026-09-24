# SEO Schema Guide

## Objetivo
Documentar como se construyen los schemas JSON-LD en el proyecto, que archivos participan, y como usar presets para automatizar al maximo con bajo riesgo de errores manuales.

## Flujo de Construccion
1. `core/render/Render.php` carga metadatos globales + `*.group.meta.php` + `*.meta.php` de la vista.
2. `core/render/Meta.php` fusiona todo en memoria y llama a `renderSchema()`.
3. `core/seo/SchemaComposer.php`:
   - Resuelve `preset`/`presets`.
   - Aplica presets built-in (moldes).
   - Normaliza bloques legacy (`website`, `webpage`).
   - Completa defaults (type/lang/title/description/image/search/org).
4. `core/seo/JsonLD.php` transforma el schema final al `@graph` JSON-LD.
5. `core/render/Meta.php` inyecta el `<script type="application/ld+json">` en el HTML.

## Archivos Involucrados
- `core/render/Render.php`: orquesta carga de metas por vista.
- `core/render/Meta.php`: estado de metas y render final del script JSON-LD.
- `core/seo/SchemaComposer.php`: composicion, presets y defaults.
- `core/seo/schema.presets.php`: catalogo de presets-molde.
- `core/seo/JsonLD.php`: renderer del grafo schema.org.
- `config/meta/global.meta.php`: datos globales que alimentan los moldes (`preset` base, `org`, `search`, etc.).
- `app/views/**/**.meta.php`: ajustes schema por vista.
- `app/views/**/**.group.meta.php`: ajustes schema por grupo de vistas.

## Regla de Arquitectura Recomendada
- Mantener en `core/seo/*` la logica reusable y los presets-molde.
- Mantener en `config/meta/global.meta.php` solo datos globales reales del sitio.
- En vistas, preferir `preset`/`presets` + pocos overrides, evitando schemas gigantes manuales.

## Presets Moldes (core)
Definidos en `core/seo/schema.presets.php` y cargados por `SchemaComposer`:

- `webpage` => `WebPage`
- `collection` / `listing` => `CollectionPage`
- `contact` => `ContactPage`
- `article` => `Article`
- `blog` / `blog_post` => `BlogPosting`
- `news` / `news_article` => `NewsArticle`
- `tech_article` => `TechArticle`
- `product` => `Product` + bloque `product`
- `software` / `app` => `SoftwareApplication` + bloque `software`
- `service` => `Service` + bloque `service`
- `course` => `Course` + bloque `course`
- `event` => `Event` + bloque `event`
- `local_business` => `LocalBusiness` + bloque `business`
- `job` / `job_posting` => `JobPosting` + bloque `job`
- `video` => `VideoObject` + bloque `video`
- `recipe` => `Recipe` + bloque `recipe`
- `creative_work` => `CreativeWork` + bloque `creativeWork`
- `faq` => `WebPage` + bloque `faq`
- `site_base`
- `marketing_page`
- `faq_page`
- `contact_page`
- `blog_article`
- `news_article`
- `tech_article`
- `product_page`
- `saas_landing`
- `service_page`
- `course_page`
- `event_page`
- `local_business_page`
- `job_posting_page`
- `video_page`
- `recipe_page`

## Datos Globales del Sitio (config/meta/global.meta.php)
En vez de definir presets en `config`, ahora se define un bloque global que alimenta todos los moldes:

```php
'schema' => [
  'preset' => 'site_base',
  'search' => [
    'target' => rtrim(site_url, '/') . '/buscar?q={search_term_string}',
  ],
  'org' => [
    'name' => 'Mi organización',
    'logo' => site_url . 'public/img/apple-touch-icon.png',
    'sameAs' => [
      'https://www.example.com/red-social',
    ],
  ],
],
```

## Mapeo de Autollenado
| Campo final JSON-LD | Fuente principal | Fallback |
| --- | --- | --- |
| `Organization.name` | `schema.org.name` | `schema.siteName` -> `meta.ogsite_name` -> `meta.title` -> host de `site_url` |
| `Organization.url` | `schema.org.url` | `site_url` |
| `Organization.logo.url` | `schema.org.logo` | `meta.ogimage` |
| `Organization.sameAs` | `schema.org.sameAs` | vacio |
| `WebSite.url` | `site_url` | n/a |
| `WebSite.name` | `schema.siteName` | `meta.ogsite_name` -> `meta.title` -> host de `site_url` |
| `WebSite.inLanguage` | `schema.lang` | `route.lang` -> `meta.oglocale` -> `es` |
| `WebSite.potentialAction.target` | `schema.search.target` | `site_url + /buscar?q={search_term_string}` |
| `WebPage.url` | `route.currentURL` | `site_url` |
| `WebPage.name` | `schema.title` | `meta.title` |
| `WebPage.description` | `schema.description` | `meta.description` |
| `WebPage.image` | `schema.image` | `schema.webpage.primaryImageOfPage.url` -> `meta.ogimage` |
| `type` principal | `schema.type` | inferencia por bloque (`product`, `service`, etc.) -> `WebPage` |
| `Article.author` | `schema.author` | `meta.author` |

## Uso Recomendado en una Vista
```php
'schema' => [
  'preset' => 'service_page',
  'service' => [
    'name' => 'Consultoria IA',
    'serviceType' => 'Automatizacion de procesos',
  ],
]
```

## Tipos y Bloques Soportados
`JsonLD.php` soporta nodos para:

- `WebSite`, `WebPage`, `Organization`
- `Article`/`BlogPosting`/`NewsArticle`/`TechArticle`
- `Product`
- `SoftwareApplication`
- `Service`
- `LocalBusiness`
- `Course`
- `Event`
- `JobPosting`
- `VideoObject`
- `Recipe`
- `CreativeWork`
- `FAQPage`
- `BreadcrumbList`
- `entities` custom (escape hatch para cualquier `@type` no soportado nativamente)

## Convenciones para Evitar Errores de Usuario
- Definir siempre `schema` global en `config/meta/global.meta.php` con `preset` base + `org` + `search`.
- Mantener placeholders `null` en los moldes para campos editables.
- Si necesitas un tipo nuevo poco comun, meterlo primero por `entities` y luego formalizarlo en `JsonLD.php` si se repite.
- Usar `preset`/`presets` en vez de copiar JSON-LD completo por vista.

## Snippets Rapidos
Preset unico:
```php
'schema' => ['preset' => 'product_page']
```

Preset encadenado:
```php
'schema' => ['presets' => ['site_base', 'faq_page']]
```

Entidad custom:
```php
'schema' => [
  'entities' => [
    [
      '@type' => 'HowTo',
      'name' => 'Configurar bot',
      'step' => [
        ['@type' => 'HowToStep', 'name' => 'Paso 1'],
      ],
    ],
  ],
]
```
