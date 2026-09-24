# SEO, Schema, and Content Rules

## SEO Lives in Meta Files

Keep page SEO concerns inside meta files:

- title
- description
- social image tags
- canonical and current URL values
- schema blocks

Do not spread those concerns through the main view markup.

## Schema Conventions

Schema can be declared from the meta file through the `schema` key.

Use existing config examples as reference instead of inventing random structures.

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

## Sitemap Awareness

Public meta files may also expose sitemap-related values.

If the page participates in sitemap generation, preserve the meta file's ability to return sitemap data without requiring the full page data flow.
