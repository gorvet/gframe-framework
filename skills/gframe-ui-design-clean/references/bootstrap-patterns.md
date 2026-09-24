# Bootstrap UX Patterns

Use these patterns as decisions, not as a mandatory visual template. First inspect the application and preserve its established language.

## Page Structure

- Use a normal container for reading-focused pages and `container-fluid` only when the task needs the available width.
- Build main-and-aside detail views with responsive Bootstrap columns. The aside follows the main content on small screens and may become sticky only when its height and interaction remain usable.
- Keep breadcrumbs, page title, supporting text, and primary action in a predictable reading order.
- Use section headings and whitespace before adding cards. A card should represent a bounded object or task, not merely decorate a region.

## Search and Filters

- Keep the query field and related filters in one form and use the Bootstrap grid for alignment.
- Use one consistent submission model across equivalent screens: explicit submit, debounced live search, or a deliberate combination.
- Reset restores every field, URL parameter, result count, and pagination state.
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
- On mobile, hide only genuinely secondary columns or switch to a purposeful list representation.

## Navigation and Sidebars

- Sidebar links should look navigable without competing with section titles.
- Keep active state, hover, focus, and visited context clear.
- Sticky sidebars need a safe top offset, a height limit when appropriate, and a non-sticky small-screen fallback.
- Use offcanvas for mobile navigation, not for essential content that should remain in the page flow.

## Modals and Feedback

- Use a modal for a focused interruption that must be resolved before continuing, not as a substitute for every detail page.
- Use toasts for non-blocking confirmations and inline alerts for feedback tied to the current form or section.
- Preserve focus on open and return it on close. Avoid nested modals.

## Visual Decisions

- Prefer the application's type scale, spacing rhythm, borders, and Bootstrap variables before adding new tokens.
- Use one clear accent and a restrained surface hierarchy.
- Radius and shadow communicate containment and elevation; do not apply them uniformly to every block.
- Add icons only when they improve recognition or affordance. Pair unfamiliar icons with text.
