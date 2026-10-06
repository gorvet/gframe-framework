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

1. Identify the public route, template, target view and ownership: application page, skeleton source or runtime module override.
2. Confirm whether the page is static, content-driven, or slug-driven.
3. Keep page metadata and schema in meta files. Check global and route indexability separately; `metaTags.robots` does not override that policy.
4. Register only the required CSS and JS through meta files.
5. Keep public markup content-focused and aligned with the selected template.
6. Treat JS as progressive enhancement, not as the main source of page markup or copy.
7. For sitemap/llms, verify their direct view-meta lookup and dataset filters rather than assuming the normal rendering context is available.

## Hard Rules

- For application-specific pages, do not modify the installed GFrame package or vendors. For an authorized framework change, edit its skeleton or module originals rather than an installed application copy.
- Keep repeated public-view patterns in the application's shared CSS instead of duplicating them across page stylesheets.
- If public route, render, or tenancy behavior does not fit the framework, stop and surface the mismatch before taking action.
- Keep public pages out of admin paths and admin templates.
- Use group meta and view meta to load public assets and SEO data.
- Keep schema definitions in meta files, not scattered through the view body.
- Preserve template resolution through `ModuleRuntime::template`, with application templates under `app/views/templates/*Template.php` and runtime providers when declared. Keep the standard shared header/footer layering.
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

## Final Checks

- Trace the route to its controller, resolved view, template and meta layers; verify the actual published asset paths.
- Check escaped text, trusted/sanitized rich HTML and absent optional content separately.
- Verify canonical/social URL values, effective robots policy and emitted JSON-LD under the relevant configuration.
- For dynamic indexes, check the direct meta lookup, placeholder-to-column mapping and explicit publication/tenant conditions.
- Check navigation selectors and interactions when affected. Report browser or external SEO checks that were not performed; passing local tests is not a claim of search-engine indexing.

## Use With Other Skills

- For Markdown, sanitized rich content or authorized lexical search, use [the content integration recipe](../gframe-backend/references/content-editor-search.md) from the effective package; frontend preview/filtering does not replace backend authorization.
- Use `gframe-ui-design-clean` when the task is mainly visual design.
- Use `gframe-core-architecture` when the task changes route, template, or render conventions.
- Use `gframe-backend` when the public page also needs controller or data contract changes.
