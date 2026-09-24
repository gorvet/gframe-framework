# Public View and Meta Rules

## Public View Locations

Common public view families live under:

- `app/views/home/`
- `app/views/slug/`
- `app/views/personalizados/`
- other non-admin view folders resolved by web routes

## Meta File Pattern

Use the same two-layer meta pattern as the rest of GFrame:

- group meta: `<group>.group.meta.php`
- view meta: `<viewName>.meta.php`

Public meta files usually carry:

- `metaTags`
- `css`
- `js`
- `schema`
- `credits`
- `sitemap`

## Content-Driven Meta

Slug or content-driven pages may rely on `$data` and `$routeParams` inside the meta file.

Guard optional values carefully so the meta file remains safe when:

- the page is rendered normally
- the meta file is read in sitemap or other framework contexts
