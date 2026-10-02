# List, Filters, Pagination, Server HTML

## Backend Contract

For AJAX list endpoints:

- receive `page` plus active filters
- return `status`
- return `html` when the frontend replaces a list block
- return `meta.page`, `meta.total_pages`, and other needed state

For initial GET screens:

- compute the real clamped page
- if requested page is invalid and request method is GET, redirect to the normalized querystring

## Rendering Strategy

List action-menu triggers use `btn btn-outline-secondary btn-list-actions btn-sm` (plus `dropdown-toggle` when needed): a light/white background and neutral outline, never a solid dark secondary button. Reuse the shared admin CSS class in future lists instead of styling each view separately. Preserve theme-aware text and light hover/open states.

1. Controller calls the model list method.
2. Controller renders the list partial with output buffering.
3. Controller appends the rendered markup as `html`.
4. JS replaces the mount container with `res.html`.

This keeps list markup in PHP views and prevents JS from embedding large HTML templates.

## Pagination Pattern

Common admin pagination uses backend-rendered controls plus JS handlers.

Render controls only when the real filtered `meta.total_pages > 1`. Empty lists and single-page lists must not show pagination, a disabled single-page bar, or substitute controls. Reuse the approved admin list markup rather than inventing another pagination style. Verify zero results, one page and multiple pages on both initial render and AJAX replacement, including filters that reduce the result to one page.

Keep these pieces aligned:

- model returns clamped page metadata
- controller returns `html` and `meta`
- partial renders pagination markup
- JS triggers reload for a selected page

If the module already uses URL-synced filters and pagination, preserve:

- `URLSearchParams`
- `history.pushState`
- `history.replaceState`
- `popstate`

If the module is simple and does not already sync URL state, do not force the pattern.

## Mount Replacement Rule

Replace only the mount section that the endpoint owns.

After replacement, rebind:

- sortable or drag-drop behavior
- pagination click handlers
- modal triggers
- any dynamic listeners tied to the replaced markup
