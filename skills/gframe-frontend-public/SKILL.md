---
name: gframe-frontend-public
description: Build and refactor public GFrame views with group and view meta files, SEO and schema blocks, template conventions, modular assets, and content-driven pages such as home, slug, and marketing screens.
---

# GFrame Frontend Public

Use this when the task is about public-facing GFrame pages rather than admin screens.

Examples:

- home and marketing pages
- public content pages rendered by slug
- SEO and meta setup in public views
- schema blocks in meta files
- public template and asset conventions

## Read Order

1. [references/public-view-meta.md](references/public-view-meta.md)
2. [references/seo-schema-content.md](references/seo-schema-content.md)
3. [references/public-template-and-assets.md](references/public-template-and-assets.md)

## Workflow

1. Identify the public route, template, and target view.
2. Confirm whether the page is static, content-driven, or slug-driven.
3. Keep SEO and schema in the view meta files, not mixed into the PHP view body.
4. Register only the required CSS and JS through meta files.
5. Keep public markup content-focused and aligned with the selected template.
6. Treat JS as progressive enhancement, not as the main source of page markup or copy.

## Hard Rules

- Do not modify GFrame source or vendored files to make a public page fit project-specific needs.
- Keep repeated public-view patterns in the application's shared CSS instead of duplicating them across page stylesheets.
- If public route, render, or tenancy behavior does not fit the framework, stop and surface the mismatch before taking action.
- Keep public pages out of admin paths and admin templates.
- Use group meta and view meta to load public assets and SEO data.
- Keep schema definitions in meta files, not scattered through the view body.
- Preserve current template layering through `app/views/templates/*Template.php`.
- Do not duplicate header or footer framework elements inside the page view.
- Do not hardcode public page sections or content blocks inside JS string templates unless the task is explicitly for a JS-driven widget.
- Do not hardcode user-facing public copy in JS when it belongs in PHP views, CMS/content data, or backend payloads.
- Build public page layout first with Bootstrap `container`, `container-fluid`, `row`, and `col-*`.
- If Bootstrap columns do not fit the design, prefer flexbox before using any CSS Grid layout.
- Do not use CSS Grid by default in public screens. Treat it as a last resort only when Bootstrap and flexbox are not enough.
- Use `section` only for major page-level areas, such as hero, CTA band, features, testimonials, contact, or a "quienes somos" block within the same page.
- Do not use `section` for small internal chunks inside `article`, cards, repeated items, content widgets, or other local components. In those cases prefer `div` with a clear class name.
- Keep content-driven pages safe when optional `$data` fields are missing.
- Do not move SEO or schema concerns into controllers unless the task is explicitly about framework render behavior.

## Use With Other Skills

- Use `gframe-ui-design-clean` when the task is mainly visual design.
- Use `gframe-core-architecture` when the task changes route, template, or render conventions.
- Use `gframe-backend` when the public page also needs controller or data contract changes.
