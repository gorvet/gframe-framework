# Admin View and Form Structure

## Basic Screen Structure

Typical admin screens keep this order:

1. page title and top actions
2. filters or form controls
3. main content mount
4. modals for secondary edit flows when needed

Keep hierarchy readable and avoid deeply nested wrappers.

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

## Token and AJAX Bridge

Do not hand-build CSRF inputs in every form.

The normal pattern is:

- form contains business fields
- global `#tokens` form contains CSRF fields
- JS combines token data plus form serialization before sending AJAX

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
