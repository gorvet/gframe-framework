# Public Templates and Assets

## Template Rule

Public pages render through public templates such as:

- `homeTemplate.php`
- `slugTemplate.php`
- other non-admin templates in `app/views/templates/`

Keep the view body focused on page content and let the template wrap the shared structure.

## Asset Registration

Load CSS and JS through meta files.

Typical public assets include:

- page CSS under `public/css/home/` or other public folders
- public page JS under `public/js/app/home/` or similar folders
- vendor assets only when the screen really needs them

## Public Markup Style

Public pages are usually content-first:

- hero or title section
- supporting sections
- CTAs
- content blocks or article body

Do not force admin-style layout patterns into public templates.

## `section` Rule

Use `section` for major page areas inside the same page, for example:

- hero
- CTA band
- quienes somos
- services
- testimonials
- contact block

Do not use `section` for internal slices inside an `article`, card, repeated content item, or small component. Those internal structures should usually be `div` plus a clear class name.

## Layout Preference

For public pages, prefer:

1. Bootstrap `container` or `container-fluid`
2. Bootstrap `row` and `col-*`
3. Flexbox for custom alignment needs
4. CSS Grid only as the last option

Avoid `display: grid` when the same result can be achieved cleanly with Bootstrap structure or flexbox.
