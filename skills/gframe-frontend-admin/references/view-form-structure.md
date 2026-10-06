# Admin View and Form Structure

## Basic Screen Structure

Typical admin screens keep this order:

1. page title and top actions
2. filters or form controls
3. main content mount
4. modals for secondary edit flows when needed

Keep hierarchy readable and avoid deeply nested wrappers.

Supporting text is optional: add a subtitle only when it provides useful information, never merely to fill the title area. For Notifications, summaries open the server-rendered detail modal through the protected AJAX endpoint; keep read-state changes CSRF-protected and row padding inside the highlighted background.

## Mandatory reference and acceptance gate

Before changing an admin screen, compare its markup with an approved screen of the same type. In the framework repository, the approved list reference is `resources/modules/notification-campaigns/application/app/views/notification-campaigns/index.php` and `_list.php`; in applications, use the corresponding runtime views, project overrides or the user's explicit reference. Do not use an unfinished screen as a design reference or create another title, spacing, card or pagination convention. Keep these conventions in this skill, not duplicated in `AGENTS.md`.

When the user requests original files from Bebots, Dane or Base Confías, copy the specified originals rather than reinterpreting them. Apply only the explicitly agreed exceptions.

Use the common admin template and CSS without local heading size, weight or margin overrides. Keep New/Add beside the title, supporting text below it, and consecutive cards separated with the existing spacing (for example `mb-4`). Do not remove list padding with `p-0` by default. Form actions stay right-aligned, with Cancel before the primary action; creation and editing share the applicable form structure.

Before delivery, compare title/subtitle/actions, card spacing and padding, footer, assets and button order with the approved reference. Check empty, single-page and multi-page lists, including filtered AJAX replacement. Check responsive layout and themes when affected. Report unverified checks rather than claiming completion. The agent owns this verification; do not require the user to repeat these conventions for each screen.

The admin `.pagetitle` headings are inline-block. Put supporting text in `span.d-block` below the heading, inside the same title wrapper; use the existing title-and-adjacent-action structure. Campaign creation and editing share delivery controls. Group content separately from delivery, keep date and frequency on the same responsive row, and put channels last among the delivery fields, before preview/test and final actions. Use distinct emphasis for preview/test controls versus Cancel and the primary action. Notification header dropdowns are module-owned; admin header actions must not inherit the public mobile navigation drawer's static dropdown or full-height list rules.

## Layout Preference

Prefer this order for layout decisions:

1. Bootstrap `container` or `container-fluid`
2. Bootstrap `row` and `col-*`
3. Flexbox for custom alignment or distribution
4. CSS Grid only as a last resort

Do not jump straight to `display: grid` for screens that Bootstrap columns or flexbox can solve cleanly.

## Semantic Structure

In admin screens, `section` should be rare.

Use `section` only when the screen truly has large, separate areas with their own heading and identity.
Do not use `section` inside card bodies, modal bodies, repeated list items, or small component fragments.
For those internal subdivisions, prefer `div`, `row`, and `col-*`.

## Form Structure

Use standard Bootstrap form markup with clear labels.

If HTML5 validation is active:

- add `class="needs-validation"`
- add `novalidate`
- keep stable `id` values on inputs if `validationFeedback()` maps messages by field id

When custom validation messages are used, keep a predictable target such as `.validation_<fieldId>`.

## Field Names and Selectors

Follow the shared [code and payload naming rules](../../gframe-core-architecture/references/naming-conventions.md). An HTML `id`, a submitted `name` and a PHP variable serve different consumers; they do not need identical spelling.

- Preserve existing DOM IDs and selector conventions, including `userRoleForm`, `managedUserRole`, `user-admin-tokens` and `all_items_pagination`. No universal DOM renaming is authorized by this guide.
- For a new field, choose an ID consistent with its component, keep it unique in the rendered page and match the label's `for`. Repeated rows should use classes and `data-*` rather than duplicate IDs.
- Use the endpoint's existing `name` contract; new business payload keys use snake_case, such as `role_id`. Preserve technical CSRF names.
- Use HTML `data-*` attributes such as `data-user-id`; match their existing JS lookup, such as `.data('user-id')`. Changing an ID also affects labels, validation targets, CSS, JS, ARIA references and tests.
- Do not rename a field, route key or selector merely to match `$userID`. Trace its producer and consumer first; see the [user-admin recipe](user-admin-ajax-recipe.md).

## Token and AJAX Bridge

Do not hand-build CSRF inputs in every form.

The normal pattern is:

- form contains business fields
- global `#tokens` form contains CSRF fields
- JS combines token data plus form serialization before sending AJAX

An existing local bridge is an exception to preserve, not a pattern to add to every new form. User-admin already supplies and serializes `#user-admin-tokens`.

## Partial Mount Pattern

For list-driven screens, keep a dedicated mount container such as:

```php
<div id="all_items">
  <?= $response['html'] ?? '' ?>
</div>
```

The backend owns the contents of that mount.

## Modal Pattern

For lightweight edits:

- keep add form on page when it improves speed
- keep edit form in Bootstrap modal when it prevents page navigation

Reuse server-rendered list HTML after the modal action succeeds.
