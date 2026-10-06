# SEO, Schema, and Content Rules

## SEO Lives in Meta Files

Keep page SEO concerns inside meta files:

- title
- description
- social image tags
- canonical and current URL values
- schema blocks

Do not spread those concerns through the main view markup.

Use the actual GFrame keys, including `title`, `description`, `canonical`, `ogurl`, `ogimage`, `ogtype` and `ogsite_name`. Render seeds `canonical` and `ogurl` from `routeParams.currentURL`, then applies the resolved metaTags; set an explicit canonical when the page contract requires another URL. Check the resulting HTML rather than inventing a different key spelling.

Storing social keys in Meta does not itself print Open Graph tags. The current skeleton header prints title, description, robots, canonical and JSON-LD, but no Open Graph meta elements. Inspect the project's actual header before claiming social previews are configured; a requested template enhancement is separate from supplying values in a page meta.

## Indexability Is a Route and Global Contract

`Meta::setMetaTags` discards `robots`. `Meta::getMetaTag('robots')` derives its result from `GFrame\Seo\SeoPolicy`: blocked pages emit `noindex,nofollow,noarchive`, otherwise `index,follow`. Use the global SEO configuration and `context.seo.indexable=false` to express exclusions; do not try to force indexing or noindex with view metaTags.

Protected routes, permission-bearing routes and error responses are excluded by that policy. A custom private middleware also needs an explicit route exclusion if the framework cannot recognize it. Indexability is not access control.

Preserve the distinct legacy exclusions: `context.sitemap.include=false` affects sitemap and llms, while `context.llms.include=false` affects llms only; neither alone changes HTML robots. `app.debug` disables indexing and JSON-LD through the configuration bridge. Individual sitemap/robots/llms switches still control their own endpoints.

## Schema Conventions

Schema can be declared from the meta file through the `schema` key.

Use existing config examples as reference instead of inventing random structures.

GFrame's `schema` input is composed by `SchemaComposer` and emitted by `JsonLD`, not copied unchanged as arbitrary JSON-LD. Inspect `src/seo/schema.presets.php` and `src/seo/SCHEMA_GUIDE.md` in the resolved package for supported `type`, `preset`/`presets` and content blocks. Preserve established schema keys such as `siteName`; business payload naming rules do not rename this API. Add SearchAction only for an explicitly configured real search target.

For public pages, common schema blocks include:

- `WebSite`
- `WebPage`
- content-specific structured data when justified

## Content Safety

Public content views may receive optional fields such as:

- `title`
- `html`
- `image`
- nested SEO metadata

When building or editing those views, keep missing optional fields from breaking rendering.

Escape plain text and attributes with the existing PHP conventions. An optional `html` field is not safe just because it has that name: render raw rich HTML only when the content contract establishes trust or backend sanitization, using the existing `GFrame\Security\HtmlSanitizer` pipeline for untrusted saved HTML.

## Sitemap Awareness

Public meta files may also expose sitemap-related values.

If the page participates in sitemap generation, preserve the meta file's ability to return sitemap data without requiring the full page data flow.

For a dynamic route, `sitemap.dynamic.params` maps router placeholders to dataset columns; for example `['slug' => 'slug']`. The dataset declares `table`, `conditions` and a bounded `limit`, with `columns` and an optional `lastmod` column. The provider queries data without running the controller or middleware and does not add publication or tenant filters automatically. Specify those conditions explicitly and preserve public parameter names.

Static route lastmod uses application view/meta file times unless overridden by `sitemap.lastmod`, then route `context.lastmod`. File modification time is not a database content timestamp.

For the full configuration and dataset examples, locate `docs/seo.md` in the project's resolved GFrame package. Do not assume a global skill's relative path reaches that package or that its version matches the project.
