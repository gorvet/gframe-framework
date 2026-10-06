# SEO Schema: nota interna de arquitectura

> La documentación pública vigente vive en [`docs/json-ld.md`](../../docs/json-ld.md). Para sitemap, robots, `llms.txt` e indexación consulta [`docs/seo.md`](../../docs/seo.md).

Este archivo permanece junto al código únicamente para describir **cómo se conectan las piezas internas**. No debe duplicar tutoriales, presets recomendados ni contratos de uso que ya están documentados en `docs/`.

## Ubicación actual

La implementación relevante vive en:

```text
src/render/Render.php
src/render/Meta.php
src/seo/SchemaComposer.php
src/seo/JsonLD.php
src/seo/schema.presets.php
```

Las rutas históricas `core/render/...` y `core/seo/...` no describen la estructura actual del paquete.

## Flujo real

```text
Render
  ↓
metas globales + template/grupo/vista
  ↓
Meta
  ↓
SchemaComposer
  ↓
JsonLD
  ↓
<script type="application/ld+json">...</script>
```

`SchemaComposer`:

- aplica `preset` o `presets` solicitados directamente;
- normaliza bloques legacy como `website` y `webpage`;
- infiere el tipo principal cuando falta;
- completa idioma, nombre del sitio, título, descripción, imagen, organización y target de búsqueda con los fallbacks actuales.

`JsonLD` transforma ese resultado en el grafo Schema.org final.

## Presets

El catálogo vive en `src/seo/schema.presets.php`.

Para código nuevo, la documentación pública recomienda los **presets directos** cuyo comportamiento está verificado, por ejemplo:

```text
webpage
collection
contact
article
blog
news
tech_article
product
software
service
course
event
local_business
job
video
recipe
creative_work
faq
```

El catálogo contiene además moldes compuestos como `site_base`, `marketing_page`, `product_page`, `service_page` y otros.

### Limitación importante

`SchemaComposer::applyPresetChain()` no resuelve recursivamente un `preset` o `presets` declarado **dentro** de otro preset. Por ello, un molde compuesto no debe documentarse internamente como alias garantizado de su preset anidado.

Algunos moldes producen el tipo esperado por inferencia porque incorporan bloques como `product`, `service` o `software`; otros pueden terminar en `WebPage`.

La referencia exacta y ejemplos actualizados están en [`docs/json-ld.md`](../../docs/json-ld.md).

## Datos globales y de vista

Los datos de schema pueden proceder de la jerarquía de metas que Render combina. La configuración normal de una aplicación se mantiene en sus archivos de meta, no dentro de `src/seo/`.

No mantengas aquí ejemplos rígidos de `config/meta/global.meta.php` como si fueran obligatorios: el contenido global pertenece al proyecto y puede variar.

## Regla de mantenimiento

Cuando cambie JSON-LD:

1. cambia primero el runtime y sus pruebas;
2. actualiza [`docs/json-ld.md`](../../docs/json-ld.md) como referencia pública;
3. actualiza [`docs/seo.md`](../../docs/seo.md) si afecta indexación/sitemap/robots/llms;
4. modifica este archivo solo si cambió la arquitectura interna o la ubicación de las clases.

No añadas aquí tutoriales duplicados ni afirmaciones sobre presets que no estén demostradas por `SchemaComposer` y `JsonLD`.
