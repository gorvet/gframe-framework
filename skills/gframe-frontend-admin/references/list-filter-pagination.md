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

The matching package's `docs/orm.md` explains data/count/page calculation; `docs/helpers-php.md` explains `PaginationHelper::render`; `docs/frontend-core.md` explains AJAX and `creaPaginacion`. Read those guides for the chosen mechanism instead of creating another paginator.

`PaginationHelper::render($totalPages, $page)` echoes HTML; legacy `pagination($total_pages, $page)` delegates to it. Neither counts records or fetches a page. Call it only inside the partial's `total_pages > 1` branch. Its existing IDs/classes are contracts, even though new component IDs follow kebab-case.

`creaPaginacion(total_pages, page)` renders JS controls for `#all_items_pagination`; the delegated click handlers call the application's `window.fetchDataForPage(page)`. The callback sends the existing filters/tokens to the authorized endpoint and replaces the returned partial. Wait for frontend-core readiness as described in the component reference. Do not mix a second custom click handler with the existing callback for the same controls.

Both helpers can render one-page controls unless the caller guards them. With zero pages, the JS helper returns before clearing previous markup; clear/hide the owned container when appropriate. Fixed IDs/global callback support one such listing per screen; independent listings need their own established container controllers. Never use this helper to infer server authorization or relevance across records not loaded.

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
