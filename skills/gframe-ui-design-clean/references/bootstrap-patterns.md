# Bootstrap UX Patterns

Use these patterns as decisions, not as a mandatory visual template. First inspect the application and preserve its established language.

For GFrame admin title/actions, cards, forms and acceptance details, follow the [canonical view rules](../../gframe-frontend-admin/references/view-form-structure.md). For public pages, follow the [public template and navigation contract](../../gframe-frontend-public/references/public-template-and-assets.md). Do not copy an admin composition into a public page or use generic guidance here to override an approved reference.

## Page Structure

- Use a normal container for reading-focused pages and `container-fluid` only when the task needs the available width.
- Build main-and-aside detail views with responsive Bootstrap columns. The aside follows the main content on small screens and may become sticky only when its height and interaction remain usable.
- Keep breadcrumbs, page title, supporting text, and primary action in a predictable reading order.
- Use section headings and whitespace before adding cards. A card should represent a bounded object or task, not merely decorate a region.

## Search and Filters

- Keep the query field and related filters in one form and use the Bootstrap grid for alignment.
- Use one consistent submission model across equivalent screens: explicit submit, debounced live search, or a deliberate combination.
- Reset clears or restores the filters owned by that form and returns results/pagination to the module's intended state. Preserve unrelated URL parameters; do not add URL synchronization to a module merely because another screen uses it.
- On narrow screens, allow filters to stack or move secondary filters into an accessible collapse or offcanvas.
- Replace only the results region during asynchronous filtering unless the page context genuinely changes.

## Lists and Results

- Use `list-group`, tables, or simple repeated rows when cards do not add meaningful grouping.
- Place counts near the heading they qualify and use a badge only when the badge treatment has semantic value.
- Keep pagination adjacent to its results and preserve active filters in pagination URLs.
- Empty states state what happened and the next useful action without decorative filler.

## Forms

- Use visible labels, appropriate input types, Bootstrap validation states, and specific inline errors.
- Group related fields by meaning rather than filling an arbitrary column grid.
- Keep the primary submit action easy to find; destructive actions are separated and require proportional confirmation.
- Do not encode required information only in placeholder text, color, icons, or tooltips.

## Tables and Dense Data

- Use a table only for values users compare across rows or columns.
- Preserve semantic `table`, `thead`, `th`, and scope relationships; use `table-responsive` when necessary.
- Prefer sensible wrapping and column priority over shrinking all text to fit.
- On mobile, preserve access to the information needed for the task. Hide only genuinely secondary columns or use an existing purposeful list representation; do not introduce duplicate responsive markup without a demonstrated need.

## Navigation and Sidebars

- Sidebar links should look navigable without competing with section titles.
- Keep active state, hover, focus, and visited context clear.
- Sticky sidebars need a safe top offset, a height limit when appropriate, and a non-sticky small-screen fallback.
- Preserve the existing mobile navigation mechanism. Bootstrap offcanvas is an option when the task needs that pattern, not a requirement to replace the current admin sidebar or public menu. Do not let two controllers manage the same menu.

## Modals and Feedback

- Use a modal for a focused interruption that must be resolved before continuing, not as a substitute for every detail page.
- Use toasts for non-blocking confirmations and inline alerts for feedback tied to the current form or section.
- In GFrame module flows, use the existing feedback helpers and preserve exact backend response codes; changing visual hierarchy does not change error semantics.
- Preserve focus on open and return it on close. Avoid nested modals.
- For asynchronous replacement, retain delegated actions and a usable focus position. Check the real interaction rather than assuming Bootstrap or a PHP partial alone guarantees focus restoration.

## Visual Decisions

- Prefer the application's type scale, spacing rhythm, borders, and Bootstrap variables before adding new tokens.
- Use one clear accent and a restrained surface hierarchy.
- Radius and shadow communicate containment and elevation; do not apply them uniformly to every block.
- Add icons only when they improve recognition or affordance. Pair unfamiliar icons with text.
